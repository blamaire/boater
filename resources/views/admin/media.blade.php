<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <header class="mb-4">
            <h1 class="font-display text-3xl text-rzvg-600">Mediabibliotheek</h1>
            <p class="text-sm text-gray-500 mt-1">Upload en beheer foto's, video's en documenten die je vervolgens in het CMS kunt kiezen.</p>
        </header>
        <livewire:admin.media-library :standalone="true" />
    </div>
</x-app-layout>
