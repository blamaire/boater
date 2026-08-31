@php($property ??= 'description')
@php($fieldId = $prefix.'-'.str_replace('.', '-', $property))
{{-- $property is een Livewire-dot-pad (bv. 'body' of 'blocks.0.content.html' —
     meerdere Tekst-blocks in één sjabloon hebben elk hun eigen pad). Daarom
     $wire.get()/$wire.set()/$wire.$watch() i.p.v. $wire.{{ $property }} als
     kale JS-property-chain (breekt op een numerieke path-segment als
     'blocks.0...'). $wire.$watch() i.p.v. @entangle() + lokale Alpine
     $watch: dat laatste bleek onbetrouwbaar buiten de directe x-model-vorm.

     setContent() gebeurt pas ná trix-initialize, niet synchroon in init():
     het <trix-editor>-custom-element initialiseert zelf asynchroon, dus
     this.$refs.editor.editor bestaat niet gegarandeerd meteen — een
     synchrone .loadHTML()-aanroep in init() kon daardoor stil een
     TypeError gooien die de rest van init() (dus ook de listener-
     registratie) afbrak, zonder zichtbare foutmelding. --}}
<div wire:ignore x-data="{
    syncing: false,
    setContent(html) {
        this.$refs.trixInput.value = html ?? '';
        if (this.$refs.editor.editor) {
            this.$refs.editor.editor.loadHTML(html ?? '');
        }
    },
    init() {
        this.$refs.editor.addEventListener('trix-initialize', () => {
            this.setContent($wire.get('{{ $property }}'));
        });
        this.$refs.editor.addEventListener('trix-change', () => {
            this.syncing = true;
            $wire.set('{{ $property }}', this.$refs.trixInput.value);
            this.$nextTick(() => { this.syncing = false; });
        });
        $wire.$watch('{{ $property }}', (value) => {
            if (this.syncing) return;
            this.setContent(value);
        });
    },
}">
    <input type="hidden" x-ref="trixInput" id="{{ $fieldId }}-trix-input">
    <trix-editor x-ref="editor" id="{{ $fieldId }}-editor" input="{{ $fieldId }}-trix-input"
        class="prose prose-sm max-w-none mt-1 border border-gray-300 rounded-md min-h-[8rem] bg-white p-3"></trix-editor>
</div>
