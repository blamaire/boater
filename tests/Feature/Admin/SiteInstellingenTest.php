<?php

use App\Livewire\Admin\SiteInstellingen;
use App\Models\Person;
use App\Models\Role;
use App\Models\SiteSettings;
use App\Models\User;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('vereist site_settings.manage permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/instellingen')->assertForbidden();
});

it('rendert het formulier voor een beheerder', function () {
    $this->actingAs($this->beheerder)->get('/beheer/instellingen')->assertOk()->assertSee('Site-instellingen');
});

it('slaat contactgegevens en sociale-media-URLs op', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(SiteInstellingen::class)
        ->set('contact_name', 'RZVG')
        ->set('contact_email', 'info@rzvg.nl')
        ->set('contact_phone', '0182-123456')
        ->set('contact_address', "Reeuwijksestraatweg 1\n2811 XX Reeuwijk")
        ->set('facebook_url', 'https://facebook.com/rzvg')
        ->set('instagram_url', 'https://instagram.com/rzvg')
        ->set('youtube_url', 'https://youtube.com/@rzvg')
        ->call('save')
        ->assertHasNoErrors();

    $settings = SiteSettings::current();
    expect($settings->contact_email)->toBe('info@rzvg.nl')
        ->and($settings->contact_phone)->toBe('0182-123456')
        ->and($settings->facebook_url)->toBe('https://facebook.com/rzvg')
        ->and($settings->hasSocials())->toBeTrue();
});

it('valideert e-mailformaat en URL-formaat', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(SiteInstellingen::class)
        ->set('contact_email', 'geen-mail')
        ->set('facebook_url', 'niet-echt-een-url')
        ->call('save')
        ->assertHasErrors(['contact_email', 'facebook_url']);
});

it('toont het contactblok in de publieke footer wanneer ingevuld', function () {
    $this->seed(MembershipTypeSeeder::class);

    $s = SiteSettings::current();
    $s->update([
        'contact_name' => 'RZVG',
        'contact_email' => 'info@rzvg.nl',
        'contact_phone' => '0182-123456',
        'facebook_url' => 'https://facebook.com/rzvg',
    ]);

    $this->get('/lid-worden')
        ->assertOk()
        ->assertSee('info@rzvg.nl')
        ->assertSee('0182-123456');
});

it('toont de fallback-verenigingsnaam als contact_name leeg is', function () {
    $this->seed(MembershipTypeSeeder::class);

    $this->get('/lid-worden')
        ->assertOk()
        ->assertSee('Roei- en Zeilvereniging Gouda');
});
