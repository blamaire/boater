<?php

use Symfony\Component\Finder\Finder;

it('has no PHP files with leading whitespace or BOM before <?php', function () {
    // Waarom: één spatie voor `<?php` in bv. een config-bestand zorgt ervoor dat
    // PHP output start vóórdat `header()`-calls kunnen worden verstuurd. Set-Cookie
    // wordt dan stil gedropt — sessie-cookie ontbreekt — CSRF-token mismatch — 419
    // bij POST /login. Deze structurele check bewaakt dat.
    $roots = ['app', 'bootstrap', 'config', 'database', 'routes', 'tests'];

    $offenders = [];
    foreach ($roots as $root) {
        $finder = (new Finder)->files()->in(base_path($root))->name('*.php');
        foreach ($finder as $file) {
            $firstBytes = (string) file_get_contents($file->getPathname(), false, null, 0, 5);
            if ($firstBytes !== '<?php') {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname())
                    .' (start: '.bin2hex($firstBytes).')';
            }
        }
    }

    expect($offenders)->toBe([]);
});
