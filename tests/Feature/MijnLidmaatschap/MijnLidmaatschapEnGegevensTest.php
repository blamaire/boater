<?php

use App\Livewire\Portal\MijnLidmaatschap;
use App\Models\AuditEntry;
use App\Models\Person;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'iris@example.nl', 'email_verified_at' => now()]);
    $this->person = Person::create([
        'first_name' => 'Iris',
        'last_name' => 'Lid',
        'email' => 'iris@example.nl',
        'account_id' => $this->user->id,
    ]);
    $this->actingAs($this->user);
});

it('wijzigt met het e-mailadres ook het inlogadres en vraagt om bevestiging', function () {
    Notification::fake();

    Livewire::test(MijnLidmaatschap::class)
        ->set('person.email', 'iris.nieuw@example.nl')
        ->call('saveContact')
        ->assertHasNoErrors()
        ->assertSet('statusMessage', fn ($m) => str_contains((string) $m, 'inlogadres'));

    expect($this->person->refresh()->email)->toBe('iris.nieuw@example.nl')
        ->and($this->user->refresh()->email)->toBe('iris.nieuw@example.nl')
        ->and($this->user->email_verified_at)->toBeNull()
        ->and(AuditEntry::query()->where('action', 'user.email_updated')->count())->toBe(1);

    Notification::assertSentTo($this->user, QueuedVerifyEmail::class);
});

it('weigert een e-mailadres dat al bij een ander account hoort', function () {
    User::factory()->create(['email' => 'bezet@example.nl']);

    Livewire::test(MijnLidmaatschap::class)
        ->set('person.email', 'bezet@example.nl')
        ->call('saveContact')
        ->assertHasErrors(['person.email']);

    expect($this->user->refresh()->email)->toBe('iris@example.nl')
        ->and($this->person->refresh()->email)->toBe('iris@example.nl');
});

it('weigert een leeg e-mailadres omdat het ook het inlogadres is', function () {
    Livewire::test(MijnLidmaatschap::class)
        ->set('person.email', '')
        ->call('saveContact')
        ->assertHasErrors(['person.email']);

    expect($this->user->refresh()->email)->toBe('iris@example.nl');
});

it('toont wachtwoord wijzigen op de pagina', function () {
    $this->get(route('portal.mijn-lidmaatschap'))
        ->assertOk()
        ->assertSee('Wachtwoord wijzigen')
        ->assertSee('action="'.route('password.update').'"', false);
});

it('stuurt /profile door naar Gegevens en lidmaatschap', function () {
    $this->get('/profile')->assertRedirect(route('portal.mijn-lidmaatschap'));
});

it('toont in het menu Gegevens en lidmaatschap in plaats van Profiel', function () {
    $this->get(route('portal.mijn-lidmaatschap'))
        ->assertSee('Gegevens en lidmaatschap')
        ->assertDontSee('>Profiel</a>', false);
});
