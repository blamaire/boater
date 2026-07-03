<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-8">
    <header>
        <h1 class="font-display text-3xl text-rzvg-600">Menu-beheer</h1>
        <p class="text-sm text-gray-500 mt-1">
            Stel het hoofdmenu van de publieke site samen. Zolang deze lijst leeg is toont de site automatisch alle publieke CMS-pagina's.
        </p>
    </header>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="font-display text-xl text-gray-900">Hoofdmenu</h2>

        @if ($items->isEmpty())
            <p class="text-sm text-gray-500 italic">Nog geen handmatige items. De publieke site toont nu automatisch de root-CMS-pagina's als menu.</p>
        @else
            <ul class="divide-y divide-gray-100 border border-gray-100 rounded">
                @foreach ($items as $item)
                    <li wire:key="item-{{ $item->id }}" class="px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="text-sm flex-1">
                                <div class="font-medium text-gray-900">{{ $item->displayLabel() }}</div>
                                <div class="text-gray-500 text-xs">{{ $item->url() ?? '(geen link)' }}</div>
                                @if (! $item->visible)
                                    <div class="text-xs text-amber-700 mt-0.5">Verborgen</div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                <button type="button" wire:click="moveUp({{ $item->id }})" title="Omhoog"
                                    class="px-2 py-1 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">↑</button>
                                <button type="button" wire:click="moveDown({{ $item->id }})" title="Omlaag"
                                    class="px-2 py-1 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">↓</button>
                                <button type="button" wire:click="toggleVisible({{ $item->id }})"
                                    class="px-2 py-1 rounded border
                                        @if ($item->visible) border-green-300 text-green-800 bg-green-50
                                        @else border-gray-300 text-gray-700 bg-gray-50 @endif">
                                    {{ $item->visible ? 'Zichtbaar' : 'Verborgen' }}
                                </button>
                                <button type="button" wire:click="delete({{ $item->id }})"
                                    onclick="return confirm('Menu-item verwijderen?')"
                                    class="px-2 py-1 rounded border border-red-300 text-red-700 hover:bg-red-50">
                                    Verwijderen
                                </button>
                            </div>
                        </div>

                        @if ($item->children->isNotEmpty())
                            <ul class="mt-3 ml-6 space-y-2">
                                @foreach ($item->children as $child)
                                    <li wire:key="child-{{ $child->id }}" class="flex items-start justify-between gap-3 border-l-2 border-gray-200 pl-3">
                                        <div class="text-sm flex-1">
                                            <div class="text-gray-900">{{ $child->displayLabel() }}</div>
                                            <div class="text-gray-500 text-xs">{{ $child->url() ?? '(geen link)' }}</div>
                                            @if (! $child->visible)
                                                <div class="text-xs text-amber-700 mt-0.5">Verborgen</div>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 text-xs">
                                            <button type="button" wire:click="moveUp({{ $child->id }})" class="px-2 py-1 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">↑</button>
                                            <button type="button" wire:click="moveDown({{ $child->id }})" class="px-2 py-1 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">↓</button>
                                            <button type="button" wire:click="toggleVisible({{ $child->id }})"
                                                class="px-2 py-1 rounded border
                                                    @if ($child->visible) border-green-300 text-green-800 bg-green-50
                                                    @else border-gray-300 text-gray-700 bg-gray-50 @endif">
                                                {{ $child->visible ? 'Zichtbaar' : 'Verborgen' }}
                                            </button>
                                            <button type="button" wire:click="delete({{ $child->id }})"
                                                onclick="return confirm('Sub-item verwijderen?')"
                                                class="px-2 py-1 rounded border border-red-300 text-red-700 hover:bg-red-50">
                                                Verwijderen
                                            </button>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="font-display text-xl text-gray-900">Menu-item toevoegen</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <x-input-label for="new-page" value="Verwijs naar CMS-pagina" />
                <select id="new-page" wire:model="newPageId"
                    class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                    <option value="">— Geen (gebruik URL) —</option>
                    @foreach ($pages as $page)
                        <option value="{{ $page->id }}">{{ $page->title }} <span class="text-gray-400">/{{ $page->slug }}</span></option>
                    @endforeach
                </select>
                @error('newPageId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="new-href" value="…of vrije URL" />
                <x-text-input id="new-href" wire:model="newHref" placeholder="https://…" class="mt-1 w-full" />
                @error('newHref') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="new-label" value="Label (optioneel; overschrijft paginatitel)" />
                <x-text-input id="new-label" wire:model="newLabel" class="mt-1 w-full" />
                @error('newLabel') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="new-parent" value="Sub-item van (optioneel)" />
                <select id="new-parent" wire:model="newParentId"
                    class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                    <option value="">— Hoofdmenu —</option>
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}">{{ $item->displayLabel() }}</option>
                    @endforeach
                </select>
                @error('newParentId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button type="button" wire:click="add"
                class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                Toevoegen
            </button>
        </div>
    </section>
</div>
