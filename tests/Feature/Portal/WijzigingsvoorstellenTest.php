<?php

use App\Enums\ChangeType;
use App\Enums\PageVersionStatus;
use App\Enums\ProposalStatus;
use App\Livewire\Portal\Wijzigingsvoorstellen;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\ReviewPolicy;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Template;
use App\Models\User;
use App\Services\Proposals\Handlers\PageVersionProposalHandler;
use App\Services\Proposals\Handlers\PersonFieldUpdateHandler;
use App\Services\Proposals\ProposalEngine;
use App\Services\Proposals\ProposalHandlerRegistry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\FakeProposalHandler;

function loginPerson(string $firstName = 'T', string $lastName = 'Ester'): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => $firstName, 'last_name' => $lastName, 'account_id' => $user->id]);

    return [$user, $person];
}

it('toont een afgewezen voorstel als actie-nodig totdat het gearchiveerd wordt', function () {
    [$user, $person] = loginPerson();

    $rejected = Proposal::create([
        'subject_type' => PersonFieldUpdateHandler::SUBJECT_TYPE,
        'change_type' => ChangeType::Update,
        'payload' => ['person_id' => $person->id, 'field' => 'first_name', 'old_value' => 'T', 'new_value' => 'Nieuw'],
        'proposed_by_person_id' => $person->id,
        'status' => ProposalStatus::Rejected,
        'decision_reason' => 'past niet bij beleid',
        'current_step' => 0,
    ]);

    // Zolang niet gearchiveerd staat het voorstel in de "actie nodig"-sectie
    // met een Archiveren-knop, buiten de ingeklapte "Afgehandeld"-lijst.
    Livewire::actingAs($user)
        ->test(Wijzigingsvoorstellen::class)
        ->assertSee('past niet bij beleid')
        ->assertSee('Afgewezen — actie nodig')
        ->assertSee('Archiveren')
        ->assertDontSee('Afgehandeld (');

    Livewire::actingAs($user)
        ->test(Wijzigingsvoorstellen::class)
        ->call('archive', $rejected->id);

    expect($rejected->refresh()->archived_at)->not->toBeNull();

    Livewire::actingAs($user)
        ->test(Wijzigingsvoorstellen::class)
        ->assertDontSee('Afgewezen — actie nodig')
        ->assertSee('Afgehandeld (1)');
});

