<?php

use App\Services\System\AppVersionResolver;
use Illuminate\Support\Facades\Blade;

function bindBranch(?string $branch): void
{
    $resolver = Mockery::mock(AppVersionResolver::class);
    $resolver->shouldReceive('currentBranch')->andReturn($branch);
    app()->instance(AppVersionResolver::class, $resolver);
}

it('toont de branch-balk met branchnaam en poort op local', function () {
    app()->detectEnvironment(fn () => 'local');
    config(['app.url' => 'http://localhost:8003']);
    bindBranch('feature/zoekfunctie');

    $html = Blade::render('<x-local-branch-bar />');

    expect($html)
        ->toContain('Lokaal')
        ->toContain('feature/zoekfunctie')
        ->toContain('localhost:8003');
});

it('meldt een onbekende branch', function () {
    app()->detectEnvironment(fn () => 'local');
    bindBranch(null);

    expect(Blade::render('<x-local-branch-bar />'))->toContain('branch onbekend');
});

it('toont de balk niet buiten local', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);
    bindBranch('feature/zoekfunctie');

    expect(trim(Blade::render('<x-local-branch-bar />')))->toBe('');
})->with(['testing', 'production']);
