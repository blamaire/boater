<?php

use App\Livewire\Admin\ActiviteitBeheer;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\MediaAsset;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActivityCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ActivityCategorySeeder::class);
    $this->category = ActivityCategory::query()->where('slug', 'roeien')->firstOrFail();

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('koppelt een geüpload bestand aan een activiteit met context activity', function () {
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toer', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('toggleFiles', $activity->id)
        ->set('newFiles', [UploadedFile::fake()->create('programma.pdf', 100, 'application/pdf')])
        ->call('uploadFiles', $activity->id)
        ->assertHasNoErrors();

    expect($activity->files()->count())->toBe(1);
    $asset = $activity->files()->first();
    expect($asset->context)->toBe(MediaAsset::CONTEXT_ACTIVITY)
        ->and($asset->original_name)->toBe('programma.pdf');
});

it('ontkoppelt een bestand van een activiteit', function () {
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toer', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('toggleFiles', $activity->id)
        ->set('newFiles', [UploadedFile::fake()->create('bijlage.pdf', 50, 'application/pdf')])
        ->call('uploadFiles', $activity->id);

    $asset = $activity->files()->firstOrFail();

    Livewire::test(ActiviteitBeheer::class)
        ->call('removeFile', $activity->id, $asset->id);

    expect($activity->files()->count())->toBe(0)
        ->and(MediaAsset::query()->find($asset->id))->not->toBeNull();
});
