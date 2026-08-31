@php($property ??= 'description')
{{-- $wire.$watch() i.p.v. @entangle() + lokale Alpine $watch: dat laatste
     bleek onbetrouwbaar buiten de directe x-model-vorm. $wire.$watch() is
     Livewire's eigen manier om op een servergestuurde wijziging van een
     component-property te reageren, ongeacht de bron.

     setContent() gebeurt pas ná trix-initialize, niet synchroon in init():
     het <trix-editor>-custom-element initialiseert zelf asynchroon, dus
     this.$refs.editor.editor bestaat niet gegarandeerd meteen — een
     synchrone .loadHTML()-aanroep in init() kon daardoor stil een
     TypeError gooien die de rest van init() (dus ook de listener-
     registratie) afbrak, zonder zichtbare foutmelding in de UI. --}}
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
            this.setContent($wire.{{ $property }});
        });
        this.$refs.editor.addEventListener('trix-change', () => {
            this.syncing = true;
            $wire.{{ $property }} = this.$refs.trixInput.value;
            this.$nextTick(() => { this.syncing = false; });
        });
        $wire.$watch('{{ $property }}', (value) => {
            if (this.syncing) return;
            this.setContent(value);
        });
    },
}">
    <input type="hidden" x-ref="trixInput" id="{{ $prefix }}-{{ $property }}-trix-input">
    <trix-editor x-ref="editor" id="{{ $prefix }}-{{ $property }}-editor" input="{{ $prefix }}-{{ $property }}-trix-input"
        class="prose prose-sm max-w-none mt-1 border border-gray-300 rounded-md min-h-[8rem] bg-white p-3"></trix-editor>
</div>
