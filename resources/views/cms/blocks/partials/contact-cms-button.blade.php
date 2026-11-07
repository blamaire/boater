{{-- "Bel/mail mij terug" als gewone CMS-knop (knopstijl 'contact'): zelfde
     tekst, icoon en doel als de zwevende <x-contact-button>, maar in de
     rechthoekige knopvorm zodat hij naast andere knoppen niet uit de toon valt. --}}
<a href="{{ route('contact') }}" class="{{ $buttonBase }} gap-2 bg-white text-rzvg-600 hover:bg-rzvg-50">
    <svg width="18" height="18" class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 0 0 2.22 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z" />
    </svg>
    Bel/mail mij terug
</a>