it('toont een eigen open voorstel en verbergt een gesloten voorstel niet uit de DOM', function () {
    [$user, $person] = loginPerson();

    $open = Proposal::create([
        'subject_type' => PersonFieldUpdateHandler::SUBJECT_TYPE,
        'change_type' => ChangeType::Update,
        'payload' => ['person_id' => $person->id, 'field' => 'first_name', 'old_value' => 'T', 'new_value' => 'Nieuw'],
        'proposed_by_person_id' => $person->id,
        'status' => ProposalStatus::InReview,
        'current_step' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(Wijzigingsvoorstellen::class)
        ->assertSee('Wijziging van voornaam')
        ->assertSee('Nieuw');

    expect($open->status)->toBe(ProposalStatus::InReview);
});

it('trekt een eigen open voorstel in maar niet dat van een ander', function () {
    [$user, $person] = loginPerson();
    [, $other] = loginPerson('An', 'Der');

    $mine = Proposal::create([
        'subject_type' => PersonFieldUpdateHandler::SUBJECT_TYPE,
        'change_type' => ChangeType::Update,
        'payload' => ['person_id' => $person->id, 'field' => 'first_name', 'old_value' => 'T', 'new_value' => 'Nieuw'],
        'proposed_by_person_id' => $person->id,
        'status' => ProposalStatus::InReview,
        'current_step' => 1,
    ]);
    $othersProposal = Proposal::create([
        'subject_type' => PersonFieldUpdateHandler::SUBJECT_TYPE,
        'change_type' => ChangeType::Update,
        'payload' => ['person_id' => $other->id, 'field' => 'first_name', 'old_value' => 'An', 'new_value' => 'Nieuw'],
        'proposed_by_person_id' => $other->id,
        'status' => ProposalStatus::InReview,
        'current_step' => 1,
    ]);

    // Ownership wordt afgedwongen met firstOrFail() (zelfde patroon als
    // MijnLidmaatschap::withdrawProposal()) — een voorstel van iemand
    // anders bestaat simpelweg niet binnen de eigen where-scope, dus dat
    // resulteert in een 404 i.p.v. een stille no-op.
    expect(fn () => Livewire::actingAs($user)
        ->test(Wijzigingsvoorstellen::class)
        ->call('withdraw', $othersProposal->id)
    )->toThrow(ModelNotFoundException::class);

    expect($othersProposal->refresh()->status)->toBe(ProposalStatus::InReview);

    Livewire::actingAs($user)
        ->test(Wijzigingsvoorstellen::class)
        ->call('withdraw', $mine->id);

    expect($mine->refresh()->status)->toBe(ProposalStatus::Withdrawn);
});

it('laat een toegewezen beslisser een voorstel goedkeuren maar niet de indiener zelf', function () {
    $engine = app(ProposalEngine::class);
    [, $proposer] = loginPerson('P', 'Roposer');
    [$reviewerUser, $reviewer] = loginPerson('R', 'Eviewer');

    $role = Role::create(['name' => 'ReviewersXYZ']);
    RoleAssignment::create([
        'person_id' => $reviewer->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);

    $policy = ReviewPolicy::create([
        'name' => 'Test-beleid',
        'subject_type' => 'test.decide',
        'auto_apply' => false,
        'steps' => [['assignee_type' => 'role', 'assignee_id' => $role->id]],
        'resubmit_behavior' => 'restart',
    ]);
    app(ProposalHandlerRegistry::class)->register('test.decide', new FakeProposalHandler);

    $proposal = $engine->submit('test.decide', ChangeType::Update, ['x' => 1], $proposer, policy: $policy);
    $step = $proposal->steps->first();

    // De indiener zelf mag niet beslissen, ook al is 'ie geen toegewezen reviewer.
    RoleAssignment::create([
        'person_id' => $proposer->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);
    $proposerUser = $proposer->account;

    Livewire::actingAs($proposerUser)
        ->test(Wijzigingsvoorstellen::class)
        ->call('approve', $step->id);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::InReview);

    Livewire::actingAs($reviewerUser)
        ->test(Wijzigingsvoorstellen::class)
        ->call('approve', $step->id);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Applied);
});

it('vereist een reden voor afwijzen en past die daarna toe', function () {
    $engine = app(ProposalEngine::class);
    [, $proposer] = loginPerson('P', 'Roposer');
    [$reviewerUser, $reviewer] = loginPerson('R', 'Eviewer2');

    $role = Role::create(['name' => 'ReviewersABC']);
    RoleAssignment::create([
        'person_id' => $reviewer->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);

    $policy = ReviewPolicy::create([
        'name' => 'Test-beleid-2',
        'subject_type' => 'test.decide2',
        'auto_apply' => false,
        'steps' => [['assignee_type' => 'role', 'assignee_id' => $role->id]],
        'resubmit_behavior' => 'restart',
    ]);
    app(ProposalHandlerRegistry::class)->register('test.decide2', new FakeProposalHandler);

    $proposal = $engine->submit('test.decide2', ChangeType::Update, ['x' => 1], $proposer, policy: $policy);
    $step = $proposal->steps->first();

    Livewire::actingAs($reviewerUser)
        ->test(Wijzigingsvoorstellen::class)
        ->call('reject', $step->id)
        ->assertHasErrors("reason.{$step->id}");

    expect($proposal->refresh()->status)->toBe(ProposalStatus::InReview);

    Livewire::actingAs($reviewerUser)
        ->test(Wijzigingsvoorstellen::class)
        ->set("reasonInputs.{$step->id}", 'past niet bij beleid')
        ->call('reject', $step->id);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Rejected)
        ->and($proposal->decision_reason)->toBe('past niet bij beleid');
});

it('trekt een pagina-voorstel in via editPageProposal en herstelt de conceptversie', function () {
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'edit-test',
        'title' => 'Edit test',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);
    [$user, $person] = loginPerson('Auteur', 'Test');
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::InReview,
        'created_by_person_id' => $person->id,
    ]);

    $engine = app(ProposalEngine::class);
    $proposal = $engine->submit(
        subjectType: PageVersionProposalHandler::SUBJECT_TYPE,
        changeType: ChangeType::Create,
        payload: ['page_id' => $page->id],
        proposer: $person,
        subjectId: $version->id,
    );

    Livewire::actingAs($user)
        ->test(Wijzigingsvoorstellen::class)
        ->call('editPageProposal', $proposal->id)
        ->assertRedirect(route('admin.pages.editor', $page));

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Withdrawn)
        ->and($version->refresh()->status)->toBe(PageVersionStatus::Draft);
});

it('archiveert een afgewezen pagina-voorstel via editPageProposal en herstelt de conceptversie', function () {
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'edit-test-rejected',
        'title' => 'Edit test afgewezen',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);
    [$user, $person] = loginPerson('Auteur', 'Rejected');
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::InReview,
        'created_by_person_id' => $person->id,
    ]);

    $proposal = Proposal::create([
        'subject_type' => PageVersionProposalHandler::SUBJECT_TYPE,
        'subject_id' => $version->id,
        'change_type' => ChangeType::Create,
        'payload' => ['page_id' => $page->id],
        'proposed_by_person_id' => $person->id,
        'status' => ProposalStatus::Rejected,
        'decision_reason' => 'niet akkoord',
        'current_step' => 0,
    ]);

    Livewire::actingAs($user)
        ->test(Wijzigingsvoorstellen::class)
        ->call('editPageProposal', $proposal->id)
        ->assertRedirect(route('admin.pages.editor', $page));

    expect($proposal->refresh())
        ->status->toBe(ProposalStatus::Rejected)
        ->archived_at->not->toBeNull();
    expect($version->refresh()->status)->toBe(PageVersionStatus::Draft);
});
