<?php

use App\Enums\BlockType;
use App\Models\Block;
use App\Models\Page;
use Database\Seeders\LocalPublicMenuSeeder;
use Database\Seeders\LocalSportPagesSeeder;
use Database\Seeders\TemplateSeeder;

/**
 * Lokale wervende sportpagina's (Roeien, Zeilen, Suppen) uit de seeder.
 */
it('zaait buiten local geen sportpagina\'s', function () {
    $this->seed(TemplateSeeder::class);
    $this->seed(LocalSportPagesSeeder::class);

    expect(Page::query()->whereIn('slug', ['roeien', 'zeilen', 'suppen'])->count())->toBe(0);
});

it('vult lokaal roeien, zeilen en suppen met wervende inhoud', function (string $slug, string $heroTitle) {
    $this->seed(TemplateSeeder::class);
    app()->detectEnvironment(fn () => 'local');
    $this->seed(LocalPublicMenuSeeder::class);

    $page = Page::query()->where('slug', $slug)->firstOrFail();
    $blocks = Block::query()
        ->whereHas('band', fn ($q) => $q->where('page_version_id', $page->published_version_id))
        ->get();

    expect($blocks->firstWhere('type', BlockType::Hero)?->content['title'])->toBe($heroTitle)
        ->and($blocks->firstWhere('type', BlockType::Accordion)?->content['items'])->not->toBeEmpty();

    $this->get($page->publicUrl())->assertOk()
        ->assertSee($heroTitle)
        ->assertSee('Zo begin je')
        ->assertSee('Wat kost het?')
        ->assertSee('Veelgestelde vragen')
        ->assertSee('Zin om het te proberen?');
})->with([
    ['roeien', 'Roeien op de Reeuwijkse plassen'],
    ['zeilen', 'Zeilen op de Reeuwijkse plassen'],
    ['suppen', 'Suppen op de Reeuwijkse plassen'],
]);

it('is idempotent en laat jeugd eenvoudig', function () {
    $this->seed(TemplateSeeder::class);
    app()->detectEnvironment(fn () => 'local');
    $this->seed(LocalPublicMenuSeeder::class);
    $this->seed(LocalSportPagesSeeder::class);

    $roeien = Page::query()->where('slug', 'roeien')->firstOrFail();
    expect($roeien->versions()->count())->toBe(1);

    $jeugd = Page::query()->where('slug', 'jeugd')->firstOrFail();
    expect(Block::query()
        ->whereHas('band', fn ($q) => $q->where('page_version_id', $jeugd->published_version_id))
        ->where('type', BlockType::Hero)
        ->exists())->toBeFalse();
});
