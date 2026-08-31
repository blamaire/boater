@php($property ??= 'description')
<div wire:ignore x-data="{
    value: @entangle($property),
    syncing: false,
    setContent(html) {
        this.$refs.trixInput.value = html ?? '';
        this.$refs.editor.editor.loadHTML(html ?? '');
    },
    init() {
        this.setContent(this.value);
        this.$refs.editor.addEventListener('trix-change', () => {
            this.syncing = true;
            this.value = this.$refs.trixInput.value;
            this.$nextTick(() => { this.syncing = false; });
        });
        // Nodig zodra dezelfde editor-DOM voor meerdere records hergebruikt
        // wordt (bv. een CRUD-modal die na edit() opnieuw opent voor een
        // ánder record) — zonder deze watcher blijft de zichtbare inhoud na
        // de eerste keer laden hangen op de oude waarde. De `syncing`-vlag
        // voorkomt dat elke eigen toetsaanslag de cursor laat springen.
        this.$watch('value', (newValue) => {
            if (this.syncing) return;
            this.setContent(newValue);
        });
    },
}">
    <input type="hidden" x-ref="trixInput" id="{{ $prefix }}-{{ $property }}-trix-input">
    <trix-editor x-ref="editor" id="{{ $prefix }}-{{ $property }}-editor" input="{{ $prefix }}-{{ $property }}-trix-input"
        class="prose prose-sm max-w-none mt-1 border border-gray-300 rounded-md min-h-[8rem] bg-white p-3"></trix-editor>
</div>
