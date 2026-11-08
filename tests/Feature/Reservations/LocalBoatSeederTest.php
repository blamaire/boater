<?php

use App\Enums\ReservableObjectStatus;
use App\Models\ObjectCategory;
use App\Models\ReservableObject;
use Database\Seeders\LocalBoatSeeder;

it('zaait geen testboten buiten APP_ENV=local', function () {
    $this->seed(LocalBoatSeeder::class);

    expect(ReservableObject::count())->toBe(0);
});

it('zaait lokaal roei- en zeilboten in een categorieboom, eenmalig', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->seed(LocalBoatSeeder::class);
    $this->seed(LocalBoatSeeder::class);

    $roeien = ObjectCategory::where('name', 'Roeiboten')->firstOrFail();
    $zeilen = ObjectCategory::where('name', 'Zeilboten')->firstOrFail();

    expect(ReservableObject::count())->toBe(19)
        ->and($roeien->requires_boat_right)->toBeTrue()
        ->and(ObjectCategory::where('parent_id', $roeien->id)->count())->toBe(4)
        ->and(ObjectCategory::where('parent_id', $zeilen->id)->count())->toBe(5)
        ->and(ReservableObject::where('name', 'Skiff 3')->value('status'))->toBe(ReservableObjectStatus::OutOfService)
        ->and(ReservableObject::where('name', 'Optimist 4')->value('attributes'))->toBe(['plaatsen' => 1]);
});
