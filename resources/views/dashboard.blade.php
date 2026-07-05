<x-app-layout>
    <x-slot name="header">Welkom{{ $person ? ', '.$person->first_name : '' }}</x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            @if ($shortcuts->isNotEmpty())
                <section aria-labelledby="shortcuts-heading">
                    <h2 id="shortcuts-heading" class="font-display text-lg text-gray-900 mb-3">Snelkoppelingen</h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($shortcuts as $shortcut)
                            <a href="{{ $shortcut['href'] }}"
                               class="block bg-white border border-gray-200 rounded-lg p-4 hover:border-rzvg-500 hover:shadow-sm transition focus:outline-none focus:border-rzvg-600">
                                <div class="flex items-baseline justify-between">
                                    <span class="font-medium text-gray-900">{{ $shortcut['label'] }}</span>
                                    @if ($shortcut['count'] !== null)
                                        <span class="inline-flex items-center justify-center min-w-[1.75rem] h-7 px-2 rounded-full bg-rzvg-500 text-white text-sm font-semibold">
                                            {{ $shortcut['count'] }}
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-2 text-sm text-gray-500">{{ $shortcut['description'] }}</p>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section aria-labelledby="roles-heading">
                <h2 id="roles-heading" class="font-display text-lg text-gray-900 mb-3">Jouw rollen</h2>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    @if ($roles->isEmpty())
                        <p class="text-sm text-gray-500">Je hebt op dit moment geen actieve rollen.</p>
                    @else
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($roles as $role)
                                <li class="inline-flex items-center gap-2 bg-rzvg-50 text-rzvg-700 border border-rzvg-200 rounded-full px-3 py-1 text-sm">
                                    <span class="font-medium">{{ $role['name'] }}</span>
                                    @if ($role['ends_at'])
                                        <span class="text-rzvg-500 text-xs">t/m {{ $role['ends_at']->translatedFormat('j M Y') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            <section aria-labelledby="permissions-heading">
                <h2 id="permissions-heading" class="font-display text-lg text-gray-900 mb-3">Jouw permissies</h2>
                <div class="bg-white border border-gray-200 rounded-lg">
                    @if ($permissionsByModule->isEmpty())
                        <p class="p-4 text-sm text-gray-500">Je hebt op dit moment geen effectieve permissies.</p>
                    @else
                        <dl class="divide-y divide-gray-100">
                            @foreach ($permissionsByModule as $module => $permissions)
                                <div class="grid sm:grid-cols-4 gap-2 p-4">
                                    <dt class="font-display text-rzvg-700 capitalize">{{ str_replace('_', ' ', $module) }}</dt>
                                    <dd class="sm:col-span-3">
                                        <ul class="space-y-1">
                                            @foreach ($permissions as $permission)
                                                <li class="text-sm">
                                                    <span class="font-medium text-gray-800">{{ $permission->description ?: $permission->key }}</span>
                                                    @if ($permission->description)
                                                        <span class="text-gray-400 text-xs ms-2">({{ $permission->key }})</span>
                                                    @endif
                                                    @if ($permission->is_sensitive)
                                                        <span class="ms-2 inline-flex items-center rounded-full bg-rzvg-50 px-2 py-0.5 text-xs font-medium text-rzvg-700 border border-rzvg-200">gevoelig</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </section>

            @unless ($person)
                <section class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-800">
                    Je account is nog niet gekoppeld aan een persoon. Neem contact op met de beheerder.
                </section>
            @endunless

        </div>
    </div>
</x-app-layout>
