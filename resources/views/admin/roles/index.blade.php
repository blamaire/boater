<x-app-layout>
    <x-slot name="header">
        <div class="flex items-baseline justify-between">
            <h1 class="font-display text-2xl text-gray-900">Rollen</h1>
            @can('roles.create')
                <a href="{{ route('admin.roles.create') }}" class="inline-flex items-center px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 transition">
                    Nieuwe rol
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Omschrijving</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Permissies</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($roles as $role)
                        <tr>
                            <td class="px-4 py-2">
                                <span class="font-medium text-gray-900">{{ $role->name }}</span>
                                @if ($role->is_system)
                                    <span class="ms-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">systeem</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $role->description }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $role->permissions->count() }}</td>
                            <td class="px-4 py-2 text-sm text-right space-x-2">
                                @can('roles.update')
                                    @if ($role->is_system)
                                        <span class="text-gray-400" title="Systeem-rol — niet wijzigbaar">Bekijken</span>
                                    @else
                                        <a href="{{ route('admin.roles.edit', $role) }}" class="text-rzvg-600 hover:text-rzvg-800">Bewerken</a>
                                    @endif
                                @endcan
                                @can('roles.delete')
                                    @unless ($role->is_system)
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="inline" onsubmit="return confirm('Rol verwijderen?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800">Verwijderen</button>
                                        </form>
                                    @endunless
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500 text-sm">
                                Nog geen rollen. Maak er een via "Nieuwe rol".
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
