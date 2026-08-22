<?php

use App\Enums\ChangeType;
use App\Enums\PageVersionStatus;
use App\Enums\ProposalStatus;
use App\Models\ApproverGroup;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\ReviewPolicy;
use App\Models\Template;
use App\Models\User;
use App\Services\Proposals\Handlers\PageVersionProposalHandler;
use App\Services\Proposals\ProposalEngine;

function makePageWithPendingVersion(Person $author): array
{
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'diff-test-'.uniqid(),
        'title' => 'Diff test',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);

    $published = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Published,
        'created_by_person_id' => $author->id,
    ]);
    $page->update(['published_version_id' => $published->id]);

    $draft = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 2,
        'status' => PageVersionStatus::InReview,
        'base_version_id' => $published->id,
        'created_by_person_id' => $author->id,
    ]);

    return [$page, $draft];
}

it('laat de indiener de diff van zijn eigen pagina-voorstel zien', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $author = Person::create(['first_name' => 'A', 'last_name' => 'Uteur', 'account_id' => $user->id]);
    [$page, $draft] = makePageWithPendingVersion($author);

    $proposal = app(ProposalEngine::class)->submit(
        subjectType: PageVersionProposalHandler::SUBJECT_TYPE,
        changeType: ChangeType::Update,
        payload: ['page_id' => $page->id],
        proposer: $author,
        subjectId: $draft->id,
    );

    $this->actingAs($user)
        ->get(route('portal.wijzigingsvoorstellen.diff', $proposal))
        ->assertOk();
});

it('laat een toegewezen beslisser de diff zien', function () {
    $author = Person::create(['first_name' => 'A', 'last_name' => 'Uteur']);
    [$page, $draft] = makePageWithPendingVersion($author);

    $group = ApproverGroup::create(['name' => 'Redactie']);
    $policy = ReviewPolicy::create([
        'name' => 'Publicatie van een pagina',
        'subject_type' => PageVersionProposalHandler::SUBJECT_TYPE,
        'auto_apply' => false,
        'steps' => [['assignee_type' => 'group', 'assignee_id' => $group->id]],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = app(ProposalEngine::class)->submit(
        subjectType: PageVersionProposalHandler::SUBJECT_TYPE,
        changeType: ChangeType::Update,
        payload: ['page_id' => $page->id],
        proposer: $author,
        subjectId: $draft->id,
        policy: $policy,
    );

    $reviewerUser = User::factory()->create(['email_verified_at' => now()]);
    $reviewer = Person::create(['first_name' => 'R', 'last_name' => 'Eviewer', 'account_id' => $reviewerUser->id]);
    $group->members()->attach($reviewer->id);

    $this->actingAs($reviewerUser)
        ->get(route('portal.wijzigingsvoorstellen.diff', $proposal))
        ->assertOk();
});

it('weigert een willekeurig ander lid', function () {
    $author = Person::create(['first_name' => 'A', 'last_name' => 'Uteur']);
    [$page, $draft] = makePageWithPendingVersion($author);

    $proposal = app(ProposalEngine::class)->submit(
        subjectType: PageVersionProposalHandler::SUBJECT_TYPE,
        changeType: ChangeType::Update,
        payload: ['page_id' => $page->id],
        proposer: $author,
        subjectId: $draft->id,
    );

    $outsiderUser = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'O', 'last_name' => 'Utsider', 'account_id' => $outsiderUser->id]);

    $this->actingAs($outsiderUser)
        ->get(route('portal.wijzigingsvoorstellen.diff', $proposal))
        ->assertForbidden();
});

it('geeft 404 voor een voorstel van een ander subject_type', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B', 'account_id' => $user->id]);

    $proposal = Proposal::create([
        'subject_type' => 'person.field_update',
        'change_type' => ChangeType::Update,
        'payload' => ['field' => 'first_name', 'old_value' => 'x', 'new_value' => 'y', 'person_id' => $person->id],
        'proposed_by_person_id' => $person->id,
        'status' => ProposalStatus::InReview,
        'current_step' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('portal.wijzigingsvoorstellen.diff', $proposal))
        ->assertNotFound();
});
