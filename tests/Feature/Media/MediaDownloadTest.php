<?php

use App\Enums\MediaType;
use App\Enums\MembershipStatus;
use App\Enums\PageVisibility;
use App\Models\MediaAsset;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\User;
use Database\Seeders\MembershipTypeSeeder;
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
    $asset = makeAsset(PageVisibility::Restricted);

    $this->get(route('media.download', ['asset' => $asset->id]))->assertForbidden();
});

it('rejects a signed download of a restricted asset for guests', function () {
    $asset = makeAsset(PageVisibility::Restricted);
    $signedUrl = URL::signedRoute('media.download', ['asset' => $asset->id], now()->addMinutes(60));

    $this->get($signedUrl)->assertForbidden();
});

it('rejects a signed download of a restricted asset for a former member without active membership', function () {
    $this->seed(MembershipTypeSeeder::class);

    $asset = makeAsset(PageVisibility::Restricted);
    $signedUrl = URL::signedRoute('media.download', ['asset' => $asset->id], now()->addMinutes(60));

    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'Oud', 'last_name' => 'Lid', 'account_id' => $user->id]);
    $type = MembershipType::query()->where('key', 'a')->firstOrFail();
    Membership::create([
        'person_id' => $person->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => now()->subYears(2)->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
        'billing_person_id' => $person->id,
    ]);

    $this->actingAs($user)->get($signedUrl)->assertForbidden();
});

it('serves a signed download of a restricted asset for an active member', function () {
    $this->seed(MembershipTypeSeeder::class);

    $asset = makeAsset(PageVisibility::Restricted);
    $signedUrl = URL::signedRoute('media.download', ['asset' => $asset->id], now()->addMinutes(60));

    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'Actief', 'last_name' => 'Lid', 'account_id' => $user->id]);
    $type = MembershipType::query()->where('key', 'a')->firstOrFail();
    Membership::create([
        'person_id' => $person->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => now()->subMonth()->toDateString(),
        'billing_person_id' => $person->id,
    ]);

    $this->actingAs($user)->get($signedUrl)->assertOk();
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
