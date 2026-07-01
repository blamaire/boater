@switch($type->value)
    @case('tekst')
        <div
            wire:ignore
            x-data="{
                value: @entangle('editingContent.html'),
                init() {
                    this.$refs.trixInput.value = this.value ?? '';
                    this.$refs.editor.addEventListener('trix-change', () => {
                        this.value = this.$refs.trixInput.value;
                    });
                }
            }"
        >
            <x-input-label value="Tekst" />
            <input type="hidden" x-ref="trixInput" id="trix-input-tekstblok">
            <trix-editor x-ref="editor" input="trix-input-tekstblok" class="mt-1 border border-gray-300 rounded-md min-h-[10rem] bg-white"></trix-editor>
        </div>
        @break

    @case('kop')
        <div>
            <x-input-label for="block-text" value="Tekst" />
            <x-text-input id="block-text" wire:model="editingContent.text" class="block mt-1 w-full" />
        </div>
        <div>
            <x-input-label for="block-level" value="Niveau" />
            <select id="block-level" wire:model="editingContent.level" class="mt-1 block w-full border-gray-300 rounded-md">
                <option value="1">H1 (hoofdtitel)</option>
                <option value="2">H2 (sectie)</option>
                <option value="3">H3 (subsectie)</option>
            </select>
        </div>
        @break

    @case('afbeelding')
        <div class="space-y-1">
            <x-input-label value="Afbeelding" />
            <div class="flex items-center gap-2">
                <x-text-input wire:model="editingContent.url" type="url" placeholder="URL of kies uit bibliotheek" class="block w-full" />
                <button type="button" wire:click="$dispatch('open-media-library', { contextId: 'image' })" class="px-2 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50 whitespace-nowrap">Uit bibliotheek</button>
            </div>
            @if (! empty($editingContent['media_asset_id']))
                <p class="text-xs text-gray-500">Gekozen uit bibliotheek (asset #{{ $editingContent['media_asset_id'] }})</p>
            @endif
        </div>
        <div>
            <x-input-label for="block-alt" value="Alt-tekst (toegankelijkheid)" />
            <x-text-input id="block-alt" wire:model="editingContent.alt" class="block mt-1 w-full" />
        </div>
        <div>
            <x-input-label for="block-caption" value="Onderschrift (optioneel)" />
            <x-text-input id="block-caption" wire:model="editingContent.caption" class="block mt-1 w-full" />
        </div>
        @break

    @case('knop')
        <div>
            <x-input-label for="block-label" value="Label" />
            <x-text-input id="block-label" wire:model="editingContent.label" class="block mt-1 w-full" />
        </div>
        <div>
            <x-input-label for="block-href" value="Doel-URL" />
            <x-text-input id="block-href" wire:model="editingContent.href" class="block mt-1 w-full" />
        </div>
        <div>
            <x-input-label for="block-style" value="Stijl" />
            <select id="block-style" wire:model="editingContent.style" class="mt-1 block w-full border-gray-300 rounded-md">
                <option value="primary">Primair (rood)</option>
                <option value="secondary">Secundair (omkaderd)</option>
            </select>
        </div>
        @break

    @case('kaart')
        <div>
            <x-input-label for="block-title" value="Titel" />
            <x-text-input id="block-title" wire:model="editingContent.title" class="block mt-1 w-full" />
        </div>
        <div>
            <x-input-label for="block-body" value="Tekst" />
            <textarea id="block-body" wire:model="editingContent.body" rows="3" class="block mt-1 w-full border-gray-300 rounded-md"></textarea>
        </div>
        <div class="space-y-1">
            <x-input-label value="Afbeelding (optioneel)" />
            <div class="flex items-center gap-2">
                <x-text-input wire:model="editingContent.image_url" type="url" placeholder="URL of kies uit bibliotheek" class="block w-full" />
                <button type="button" wire:click="$dispatch('open-media-library', { contextId: 'card-image' })" class="px-2 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50 whitespace-nowrap">Uit bibliotheek</button>
            </div>
        </div>
        <div>
            <x-input-label for="block-href" value="Link-URL (optioneel)" />
            <x-text-input id="block-href" wire:model="editingContent.href" class="block mt-1 w-full" />
        </div>
        @break

    @case('icoon_tekst')
        <div>
            <x-input-label for="block-icon" value="Icoon (emoji of teken)" />
            <x-text-input id="block-icon" wire:model="editingContent.icon" class="block mt-1 w-full font-mono" />
        </div>
        <div>
            <x-input-label for="block-title" value="Titel" />
            <x-text-input id="block-title" wire:model="editingContent.title" class="block mt-1 w-full" />
        </div>
        <div>
            <x-input-label for="block-body" value="Tekst" />
            <textarea id="block-body" wire:model="editingContent.body" rows="2" class="block mt-1 w-full border-gray-300 rounded-md"></textarea>
        </div>
        @break

    @case('gallerij')
        <div>
            <x-input-label value="Afbeeldingen (één URL per regel, optioneel || alt)" />
            <textarea wire:model="editingContent.images_raw" rows="5" placeholder="https://… || Alt-tekst&#10;https://…"
                class="block mt-1 w-full border-gray-300 rounded-md font-mono text-sm"></textarea>
            <p class="text-xs text-gray-500 mt-1">Eén URL per regel. Volledige mediabibliotheek-integratie voor gallerij volgt later.</p>
        </div>
        @break

    @case('accordeon')
        <div>
            <x-input-label value="Items (Vraag || Antwoord per regel)" />
            <textarea wire:model="editingContent.items_raw" rows="6" placeholder="Wat is X? || Het antwoord op X.&#10;Hoe doe ik Y? || Zo doe je Y."
                class="block mt-1 w-full border-gray-300 rounded-md font-mono text-sm"></textarea>
            <p class="text-xs text-gray-500 mt-1">Vraag en antwoord scheiden met <code>||</code>. Op de pagina wordt dit een uitklapbare FAQ-lijst.</p>
        </div>
        @break

    @case('citaat')
        <div>
            <x-input-label for="block-text" value="Citaat" />
            <textarea id="block-text" wire:model="editingContent.text" rows="3" class="block mt-1 w-full border-gray-300 rounded-md"></textarea>
        </div>
        <div>
            <x-input-label for="block-source" value="Bron (optioneel)" />
            <x-text-input id="block-source" wire:model="editingContent.source" class="block mt-1 w-full" />
        </div>
        @break

    @case('video_embed')
        <div>
            <x-input-label for="block-provider" value="Provider" />
            <select id="block-provider" wire:model="editingContent.provider" class="mt-1 block w-full border-gray-300 rounded-md">
                <option value="youtube">YouTube</option>
                <option value="vimeo">Vimeo</option>
                <option value="other">Anders (volledige embed-URL)</option>
            </select>
        </div>
        <div>
            <x-input-label for="block-embed" value="Embed-URL" />
            <x-text-input id="block-embed" wire:model="editingContent.embed_url" type="url" class="block mt-1 w-full" />
            <p class="text-xs text-gray-500 mt-1">Voor YouTube: https://www.youtube.com/embed/&lt;id&gt;</p>
        </div>
        @break

    @case('bestand')
        <div class="space-y-1">
            <x-input-label value="Bestand" />
            <div class="flex items-center gap-2">
                <x-text-input wire:model="editingContent.url" type="url" placeholder="URL of kies uit bibliotheek" class="block w-full" />
                <button type="button" wire:click="$dispatch('open-media-library', { contextId: 'file' })" class="px-2 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50 whitespace-nowrap">Uit bibliotheek</button>
            </div>
        </div>
        <div>
            <x-input-label for="block-label" value="Label" />
            <x-text-input id="block-label" wire:model="editingContent.label" class="block mt-1 w-full" />
        </div>
        <div>
            <x-input-label for="block-size" value="Grootte (optioneel, bv. 1.2 MB)" />
            <x-text-input id="block-size" wire:model="editingContent.size" class="block mt-1 w-full" />
        </div>
        @break

    @case('scheiding')
        <p class="text-sm text-gray-500">Dit blok heeft geen instellingen — het toont een horizontale lijn.</p>
        @break
@endswitch
