<div wire:ignore x-data="{
    value: @entangle('description'),
    init() {
        this.$refs.trixInput.value = this.value ?? '';
        this.$refs.editor.editor.loadHTML(this.value ?? '');
        this.$refs.editor.addEventListener('trix-change', () => {
            this.value = this.$refs.trixInput.value;
        });
    },
}">
    <input type="hidden" x-ref="trixInput" id="{{ $prefix }}-description-trix-input">
    <trix-editor x-ref="editor" input="{{ $prefix }}-description-trix-input"
        class="prose prose-sm max-w-none mt-1 border border-gray-300 rounded-md min-h-[8rem] bg-white p-3"></trix-editor>
</div>
