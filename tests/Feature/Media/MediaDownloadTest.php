<?php

use App\Enums\MediaType;
use App\Enums\PageVisibility;
use App\Models\MediaAsset;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('media');
    $this->seed(PermissionSeeder::class);
});

function makeAsset(PageVisibility $visibility = PageVisibility::Public, MediaType $type = MediaType::Document): MediaAsset
{
    Storage::disk('media')->put('assets/2026/test.pdf', 'inhoud');

    return MediaAsset::create([
        'disk' => 'media',
        'path' => 'assets/2026/test.pdf',
        'original_name' => 'test.pdf',
        'mime_type' => 'application/pdf',
        'type' => $type,
        'file_size' => 6,
        'visibility' => $visibility,
    ]);
}

it('rejects a download without a signature', function () {
    $asset = makeAsset(PageVisibility::Members);

    $this->get(route('media.download', ['asset' => $asset->id]))->assertForbidden();
});

it('rejects a signed download for members-only when guest', function () {
    $asset = makeAsset(PageVisibility::Members);
    $signedUrl = URL::signedRoute('media.download', ['asset' => $asset->id], now()->addMinutes(60));

    $this->get($signedUrl)->assertForbidden();
});

it('serves a signed download for members-only when logged in', function () {
    $asset = makeAsset(PageVisibility::Members);
    $signedUrl = URL::signedRoute('media.download', ['asset' => $asset->id], now()->addMinutes(60));

    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'M', 'last_name' => 'Ember', 'account_id' => $user->id]);

    $this->actingAs($user)->get($signedUrl)->assertOk();
});

it('rejects a restricted download without media.view permission', function () {
    $asset = makeAsset(PageVisibility::Restricted);
    $signedUrl = URL::signedRoute('media.download', ['asset' => $asset->id], now()->addMinutes(60));

    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Eperkt', 'account_id' => $user->id]);

    $this->actingAs($user)->get($signedUrl)->assertForbidden();
});

it('serves a restricted download when the user has media.view', function () {
    $asset = makeAsset(PageVisibility::Restricted);
    $signedUrl = URL::signedRoute('media.download', ['asset' => $asset->id], now()->addMinutes(60));

    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'G', 'last_name' => 'Ea', 'account_id' => $user->id]);
    PersonPermission::create([
        'person_id' => $person->id,
        'permission_id' => Permission::where('key', 'media.view')->value('id'),
        'status' => 'active',
    ]);

    $this->actingAs($user)->get($signedUrl)->assertOk();
});
