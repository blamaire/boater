{{-- Footer van de publieke site (homepage-ontwerp, bordeaux = donker-900).
     Contactgegevens en juridische pagina's komen uit SiteSettings
     (PublicFooterComposer), de sportlinks uit het hoofdmenu (PublicNavComposer). --}}
@php
    $footerNav = collect($publicNav ?? [])->map(function ($item) {
        $isNav = $item instanceof \App\Models\NavItem;

        return [
            'label' => $isNav ? $item->displayLabel() : $item->title,
            'href' => ($isNav ? $item->url() : $item->publicUrl()) ?: '#',
        ];
    });
    $footerLink = 'text-white/80 hover:text-white';
@endphp
<footer class="bg-donker-900 text-white/80 mt-auto">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 text-[0.95rem]">
        {{-- Contactblok --}}
        <div class="flex flex-col gap-2">
            <p class="font-display text-2xl text-white">RZVG</p>
            <p>{{ $siteSettings->contact_name ?? 'Roei- en Zeilvereniging Gouda' }}</p>
            @if ($siteSettings->contact_address)
                <p class="whitespace-pre-line">{{ $siteSettings->contact_address }}</p>
            @endif
            @if ($siteSettings->contact_email)
                <a href="mailto:{{ $siteSettings->contact_email }}" class="{{ $footerLink }}">{{ $siteSettings->contact_email }}</a>
            @endif
            @if ($siteSettings->contact_phone)
                <a href="tel:{{ preg_replace('/\s+/', '', $siteSettings->contact_phone) }}" class="{{ $footerLink }}">{{ $siteSettings->contact_phone }}</a>
            @endif

            @if ($siteSettings->hasSocials())
                <div class="flex items-center gap-4 mt-3">
                    @if ($siteSettings->facebook_url)
                        <a href="{{ $siteSettings->facebook_url }}" target="_blank" rel="noopener noreferrer" aria-label="Facebook" class="{{ $footerLink }}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                                <path d="M13.5 21v-8h2.7l.4-3.1H13.5V7.9c0-.9.3-1.5 1.6-1.5h1.7V3.6c-.3-.1-1.4-.2-2.6-.2-2.6 0-4.4 1.6-4.4 4.5v2h-2.9V13h2.9v8h3.7Z"/>
                            </svg>
                        </a>
                    @endif
                    @if ($siteSettings->instagram_url)
                        <a href="{{ $siteSettings->instagram_url }}" target="_blank" rel="noopener noreferrer" aria-label="Instagram" class="{{ $footerLink }}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                                <path d="M12 2.2c3.2 0 3.6 0 4.8.1 1.2.1 1.8.2 2.2.4.6.2 1 .5 1.4.9.4.4.7.8.9 1.4.2.4.4 1 .4 2.2.1 1.2.1 1.6.1 4.8s0 3.6-.1 4.8c-.1 1.2-.2 1.8-.4 2.2-.2.6-.5 1-.9 1.4-.4.4-.8.7-1.4.9-.4.2-1 .4-2.2.4-1.2.1-1.6.1-4.8.1s-3.6 0-4.8-.1c-1.2-.1-1.8-.2-2.2-.4-.6-.2-1-.5-1.4-.9-.4-.4-.7-.8-.9-1.4-.2-.4-.4-1-.4-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.8c.1-1.2.2-1.8.4-2.2.2-.6.5-1 .9-1.4.4-.4.8-.7 1.4-.9.4-.2 1-.4 2.2-.4C8.4 2.2 8.8 2.2 12 2.2ZM12 4c-3.1 0-3.5 0-4.7.1-1.1.1-1.7.2-2.1.3-.5.2-.9.4-1.3.8-.4.4-.6.8-.8 1.3-.1.4-.2 1-.3 2.1C2.7 8.5 2.7 8.9 2.7 12s0 3.5.1 4.7c.1 1.1.2 1.7.3 2.1.2.5.4.9.8 1.3.4.4.8.6 1.3.8.4.1 1 .2 2.1.3 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c1.1-.1 1.7-.2 2.1-.3.5-.2.9-.4 1.3-.8.4-.4.6-.8.8-1.3.1-.4.2-1 .3-2.1.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-1.1-.2-1.7-.3-2.1-.2-.5-.4-.9-.8-1.3-.4-.4-.8-.6-1.3-.8-.4-.1-1-.2-2.1-.3C15.5 4 15.1 4 12 4Zm0 3a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 1.8a3.2 3.2 0 1 0 0 6.4 3.2 3.2 0 0 0 0-6.4ZM17.6 6a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4Z"/>
                            </svg>
                        </a>
                    @endif
                    @if ($siteSettings->youtube_url)
                        <a href="{{ $siteSettings->youtube_url }}" target="_blank" rel="noopener noreferrer" aria-label="YouTube" class="{{ $footerLink }}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                                <path d="M21.6 7.2c-.2-.9-.9-1.6-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4c-.9.2-1.6.9-1.8 1.8C2 8.8 2 12 2 12s0 3.2.4 4.8c.2.9.9 1.6 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4c.9-.2 1.6-.9 1.8-1.8.4-1.6.4-4.8.4-4.8s0-3.2-.4-4.8ZM10 15.1V8.9l5.2 3.1L10 15.1Z"/>
                            </svg>
                        </a>
                    @endif
                </div>
            @endif
        </div>

        {{-- Hoofdmenu --}}
        @if ($footerNav->isNotEmpty())
            <nav aria-label="Ontdek" class="flex flex-col gap-2">
                <p class="font-semibold text-white">Ontdek</p>
                @foreach ($footerNav as $item)
                    <a href="{{ $item['href'] }}" class="{{ $footerLink }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
        @endif

        {{-- Vereniging --}}
        <nav aria-label="Vereniging" class="flex flex-col gap-2">
            <p class="font-semibold text-white">Vereniging</p>
            <a href="{{ route('contact') }}" class="{{ $footerLink }}">Contact</a>
            @if ($siteSettings->privacyPage)
                <a href="{{ $siteSettings->privacyPage->publicUrl() }}" class="{{ $footerLink }}">Privacy</a>
            @endif
            @if ($siteSettings->termsPage)
                <a href="{{ $siteSettings->termsPage->publicUrl() }}" class="{{ $footerLink }}">Voorwaarden</a>
            @endif
        </nav>

        {{-- Leden --}}
        <nav aria-label="Leden" class="flex flex-col gap-2">
            <p class="font-semibold text-white">Leden</p>
            @auth
                <a href="{{ route('dashboard') }}" class="{{ $footerLink }}">Ledenpagina</a>
            @else
                <a href="{{ route('login') }}" class="{{ $footerLink }}">Inloggen</a>
                <a href="{{ route('lid-worden') }}" class="{{ $footerLink }}">Lid worden</a>
            @endauth
        </nav>
    </div>

    <div class="border-t border-white/15">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between gap-4 text-sm text-white/70">
            <span>&copy; {{ now()->year }} RZVG</span>
            <span class="font-display">Roei- en Zeilvereniging Gouda</span>
        </div>
    </div>
</footer>
