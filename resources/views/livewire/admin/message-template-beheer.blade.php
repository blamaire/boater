<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Berichtsjablonen (§24): onderwerp en inhoud van de e-mail die de app automatisch verstuurt. De sleutel
        (<code>key</code>) ligt vast in de code en kan na aanmaken niet meer wijzigen; onderwerp en inhoud wel.
        <code>@{{variabele}}</code>-tokens in het sjabloon worden bij verzending automatisch ingevuld.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="flex justify-end">
        <button type="button" x-data="" wire:click="resetForm" x-on:click="$dispatch('open-modal', 'message-template-form')"
            class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">
            + Nieuw sjabloon
        </button>
    </div>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm">
                <colgroup>
                    <col class="w-56">
                    <col>
                    <col class="w-36">
                    <col class="w-8">
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sleutel</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($templates as $template)
                        <tr wire:key="message-template-{{ $template->id }}" @class(['bg-rzvg-50' => $editingId === $template->id])>
                            <td class="px-4 py-2 font-mono text-xs text-gray-700">{{ $template->key }}</td>
                            <td class="px-4 py-2 text-gray-900">{{ $template->name }}</td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $template->type->label() }}</td>
                            <x-action-cell click="edit({{ $template->id }})" icon="pencil" title="Bewerken" variant="primary" />
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Nog geen sjablonen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <x-modal name="message-template-form" maxWidth="4xl">
        {{-- Variabele-inserter: leest de beschikbare namen live uit $wire (dus
             altijd actueel, ook na het wisselen van sjabloon in dezelfde
             modal). "Variabele invoegen" plakt het volledige token op de
             cursorpositie; typ je zelf de dubbele-accolade-opener dan opent
             hetzelfde menu automatisch en wordt alleen de rest aangevuld.
             Voor de Trix-body wordt niet op de exacte cursorpositie
             gepositioneerd (lastig betrouwbaar te bepalen in een
             contenteditable) — het menu verschijnt vast onder het veld.
             Let op: geen letterlijke dubbele-accolade-tekenreeksen in dit
             bestand — Blade scant de hele gecompileerde view erop, ook
             binnen JS-strings in een attribuutwaarde (zie eerdere bugfix),
             dus overal karaktergewijs opgebouwd via '{' + '{'. --}}
        <div class="p-6 space-y-4" x-data="{
            showMenu: false,
            viaTrigger: false,
            menuFor: 'body',
            filter: '',
            lastKeys: '',
            openBrace: '{' + '{',
            closeBrace: '}' + '}',
            get variables() {
                return ($wire.availableVariables || [])
                    .filter(v => v.toLowerCase().includes(this.filter.toLowerCase()));
            },
            openMenu(target, viaTrigger) {
                this.menuFor = target;
                this.viaTrigger = viaTrigger;
                this.filter = '';
                this.showMenu = true;
            },
            checkTrigger(event, target) {
                this.lastKeys = (this.lastKeys + event.key).slice(-2);
                if (this.lastKeys === this.openBrace) {
                    this.lastKeys = '';
                    this.openMenu(target, true);
                }
            },
            insert(name) {
                const value = this.viaTrigger ? (name + this.closeBrace) : (this.openBrace + name + this.closeBrace);
                if (this.menuFor === 'subject') {
                    const el = document.getElementById('mt-subject');
                    if (el) {
                        const pos = el.selectionStart ?? el.value.length;
                        el.value = el.value.slice(0, pos) + value + el.value.slice(pos);
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        el.focus();
                        el.setSelectionRange(pos + value.length, pos + value.length);
                    }
                } else {
                    const editorEl = document.getElementById('mt-body-editor');
                    if (editorEl && editorEl.editor) {
                        editorEl.editor.insertString(value);
                    }
                }
                this.showMenu = false;
            },
            init() {
                const editorEl = document.getElementById('mt-body-editor');
                if (editorEl) {
                    editorEl.addEventListener('keydown', (e) => this.checkTrigger(e, 'body'));
                }
            },
        }">
            <h2 class="font-medium text-gray-900 text-lg">{{ $editingId ? 'Sjabloon bewerken' : 'Nieuw sjabloon' }}</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-gray-600">Sleutel</span>
                    <input type="text" wire:model="key" @disabled($editingId) placeholder="bv. enrollment_confirmed"
                        class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm font-mono disabled:bg-gray-100 disabled:text-gray-500" />
                    @error('key') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-gray-600">Type</span>
                    <select wire:model="type" @disabled($editingId) class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm disabled:bg-gray-100">
                        <option value="transactioneel">Transactioneel</option>
                        <option value="redactioneel">Redactioneel</option>
                    </select>
                    @error('type') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm sm:col-span-2">
                    <span class="text-gray-600">Naam <span class="text-gray-400">(intern, voor in dit overzicht)</span></span>
                    <input type="text" wire:model="name" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                    @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </label>

                <div class="relative">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Onderwerp</span>
                        <button type="button" x-on:click="openMenu('subject', false)"
                            class="text-xs text-rzvg-600 hover:text-rzvg-800 hover:underline">+ Variabele</button>
                    </div>
                    <input type="text" wire:model="subject" id="mt-subject"
                        x-on:keyup="checkTrigger($event, 'subject')" x-on:focus="menuFor = 'subject'"
                        class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                    @error('subject') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror

                    <div x-show="showMenu && menuFor === 'subject'" x-on:click.outside="showMenu = false" x-cloak
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

                <div class="sm:col-span-2 relative">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Inhoud</span>
                        <button type="button" x-on:click="openMenu('body', false)"
                            class="text-xs text-rzvg-600 hover:text-rzvg-800 hover:underline">+ Variabele</button>
                    </div>
                    @include('livewire.admin.partials.trix-editor', ['prefix' => 'mt', 'property' => 'body'])
                    @error('body') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror

                    <div x-show="showMenu && menuFor === 'body'" x-on:click.outside="showMenu = false" x-cloak
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
