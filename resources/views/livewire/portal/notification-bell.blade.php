<div class="relative" x-data x-on:click.outside="$wire.open = false">
    <button type="button" wire:click="toggle" class="relative inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100" aria-label="Meldingen">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        @if ($this->unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center h-4 min-w-4 px-1 rounded-full bg-rzvg-500 text-white text-[10px] font-medium">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="$wire.open" x-cloak x-transition
        class="absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-md shadow-lg z-50">
        <div class="max-h-80 overflow-y-auto divide-y divide-gray-100">
            @forelse ($this->recent as $notification)
                <div class="px-3 py-2" wire:key="bell-notification-{{ $notification->id }}">
                    @if ($notification->link)
                        <a href="{{ $notification->link }}" wire:click="markAsRead({{ $notification->id }})"
                            class="block text-sm hover:text-rzvg-700"
                            @class(['font-medium text-gray-900' => $notification->read_at === null, 'text-gray-600' => $notification->read_at !== null])>
                            {{ $notification->subject }}
                        </a>
                    @else
                        <button type="button" wire:click="markAsRead({{ $notification->id }})"
                            class="block w-full text-left text-sm hover:text-rzvg-700"
                            @class(['font-medium text-gray-900' => $notification->read_at === null, 'text-gray-600' => $notification->read_at !== null])>
                            {{ $notification->subject }}
                        </button>
                    @endif
                    <p class="text-xs text-gray-400 mt-0.5">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
            @empty
                <div class="px-3 py-4 text-center text-sm text-gray-400">Geen meldingen.</div>
            @endforelse
        </div>
        <a href="{{ route('portal.meldingen') }}" class="block px-3 py-2 text-center text-xs text-rzvg-600 hover:text-rzvg-800 border-t border-gray-100 hover:bg-gray-50">
            Alle meldingen bekijken
        </a>
    </div>
</div>
