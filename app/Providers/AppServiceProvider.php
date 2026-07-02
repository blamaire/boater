<?php

namespace App\Providers;

use App\Models\PersonPermission;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Observers\PersonPermissionObserver;
use App\Observers\RoleAssignmentObserver;
use App\Services\Authorization\EffectivePermissions;
use App\Services\Proposals\Handlers\MembershipApplicationHandler;
use App\Services\Proposals\Handlers\PageVersionProposalHandler;
use App\Services\Proposals\ProposalHandlerRegistry;
use App\View\Composers\PublicNavComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProposalHandlerRegistry::class);
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

        RoleAssignment::observe(RoleAssignmentObserver::class);
        PersonPermission::observe(PersonPermissionObserver::class);

        app(ProposalHandlerRegistry::class)->register(
            PageVersionProposalHandler::SUBJECT_TYPE,
            app(PageVersionProposalHandler::class),
        );

        app(ProposalHandlerRegistry::class)->register(
            MembershipApplicationHandler::SUBJECT_TYPE,
            app(MembershipApplicationHandler::class),
        );

        View::composer('public._nav', PublicNavComposer::class);
    }
}
