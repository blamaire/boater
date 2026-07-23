<?php

use App\Enums\AssigneeType;
use App\Enums\PageVersionStatus;
use App\Enums\ProposalStatus;
use App\Enums\ResubmitBehavior;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\Proposal;
use App\Models\ReviewPolicy;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Template;
use App\Models\User;
use App\Services\Proposals\Handlers\PageVersionProposalHandler;
use App\Services\Proposals\ProposalEngine;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReviewPolicySeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
    $this->seed(ReviewPolicySeeder::class);
});

it('submits a draft to the proposal engine when "indienen" is pressed', function () {
    [$proposerUser, $proposerPerson] = makeEditor();

    $page = Page::create([
        'slug' => 'mijn-pagina',
        'title' => 'Mijn pagina',
        'template_id' => $this->template->id,
    ]);
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Draft,
        'created_by_person_id' => $proposerPerson->id,
    ]);

    $this->actingAs($proposerUser)
        ->post("/beheer/paginas/{$page->id}/versies/{$version->id}/indienen")
        ->assertRedirect();

    expect($version->fresh()->status)->toBe(PageVersionStatus::InReview);

    $proposal = Proposal::where('subject_type', PageVersionProposalHandler::SUBJECT_TYPE)
        ->where('subject_id', $version->id)
        ->first();

    expect($proposal)->not->toBeNull()
        ->and($proposal->status)->toBe(ProposalStatus::InReview);
});

it('publishes a page when the proposal is approved', function () {
    [$proposerUser, $proposerPerson] = makeEditor();
    [$reviewerUser, $reviewerPerson, $reviewerRole] = makeReviewer();

    ReviewPolicy::where('subject_type', PageVersionProposalHandler::SUBJECT_TYPE)
        ->update([
            'steps' => [
                ['assignee_type' => AssigneeType::Role->value, 'assignee_id' => $reviewerRole->id],
            ],
            'bypass_permission' => null,
            'resubmit_behavior' => ResubmitBehavior::Restart,
        ]);

    $page = Page::create([
        'slug' => 'feedpagina',
        'title' => 'Pagina',
        'template_id' => $this->template->id,
    ]);
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Draft,
        'created_by_person_id' => $proposerPerson->id,
    ]);

    $this->actingAs($proposerUser)
        ->post("/beheer/paginas/{$page->id}/versies/{$version->id}/indienen")
        ->assertRedirect();

    $proposal = Proposal::where('subject_id', $version->id)->firstOrFail();
    $step = $proposal->steps()->where('status', 'pending')->firstOrFail();

    app(ProposalEngine::class)->approveStep($step, $reviewerPerson);

    expect($version->fresh()->status)->toBe(PageVersionStatus::Published);
    expect($page->fresh()->published_version_id)->toBe($version->id);
});

it('gaat ook voor een indiener met pages.publish via review i.p.v. direct bypassen (indienen-knop)', function () {
    [$publisherUser, $publisherPerson] = makePublisher();

    $page = Page::create([
        'slug' => 'bypass-toch-review',
        'title' => 'Bypass toch review',
        'template_id' => $this->template->id,
    ]);
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Draft,
        'created_by_person_id' => $publisherPerson->id,
    ]);

    $this->actingAs($publisherUser)
        ->post("/beheer/paginas/{$page->id}/versies/{$version->id}/indienen")
        ->assertRedirect();

    expect($version->fresh()->status)->toBe(PageVersionStatus::InReview);
    expect($page->fresh()->published_version_id)->toBeNull();

    $proposal = Proposal::where('subject_id', $version->id)->firstOrFail();
    expect($proposal->status)->toBe(ProposalStatus::InReview);
});

it('publiceert direct zonder goedkeuring via de expliciete knop, voor iemand met pages.publish', function () {
    [$publisherUser, $publisherPerson] = makePublisher();

    $page = Page::create([
        'slug' => 'direct-publiceren',
        'title' => 'Direct publiceren',
        'template_id' => $this->template->id,
    ]);
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Draft,
        'created_by_person_id' => $publisherPerson->id,
    ]);

    $this->actingAs($publisherUser)
        ->post("/beheer/paginas/{$page->id}/versies/{$version->id}/publiceren")
        ->assertRedirect();

    expect($version->fresh()->status)->toBe(PageVersionStatus::Published);
    expect($page->fresh()->published_version_id)->toBe($version->id);

    $proposal = Proposal::where('subject_id', $version->id)->firstOrFail();
    expect($proposal->status)->toBe(ProposalStatus::Applied);
});

it('weigert de directe publicatie-route voor iemand zonder pages.publish', function () {
    [$editorUser, $editorPerson] = makeEditor();

    $page = Page::create([
        'slug' => 'geen-publish-recht',
        'title' => 'Geen publish-recht',
        'template_id' => $this->template->id,
    ]);
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Draft,
        'created_by_person_id' => $editorPerson->id,
    ]);

    $this->actingAs($editorUser)
        ->post("/beheer/paginas/{$page->id}/versies/{$version->id}/publiceren")
        ->assertForbidden();

    expect($version->fresh()->status)->toBe(PageVersionStatus::Draft);
    expect(Proposal::count())->toBe(0);
});

it('refuses to submit a non-draft version', function () {
    [$user, $person] = makeEditor();
    $page = Page::create([
        'slug' => 'al-published',
        'title' => 'Page',
        'template_id' => $this->template->id,
    ]);
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Published,
    ]);

    $this->actingAs($user)
        ->post("/beheer/paginas/{$page->id}/versies/{$version->id}/indienen")
        ->assertRedirect();

    expect(Proposal::count())->toBe(0);
});

function makeEditor(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create([
        'first_name' => 'E',
        'last_name' => 'Ditor'.uniqid(),
        'account_id' => $user->id,
    ]);
    foreach (['pages.view', 'pages.create', 'pages.update'] as $key) {
        PersonPermission::create([
            'person_id' => $person->id,
            'permission_id' => Permission::where('key', $key)->value('id'),
            'status' => 'active',
        ]);
    }

    return [$user, $person];
}

function makePublisher(): array
{
    [$user, $person] = makeEditor();
    PersonPermission::create([
        'person_id' => $person->id,
        'permission_id' => Permission::where('key', 'pages.publish')->value('id'),
        'status' => 'active',
    ]);

    return [$user, $person];
}

function makeReviewer(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create([
        'first_name' => 'R',
        'last_name' => 'Eviewer'.uniqid(),
        'account_id' => $user->id,
    ]);
    $role = Role::create(['name' => 'Reviewer'.uniqid()]);
    RoleAssignment::create([
        'person_id' => $person->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);

    return [$user, $person, $role];
}
