<div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">Meldingen over goedkeuringen, vrijgekomen wachtlijstplekken en dergelijke.</p>
        @if ($notifications->contains(fn ($n) => $n->read_at === null))
            <button type="button" wire:click="markAllAsRead" class="text-sm text-rzvg-600 hover:text-rzvg-800 hover:underline whitespace-nowrap">
                Alles markeren als gelezen
            </button>
        @endif
    </div>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 divide-y divide-gray-100">
        @forelse ($notifications as $notification)
            <div class="flex items-start gap-3 px-4 py-3" wire:key="notification-{{ $notification->id }}">
                <span class="mt-1.5 h-2 w-2 rounded-full shrink-0 {{ $notification->read_at === null ? 'bg-rzvg-500' : 'bg-transparent' }}"></span>
                <div class="flex-1 min-w-0">
                    @if ($notification->link)
                        <a href="{{ $notification->link }}" wire:click="markAsRead({{ $notification->id }})"
                            class="text-sm text-gray-900 hover:text-rzvg-700 hover:underline">{{ $notification->subject }}</a>
                    @else
                        <span class="text-sm text-gray-900">{{ $notification->subject }}</span>
                    @endif
                    @if ($notification->body)
                        <p class="text-xs text-gray-500 mt-0.5">{{ $notification->body }}</p>
                    @endif
                    <p class="text-xs text-gray-400 mt-0.5">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
                @if ($notification->read_at === null)
                    <button type="button" wire:click="markAsRead({{ $notification->id }})"
                        class="text-xs text-gray-400 hover:text-gray-700 whitespace-nowrap">Markeer gelezen</button>
                @endif
            </div>
        @empty
            <div class="px-4 py-6 text-center text-gray-500 text-sm">Nog geen meldingen.</div>
        @endforelse
    </section>

    {{ $notifications->links() }}
</div>
