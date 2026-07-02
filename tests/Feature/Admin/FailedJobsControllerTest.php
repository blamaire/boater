<?php

use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function loginWithQueueManage(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create([
        'first_name' => 'F',
        'last_name' => 'Beheerder',
        'account_id' => $user->id,
    ]);
    PersonPermission::create([
        'person_id' => $person->id,
        'permission_id' => Permission::where('key', 'queue.manage')->value('id'),
        'status' => 'active',
    ]);

    return $user;
}

function makeFailedJob(?string $uuid = null): string
{
    $uuid = $uuid ?? Str::uuid()->toString();
    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['job' => 'App\\Jobs\\TestJob', 'data' => 'x']),
        'exception' => 'Iets misging',
        'failed_at' => now(),
    ]);

    return $uuid;
}

it('redirects guests to login', function () {
    $this->get('/beheer/failed-jobs')->assertRedirect('/login');
});

it('forbids users without queue.manage', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'J', 'last_name' => 'Lid', 'account_id' => $user->id]);

    $this->actingAs($user)->get('/beheer/failed-jobs')->assertForbidden();
});

it('shows the list to users with queue.manage', function () {
    $user = loginWithQueueManage();
    makeFailedJob();

    $this->actingAs($user)
        ->get('/beheer/failed-jobs')
        ->assertOk()
        ->assertSee('Failed jobs')
        ->assertSee('TestJob');
});

it('shows a friendly message when there are no failed jobs', function () {
    $user = loginWithQueueManage();

    $this->actingAs($user)
        ->get('/beheer/failed-jobs')
        ->assertOk()
        ->assertSee('Geen failed jobs');
});

it('calls queue:retry when retry action is triggered', function () {
    $user = loginWithQueueManage();
    $uuid = makeFailedJob();

    Artisan::shouldReceive('call')
        ->once()
        ->with('queue:retry', ['id' => [$uuid]]);

    $this->actingAs($user)
        ->post("/beheer/failed-jobs/{$uuid}/opnieuw")
        ->assertRedirect(route('admin.failed-jobs.index'));
});

it('calls queue:forget when destroy action is triggered', function () {
    $user = loginWithQueueManage();
    $uuid = makeFailedJob();

    Artisan::shouldReceive('call')
        ->once()
        ->with('queue:forget', ['id' => $uuid]);

    $this->actingAs($user)
        ->delete("/beheer/failed-jobs/{$uuid}")
        ->assertRedirect(route('admin.failed-jobs.index'));
});

it('returns 404 when retrying a non-existent job', function () {
    $user = loginWithQueueManage();

    $this->actingAs($user)
        ->post('/beheer/failed-jobs/does-not-exist/opnieuw')
        ->assertNotFound();
});
