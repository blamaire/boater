<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Berichtsjablonen (§24), georganiseerd in mappen: "Systeemberichten" bevat de vaste, code-gestuurde e-mail van
        de app (niet aan te maken of te verwijderen); onder "Mailings" beheer je vrij je eigen sjablonen. Inhoud wordt
        opgebouwd uit blokken (net als een CMS-pagina, maar zonder banden/kolommen — e-mail is van nature één kolom).
        <code>@{{variabele}}</code>-tokens worden bij verzending automatisch ingevuld — typ <code>@{{</code> in een
        veld om de beschikbare variabelen te zien.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <nav class="text-sm text-gray-500 flex items-center gap-1.5 flex-wrap">
        <button type="button" wire:click="openFolder" class="hover:text-rzvg-600 hover:underline">Berichtsjablonen</button>
        @foreach ($breadcrumbs as $crumb)
            <span>&rsaquo;</span>
            @if ($loop->last)
                <span class="text-gray-900 font-medium">{{ $crumb->name }}</span>
            @else
                <button type="button" wire:click="openFolder({{ $crumb->id }})" class="hover:text-rzvg-600 hover:underline">{{ $crumb->name }}</button>
            @endif
        @endforeach
    </nav>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach ($subFolders as $folder)
                <div class="relative group border border-gray-200 rounded-lg p-3 bg-white hover:border-rzvg-300 hover:shadow-sm" wire:key="mt-folder-{{ $folder->id }}">
                    @if ($editingFolderId === $folder->id)
                        <div class="flex items-center gap-1.5">
                            <input type="text" wire:model="editingFolderName" wire:keydown.enter="renameFolder" autofocus
                                class="flex-1 min-w-0 border-gray-300 rounded shadow-sm text-sm" />
                            <button type="button" wire:click="renameFolder" class="text-green-600 hover:text-green-800 shrink-0" title="Opslaan">
                                <x-action-icon name="check" />
                            </button>
                            <button type="button" wire:click="cancelRenameFolder" class="text-gray-400 hover:text-gray-700 shrink-0" title="Annuleren">
                                <x-action-icon name="xmark" />
                            </button>
                        </div>
                        @error('editingFolderName') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    @else
                        <button type="button" wire:click="openFolder({{ $folder->id }})" class="flex items-center gap-2 text-left w-full pr-8">
                            <span class="text-xl shrink-0">📁</span>
                            <span class="text-sm font-medium text-gray-900 truncate">{{ $folder->name }}</span>
                        </button>
                        @if ($folder->is_system)
                            <span class="absolute top-2 right-2 text-[10px] uppercase tracking-wide text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-200">systeem</span>
                        @else
                            <div class="absolute top-2 right-2 hidden group-hover:flex items-center gap-1.5">
                                <button type="button" wire:click="startRenameFolder({{ $folder->id }})" class="text-gray-400 hover:text-gray-700" title="Hernoemen">
                                    <x-action-icon name="pencil" />
                                </button>
                                <button type="button" wire:click="deleteFolder({{ $folder->id }})"
                                    onclick="return confirm('Map [{{ $folder->name }}] verwijderen?');"
                                    class="text-red-400 hover:text-red-700" title="Verwijderen">
                                    <x-action-icon name="trash" />
                                </button>
                            </div>
                        @endif
                    @endif
                </div>
            @endforeach

            @if ($currentFolder !== null)
                <div class="border border-dashed border-gray-300 rounded-lg p-3 bg-gray-50/60">
                    <div class="flex items-center gap-1.5">
                        <input type="text" wire:model="newFolderName" wire:keydown.enter="addFolder" placeholder="+ Nieuwe map..."
                            class="flex-1 min-w-0 border-gray-300 rounded shadow-sm text-sm" />
                        <button type="button" wire:click="addFolder" class="text-rzvg-600 hover:text-rzvg-800 shrink-0" title="Map toevoegen">
                            <x-action-icon name="plus" />
                        </button>
                    </div>
                    @error('newFolderName') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>

        @if ($subFolders->isEmpty() && $currentFolder === null)
            <p class="text-sm text-gray-500">Geen mappen.</p>
        @endif
    </section>

    @if ($currentFolder !== null)
        @if ($canCreateHere)
            <div class="flex justify-end">
                <button type="button" x-data="" wire:click="resetForm" x-on:click="$dispatch('open-modal', 'message-template-form')"
                    class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">
                    + Nieuw sjabloon
                </button>
            </div>
        @endif

        <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm">
                    <colgroup>
                        <col>
                        <col class="w-8">
                        <col class="w-8">
                    </colgroup>
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                            <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Bewerken</th>
                            <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Verwijderen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($templatesInFolder as $template)
                            <tr wire:key="message-template-{{ $template->id }}" @class(['bg-rzvg-50' => $editingId === $template->id])>
                                <td class="px-4 py-2 text-gray-900">{{ $template->name }}</td>
                                <x-action-cell click="edit({{ $template->id }})" icon="pencil" title="Bewerken" variant="primary" />
                                <x-action-cell
                                    :icon="$template->type->value === 'transactioneel' ? null : 'trash'"
                                    click="deleteTemplate({{ $template->id }})"
                                    confirm="Sjabloon [{{ $template->name }}] verwijderen?"
                                    title="Verwijderen" variant="danger" />
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Nog geen sjablonen in deze map.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <x-modal name="message-template-form" maxWidth="4xl">
        {{-- Variabele-inserter, gedelegeerd op de hele modal: typ {{ in eender
             welk veld (onderwerp of een blok-veld) en het menu opent op dat
             veld — geen aparte listener per (dynamisch toe-/afneembaar)
             veld nodig. Elk doelveld heeft een uniek id; insert() onderscheidt
             een Trix-editor (heeft .editor) van een gewone input.
             Let op: geen letterlijke dubbele-accolade-tekenreeksen in dit
             bestand — Blade scant de hele gecompileerde view erop, ook
             binnen JS-strings in een attribuutwaarde — dus overal
             karaktergewijs opgebouwd via '{' + '{'. --}}
        <div class="p-6 space-y-4" x-data="{
            showMenu: false,
            viaTrigger: false,
            menuFor: null,
            filter: '',
            lastKeys: '',
            openBrace: '{' + '{',
            closeBrace: '}' + '}',
            get variables() {
                return ($wire.availableVariables || [])
                    .filter(v => v.toLowerCase().includes(this.filter.toLowerCase()));
            },
            openMenu(fieldId, viaTrigger) {
                this.menuFor = fieldId;
                this.viaTrigger = viaTrigger;
                this.filter = '';
                this.showMenu = true;
            },
            checkTrigger(event) {
                if (!event.target || !event.target.id) return;
                this.lastKeys = (this.lastKeys + event.key).slice(-2);
                if (this.lastKeys === this.openBrace) {
                    this.lastKeys = '';
                    this.openMenu(event.target.id, true);
                }
            },
            insert(name) {
                const value = this.viaTrigger ? (name + this.closeBrace) : (this.openBrace + name + this.closeBrace);
                const el = this.menuFor ? document.getElementById(this.menuFor) : null;
                if (el) {
                    if (el.editor) {
                        el.editor.insertString(value);
                    } else {
                        const pos = el.selectionStart ?? el.value.length;
                        el.value = el.value.slice(0, pos) + value + el.value.slice(pos);
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        el.focus();
                        el.setSelectionRange(pos + value.length, pos + value.length);
                    }
                }
                this.showMenu = false;
            },
        }" x-on:keydown.capture="checkTrigger($event)">
            <h2 class="font-medium text-gray-900 text-lg">{{ $editingId ? 'Sjabloon bewerken' : 'Nieuw sjabloon' }}</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm sm:col-span-2">
                    <span class="text-gray-600">Titel</span>
                    <input type="text" wire:model="name" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                    @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </label>

                <div class="relative sm:col-span-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Onderwerp</span>
                        <button type="button" x-on:click="openMenu('mt-subject', false)"
                            class="text-xs text-rzvg-600 hover:text-rzvg-800 hover:underline">+ Variabele</button>
                    </div>
                    <input type="text" wire:model="subject" id="mt-subject"
                        class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                    @error('subject') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror

                    <div x-show="showMenu && menuFor === 'mt-subject'" x-on:click.outside="showMenu = false" x-cloak
                        class="absolute z-10 mt-1 w-64 bg-white border border-gray-200 rounded-md shadow-lg">
                        <input type="text" x-model="filter" placeholder="Zoeken..."
                            class="w-full border-0 border-b border-gray-200 text-sm px-3 py-2 focus:ring-0">
                        <ul class="max-h-48 overflow-y-auto py-1">
                            <template x-for="name in variables" :key="name">
                                <li>
                                    <button type="button" x-on:click="insert(name)" x-text="name"
                                        class="block w-full text-left px-3 py-1.5 text-sm font-mono text-gray-700 hover:bg-rzvg-50"></button>
                                </li>
                            </template>
                            <li x-show="variables.length === 0" class="px-3 py-1.5 text-sm text-gray-400">Geen variabelen</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Blokken --}}
            <div class="space-y-3 pt-2 border-t border-gray-100">
                <div class="flex items-center justify-between pt-3">
                    <span class="text-sm font-medium text-gray-700">Inhoud (blokken)</span>
                </div>
                @error('blocks') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror

                @foreach ($blocks as $index => $block)
                    <div class="border border-gray-200 rounded-md p-3 bg-gray-50/60" wire:key="mt-block-{{ $index }}">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-medium text-gray-500 uppercase">
                                {{ \App\Enums\MessageBlockType::from($block['type'])->label() }}
                            </span>
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="moveBlock({{ $index }}, 'up')" @disabled($index === 0)
                                    class="text-gray-400 hover:text-gray-700 disabled:opacity-30 disabled:cursor-not-allowed" title="Omhoog">&uarr;</button>
                                <button type="button" wire:click="moveBlock({{ $index }}, 'down')" @disabled($index === count($blocks) - 1)
                                    class="text-gray-400 hover:text-gray-700 disabled:opacity-30 disabled:cursor-not-allowed" title="Omlaag">&darr;</button>
                                <button type="button" wire:click="removeBlock({{ $index }})"
                                    class="text-red-400 hover:text-red-700" title="Verwijderen">&times;</button>
                            </div>
                        </div>
                        @error("blocks.{$index}") <p class="text-red-600 text-xs mb-2">{{ $message }}</p> @enderror

                        @switch ($block['type'])
                            @case ('tekst')
                                @include('livewire.admin.partials.trix-editor', ['prefix' => 'mt', 'property' => "blocks.{$index}.content.html"])
                                @break

                            @case ('kop')
                                <div class="flex gap-2">
                                    <select wire:model="blocks.{{ $index }}.content.level" class="border-gray-300 rounded shadow-sm text-sm w-24">
                                        <option value="1">H1</option>
                                        <option value="2">H2</option>
                                        <option value="3">H3</option>
                                    </select>
                                    <input type="text" wire:model="blocks.{{ $index }}.content.text" id="mt-blocks-{{ $index }}-content-text"
                                        placeholder="Koptekst" class="flex-1 border-gray-300 rounded shadow-sm text-sm" />
                                </div>
                                @break

                            @case ('knop')
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <input type="text" wire:model="blocks.{{ $index }}.content.label" id="mt-blocks-{{ $index }}-content-label"
                                        placeholder="Knoptekst" class="border-gray-300 rounded shadow-sm text-sm" />
                                    <input type="text" wire:model="blocks.{{ $index }}.content.href" id="mt-blocks-{{ $index }}-content-href"
                                        placeholder="URL (bv. @{{reset_url}})" class="border-gray-300 rounded shadow-sm text-sm font-mono" />
                                </div>
                                @break

                            @case ('afbeelding')
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <input type="text" wire:model="blocks.{{ $index }}.content.url" id="mt-blocks-{{ $index }}-content-url"
                                        placeholder="Afbeeldings-URL" class="border-gray-300 rounded shadow-sm text-sm font-mono" />
                                    <input type="text" wire:model="blocks.{{ $index }}.content.alt" id="mt-blocks-{{ $index }}-content-alt"
                                        placeholder="Alt-tekst" class="border-gray-300 rounded shadow-sm text-sm" />
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Plak een volledige, permanente URL — de mediabibliotheek geeft tijdelijke links (1 uur geldig) en is hier nog niet gekoppeld.</p>
                                @break

                            @case ('scheiding')
                                <p class="text-xs text-gray-400 italic">Geen instellingen — rendert een horizontale lijn.</p>
                                @break

                            @case ('citaat')
                                <div class="space-y-2">
                                    <textarea wire:model="blocks.{{ $index }}.content.text" id="mt-blocks-{{ $index }}-content-text" rows="2"
                                        placeholder="Citaattekst" class="w-full border-gray-300 rounded shadow-sm text-sm"></textarea>
                                    <input type="text" wire:model="blocks.{{ $index }}.content.source" id="mt-blocks-{{ $index }}-content-source"
                                        placeholder="Bron (optioneel)" class="w-full border-gray-300 rounded shadow-sm text-sm" />
                                </div>
                                @break
                        @endswitch
                    </div>
                @endforeach

                <div class="flex flex-wrap gap-2 pt-1">
                    @foreach ($blockTypes as $blockType)
                        <button type="button" wire:click="addBlock('{{ $blockType->value }}')"
                            class="px-2.5 py-1 border border-gray-300 rounded text-xs text-gray-700 hover:bg-gray-50">
                            + {{ $blockType->label() }}
                        </button>
                    @endforeach
                </div>

                {{-- Gedeeld dropdown-menu voor blok-velden — buiten de foreach zodat er
                     maar één instantie is; positionering is daardoor niet exact bij
                     het brontveld (lastig generiek te bepalen over een dynamische
                     lijst), maar wel altijd zichtbaar onderaan de blokkensectie. --}}
                <div x-show="showMenu && menuFor !== 'mt-subject' && menuFor !== null" x-on:click.outside="showMenu = false" x-cloak
                    class="w-64 bg-white border border-gray-200 rounded-md shadow-lg">
                    <input type="text" x-model="filter" placeholder="Zoeken..."
                        class="w-full border-0 border-b border-gray-200 text-sm px-3 py-2 focus:ring-0">
                    <ul class="max-h-48 overflow-y-auto py-1">
                        <template x-for="name in variables" :key="name">
                            <li>
                                <button type="button" x-on:click="insert(name)" x-text="name"
                                    class="block w-full text-left px-3 py-1.5 text-sm font-mono text-gray-700 hover:bg-rzvg-50"></button>
                            </li>
                        </template>
                        <li x-show="variables.length === 0" class="px-3 py-1.5 text-sm text-gray-400">Geen variabelen</li>
                    </ul>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" wire:click="resetForm" x-on:click="$dispatch('close')"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm">Annuleren</button>
                <button type="button" wire:click="save"
                    class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">
                    {{ $editingId ? 'Opslaan' : 'Aanmaken' }}
                </button>
            </div>
        </div>
    </x-modal>
</div>
