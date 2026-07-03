<?php

use App\Enums\ProposalStatus;
use App\Livewire\Portal\MijnLidmaatschap;
use App\Models\AuditEntry;
use App\Models\Household;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Proposals\Handlers\PersonFieldUpdateHandler;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReviewPolicySeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ReviewPolicySeeder::class);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->person = Person::create([
        'first_name' => 'Iris',
        'last_name' => 'Lid',
        'email' => 'iris@example.nl',
        'phone' => '0612345678',
        'account_id' => $this->user->id,
    ]);
    $this->actingAs($this->user);
});

it('slaat een niet-gevoelig veld (phone) direct op en logt naar audit', function () {
    Livewire::test(MijnLidmaatschap::class)
        ->set('person.phone', '0611111111')
        ->call('saveDirect', 'person', 'phone')
        ->assertHasNoErrors();

    expect($this->person->refresh()->phone)->toBe('0611111111');

    expect(AuditEntry::query()->where('action', 'person.field_updated')->count())->toBe(1);
});

it('slaat een adres-veld direct op en maakt een household aan indien nodig', function () {
    Livewire::test(MijnLidmaatschap::class)
        ->set('household.street', 'Kortlandstraat')
        ->call('saveDirect', 'household', 'street')
        ->assertHasNoErrors();

    $this->person->refresh();
    expect($this->person->household)->not->toBeNull();
    expect($this->person->household->street)->toBe('Kortlandstraat');

    expect(AuditEntry::query()->where('action', 'household.field_updated')->count())->toBe(1);
});

it('werkt een bestaand household bij zonder een nieuw aan te maken', function () {
    $household = Household::create(['name' => 'Lid', 'city' => 'Gouda']);
    $this->person->household_id = $household->id;
    $this->person->save();

    Livewire::test(MijnLidmaatschap::class)
        ->set('household.city', 'Rotterdam')
        ->call('saveDirect', 'household', 'city')
        ->assertHasNoErrors();

    expect($household->refresh()->city)->toBe('Rotterdam');
    expect(Household::query()->count())->toBe(1);
});

it('dient een gevoelig veld (first_name) in als Proposal in review', function () {
    Livewire::test(MijnLidmaatschap::class)
        ->call('submitSensitive', 'first_name', 'Irene')
        ->assertHasNoErrors();

    $proposal = Proposal::query()->first();
    expect($proposal)->not->toBeNull();
    expect($proposal->subject_type)->toBe(PersonFieldUpdateHandler::SUBJECT_TYPE);
    expect($proposal->status)->toBe(ProposalStatus::InReview);
    expect($proposal->payload)->toMatchArray([
        'person_id' => $this->person->id,
        'field' => 'first_name',
        'new_value' => 'Irene',
        'old_value' => 'Iris',
    ]);

    // Naam blijft ongewijzigd totdat het voorstel is goedgekeurd.
    expect($this->person->refresh()->first_name)->toBe('Iris');

    expect(AuditEntry::query()->where('action', 'proposal.submitted')->count())->toBe(1);
});

it('valideert e-mail bij directe opslag', function () {
    Livewire::test(MijnLidmaatschap::class)
        ->set('person.email', 'geen-email')
        ->call('saveDirect', 'person', 'email')
        ->assertHasErrors(['person.email']);
});
