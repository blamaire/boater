@php
    // Beheer- en portaal-menu-items staan alfabetisch op label (Dashboard uitgezonderd — blijft startpunt).
    $item = fn (?string $href, string $label, bool $active = false, bool $soon = false) => compact('href', 'label', 'active', 'soon');
    $items = [
        $item(route('dashboard'), 'Dashboard', request()->routeIs('dashboard')),
        $item(route('portal.mijn-lidmaatschap'), 'Mijn lidmaatschap', request()->routeIs('portal.mijn-lidmaatschap')),
        auth()->user()?->can('reservations.create')
            ? $item(route('portal.reserveren'), 'Reserveren', request()->routeIs('portal.reserveren'))
            : $item(null, 'Reserveren', false, true),
        auth()->user()?->can('damage_reports.create')
            ? $item(route('portal.schade-melden'), 'Schade melden', request()->routeIs('portal.schade-melden'))
            : $item(null, 'Schade melden', false, true),
        $item(null, 'Voorstellen', false, true),
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

    @canany(['pages.view', 'media.view', 'menu.manage', 'site_settings.manage', 'environments.manage', 'roles.view', 'users.manage', 'activities.view', 'reservable_objects.manage', 'reservations.view', 'reservations.update', 'damage_reports.view', 'approver_groups.manage', 'audit_trail.view', 'products.manage', 'invoices.manage'])
        <div>
            <h3 class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Beheer</h3>
            {{-- Beheer-menu-items staan alfabetisch op label; sommige zijn gegroepeerd
                 in een inklapbare submodule (Boekhouding, Gebruikersbeheer,
                 Instellingen, Paginabeheer, Reserveringen). Submodules staan standaard
                 ingeklapt, behalve de groep waarin de huidige pagina valt. --}}
            <ul class="mt-2 space-y-1">
                @can('activities.view')
                    <li>
                        <a href="{{ route('admin.activities.index') }}"
                            @class([
                                'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.activities.*') || request()->routeIs('admin.activity-categories.*'),
                                'text-gray-700' => ! (request()->routeIs('admin.activities.*') || request()->routeIs('admin.activity-categories.*')),
                            ])>Activiteiten</a>
                    </li>
                @endcan
                @can('audit_trail.view')
                    <li>
                        <a href="{{ route('admin.audit.index') }}"
                            @class([
                                'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.audit.*'),
                                'text-gray-700' => ! request()->routeIs('admin.audit.*'),
                            ])>Auditlogboek</a>
                    </li>
                @endcan
                @canany(['invoices.manage', 'products.manage'])
                    @php
                        $boekhoudingActief = request()->routeIs('admin.billing.*')
                            || request()->routeIs('admin.invoices.*')
                            || request()->routeIs('admin.products.*');
                    @endphp
                    <li x-data="{ open: {{ $boekhoudingActief ? 'true' : 'false' }} }">
                        <button type="button" @click="open = ! open"
                            @class([
                                'flex w-full items-center justify-between px-3 py-2 rounded-md hover:bg-rzvg-50',
                                'text-rzvg-700 font-medium' => $boekhoudingActief,
                                'text-gray-700' => ! $boekhoudingActief,
                            ])>
                            <span>Boekhouding</span>
                            <svg width="16" height="16" class="h-4 w-4 shrink-0 transition-transform" :class="open && 'rotate-90'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <ul x-show="open" @unless($boekhoudingActief) style="display: none;" @endunless class="mt-1 ml-3 space-y-1 border-l border-gray-200 pl-3">
                            @can('invoices.manage')
                                <li>
                                    <a href="{{ route('admin.billing.index') }}"
                                        @class([
                                            'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                            'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.billing.*') || request()->routeIs('admin.invoices.*'),
                                            'text-gray-700' => ! (request()->routeIs('admin.billing.*') || request()->routeIs('admin.invoices.*')),
                                        ])>Facturatie</a>
                                </li>
                            @endcan
                            @can('products.manage')
                                <li>
                                    <a href="{{ route('admin.products.index') }}"
                                        @class([
                                            'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                            'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.products.*'),
                                            'text-gray-700' => ! request()->routeIs('admin.products.*'),
                                        ])>Producten</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcanany
                @canany(['users.manage', 'approver_groups.manage', 'roles.view'])
                    @php
                        $gebruikersActief = request()->routeIs('admin.users.*')
                            || request()->routeIs('admin.person-permissions.*')
                            || request()->routeIs('admin.approver-groups.*')
                            || request()->routeIs('admin.roles.*')
                            || request()->routeIs('admin.person-roles.*');
                    @endphp
                    <li x-data="{ open: {{ $gebruikersActief ? 'true' : 'false' }} }">
                        <button type="button" @click="open = ! open"
                            @class([
                                'flex w-full items-center justify-between px-3 py-2 rounded-md hover:bg-rzvg-50',
                                'text-rzvg-700 font-medium' => $gebruikersActief,
                                'text-gray-700' => ! $gebruikersActief,
                            ])>
                            <span>Gebruikersbeheer</span>
                            <svg width="16" height="16" class="h-4 w-4 shrink-0 transition-transform" :class="open && 'rotate-90'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <ul x-show="open" @unless($gebruikersActief) style="display: none;" @endunless class="mt-1 ml-3 space-y-1 border-l border-gray-200 pl-3">
                            @can('users.manage')
                                <li>
                                    @php
                                        $inUsersSection = request()->routeIs('admin.users.*') || request()->routeIs('admin.person-permissions.*');
                                    @endphp
                                    <a href="{{ route('admin.users.index') }}"
                                        @class([
                                            'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                            'bg-rzvg-100 text-rzvg-700 font-medium' => $inUsersSection,
                                            'text-gray-700' => ! $inUsersSection,
                                        ])>Gebruikers</a>
                                </li>
                            @endcan
                            @can('approver_groups.manage')
                                <li>
                                    <a href="{{ route('admin.approver-groups.index') }}"
                                        @class([
                                            'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                            'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.approver-groups.*'),
                                            'text-gray-700' => ! request()->routeIs('admin.approver-groups.*'),
                                        ])>Goedkeuringsgroepen</a>
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
                    </li>
                @endcanany
                @canany(['site_settings.manage', 'environments.manage'])
                    @php
                        $instellingenActief = request()->routeIs('admin.site-settings') || request()->routeIs('admin.environments');
                    @endphp
                    <li x-data="{ open: {{ $instellingenActief ? 'true' : 'false' }} }">
                        <button type="button" @click="open = ! open"
                            @class([
                                'flex w-full items-center justify-between px-3 py-2 rounded-md hover:bg-rzvg-50',
                                'text-rzvg-700 font-medium' => $instellingenActief,
                                'text-gray-700' => ! $instellingenActief,
                            ])>
                            <span>Instellingen</span>
                            <svg width="16" height="16" class="h-4 w-4 shrink-0 transition-transform" :class="open && 'rotate-90'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <ul x-show="open" @unless($instellingenActief) style="display: none;" @endunless class="mt-1 ml-3 space-y-1 border-l border-gray-200 pl-3">
                            @can('site_settings.manage')
                                <li>
                                    <a href="{{ route('admin.site-settings') }}"
                                        @class([
                                            'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                            'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.site-settings'),
                                            'text-gray-700' => ! request()->routeIs('admin.site-settings'),
                                        ])>Contactinformatie</a>
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
                        </ul>
                    </li>
                @endcanany
                @canany(['pages.view', 'media.view', 'menu.manage'])
                    @php
                        $paginaActief = request()->routeIs('admin.pages.*') || request()->routeIs('admin.media') || request()->routeIs('admin.menu');
                    @endphp
                    <li x-data="{ open: {{ $paginaActief ? 'true' : 'false' }} }">
                        <button type="button" @click="open = ! open"
                            @class([
                                'flex w-full items-center justify-between px-3 py-2 rounded-md hover:bg-rzvg-50',
                                'text-rzvg-700 font-medium' => $paginaActief,
                                'text-gray-700' => ! $paginaActief,
                            ])>
                            <span>Paginabeheer</span>
                            <svg width="16" height="16" class="h-4 w-4 shrink-0 transition-transform" :class="open && 'rotate-90'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <ul x-show="open" @unless($paginaActief) style="display: none;" @endunless class="mt-1 ml-3 space-y-1 border-l border-gray-200 pl-3">
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
                            @can('pages.view')
                                <li>
                                    <a href="{{ route('admin.pages.index') }}"
                                        @class([
                                            'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                            'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.pages.*'),
                                            'text-gray-700' => ! request()->routeIs('admin.pages.*'),
                                        ])>Pagina's</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcanany
                @canany(['reservable_objects.manage', 'reservations.view', 'reservations.update', 'damage_reports.view'])
                    @php
                        $reserveringenActief = request()->routeIs('admin.reservable-objects.*')
                            || request()->routeIs('admin.object-categories.*')
                            || request()->routeIs('admin.reservations.*')
                            || request()->routeIs('admin.reservation-rules.*')
                            || request()->routeIs('admin.damage-reports.*');
                    @endphp
                    <li x-data="{ open: {{ $reserveringenActief ? 'true' : 'false' }} }">
                        <button type="button" @click="open = ! open"
                            @class([
                                'flex w-full items-center justify-between px-3 py-2 rounded-md hover:bg-rzvg-50',
                                'text-rzvg-700 font-medium' => $reserveringenActief,
                                'text-gray-700' => ! $reserveringenActief,
                            ])>
                            <span>Reserveringen</span>
                            <svg width="16" height="16" class="h-4 w-4 shrink-0 transition-transform" :class="open && 'rotate-90'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <ul x-show="open" @unless($reserveringenActief) style="display: none;" @endunless class="mt-1 ml-3 space-y-1 border-l border-gray-200 pl-3">
                            @can('reservable_objects.manage')
                                <li>
                                    <a href="{{ route('admin.reservable-objects.index') }}"
                                        @class([
                                            'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                            'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.reservable-objects.*') || request()->routeIs('admin.object-categories.*'),
                                            'text-gray-700' => ! (request()->routeIs('admin.reservable-objects.*') || request()->routeIs('admin.object-categories.*')),
                                        ])>Objecten</a>
                                </li>
                            @endcan
                            @can('reservations.view')
                                <li>
                                    <a href="{{ route('admin.reservations.index') }}"
                                        @class([
                                            'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                            'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.reservations.*'),
                                            'text-gray-700' => ! request()->routeIs('admin.reservations.*'),
                                        ])>Reserveringen</a>
                                </li>
                            @endcan
                            @can('reservations.update')
                                <li>
                                    <a href="{{ route('admin.reservation-rules.index') }}"
                                        @class([
                                            'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                            'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.reservation-rules.*'),
                                            'text-gray-700' => ! request()->routeIs('admin.reservation-rules.*'),
                                        ])>Reserveringsregels</a>
                                </li>
                            @endcan
                            @can('damage_reports.view')
                                <li>
                                    <a href="{{ route('admin.damage-reports.index') }}"
                                        @class([
                                            'block px-3 py-2 rounded-md hover:bg-rzvg-50',
                                            'bg-rzvg-100 text-rzvg-700 font-medium' => request()->routeIs('admin.damage-reports.*'),
                                            'text-gray-700' => ! request()->routeIs('admin.damage-reports.*'),
                                        ])>Schademeldingen</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcanany
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
