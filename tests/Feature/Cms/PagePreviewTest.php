<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\MembershipStatus;
use App\Enums\PageVersionStatus;
use App\Models\Band;
use App\Models\Block;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Person;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
});

function createPageVersionWithHeading(Page $page, int $versionNo, PageVersionStatus $status, string $text, ?int $createdByPersonId = null): PageVersion
{
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => $versionNo,
        'status' => $status,
        'created_by_person_id' => $createdByPersonId,
    ]);
    $band = Band::create([
        'page_version_id' => $version->id,
        'zone' => 'hoofd',
        'layout' => BandLayout::OneColumn,
        'sort_order' => 0,
    ]);
    Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Heading,
        'content' => ['level' => 1, 'text' => $text],
    ]);

    return $version;
}

function memberWithActiveMembershipOnly(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'Actief', 'last_name' => 'Lid'.uniqid(), 'account_id' => $user->id]);
    $type = MembershipType::query()->where('key', 'a')->firstOrFail();
    Membership::create([
        'person_id' => $person->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => now()->subMonth()->toDateString(),
        'billing_person_id' => $person->id,
    ]);

    return [$user, $person];
}

it('laat een lid met alleen pages.propose de eigen conceptversie previewen, niet de gepubliceerde inhoud', function () {
    [$user, $person] = memberWithActiveMembershipOnly();

    $page = Page::create([
        'slug' => 'preview-concept',
        'title' => 'Preview concept',
        'template_id' => $this->template->id,
    ]);
    $published = createPageVersionWithHeading($page, 1, PageVersionStatus::Published, 'Gepubliceerde tekst');
    $page->update(['published_version_id' => $published->id]);
    $draft = createPageVersionWithHeading($page, 2, PageVersionStatus::Draft, 'Concepttekst', $person->id);

    $this->actingAs($user)
        ->get(route('admin.pages.versions.preview', [$page, $draft]))
        ->assertOk()
        ->assertSee('Concepttekst')
        ->assertDontSee('Gepubliceerde tekst');
});

it('toont de voorvertoningsbalk op de preview-pagina', function () {
    [$user, $person] = memberWithActiveMembershipOnly();

    $page = Page::create([
        'slug' => 'preview-balk',
        'title' => 'Preview balk',
        'template_id' => $this->template->id,
    ]);
    $draft = createPageVersionWithHeading($page, 1, PageVersionStatus::Draft, 'Concepttekst', $person->id);

    $this->actingAs($user)
        ->get(route('admin.pages.versions.preview', [$page, $draft]))
        ->assertOk()
        ->assertSee('Voorvertoning van v1');
});

it('toont de knop "Wijziging voorstellen" niet in preview-modus, wel op de normale publieke pagina', function () {
    [$user, $person] = memberWithActiveMembershipOnly();

    $page = Page::create([
        'slug' => 'preview-knop',
        'title' => 'Preview knop',
        'template_id' => $this->template->id,
    ]);
    $published = createPageVersionWithHeading($page, 1, PageVersionStatus::Published, 'Gepubliceerde tekst');
    $page->update(['published_version_id' => $published->id]);

    $this->actingAs($user)
        ->get(route('admin.pages.versions.preview', [$page, $published]))
        ->assertOk()
        ->assertDontSee('Wijziging voorstellen');

    $this->actingAs($user)
        ->get($page->publicUrl())
        ->assertOk()
        ->assertSee('Wijziging voorstellen');
});

it('weigert de preview-route voor een gebruiker zonder pages.propose', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'Geen', 'last_name' => 'Rechten', 'account_id' => $user->id]);

    $page = Page::create([
        'slug' => 'preview-geen-rechten',
        'title' => 'Preview geen rechten',
        'template_id' => $this->template->id,
    ]);
    $draft = createPageVersionWithHeading($page, 1, PageVersionStatus::Draft, 'Concepttekst');

    $this->actingAs($user)
        ->get(route('admin.pages.versions.preview', [$page, $draft]))
        ->assertForbidden();
});

it('laat een gearchiveerde historische versie ook previewen', function () {
    [$user, $person] = memberWithActiveMembershipOnly();

    $page = Page::create([
        'slug' => 'preview-archief',
        'title' => 'Preview archief',
        'template_id' => $this->template->id,
    ]);
    $archived = createPageVersionWithHeading($page, 1, PageVersionStatus::Archived, 'Oude tekst');

    $this->actingAs($user)
        ->get(route('admin.pages.versions.preview', [$page, $archived]))
        ->assertOk()
        ->assertSee('Oude tekst');
});

it('toont geen voorvertoningsbalk op de normale publieke route', function () {
    $page = Page::create([
        'slug' => 'geen-balk',
        'title' => 'Geen balk',
        'template_id' => $this->template->id,
    ]);
    $published = createPageVersionWithHeading($page, 1, PageVersionStatus::Published, 'Tekst');
    $page->update(['published_version_id' => $published->id]);

    $this->get($page->publicUrl())
        ->assertOk()
        ->assertDontSee('Voorvertoning van');
});
