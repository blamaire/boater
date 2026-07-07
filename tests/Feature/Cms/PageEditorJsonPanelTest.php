<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Livewire\Admin\PageEditor;
use App\Models\Band;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Person;
use App\Models\Role;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);

    $this->page = Page::create([
        'slug' => 'over',
        'title' => 'Over',
        'type' => PageType::Content,
        'visibility' => PageVisibility::Public,
        'template_id' => $this->template->id,
    ]);
    $this->version = PageVersion::create([
        'page_id' => $this->page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Draft,
    ]);
    $band = Band::create([
        'page_version_id' => $this->version->id,
        'zone' => 'hoofd',
        'layout' => BandLayout::OneColumn,
        'sort_order' => 0,
    ]);
    Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Text,
        'content' => ['html' => '<p>Origineel</p>'],
        'visibility' => PageVisibility::Public,
    ]);
});

it('serialiseert de huidige versie naar JSON', function () {
    $this->actingAs($this->beheerder);

    $json = Livewire::test(PageEditor::class, ['versionId' => $this->version->id])
        ->call('toggleJsonPanel')
        ->get('importJsonText');

    $decoded = json_decode($json, true);
    expect($decoded)->toBeArray()
        ->and($decoded['bands'])->toHaveCount(1)
        ->and($decoded['bands'][0]['blocks'][0]['content']['html'])->toBe('<p>Origineel</p>');
});

it('vervangt bands en blocks bij het toepassen van geïmporteerde JSON', function () {
    $nieuw = [
        'bands' => [
            [
                'zone' => 'hoofd',
                'layout' => BandLayout::TwoColumns->value,
                'sort_order' => 0,
                'blocks' => [
                    [
                        'type' => BlockType::Text->value,
                        'column_index' => 0,
                        'sort_order' => 0,
                        'content' => ['html' => '<p>Geïmporteerd links</p>'],
                        'visibility' => PageVisibility::Public->value,
                    ],
                    [
                        'type' => BlockType::Heading->value,
                        'column_index' => 1,
                        'sort_order' => 0,
                        'content' => ['level' => 2, 'text' => 'Rechts'],
                        'visibility' => PageVisibility::Public->value,
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($this->beheerder);

    Livewire::test(PageEditor::class, ['versionId' => $this->version->id])
        ->set('importJsonText', json_encode($nieuw))
        ->call('applyImportedJson')
        ->assertHasNoErrors()
        ->assertSet('showJsonPanel', false)
        ->assertSet('importJsonText', '')
        ->assertSet('jsonStatus', 'Broncode toegepast op deze conceptversie.');

    $this->version->refresh()->load('bands.blocks');
    expect($this->version->bands)->toHaveCount(1)
        ->and($this->version->bands->first()->layout)->toBe(BandLayout::TwoColumns)
        ->and($this->version->bands->first()->blocks)->toHaveCount(2)
        ->and($this->version->bands->first()->blocks->firstWhere('column_index', 0)->content['html'])->toBe('<p>Geïmporteerd links</p>');
});

it('weigert kapotte JSON met een duidelijke melding', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(PageEditor::class, ['versionId' => $this->version->id])
        ->set('importJsonText', '{ dit is geen json')
        ->call('applyImportedJson')
        ->assertSet('jsonStatus', 'Kon de JSON niet lezen. Zorg dat je een object met een "bands"-lijst plakt.');
});

it('geeft een nette melding bij een onbekende block-type of layout in de JSON', function () {
    $this->actingAs($this->beheerder);

    // Onbekend blocktype 'raket' → geen 500, wel een status-melding.
    Livewire::test(PageEditor::class, ['versionId' => $this->version->id])
        ->set('importJsonText', json_encode([
            'bands' => [
                [
                    'zone' => 'hoofd',
                    'layout' => 1,
                    'sort_order' => 0,
                    'blocks' => [
                        [
                            'type' => 'raket',
                            'column_index' => 0,
                            'sort_order' => 0,
                            'content' => [],
                            'visibility' => 'public',
                        ],
                    ],
                ],
            ],
        ]))
        ->call('applyImportedJson')
        ->assertSet('jsonStatus', 'Kon de JSON niet toepassen: band #0, block #0: onbekend type [raket].');
});

it('weigert JSON-toepassen op een niet-bewerkbare (bv. gepubliceerde) versie', function () {
    $this->version->update(['status' => PageVersionStatus::Published]);

    $this->actingAs($this->beheerder);

    Livewire::test(PageEditor::class, ['versionId' => $this->version->id])
        ->set('importJsonText', '{"bands":[]}')
        ->call('applyImportedJson')
        ->assertForbidden();
});
