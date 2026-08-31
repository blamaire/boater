<?php

use App\Livewire\Admin\ActiviteitBeheer;
use App\Mail\TemplatedMail;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ApproverGroup;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\Activities\EnrollmentService;
use Database\Seeders\ActivityCategorySeeder;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ActivityCategorySeeder::class);
    $this->seed(MessageTemplateSeeder::class);
    $this->category = ActivityCategory::query()->where('slug', 'roeien')->firstOrFail();

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('voegt een gedelegeerd beheerder toe met standaard notify aan en kan die weer verwijderen', function () {
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toer', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);
    $manager = Person::create(['first_name' => 'M', 'last_name' => 'anager', 'email' => 'manager@example.test']);

    $this->actingAs($this->beheerder);

    $component = Livewire::test(ActiviteitBeheer::class)
        ->call('toggleManagers', $activity->id)
        ->set('addManagerPersonId', $manager->id)
        ->call('addManager', $activity->id);

    $notifyEnabled = function () use ($activity, $manager): bool {
        // @phpstan-ignore property.notFound (dynamische pivot-kolom, withPivot('notify'))
        return (bool) $activity->managers()->where('persons.id', $manager->id)->first()->pivot->notify;
    };

    expect($activity->managers()->where('persons.id', $manager->id)->exists())->toBeTrue()
        ->and($notifyEnabled())->toBeTrue();

    $component->call('toggleManagerNotify', $activity->id, $manager->id);
    expect($notifyEnabled())->toBeFalse();

    $component->call('removeManager', $activity->id, $manager->id);
    expect($activity->managers()->where('persons.id', $manager->id)->exists())->toBeFalse();
});

it('voegt een goedkeuringsgroep toe als beheerder en kan die weer verwijderen', function () {
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toer', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);
    $group = ApproverGroup::create(['name' => 'Bestuur']);
    $member = Person::create(['first_name' => 'M', 'last_name' => 'ember']);
    $group->members()->attach($member->id);

    $this->actingAs($this->beheerder);

    $component = Livewire::test(ActiviteitBeheer::class)
        ->call('toggleManagers', $activity->id)
        ->set('addManagerGroupId', $group->id)
        ->call('addManagerGroup', $activity->id);

    expect($activity->fresh()->isManagedBy($member))->toBeTrue();

    $notifyEnabled = function () use ($activity, $group): bool {
        // @phpstan-ignore property.notFound (dynamische pivot-kolom, withPivot('notify'))
        return (bool) $activity->managerGroups()->where('approver_groups.id', $group->id)->first()->pivot->notify;
    };
    expect($notifyEnabled())->toBeTrue();

    $component->call('toggleManagerGroupNotify', $activity->id, $group->id);
    expect($notifyEnabled())->toBeFalse();

    $component->call('removeManagerGroup', $activity->id, $group->id);
    expect($activity->fresh()->isManagedBy($member))->toBeFalse();
});

it('mailt leden van een gekoppelde beheerdersgroep bij een wijziging', function () {
    Mail::fake();

    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toer', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);
    $group = ApproverGroup::create(['name' => 'Bestuur']);
    $member = Person::create(['first_name' => 'M', 'last_name' => 'ember', 'email' => 'bestuur-lid@example.test']);
    $group->members()->attach($member->id);
    $activity->managerGroups()->attach($group->id, ['notify' => true]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('editActivity', $activity->id)
        ->set('location', 'Nieuwe steiger')
        ->call('saveActivity');

    Mail::assertQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo('bestuur-lid@example.test'));
});

it('mailt een beheerder met notify=true bij een wijziging van de activiteit', function () {
    Mail::fake();

    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toer', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);
    $manager = Person::create(['first_name' => 'M', 'last_name' => 'anager', 'email' => 'manager@example.test']);
    $activity->managers()->attach($manager->id, ['notify' => true]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('editActivity', $activity->id)
        ->set('location', 'Nieuwe steiger')
        ->call('saveActivity');

    Mail::assertQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo('manager@example.test'));
});

it('mailt geen beheerder met notify=false', function () {
    Mail::fake();

    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toer', 'starts_at' => now()->addDays(2), 'capacity' => 5,
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);
    $manager = Person::create(['first_name' => 'M', 'last_name' => 'anager', 'email' => 'manager@example.test']);
    $activity->managers()->attach($manager->id, ['notify' => false]);

    $lid = User::factory()->create(['email_verified_at' => now()]);
    $lidPerson = Person::create(['first_name' => 'L', 'last_name' => 'id', 'account_id' => $lid->id]);

    app(EnrollmentService::class)->enroll($activity, $lidPerson);

    Mail::assertNotQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo('manager@example.test'));
});

it('mailt een beheerder bij een nieuwe inschrijving', function () {
    Mail::fake();

    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toer', 'starts_at' => now()->addDays(2), 'capacity' => 5,
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);
    $manager = Person::create(['first_name' => 'M', 'last_name' => 'anager', 'email' => 'manager@example.test']);
    $activity->managers()->attach($manager->id, ['notify' => true]);

    $lid = User::factory()->create(['email_verified_at' => now()]);
    $lidPerson = Person::create(['first_name' => 'L', 'last_name' => 'id', 'account_id' => $lid->id]);

    app(EnrollmentService::class)->enroll($activity, $lidPerson);

    Mail::assertQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo('manager@example.test'));
});
