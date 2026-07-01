<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()
            ->with('permissions')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', [
            'roles' => $roles,
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', [
            'permissionsByModule' => $this->permissionsByModule(),
            'selectedPermissionIds' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request, role: null);

        $role = DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);
            $role->permissions()->sync($data['permissions'] ?? []);

            return $role;
        });

        return redirect()
            ->route('admin.roles.edit', $role)
            ->with('status', 'Rol aangemaakt.');
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', [
            'role' => $role,
            'permissionsByModule' => $this->permissionsByModule(),
            'selectedPermissionIds' => $role->permissions->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'Systeem-rollen kunnen niet gewijzigd worden.');
        }

        $data = $this->validateData($request, role: $role);

        DB::transaction(function () use ($role, $data) {
            $role->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);
            $role->permissions()->sync($data['permissions'] ?? []);
        });

        return redirect()
            ->route('admin.roles.edit', $role)
            ->with('status', 'Rol bijgewerkt.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'Systeem-rollen kunnen niet verwijderd worden.');
        }

        $role->delete();

        return redirect()
            ->route('admin.roles.index')
            ->with('status', 'Rol verwijderd.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?Role $role): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ], [
            'name.required' => 'Een rol heeft een naam nodig.',
            'name.unique' => 'Er bestaat al een rol met deze naam.',
        ]);
    }

    /**
     * @return array<string, Collection<int, Permission>>
     */
    private function permissionsByModule(): array
    {
        return Permission::query()
            ->orderBy('module')
            ->orderBy('action')
            ->get()
            ->groupBy('module')
            ->all();
    }
}
