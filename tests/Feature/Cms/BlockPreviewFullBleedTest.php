<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageVersionStatus;
use App\Livewire\Admin\PageEditor;
use App\Models\Band;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use App\Models\User;
use Livewire\Livewire;

/**
 * Full-bleed hero/video/feature-secties gebruiken op de publieke pagina
 * bewust w-screen + left-1/2 -translate-x-1/2 om buiten de contentbreedte
 * uit te breken. Diezelfde partial (cms.blocks.preview) wordt ook gebruikt
 * in de bewerker/diff/conflict-resolver, waar die truc de layout juist kapot
 * maakt (een grote hero-foto werd breder dan de bewerker zelf). De partial
 * moet dus alleen full-bleed renderen als $fullBleed niet expliciet op false
 * staat.
 */
function makeHeroPage(): array
{
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'hero-test',
        'title' => 'Hero test',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Published,
    ]);
    $band = Band::create(['page_version_id' => $version->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
    Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Hero,
        'content' => ['title' => 'Welkom'],
        'visibility' => 'publiek',
    ]);
    $page->update(['published_version_id' => $version->id]);

    return [$page, $version];
}

it('rendert een hero-blok full-bleed (w-screen) op de publieke pagina', function () {
    [$page] = makeHeroPage();

    $this->get($page->publicUrl())
        ->assertOk()
        ->assertSee('w-screen', false);
});

it('rendert een hero-blok niet full-bleed in de paginabewerker', function () {
    [$page, $version] = makeHeroPage();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $html = Livewire::actingAs($user)
        ->test(PageEditor::class, ['versionId' => $version->id])
        ->html();

    expect($html)->not->toContain('w-screen');
});
