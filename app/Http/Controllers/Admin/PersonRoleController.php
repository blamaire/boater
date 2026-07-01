<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\Role;
use App\Models\RoleAssignment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class PersonRoleController extends Controller
{
    public function index(Person $person): View
    {
        $assignments = RoleAssignment::query()
            ->with(['role', 'assignedBy'])
            ->where('person_id', $person->id)
            ->orderByDesc('assigned_at')
            ->get();

        $availableRoles = Role::query()
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return view('admin.persons.roles', [
            'person' => $person,
            'assignments' => $assignments,
            'availableRoles' => $availableRoles,
        ]);
    }

    public function store(Request $request, Person $person): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'ends_at' => ['nullable', 'date', 'after:today'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'role_id.required' => 'Kies een rol om toe te wijzen.',
            'ends_at.after' => 'De einddatum moet in de toekomst liggen.',
        ]);

        $assignerPersonId = $request->user()?->person?->id;

        RoleAssignment::create([
            'person_id' => $person->id,
            'role_id' => $data['role_id'],
            'status' => 'active',
            'assigned_by' => $assignerPersonId,
            'assigned_at' => Carbon::now(),
            'ends_at' => $data['ends_at'] ?? null,
            'reason' => $data['reason'] ?? null,
        ]);

        return redirect()
            ->route('admin.person-roles.index', $person)
            ->with('status', 'Rol toegewezen.');
    }

    public function destroy(Person $person, RoleAssignment $assignment): RedirectResponse
    {
        if ($assignment->person_id !== $person->id) {
            abort(404);
        }

        $assignment->update([
            'status' => 'deactivated',
            'deactivated_at' => Carbon::now(),
        ]);

        return redirect()
            ->route('admin.person-roles.index', $person)
            ->with('status', 'Roltoewijzing gedeactiveerd.');
    }
}
