<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Models\Permission;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\RoleAssignment;
use App\Services\Authorization\EffectivePermissions;
use App\Services\Proposals\ReviewerResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __construct(
        private readonly EffectivePermissions $permissions,
        private readonly ReviewerResolver $reviewerResolver,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $person = $user?->person;

        $roles = $person ? $this->activeRolesFor($person) : collect();
        $permissionKeys = $person ? $this->permissions->for($person) : collect();
        $permissionsByModule = $this->resolvePermissionsByModule($permissionKeys);

        $shortcuts = $person ? $this->buildShortcuts($person, $permissionKeys) : collect();

        return view('dashboard', [
            'person' => $person,
            'roles' => $roles,
            'permissionsByModule' => $permissionsByModule,
            'shortcuts' => $shortcuts,
        ]);
    }

    private function activeRolesFor(Person $person): Collection
    {
        $now = Carbon::now();

        return $person->roleAssignments()
            ->with('role')
            ->where('status', 'active')
            ->whereNull('deactivated_at')
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            })
            ->get()
            ->map(fn (RoleAssignment $assignment) => [
                'name' => $assignment->role->name,
                'ends_at' => $assignment->ends_at,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, string>  $keys
     */
    private function resolvePermissionsByModule(Collection $keys): Collection
    {
        if ($keys->isEmpty()) {
            return collect();
        }

        $permissions = Permission::query()
            ->whereIn('key', $keys->all())
            ->orderBy('module')
            ->orderBy('action')
            ->get();

        $grouped = [];
        foreach ($permissions as $permission) {
            $grouped[$permission->module][] = $permission;
        }

        return collect($grouped);
    }

    /**
     * @param  Collection<int, string>  $permissionKeys
     */
    private function buildShortcuts(Person $person, Collection $permissionKeys): Collection
    {
        $shortcuts = collect();

        $toDecide = $this->reviewerResolver->decidableStepsQuery($person)->count();
        if ($toDecide > 0) {
            $shortcuts->push([
                'label' => 'Te beslissen',
                'count' => $toDecide,
                'href' => route('portal.wijzigingsvoorstellen'),
                'description' => 'Voorstellen waarop jij als beslisser bent toegewezen.',
            ]);
        }

        $myOpen = Proposal::query()
            ->where('proposed_by_person_id', $person->id)
            ->whereIn('status', [
                ProposalStatus::Submitted,
                ProposalStatus::InReview,
                ProposalStatus::Returned,
            ])
            ->count();
        if ($myOpen > 0) {
            $shortcuts->push([
                'label' => 'Mijn open voorstellen',
                'count' => $myOpen,
                'href' => route('portal.wijzigingsvoorstellen'),
                'description' => 'Voorstellen die je hebt ingediend en nog in behandeling zijn.',
            ]);
        }

        if ($permissionKeys->contains('audit_trail.view')) {
            $shortcuts->push([
                'label' => 'Audit trail',
                'count' => null,
                'href' => '#',
                'description' => 'Volledig logboek van alle wijzigingen.',
            ]);
        }

        if ($permissionKeys->contains('roles.update')) {
            $shortcuts->push([
                'label' => 'Rollen beheren',
                'count' => null,
                'href' => '#',
                'description' => 'Wijs rollen toe of trek ze in.',
            ]);
        }

        return $shortcuts;
    }
}
