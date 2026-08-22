<?php

use App\Enums\FeedbackCategory;
use App\Enums\FeedbackStatus;
use App\Livewire\Admin\FeedbackBeheer;
use App\Models\AuditEntry;
use App\Models\Feedback;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function feedbackManagerUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'Beheer', 'last_name' => 'Der', 'account_id' => $user->id]);
    PersonPermission::create([
        'person_id' => $person->id,
        'permission_id' => Permission::where('key', 'feedback.manage')->value('id'),
        'status' => 'active',
    ]);

    return $user;
}

function feedbackEntry(array $overrides = []): Feedback
{
    $person = Person::create(['first_name' => 'Ge', 'last_name' => 'Ver'.uniqid(), 'account_id' => User::factory()->create(['email_verified_at' => now()])->id]);

    return Feedback::create(array_merge([
        'person_id' => $person->id,
        'category' => FeedbackCategory::Bug,
        'message' => 'Test-bericht',
        'url' => 'https://rzvg.test/pagina/over-ons',
        'app_version' => 'v0.1.0-1-gabc1234',
        'status' => FeedbackStatus::Nieuw,
    ], $overrides));
}

it('vereist feedback.manage om de terugkoppellijst te zien', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'Jan', 'last_name' => 'Lid', 'account_id' => $user->id]);

    $this->actingAs($user)->get('/beheer/terugkoppeling')->assertForbidden();
});

it('toont ingediende terugkoppeling voor een beheerder', function () {
    feedbackEntry(['message' => 'Iets werkt niet lekker']);

    $this->actingAs(feedbackManagerUser())
        ->get('/beheer/terugkoppeling')
        ->assertOk()
        ->assertSee('Iets werkt niet lekker');
});

it('filtert op status', function () {
    feedbackEntry(['message' => 'Nieuw item', 'status' => FeedbackStatus::Nieuw]);
    feedbackEntry(['message' => 'Afgehandeld item', 'status' => FeedbackStatus::Afgehandeld]);

    Livewire::actingAs(feedbackManagerUser())
        ->test(FeedbackBeheer::class)
        ->set('filterStatus', FeedbackStatus::Afgehandeld->value)
        ->assertSee('Afgehandeld item')
        ->assertDontSee('Nieuw item');
});

it('filtert op categorie', function () {
    feedbackEntry(['message' => 'Een bug', 'category' => FeedbackCategory::Bug]);
    feedbackEntry(['message' => 'Een suggestie', 'category' => FeedbackCategory::Suggestie]);

    Livewire::actingAs(feedbackManagerUser())
        ->test(FeedbackBeheer::class)
        ->set('filterCategory', FeedbackCategory::Suggestie->value)
        ->assertSee('Een suggestie')
        ->assertDontSee('Een bug');
});

it('werkt de status bij en legt het vast in het auditlogboek', function () {
    $feedback = feedbackEntry();

    Livewire::actingAs(feedbackManagerUser())
        ->test(FeedbackBeheer::class)
        ->call('updateStatus', $feedback->id, FeedbackStatus::InBehandeling->value);

    expect($feedback->fresh()->status)->toBe(FeedbackStatus::InBehandeling);

    $entry = AuditEntry::where('action', 'feedback.status_updated')->where('subject_id', $feedback->id)->firstOrFail();
    expect($entry->before['status'])->toBe('nieuw')
        ->and($entry->after['status'])->toBe('in_behandeling');
});
