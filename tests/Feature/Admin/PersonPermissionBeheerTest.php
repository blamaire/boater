<?php

use App\Livewire\Admin\PersonPermissionBeheer;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\EffectivePermissions;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    $beheerderPerson = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id]);
    $beheerderPerson->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $this->doelPerson = Person::create(['first_name' => 'Doel', 'last_name' => 'Persoon']);
});

it('vereist users.manage permissie voor de rechten-pagina', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get("/beheer/personen/{$this->doelPerson->id}/rechten")->assertForbidden();
});

it('rendert de rechten-pagina voor een beheerder', function () {
    $this->actingAs($this->beheerder)
        ->get("/beheer/personen/{$this->doelPerson->id}/rechten")
        ->assertOk()
        ->assertSee('Rechten voor Doel Persoon');
});

it('kent een direct recht toe en effectieve rechten reflecteren dat', function () {
    $perm = Permission::query()->where('key', 'menu.manage')->firstOrFail();

    $this->actingAs($this->beheerder);

    Livewire::test(PersonPermissionBeheer::class, ['person' => $this->doelPerson])
        ->call('grant', $perm->id);

    expect(PersonPermission::query()->count())->toBe(1)
        ->and(app(EffectivePermissions::class)->has($this->doelPerson, 'menu.manage'))->toBeTrue();
});

it('trekt een directe toewijzing weer in', function () {
    $perm = Permission::query()->where('key', 'menu.manage')->firstOrFail();
    $pp = PersonPermission::query()->create([
        'person_id' => $this->doelPerson->id,
        'permission_id' => $perm->id,
        'status' => 'active',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(PersonPermissionBeheer::class, ['person' => $this->doelPerson])
        ->call('revoke', $pp->id);

    expect(PersonPermission::query()->count())->toBe(0)
        ->and(app(EffectivePermissions::class)->has($this->doelPerson, 'menu.manage'))->toBeFalse();
});
