@php
    $item = fn (?string $href, string $label, bool $active = false, bool $soon = false) => compact('href', 'label', 'active', 'soon');
    $items = [
        $item(route('dashboard'), 'Dashboard', request()->routeIs('dashboard')),
        $item(null, 'Voorstellen', false, true),
        $item(route('portal.mijn-lidmaatschap'), 'Mijn lidmaatschap', request()->routeIs('portal.mijn-lidmaatschap')),
        $item(null, 'Activiteiten & Reserveren', false, true),
    ];
@endphp

<nav class="p-4 space-y-6 text-sm">
    <div>
        <h3 class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Portaal</h3>
        <ul class="mt-2 space-y-1">
            @foreach ($items as $it)
                <li>
                    @if ($it['soon'])
                        <span class="block px-3 py-2 rounded-md text-gray-400 italic">
                            {{ $it['label'] }} <span class="text-xs">(binnenkort)</span>
                        </span>
                    @else
                        <a href="{{ $it['href'] }}"
                            @class([
                                'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                'bg-rzvg-100 text-rzvg-700 font-medium' => $it['active'],
                                'text-gray-700' => ! $it['active'],
                            ])>
                            {{ $it['label'] }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    @canany(['pages.view', 'media.view', 'menu.manage', 'site_settings.manage', 'environments.manage', 'roles.view'])
        <div>
            <h3 class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Beheer</h3>
            <ul class="mt-2 space-y-1">
                @can('pages.view')
                    <li>
                        <a href="{{ route('admin.pages.index') }}"
                            @class([
                                'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.pages.*'),
                                'text-gray-700' => ! request()->routeIs('admin.pages.*'),
                            ])>Content</a>
                    </li>
                @endcan
                @can('media.view')
                    <li>
                        <a href="{{ route('admin.media') }}"
                            @class([
                                'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.media'),
                                'text-gray-700' => ! request()->routeIs('admin.media'),
                            ])>Media</a>
                    </li>
                @endcan
                @can('menu.manage')
                    <li>
                        <a href="{{ route('admin.menu') }}"
                            @class([
                                'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.menu'),
                                'text-gray-700' => ! request()->routeIs('admin.menu'),
                            ])>Menu</a>
                    </li>
                @endcan
                @can('site_settings.manage')
                    <li>
                        <a href="{{ route('admin.site-settings') }}"
                            @class([
                                'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.site-settings'),
                                'text-gray-700' => ! request()->routeIs('admin.site-settings'),
                            ])>Instellingen</a>
                    </li>
                @endcan
                @can('environments.manage')
                    <li>
                        <a href="{{ route('admin.environments') }}"
                            @class([
                                'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.environments'),
                                'text-gray-700' => ! request()->routeIs('admin.environments'),
                            ])>Omgevingen</a>
                    </li>
                @endcan
                @can('roles.view')
                    <li>
                        <a href="{{ route('admin.roles.index') }}"
                            @class([
                                'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.roles.*') || request()->routeIs('admin.person-roles.*'),
                                'text-gray-700' => ! (request()->routeIs('admin.roles.*') || request()->routeIs('admin.person-roles.*')),
                            ])>Rollen &amp; permissies</a>
                    </li>
                @endcan
            </ul>
        </div>
    @endcanany

    <div class="border-t border-gray-200 pt-4">
        <div class="px-3 py-2">
            <div class="font-medium text-gray-900">{{ Auth::user()->name }}</div>
            <div class="text-xs text-gray-500 truncate">{{ Auth::user()->email }}</div>
        </div>
        <ul class="mt-2 space-y-1">
            <li>
                <a href="{{ route('profile.edit') }}"
                    @class([
                        'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                        'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('profile.edit'),
                        'text-gray-700' => ! request()->routeIs('profile.edit'),
                    ])>Profiel</a>
            </li>
            <li>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full text-left px-3 py-2 rounded-md text-gray-700 hover:bg-red-50 hover:text-red-700">
                        Uitloggen
                    </button>
                </form>
            </li>
        </ul>
    </div>
</nav>
