@props([
    'page' => null,
    'templates',
    'parents',
    'visibilities',
    'types',
    'action',
    'method' => 'POST',
])

<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <x-input-label for="title" value="Titel" />
        <x-text-input id="title" name="title" type="text" class="block mt-1 w-full" required value="{{ old('title', $page?->title) }}" />
        <x-input-error :messages="$errors->get('title')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="slug" value="Slug" />
        <x-text-input id="slug" name="slug" type="text" class="block mt-1 w-full font-mono" required value="{{ old('slug', $page?->slug) }}" />
        <p class="text-xs text-gray-500 mt-1">Alleen kleine letters, cijfers en streepjes. Verschijnt in de URL.</p>
        <x-input-error :messages="$errors->get('slug')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="parent_id" value="Bovenliggende pagina" />
        <select id="parent_id" name="parent_id" class="mt-1 block w-full border-gray-300 focus:border-rzvg-500 focus:ring-rzvg-500 rounded-md shadow-sm">
            <option value="">— Geen (root) —</option>
            @foreach ($parents as $parent)
                <option value="{{ $parent->id }}" @selected(old('parent_id', $page?->parent_id) == $parent->id)>{{ $parent->title }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('parent_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="template_id" value="Sjabloon" />
        <select id="template_id" name="template_id" class="mt-1 block w-full border-gray-300 focus:border-rzvg-500 focus:ring-rzvg-500 rounded-md shadow-sm" required>
            @foreach ($templates as $template)
                <option value="{{ $template->id }}" @selected(old('template_id', $page?->template_id) == $template->id)>{{ $template->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('template_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="visibility" value="Zichtbaarheid" />
        <select id="visibility" name="visibility" class="mt-1 block w-full border-gray-300 focus:border-rzvg-500 focus:ring-rzvg-500 rounded-md shadow-sm" required>
            @foreach ($visibilities as $visibility)
                <option value="{{ $visibility->value }}" @selected(old('visibility', $page?->visibility?->value ?? 'publiek') === $visibility->value)>{{ ucfirst($visibility->value) }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('visibility')" class="mt-2" />
    </div>

    <div class="flex items-center gap-3 pt-2">
        <x-primary-button>{{ $page ? 'Bijwerken' : 'Aanmaken' }}</x-primary-button>
        <a href="{{ route('admin.pages.index') }}" class="text-sm text-gray-600 hover:text-gray-800">Annuleren</a>
    </div>
</form>
