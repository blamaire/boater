<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <h2 class="font-display text-2xl text-gray-900">
                Rechten voor {{ $person->first_name }}{{ $person->last_name_prefix ? ' '.$person->last_name_prefix : '' }} {{ $person->last_name }}
            </h2>
            <p class="text-sm text-gray-500 mt-1">
                Effectieve rechten = som van de rollen én directe toewijzingen. Directe toewijzingen zet je hier per recht aan of uit; rol-rechten pas je aan bij de rol.
            </p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="text-sm text-rzvg-600 hover:text-rzvg-800">
            ← Terug naar gebruikers
        </a>
    </div>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    @foreach ($permissionsByModule as $module => $permissions)
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <h3 class="px-4 py-2 bg-gray-50 border-b border-gray-200 text-sm font-medium text-gray-700 uppercase tracking-wide">
                {{ $module }}
            </h3>
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
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-blue-700 border border-blue-200">
                                        via rol: {{ implode(', ', $viaRoles) }}
                                    </span>
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
</div>
