@if (app()->environment('local'))
    @php
        $branch = app(\App\Services\System\AppVersionResolver::class)->currentBranch();
        $hostPort = parse_url((string) config('app.url'), PHP_URL_HOST) . (parse_url((string) config('app.url'), PHP_URL_PORT) ? ':' . parse_url((string) config('app.url'), PHP_URL_PORT) : '');
    @endphp
    <div class="flex h-7 w-full items-center justify-center border-b border-yellow-400 bg-yellow-300 px-4 text-xs text-yellow-950" data-testid="local-branch-bar">
        Lokaal ·
        @if ($branch)
            branch&nbsp;<span class="font-mono">{{ $branch }}</span>
        @else
            branch onbekend
        @endif
        · {{ $hostPort }}
    </div>
@endif
