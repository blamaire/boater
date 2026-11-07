@props(['id' => 'zoek', 'value' => '', 'class' => ''])

{{-- Zoekveld met zoektermsuggesties. Zonder JavaScript werkt het als gewoon
     GET-formulier naar de resultatenpagina. --}}
<form method="GET" action="{{ route('zoeken') }}" role="search" {{ $attributes->merge(['class' => 'relative '.$class]) }}
    x-data="{
        query: @js((string) $value),
        suggestions: [],
        open: false,
        active: -1,
        timer: null,
        controller: null,
        onInput() {
            clearTimeout(this.timer);
            this.active = -1;
            if (this.query.trim().length < 2) {
                this.suggestions = [];
                this.open = false;
                return;
            }
            this.timer = setTimeout(() => this.fetchSuggestions(), 200);
        },
        async fetchSuggestions() {
            if (this.controller) this.controller.abort();
            this.controller = new AbortController();
            try {
                const response = await fetch('{{ route('zoeken.suggesties') }}?q=' + encodeURIComponent(this.query.trim()), {
                    headers: { 'Accept': 'application/json' },
                    signal: this.controller.signal,
                });
                if (! response.ok) return;
                const data = await response.json();
                this.suggestions = data.suggestions || [];
                this.open = this.suggestions.length > 0;
            } catch (e) {
                if (e.name !== 'AbortError') this.open = false;
            }
        },
        move(step) {
            if (! this.open) return;
            const count = this.suggestions.length;
            this.active = this.active === -1 ? (step > 0 ? 0 : count - 1) : (this.active + step + count) % count;
        },
        choose(term) {
            window.location.href = '{{ route('zoeken') }}?q=' + encodeURIComponent(term);
        },
        onEnter(event) {
            if (this.open && this.active >= 0) {
                event.preventDefault();
                this.choose(this.suggestions[this.active]);
            }
        },
    }"
    @click.outside="open = false">
    <label for="{{ $id }}-q" class="sr-only">Zoeken</label>
    <div class="flex items-stretch">
        <input id="{{ $id }}-q" type="search" name="q" x-model="query" value="{{ $value }}"
            placeholder="Zoeken…" autocomplete="off"
            role="combobox" aria-autocomplete="list" aria-controls="{{ $id }}-lijst"
            :aria-expanded="open ? 'true' : 'false'"
            :aria-activedescendant="active >= 0 ? '{{ $id }}-optie-' + active : null"
            @input="onInput()"
            @keydown.arrow-down.prevent="move(1)"
            @keydown.arrow-up.prevent="move(-1)"
            @keydown.enter="onEnter($event)"
            @keydown.escape="open = false"
            class="min-w-0 w-full rounded-l-md border border-sand-200 border-r-0 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-rzvg-500 focus:ring-rzvg-500">
        <button type="submit" aria-label="Zoeken"
            class="inline-flex items-center justify-center rounded-r-md bg-rzvg-500 px-3 text-white hover:bg-rzvg-600">
            <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
            </svg>
        </button>
    </div>
    <ul id="{{ $id }}-lijst" role="listbox" aria-label="Zoeksuggesties" x-show="open" x-cloak
        class="absolute left-0 right-0 top-full z-50 mt-1 min-w-[12rem] overflow-hidden rounded-md border border-sand-200 bg-white text-sm shadow-lg">
        <template x-for="(term, index) in suggestions" :key="term">
            <li role="option" :id="'{{ $id }}-optie-' + index" :aria-selected="active === index ? 'true' : 'false'"
                @click="choose(term)" @mouseenter="active = index"
                :class="active === index ? 'bg-sand-100' : ''"
                class="cursor-pointer px-3 py-2 text-gray-900" x-text="term"></li>
        </template>
    </ul>
</form>
