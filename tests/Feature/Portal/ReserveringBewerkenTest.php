<?php

use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use App\Enums\ReservableObjectStatus;
use App\Enums\ReservationConstraintType;
use App\Livewire\Portal\ReserveringBewerken;
use App\Models\ObjectCategory;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\ReservableObject;
use App\Models\Reservation;
use App\Models\ReservationRule;
use App\Models\User;
use App\Services\Proposals\Handlers\ReservationProposalHandler;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReviewPolicySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);
    $this->seed(ReviewPolicySeeder::class);

    $this->category = ObjectCategory::create([
        'name' => 'Zaal',
        'slug' => 'zaal',
        'sort_order' => 10,
    ]);
    $this->object = ReservableObject::create([
        'object_category_id' => $this->category->id,
        'name' => 'Grote zaal',
        'sort_order' => 10,
        'status' => ReservableObjectStatus::Available,
    ]);

    ReservationRule::create([
        'name' => 'Max 60m',
        'object_category_id' => $this->category->id,
        'constraint_type' => ReservationConstraintType::Duration,
        'limit_value' => 60,
        'per_person' => true,
    ]);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->person = Person::create([
        'first_name' => 'Jan',
        'last_name' => 'Reserveerder',
        'account_id' => $this->user->id,
    ]);

    $this->proposal = Proposal::create([
        'subject_type' => ReservationProposalHandler::SUBJECT_TYPE,
        'change_type' => ChangeType::Create,
        'payload' => [
            'reservable_object_id' => $this->object->id,
            'person_id' => $this->person->id,
            'requested_by_person_id' => $this->person->id,
            'starts_at' => Carbon::parse('2027-01-01 09:00')->toIso8601String(),
            'ends_at' => Carbon::parse('2027-01-01 11:00')->toIso8601String(),
            'note' => 'Oorspronkelijke notitie',
            'submission_reason' => 'violations',
            'violations' => [['rule_id' => 1, 'rule_name' => 'Max 60m', 'message' => 'Te lang']],
        ],
        'proposed_by_person_id' => $this->person->id,
        'status' => ProposalStatus::InReview,
        'current_step' => 1,
    ]);
});

it('vult het formulier vooraf met de bestaande periode en notitie', function () {
    Livewire::actingAs($this->user)
        ->test(ReserveringBewerken::class, ['proposal' => $this->proposal])
        ->assertSet('note', 'Oorspronkelijke notitie')
        ->assertSet('startsAt', '2027-01-01T09:00');
});

it('trekt in en reserveert direct als de nieuwe periode geen regel meer overtreedt', function () {
    Livewire::actingAs($this->user)
        ->test(ReserveringBewerken::class, ['proposal' => $this->proposal])
        ->set('startsAt', '2027-02-01T09:00')
        ->set('endsAt', '2027-02-01T09:30')
        ->set('note', 'Aangepast')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->proposal->refresh()->status)->toBe(ProposalStatus::Withdrawn);

    $reservation = Reservation::query()
        ->where('reservable_object_id', $this->object->id)
        ->where('person_id', $this->person->id)
        ->first();

    expect($reservation)->not->toBeNull()
        ->and($reservation->note)->toBe('Aangepast');

    expect(Proposal::query()->where('subject_type', ReservationProposalHandler::SUBJECT_TYPE)->where('status', ProposalStatus::InReview)->count())->toBe(0);
});

it('trekt in en dient een nieuw voorstel in als de aangepaste periode nog steeds een regel overtreedt', function () {
    Livewire::actingAs($this->user)
        ->test(ReserveringBewerken::class, ['proposal' => $this->proposal])
        ->set('startsAt', '2027-03-01T09:00')
        ->set('endsAt', '2027-03-01T11:00')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->proposal->refresh()->status)->toBe(ProposalStatus::Withdrawn);

    $newProposal = Proposal::query()
        ->where('id', '!=', $this->proposal->id)
        ->where('subject_type', ReservationProposalHandler::SUBJECT_TYPE)
        ->latest('id')
        ->first();

    expect($newProposal)->not->toBeNull()
        ->and($newProposal->status)->toBe(ProposalStatus::InReview)
        ->and($newProposal->proposed_by_person_id)->toBe($this->person->id);
});

it('dient opnieuw in vanuit een afgewezen voorstel en archiveert het oude automatisch', function () {
    $this->proposal->update(['status' => ProposalStatus::Rejected, 'decision_reason' => 'te lang']);

    Livewire::actingAs($this->user)
        ->test(ReserveringBewerken::class, ['proposal' => $this->proposal])
        ->set('startsAt', '2027-04-01T09:00')
        ->set('endsAt', '2027-04-01T09:30')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->proposal->refresh())
        ->status->toBe(ProposalStatus::Rejected)
        ->archived_at->not->toBeNull();

    $reservation = Reservation::query()
        ->where('reservable_object_id', $this->object->id)
        ->where('person_id', $this->person->id)
        ->first();
    expect($reservation)->not->toBeNull();
});

it('weigert een lid dat niet de indiener is', function () {
    $otherUser = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'An', 'last_name' => 'Der', 'account_id' => $otherUser->id]);

    $this->actingAs($otherUser)
        ->get(route('portal.wijzigingsvoorstellen.reservation.edit', $this->proposal))
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
        ->get(route('portal.wijzigingsvoorstellen.reservation.edit', $otherProposal))
        ->assertNotFound();
});

it('weigert een al afgehandeld voorstel', function () {
    $this->proposal->update(['status' => ProposalStatus::Applied]);

    $this->actingAs($this->user)
        ->get(route('portal.wijzigingsvoorstellen.reservation.edit', $this->proposal))
        ->assertForbidden();
});
