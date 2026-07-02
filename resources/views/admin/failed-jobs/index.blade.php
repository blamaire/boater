<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-gray-900">Failed jobs</h1>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Queue</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Gefaald op</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Payload</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Foutmelding</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($jobs as $job)
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-700 font-mono">{{ $job['connection'] }} / {{ $job['queue'] }}</td>
                            <td class="px-4 py-2 text-sm text-gray-500 whitespace-nowrap">{{ $job['failed_at'] }}</td>
                            <td class="px-4 py-2 text-xs text-gray-600 font-mono break-all max-w-sm">{{ $job['payload_preview'] }}</td>
                            <td class="px-4 py-2 text-xs text-gray-600 font-mono break-all max-w-md">{{ $job['exception_preview'] }}</td>
                            <td class="px-4 py-2 text-sm text-right space-x-2 whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.failed-jobs.retry', ['uuid' => $job['uuid']]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-rzvg-600 hover:text-rzvg-800">Opnieuw</button>
                                </form>
                                <form method="POST" action="{{ route('admin.failed-jobs.destroy', ['uuid' => $job['uuid']]) }}" class="inline" onsubmit="return confirm('Job verwijderen?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">Verwijderen</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500 text-sm">
                                Geen failed jobs — alle achtergrondtaken lopen zonder fouten door.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
