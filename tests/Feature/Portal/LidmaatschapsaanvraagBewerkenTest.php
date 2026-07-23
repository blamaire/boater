<?php

use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use App\Livewire\Portal\LidmaatschapsaanvraagBewerken;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Proposals\Handlers\MembershipApplicationHandler;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReviewPolicySeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);
    $this->seed(ReviewPolicySeeder::class);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->person = Person::create([
        'first_name' => 'Jan',
        'last_name' => 'Bestaand',
        'account_id' => $this->user->id,
    ]);

    $this->payload = [
        'person' => [
            'first_name' => 'Jan', 'last_name_prefix' => null, 'last_name' => 'Bestaand',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'email' => 'jan@oud.test', 'phone' => null,
        ],
        'address' => [
            'postal_code' => '1234AB', 'house_number' => '1', 'house_number_addition' => null,
            'street' => 'Hoofdstraat', 'city' => 'Gouda',
        ],
        'membership_type_key' => 'a',
        'membership_type_override_reason' => null,
        'is_minor' => false,
        'guardian' => null,
        'agreements' => ['statutes' => true, 'house_rules' => true, 'privacy' => true],
    ];

    $this->proposal = Proposal::create([
        'subject_type' => MembershipApplicationHandler::SUBJECT_TYPE,
        'change_type' => ChangeType::Create,
        'payload' => $this->payload,
        'proposed_by_person_id' => $this->person->id,
        'status' => ProposalStatus::InReview,
        'current_step' => 1,
    ]);
});

it('vult het formulier vooraf met de bestaande payload', function () {
    Livewire::actingAs($this->user)
        ->test(LidmaatschapsaanvraagBewerken::class, ['proposal' => $this->proposal])
        ->assertSet('first_name', 'Jan')
        ->assertSet('email', 'jan@oud.test')
        ->assertSet('postal_code', '1234AB')
        ->assertSet('membership_type_key', 'a');
});

it('trekt het oude voorstel in en dient de bijgewerkte gegevens opnieuw in', function () {
    Livewire::actingAs($this->user)
        ->test(LidmaatschapsaanvraagBewerken::class, ['proposal' => $this->proposal])
        ->set('phone', '0611112222')
        ->set('agree_statutes', true)
        ->set('agree_house_rules', true)
        ->set('agree_privacy', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->proposal->refresh()->status)->toBe(ProposalStatus::Withdrawn);

    $newProposal = Proposal::query()
        ->where('id', '!=', $this->proposal->id)
        ->where('subject_type', MembershipApplicationHandler::SUBJECT_TYPE)
        ->latest('id')
        ->first();

    expect($newProposal)->not->toBeNull()
        ->and($newProposal->status)->toBe(ProposalStatus::InReview)
        ->and($newProposal->proposed_by_person_id)->toBe($this->person->id)
        ->and($newProposal->payload['person']['phone'])->toBe('0611112222')
        ->and($newProposal->payload['person']['first_name'])->toBe('Jan');
});

it('dient opnieuw in vanuit een afgewezen voorstel en archiveert het oude automatisch', function () {
    $this->proposal->update(['status' => ProposalStatus::Rejected, 'decision_reason' => 'past niet']);

    Livewire::actingAs($this->user)
        ->test(LidmaatschapsaanvraagBewerken::class, ['proposal' => $this->proposal])
        ->set('phone', '0699998888')
        ->set('agree_statutes', true)
        ->set('agree_house_rules', true)
        ->set('agree_privacy', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->proposal->refresh())
        ->status->toBe(ProposalStatus::Rejected)
        ->archived_at->not->toBeNull();

    $newProposal = Proposal::query()
        ->where('id', '!=', $this->proposal->id)
        ->where('subject_type', MembershipApplicationHandler::SUBJECT_TYPE)
        ->latest('id')
        ->first();

    expect($newProposal)->not->toBeNull()
        ->and($newProposal->status)->toBe(ProposalStatus::InReview)
        ->and($newProposal->payload['person']['phone'])->toBe('0699998888');
});

it('weigert een lid dat niet de indiener is', function () {
    $otherUser = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'An', 'last_name' => 'Der', 'account_id' => $otherUser->id]);

    $this->actingAs($otherUser)
        ->get(route('portal.wijzigingsvoorstellen.membership-application.edit', $this->proposal))
        ->assertForbidden();
});

it('geeft 404 voor een voorstel van een ander subject_type', function () {
    $otherProposal = Proposal::create([
        'subject_type' => 'person.field_update',
        'change_type' => ChangeType::Update,
        'payload' => ['field' => 'first_name', 'old_value' => 'x', 'new_value' => 'y', 'person_id' => $this->person->id],
        'proposed_by_person_id' => $this->person->id,
        'status' => ProposalStatus::InReview,
        'current_step' => 1,
    ]);

    $this->actingAs($this->user)
        ->get(route('portal.wijzigingsvoorstellen.membership-application.edit', $otherProposal))
        ->assertNotFound();
});

it('weigert een al afgehandeld voorstel', function () {
    $this->proposal->update(['status' => ProposalStatus::Applied]);

    $this->actingAs($this->user)
        ->get(route('portal.wijzigingsvoorstellen.membership-application.edit', $this->proposal))
        ->assertForbidden();
});
