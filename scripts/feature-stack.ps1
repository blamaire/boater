<#
.SYNOPSIS
    Extra Docker-stack per feature: een git-worktree naast de repo met een
    eigen compose-project, eigen poorten, eigen volumes en een eigen database.

.DESCRIPTION
    De standaardstack (deze repo, poorten 8000/5173/3307/8025) blijft zoals hij
    is. Met `start <naam>` komt er een worktree in ..\boater-<naam> op branch
    feature/<naam> (of -Branch), met compose-project rzvg-<naam> en de poorten
    van het eerstvolgende vrije slot n (1-9):

        web     8000+n   (http://localhost:800n)
        vite    5173+n
        mysql   3307+n
        mailpit 8025+n

    Binnen de worktree werkt gewoon `docker compose exec app …`: de projectnaam
    en poorten staan in de .env van die worktree.

.EXAMPLE
    scripts/feature-stack.ps1 start zoekfunctie
    scripts/feature-stack.ps1 start keuzehulp -Branch feature/keuzehulp-lidmaatschap
    scripts/feature-stack.ps1 list
    scripts/feature-stack.ps1 stop zoekfunctie
    scripts/feature-stack.ps1 remove zoekfunctie
#>
param(
    [Parameter(Mandatory, Position = 0)]
    [ValidateSet('start', 'stop', 'remove', 'list')]
    [string] $Command,

    [Parameter(Position = 1)]
    [string] $Name,

    # Branch voor de worktree; standaard feature/<naam>. Bestaat hij nog niet,
    # dan wordt hij aangemaakt vanaf -Base.
    [string] $Branch,

    [string] $Base = 'origin/main',

    # Sla `composer db-reset` over (bv. als je de database zelf vult).
    [switch] $NoSeed,

    # remove: ook als de worktree nog niet-gecommitte wijzigingen heeft.
    [switch] $Force
)

$ErrorActionPreference = 'Stop'

# De hoofdrepo, ook als het script vanuit een worktree wordt aangeroepen.
$gitCommonDir = git -C $PSScriptRoot rev-parse --path-format=absolute --git-common-dir
$mainRepo = Split-Path $gitCommonDir -Parent
$parentDir = Split-Path $mainRepo -Parent
$maxSlots = 9

function Get-StackDir([string] $stackName) { Join-Path $parentDir "boater-$stackName" }

function Assert-Name {
    if (-not $Name) { throw "Geef een naam op, bv. 'scripts/feature-stack.ps1 $Command zoekfunctie'." }
    if ($Name -notmatch '^[a-z0-9][a-z0-9-]{0,30}$') {
        throw "Naam '$Name' mag alleen kleine letters, cijfers en '-' bevatten (max. 31 tekens)."
    }
}

function Read-DotEnvValue([string] $file, [string] $key) {
    if (-not (Test-Path $file)) { return $null }
    $line = Get-Content $file | Where-Object { $_ -match "^$key=" } | Select-Object -Last 1
    if ($line) { return ($line -split '=', 2)[1] }
    return $null
}

# Alle feature-stacks: worktrees met een RZVG_STACK_SLOT in hun .env.
function Get-Stacks {
    $paths = git -C $mainRepo worktree list --porcelain |
        Where-Object { $_ -like 'worktree *' } |
        ForEach-Object { $_.Substring(9) }

    foreach ($path in $paths) {
        $envFile = Join-Path $path '.env'
        $slot = Read-DotEnvValue $envFile 'RZVG_STACK_SLOT'
        if (-not $slot) { continue }
        [pscustomobject]@{
            Name    = (Split-Path $path -Leaf) -replace '^boater-', ''
            Slot    = [int] $slot
            Path    = $path
            Branch  = (git -C $path branch --show-current)
            Url     = "http://localhost:$(Read-DotEnvValue $envFile 'RZVG_WEB_PORT')"
            Project = Read-DotEnvValue $envFile 'COMPOSE_PROJECT_NAME'
        }
    }
}

function Set-DotEnvValues([string] $file, [hashtable] $values) {
    $lines = @(Get-Content $file | Where-Object {
        $line = $_
        -not ($values.Keys | Where-Object { $line -match "^$_=" })
    })
    $lines += ''
    $lines += '# --- feature-stack (scripts/feature-stack.ps1) ---'
    foreach ($key in $values.Keys | Sort-Object) { $lines += "$key=$($values[$key])" }
    Set-Content -Path $file -Value $lines -Encoding utf8NoBOM
}

function Invoke-Compose([string] $dir, [string[]] $arguments) {
    Push-Location $dir
    try {
        & docker compose @arguments
        if ($LASTEXITCODE -ne 0) { throw "docker compose $($arguments -join ' ') faalde (exit $LASTEXITCODE)." }
    } finally {
        Pop-Location
    }
}

# In een worktree is `.git` een bestand met een Windows-pad (gitdir: C:/…/.git/worktrees/…)
# dat in de Linux-container niet bestaat, waardoor git daar stukloopt (o.a. AppVersionResolver
# en de branch-balk). De override mount de .git van de hoofdrepo read-only en wijst GIT_DIR
# naar de worktree-map daarbinnen. Docker compose laadt het bestand automatisch; het staat in .gitignore.
function Write-ComposeOverride([string] $dir) {
    $gitFile = Get-Content (Join-Path $dir '.git') -Raw
    if ($gitFile -notmatch 'gitdir:\s*(.+?)\s*$') { throw "Kan gitdir niet lezen uit $dir\.git." }
    $worktreeName = ($Matches[1] -replace '\\', '/').TrimEnd('/').Split('/')[-1]
    $mainGit = ($gitCommonDir -replace '\\', '/').TrimEnd('/')

    $services = foreach ($service in 'app', 'queue', 'scheduler') {
@"
  ${service}:
    volumes:
      - "${mainGit}:/var/www/html/.git-main:ro"
    environment:
      GIT_DIR: /var/www/html/.git-main/worktrees/$worktreeName
      GIT_WORK_TREE: /var/www/html
"@
    }
    $content = "# Gegenereerd door scripts/feature-stack.ps1 - niet committen.`nservices:`n" + ($services -join "`n") + "`n"
    Set-Content -Path (Join-Path $dir 'docker-compose.override.yml') -Value $content -Encoding utf8NoBOM
}

switch ($Command) {
    'list' {
        $stacks = @(Get-Stacks)
        if ($stacks.Count -eq 0) {
            Write-Host 'Geen feature-stacks. De standaardstack draait op http://localhost:8000.'
            return
        }
        $stacks | Sort-Object Slot | ForEach-Object {
            Push-Location $_.Path
            $running = @(docker compose ps --status running -q 2>$null).Count
            Pop-Location
            $_ | Add-Member -NotePropertyName Draait -NotePropertyValue "$running containers" -PassThru
        } | Format-Table Name, Branch, Url, Draait, Path -AutoSize
    }

    'start' {
        Assert-Name
        $dir = Get-StackDir $Name
        $branchName = if ($Branch) { $Branch } else { "feature/$Name" }

        if (-not (Test-Path $dir)) {
            git -C $mainRepo fetch --quiet origin
            $localExists = git -C $mainRepo branch --list $branchName
            $remoteExists = git -C $mainRepo branch -r --list "origin/$branchName"
            if ($localExists) {
                git -C $mainRepo worktree add $dir $branchName
            } elseif ($remoteExists) {
                git -C $mainRepo worktree add --track -b $branchName $dir "origin/$branchName"
            } else {
                git -C $mainRepo worktree add --no-track -b $branchName $dir $Base
            }
            if ($LASTEXITCODE -ne 0) { throw "git worktree add faalde." }

            $usedSlots = @(Get-Stacks | ForEach-Object Slot)
            $slot = 1..$maxSlots | Where-Object { $usedSlots -notcontains $_ } | Select-Object -First 1
            if (-not $slot) { throw "Alle $maxSlots slots zijn bezet; ruim eerst een stack op met 'remove'." }

            # Env-bestanden staan buiten git: kopiëren uit de hoofdrepo en per stack aanpassen.
            Copy-Item (Join-Path $mainRepo '.env') (Join-Path $dir '.env')
            Copy-Item (Join-Path $mainRepo '.env.local') (Join-Path $dir '.env.local')

            $webPort = 8000 + $slot
            Set-DotEnvValues (Join-Path $dir '.env') @{
                COMPOSE_PROJECT_NAME = "rzvg-$Name"
                RZVG_STACK_SLOT      = $slot
                RZVG_WEB_PORT        = $webPort
                RZVG_VITE_PORT       = 5173 + $slot
                RZVG_DB_PORT         = 3307 + $slot
                RZVG_MAIL_PORT       = 8025 + $slot
            }
            Set-DotEnvValues (Join-Path $dir '.env.local') @{ APP_URL = "http://localhost:$webPort" }
            Write-Host "Worktree $dir aangemaakt op branch $branchName (slot $slot)."
        } else {
            Write-Host "Worktree $dir bestaat al; stack wordt (opnieuw) gestart."
        }

        Write-ComposeOverride $dir
        Invoke-Compose $dir @('up', '-d', '--build')
        # Wordt app opnieuw gemaakt (nieuw intern IP), dan blijft nginx het oude upstream-IP
        # gebruiken en geeft de stack een 502; een herstart van web lost dat op.
        Invoke-Compose $dir @('restart', 'web')
        # Queue en scheduler pas starten als vendor/ en de database klaar zijn
        # (anders crashen ze op een ontbrekende autoload of op migrate:fresh).
        Invoke-Compose $dir @('stop', 'queue', 'scheduler')
        Invoke-Compose $dir @('exec', '-T', 'app', 'composer', 'install', '--no-interaction')

        if (-not $NoSeed) {
            # MySQL heeft bij een nieuw volume even nodig om op te starten.
            $seeded = $false
            for ($i = 1; $i -le 20 -and -not $seeded; $i++) {
                Push-Location $dir
                docker compose exec -T app composer db-reset *> $null
                $seeded = $LASTEXITCODE -eq 0
                Pop-Location
                if (-not $seeded) { Start-Sleep -Seconds 5 }
            }
            if (-not $seeded) { throw "composer db-reset bleef falen; controleer 'docker compose logs db' in $dir." }
        }
        Invoke-Compose $dir @('up', '-d', 'queue', 'scheduler')

        $stack = Get-Stacks | Where-Object Name -eq $Name
        Write-Host ''
        Write-Host "Feature-stack '$Name' draait: $($stack.Url)"
        Write-Host "  map:    $dir"
        Write-Host "  branch: $($stack.Branch)"
        Write-Host "  mail:   http://localhost:$(8025 + $stack.Slot)"
        Write-Host "  in die map werkt 'docker compose exec app …' zoals gewoonlijk."
    }

    'stop' {
        Assert-Name
        $dir = Get-StackDir $Name
        if (-not (Test-Path $dir)) { throw "Geen feature-stack '$Name' ($dir bestaat niet)." }
        Invoke-Compose $dir @('stop')
        Write-Host "Feature-stack '$Name' gestopt (start opnieuw met 'start $Name')."
    }

    'remove' {
        Assert-Name
        $dir = Get-StackDir $Name
        if (-not (Test-Path $dir)) { throw "Geen feature-stack '$Name' ($dir bestaat niet)." }

        $dirty = git -C $dir status --porcelain
        if ($dirty -and -not $Force) {
            throw "Worktree $dir heeft niet-gecommitte wijzigingen; commit ze eerst of gebruik -Force (ze gaan dan verloren)."
        }

        Invoke-Compose $dir @('down', '-v', '--remove-orphans')
        if ($Force) { git -C $mainRepo worktree remove --force $dir } else { git -C $mainRepo worktree remove $dir }
        if ($LASTEXITCODE -ne 0) { throw "git worktree remove faalde." }
        Write-Host "Feature-stack '$Name' opgeruimd (containers, volumes en worktree). De branch blijft bestaan."
    }
}
