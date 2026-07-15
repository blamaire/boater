@switch($type->value)
    @case('tekst')
        <div
            wire:ignore
            x-data="{
                value: @entangle('editingContent.html'),
                showSource: false,
                init() {
                    this.$refs.trixInput.value = this.value ?? '';
                    this.$refs.editor.addEventListener('trix-change', () => {
                        if (! this.showSource) {
                            this.value = this.$refs.trixInput.value;
                        }
                    });
                },
                toggleSource() {
                    if (! this.showSource) {
                        // Visueel → Broncode: pak de nu-actuele HTML uit Trix.
                        this.value = this.$refs.trixInput.value;
                    } else {
                        // Broncode → Visueel: laad de aangepaste bron in Trix.
                        this.$refs.editor.editor.loadHTML(this.value ?? '');
                        this.$refs.trixInput.value = this.value ?? '';
                    }
                    this.showSource = ! this.showSource;
                }
            }"
        >
            <div class="flex items-center justify-between">
                <x-input-label value="Tekst" />
                <button type="button" @click="toggleSource()"
                    class="text-xs px-2 py-1 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
                    <span x-show="! showSource">&lt;/&gt; Broncode</span>
                    <span x-show="showSource" x-cloak>👁 Visueel</span>
                </button>
            </div>

            <input type="hidden" x-ref="trixInput" id="trix-input-tekstblok">
            <trix-editor x-ref="editor" input="trix-input-tekstblok"
                x-show="! showSource"
                class="prose max-w-none mt-1 border border-gray-300 rounded-md min-h-[50rem] bg-white p-3"></trix-editor>

            <textarea x-show="showSource" x-cloak
                x-model="value"
                class="mt-1 w-full min-h-[50rem] border border-gray-300 rounded-md p-3 font-mono text-sm bg-gray-50"
                spellcheck="false"
                placeholder="&lt;p&gt;HTML broncode…&lt;/p&gt;"></textarea>
            <p x-show="showSource" x-cloak class="text-xs text-gray-500 mt-1">
                Rechtstreekse HTML — bij het terugschakelen naar visueel wordt de bron door de editor geïnterpreteerd; niet-ondersteunde tags kunnen weggefilterd worden.
            </p>
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

    @case('hero')
        <div class="space-y-1">
            <x-input-label value="Foto (pagina-vullend)" />
            <button type="button" wire:click="$dispatch('open-media-library', { contextId: 'hero-image' })" class="mt-1 px-3 py-1.5 border border-gray-300 rounded text-sm hover:bg-gray-50">Kies uit bibliotheek</button>
            @if (! empty($editingContent['media_asset_id']))
                <p class="text-xs text-gray-500">Asset #{{ $editingContent['media_asset_id'] }} gekozen.</p>
            @else
                <p class="text-xs text-gray-500">Nog geen foto gekozen.</p>
            @endif
        </div>
        <div>
            <x-input-label for="hero-title" value="Titel" />
            <x-text-input id="hero-title" wire:model="editingContent.title" class="block mt-1 w-full" />
        </div>
        <div>
            <x-input-label for="hero-subtitle" value="Ondertitel / slogan" />
            <x-text-input id="hero-subtitle" wire:model="editingContent.subtitle" class="block mt-1 w-full" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <x-input-label for="hero-cta-label" value="CTA-knop 1 label" />
                <x-text-input id="hero-cta-label" wire:model="editingContent.cta_label" class="block mt-1 w-full" />
            </div>
            <div>
                <x-input-label for="hero-cta-href" value="CTA-knop 1 URL" />
                <x-text-input id="hero-cta-href" wire:model="editingContent.cta_href" class="block mt-1 w-full" />
            </div>
            <div>
                <x-input-label for="hero-cta2-label" value="CTA-knop 2 label (optioneel)" />
                <x-text-input id="hero-cta2-label" wire:model="editingContent.cta2_label" class="block mt-1 w-full" />
            </div>
            <div>
                <x-input-label for="hero-cta2-href" value="CTA-knop 2 URL" />
                <x-text-input id="hero-cta2-href" wire:model="editingContent.cta2_href" class="block mt-1 w-full" />
            </div>
        </div>
        @break

    @case('video')
        <div class="space-y-1">
            <x-input-label value="Video (uit mediabibliotheek)" />
            <button type="button" wire:click="$dispatch('open-media-library', { contextId: 'video-asset' })" class="mt-1 px-3 py-1.5 border border-gray-300 rounded text-sm hover:bg-gray-50">Kies uit bibliotheek</button>
            @if (! empty($editingContent['media_asset_id']))
                <p class="text-xs text-gray-500">Asset #{{ $editingContent['media_asset_id'] }} gekozen.</p>
            @else
                <p class="text-xs text-gray-500">Nog geen video gekozen.</p>
            @endif
        </div>
        @break

    @case('feature_sectie')
        <div class="space-y-1">
            <x-input-label value="Foto" />
            <button type="button" wire:click="$dispatch('open-media-library', { contextId: 'feature-image' })" class="mt-1 px-3 py-1.5 border border-gray-300 rounded text-sm hover:bg-gray-50">Kies uit bibliotheek</button>
            @if (! empty($editingContent['media_asset_id']))
                <p class="text-xs text-gray-500">Asset #{{ $editingContent['media_asset_id'] }} gekozen.</p>
            @endif
        </div>
        <div>
            <x-input-label for="feat-title" value="Titel" />
            <x-text-input id="feat-title" wire:model="editingContent.title" class="block mt-1 w-full" />
        </div>
        <div>
            <x-input-label for="feat-body" value="Tekst (HTML toegestaan)" />
            <textarea id="feat-body" wire:model="editingContent.body" rows="4" class="mt-1 block w-full border-gray-300 rounded"></textarea>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <x-input-label for="feat-cta-label" value="CTA-label" />
                <x-text-input id="feat-cta-label" wire:model="editingContent.cta_label" class="block mt-1 w-full" />
            </div>
            <div>
                <x-input-label for="feat-cta-href" value="CTA-URL" />
                <x-text-input id="feat-cta-href" wire:model="editingContent.cta_href" class="block mt-1 w-full" />
            </div>
        </div>
        <div>
            <x-input-label for="feat-side" value="Foto links of rechts" />
            <select id="feat-side" wire:model="editingContent.image_side" class="mt-1 block w-full border-gray-300 rounded">
                <option value="left">Links</option>
                <option value="right">Rechts</option>
            </select>
        </div>
        @break

    @case('agenda')
        <div>
            <x-input-label for="agenda-title" value="Titel boven de lijst (optioneel)" />
            <x-text-input id="agenda-title" wire:model="editingContent.title" class="mt-1 w-full" />
        </div>
        <div>
            <x-input-label value="Voorfilter categorieën (leeg = alle)" />
            <div class="mt-1 flex flex-wrap gap-2">
                @foreach (\App\Models\ActivityCategory::query()->orderBy('sort_order')->get() as $cat)
                    <label class="inline-flex items-center gap-1 border border-gray-200 rounded px-2 py-1 hover:bg-gray-50 text-sm">
                        <input type="checkbox" value="{{ $cat->id }}" wire:model="editingContent.category_ids"
                            class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                        <span>{{ $cat->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <div>
            <x-input-label for="agenda-period" value="Periode: hoeveel dagen vooruit (0 = onbeperkt)" />
            <x-text-input id="agenda-period" type="number" min="0" wire:model="editingContent.period_days" class="mt-1 w-full" />
        </div>
        <div>
            <x-input-label for="agenda-limit" value="Maximaal aantal items" />
            <x-text-input id="agenda-limit" type="number" min="1" wire:model="editingContent.limit" class="mt-1 w-full" />
        </div>
        <div class="flex items-center gap-2">
            <input id="agenda-hide-history" type="checkbox" wire:model="editingContent.hide_history"
                class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
            <label for="agenda-hide-history" class="text-sm text-gray-700">Historie verbergen (voorkeur; gebruiker kan omzetten)</label>
        </div>
        <div class="flex items-center gap-2">
            <input id="agenda-user-filter" type="checkbox" wire:model="editingContent.allow_user_filter"
                class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
            <label for="agenda-user-filter" class="text-sm text-gray-700">Bezoekers verder laten filteren</label>
        </div>
        @break
@endswitch
