<?php

use App\Enums\ChangeType;
use App\Enums\PageVersionStatus;
use App\Enums\ProposalStatus;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\Template;
use App\Models\User;
use App\Services\Proposals\Handlers\PageVersionProposalHandler;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id])
        ->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $this->proposal = Proposal::create([
        'subject_type' => Person::class,
        'subject_id' => 1,
        'change_type' => ChangeType::Update,
        'payload' => ['city' => 'Utrecht'],
        'status' => ProposalStatus::Applied,
        'current_step' => 0,
    ]);
});

it('vereist audit_trail.view permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get("/beheer/voorstellen/{$this->proposal->id}")->assertForbidden();
});

it('toont het voorstel read-only voor een beheerder', function () {
    $this->actingAs($this->beheerder)
        ->get("/beheer/voorstellen/{$this->proposal->id}")
        ->assertOk()
        ->assertSee('Voorstel #'.$this->proposal->id)
        ->assertSee('Utrecht')
        ->assertSee('Toegepast');
});

it('linkt een CMS-voorstel naar de vergelijk-pagina van vorige vs voorgestelde versie', function () {
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create(['slug' => 'over-ons', 'title' => 'Over ons', 'template_id' => $template->id]);

    $vorige = PageVersion::create(['page_id' => $page->id, 'version_no' => 1, 'status' => PageVersionStatus::Published]);
    $page->update(['published_version_id' => $vorige->id]);
    $voorgesteld = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 2,
        'status' => PageVersionStatus::Draft,
        'base_version_id' => $vorige->id,
    ]);

    $proposal = Proposal::create([
        'subject_type' => PageVersionProposalHandler::SUBJECT_TYPE,
        'subject_id' => $voorgesteld->id,
        'change_type' => ChangeType::Update,
        'payload' => ['page_id' => $page->id],
        'status' => ProposalStatus::Submitted,
        'current_step' => 1,
    ]);

    $this->actingAs($this->beheerder)
        ->get("/beheer/voorstellen/{$proposal->id}")
        ->assertOk()
        ->assertSee('Over ons')
        ->assertSee(route('admin.pages.history.diff', ['page' => $page, 'version' => $vorige, 'other' => $voorgesteld]), false);
});
