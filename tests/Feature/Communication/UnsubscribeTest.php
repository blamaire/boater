<?php

use App\Models\CommunicationPreference;
use App\Models\Person;
use Illuminate\Support\Facades\URL;

it('zet opted_in op false via een geldige ondertekende afmeldlink', function () {
    $person = Person::create(['first_name' => 'Jan', 'last_name' => 'Test', 'email' => 'jan@example.test']);
    CommunicationPreference::create(['person_id' => $person->id, 'category' => 'nieuwsbrief', 'opted_in' => true]);

    $url = URL::signedRoute('communication-preferences.unsubscribe', ['person' => $person->id, 'category' => 'nieuwsbrief']);

    $this->get($url)->assertOk()->assertSee('afgemeld', false);

    $preference = CommunicationPreference::query()->where('person_id', $person->id)->where('category', 'nieuwsbrief')->firstOrFail();
    expect($preference->opted_in)->toBeFalse();
});

it('maakt een rij aan als er nog geen voorkeur bestond', function () {
    $person = Person::create(['first_name' => 'Jan', 'last_name' => 'Test', 'email' => 'jan@example.test']);

    $url = URL::signedRoute('communication-preferences.unsubscribe', ['person' => $person->id, 'category' => 'nieuwsbrief']);

    $this->get($url)->assertOk();

    expect(CommunicationPreference::query()->where('person_id', $person->id)->where('category', 'nieuwsbrief')->where('opted_in', false)->exists())->toBeTrue();
});

it('weigert een niet-ondertekende of gemanipuleerde afmeldlink', function () {
    $person = Person::create(['first_name' => 'Jan', 'last_name' => 'Test', 'email' => 'jan@example.test']);

    $this->get("/communicatievoorkeuren/afmelden/{$person->id}/nieuwsbrief")->assertForbidden();
});
