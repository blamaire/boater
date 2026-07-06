<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex justify-end">
        <a href="{{ route('admin.users.index') }}" class="text-sm text-rzvg-600 hover:text-rzvg-800 whitespace-nowrap">
            ← Terug naar gebruikers
        </a>
    </div>

    <div>
        <h2 class="font-display text-2xl text-gray-900">
            Rollen en rechten voor {{ $person->first_name }}{{ $person->last_name_prefix ? ' '.$person->last_name_prefix : '' }} {{ $person->last_name }}
        </h2>
        <p class="text-sm text-gray-500 mt-1">
            Effectieve rechten = som van rollen én directe toewijzingen. Rol-rechten pas je aan bij de rol; directe rechten regel je hieronder per persoon.
        </p>
    </div>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h3 class="font-display text-xl text-gray-900">Rollen</h3>

        @if ($activeAssignments->isEmpty())
            <p class="text-sm text-gray-500 italic">Deze persoon heeft nog geen actieve rollen.</p>
        @else
            <ul class="flex flex-wrap gap-2">
                @foreach ($activeAssignments as $assignment)
                    <li wire:key="assignment-{{ $assignment->id }}"
                        class="inline-flex items-center gap-2 rounded-full bg-blue-50 border border-blue-200 px-3 py-1 text-sm text-blue-800">
                        <span class="font-medium">{{ $assignment->role->name }}</span>
                        @if ($assignment->reason)
                            <span class="text-xs text-blue-600" title="{{ $assignment->reason }}">ⓘ</span>
                        @endif
                        <button type="button" wire:click="deactivateAssignment({{ $assignment->id }})"
                            onclick="return confirm('Rol [{{ $assignment->role->name }}] deactiveren?');"
                            class="text-blue-500 hover:text-blue-800" aria-label="Deactiveren">
                            ×
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($allAssignments->isNotEmpty())
            <div class="border-t border-gray-100 pt-4">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Historie</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rol</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Toegewezen op</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Door</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reden</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Einddatum</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ingetrokken op</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($allAssignments as $assignment)
                                <tr wire:key="hist-{{ $assignment->id }}"
                                    @class(['bg-gray-50/50 text-gray-500' => $assignment->status !== 'active'])>
                                    <td class="px-3 py-2 font-medium">{{ $assignment->role->name }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ $assignment->assigned_at?->format('d-m-Y') ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($assignment->assignedBy)
                                            {{ $assignment->assignedBy->first_name }} {{ $assignment->assignedBy->last_name }}
                                        @else
                                            <span class="text-gray-400 italic">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-xs">{{ $assignment->reason ?? '—' }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ $assignment->ends_at?->format('d-m-Y') ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ $assignment->deactivated_at?->format('d-m-Y') ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($assignment->status === 'active')
                                            <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700 border border-green-200">actief</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 border border-gray-200">{{ $assignment->status }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="border-t border-gray-100 pt-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2">Rol toevoegen</h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                <div>
                    <x-input-label for="new-role" value="Rol" />
                    <select id="new-role" wire:model="newRoleId"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm text-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                        <option value="">— Kies een rol —</option>
                        @foreach ($availableRoles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('newRoleId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label for="new-role-reason" value="Reden (optioneel)" />
                    <x-text-input id="new-role-reason" wire:model="newRoleReason" class="mt-1 w-full" />
                </div>
                <div>
                    <button type="button" wire:click="assignRole"
                        class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                        Rol toewijzen
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="space-y-4">
        <h3 class="font-display text-xl text-gray-900">Rechten</h3>

        @foreach ($permissionsByModule as $module => $permissions)
            <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <h4 class="px-4 py-2 bg-gray-50 border-b border-gray-200 text-sm font-medium text-gray-700 uppercase tracking-wide">
                    {{ $module }}
                </h4>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <tbody>
                        @foreach ($permissions as $permission)
                            @php
                                $viaRoles = $rolePermissions[$permission->key] ?? [];
                                $direct = $directPermissions[$permission->key] ?? null;
                            @endphp
                            <tr>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-gray-900">{{ $permission->key }}</div>
                                    <div class="text-xs text-gray-500">{{ $permission->description }}</div>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-xs">
                                    @if (! empty($viaRoles))
                                        <div class="inline-flex flex-wrap gap-1 align-middle">
                                            @foreach ($viaRoles as $roleName)
                                                <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-blue-700 border border-blue-200">
                                                    via rol: {{ $roleName }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($direct)
                                        <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-green-700 border border-green-200 ml-1">
                                            direct
                                        </span>
                                    @endif
                                    @if (empty($viaRoles) && ! $direct)
                                        <span class="text-gray-400 italic">niet toegekend</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    @if ($direct)
                                        <button type="button" wire:click="revoke({{ $direct->id }})"
                                            class="text-red-600 hover:text-red-800 text-xs"
                                            onclick="return confirm('Directe toewijzing verwijderen? Rol-rechten blijven ongewijzigd.');">
                                            Directe toewijzing verwijderen
                                        </button>
                                    @else
                                        <button type="button" wire:click="grant({{ $permission->id }})"
                                            class="text-rzvg-600 hover:text-rzvg-800 text-xs">
                                            Direct toekennen
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach
    </section>
</div>
