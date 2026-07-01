<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-gray-900">
            Rollen van {{ $person->first_name }} {{ $person->last_name }}
        </h1>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Nieuwe rol toewijzen</h2>
            <form method="POST" action="{{ route('admin.person-roles.store', $person) }}" class="space-y-3">
                @csrf

                @if ($errors->any())
                    <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">
                        <ul class="list-disc ps-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="role_id" class="block text-sm font-medium text-gray-700">Rol</label>
                        <select id="role_id" name="role_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm">
                            <option value="">— Kies een rol —</option>
                            @foreach ($availableRoles as $role)
                                <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="ends_at" class="block text-sm font-medium text-gray-700">Einddatum (optioneel)</label>
                        <input type="date" id="ends_at" name="ends_at" value="{{ old('ends_at') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm" />
                    </div>

                    <div>
                        <label for="reason" class="block text-sm font-medium text-gray-700">Reden (optioneel)</label>
                        <input type="text" id="reason" name="reason" maxlength="500" value="{{ old('reason') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 transition">
                        Toewijzen
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <h2 class="text-sm font-semibold text-gray-900 px-4 pt-4">Toewijzingen</h2>
            <table class="min-w-full divide-y divide-gray-200 mt-3">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rol</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Toegewezen</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Einddatum</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reden</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($assignments as $assignment)
                        <tr>
                            <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $assignment->role?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm">
                                @if ($assignment->status === 'active')
                                    <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700 border border-green-200">actief</span>
                                @elseif ($assignment->status === 'deactivated')
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">gedeactiveerd</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-yellow-50 px-2 py-0.5 text-xs text-yellow-700 border border-yellow-200">{{ $assignment->status }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $assignment->assigned_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $assignment->ends_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $assignment->reason ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm text-right">
                                @if ($assignment->status === 'active')
                                    <form method="POST" action="{{ route('admin.person-roles.destroy', [$person, $assignment]) }}"
                                          onsubmit="return confirm('Roltoewijzing deactiveren?');" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800">Deactiveren</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500 text-sm">Nog geen roltoewijzingen.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
