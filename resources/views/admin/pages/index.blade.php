<x-app-layout>
    <x-slot name="header">Pagina's</x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        @can('pages.create')
            <div class="flex justify-end">
                <a href="{{ route('admin.pages.create') }}" class="inline-flex items-center px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 transition">
                    Nieuwe pagina
                </a>
            </div>
        @endcan
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
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Titel</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pad</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Zichtbaarheid</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($pages as $page)
                        <tr>
                            <td class="px-4 py-2">
                                <span class="font-medium text-gray-900">{{ $page->title }}</span>
                                @if ($page->type->value === 'systeem')
                                    <span class="ms-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">systeem</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-500 font-mono">{{ $page->publicUrl() }}</td>
                            <td class="px-4 py-2 text-sm">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                    {{ $page->visibility->value }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm">
                                @if ($page->publishedVersion)
                                    <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700 border border-green-200">gepubliceerd (v{{ $page->publishedVersion->version_no }})</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-yellow-50 px-2 py-0.5 text-xs text-yellow-700 border border-yellow-200">concept</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-right space-x-2">
                                @can('pages.update')
                                    <a href="{{ route('admin.pages.editor', $page) }}" class="text-rzvg-600 hover:text-rzvg-800">Bewerken</a>
                                    <a href="{{ route('admin.pages.edit', $page) }}" class="text-gray-600 hover:text-gray-800">Instellingen</a>
                                @endcan
                                @can('pages.push')
                                    @if ($page->publishedVersion && $pushEnvironments->isNotEmpty())
                                        <form method="POST" action="{{ route('admin.pages.push', $page) }}"
                                            class="inline-flex items-center gap-1"
                                            onsubmit="return confirm('Deze pagina naar de gekozen omgeving pushen?');">
                                            @csrf
                                            <select name="environment_id" required
                                                class="text-xs border-gray-300 rounded py-1 pl-2 pr-6 focus:border-rzvg-600 focus:ring-rzvg-600">
                                                <option value="">Push naar…</option>
                                                @foreach ($pushEnvironments as $env)
                                                    <option value="{{ $env->id }}">{{ $env->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="text-rzvg-600 hover:text-rzvg-800">Push</button>
                                        </form>
                                    @endif
                                @endcan
                                @can('pages.delete')
                                    @if ($page->type->isDeletable())
                                        <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="inline" onsubmit="return confirm('Pagina verwijderen?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800">Verwijderen</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500 text-sm">
                                Nog geen pagina's. Maak er een via "Nieuwe pagina".
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
