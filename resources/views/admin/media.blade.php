<x-app-layout>
    <x-slot name="header">Mediabibliotheek</x-slot>

    <div class="max-w-6xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-4">
        <p class="text-sm text-gray-500">Upload en beheer foto's, video's en documenten die je vervolgens in het CMS kunt kiezen.</p>
        <livewire:admin.media-library :standalone="true" />
    </div>
</x-app-layout>
