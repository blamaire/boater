<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Authorization\EffectivePermissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(function (User $user, string $ability) {
            $person = $user->person;

            if ($person && app(EffectivePermissions::class)->has($person, $ability)) {
                return true;
            }

            return null;
        });
    }
}
