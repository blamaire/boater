{{-- Ledenstrook voor gasten: leden vinden hun zelfservice na het inloggen,
     de rest van de pagina is gericht op nieuwe bezoekers. Staat direct onder
     een openings-hero (zie public/page.blade.php), anders boven de header.
     max-w-[72rem] = leesbreedte max-w-6xl, maar een eigen klasse zodat de
     full-bleed-band van de hero onbegrensd blijft (BlockPreviewFullBleedTest). --}}
@guest
    <div class="bg-sand-100 border-b border-sand-200 text-gray-700">
        <div class="max-w-[72rem] mx-auto px-4 sm:px-6 lg:px-8 py-2 flex flex-wrap items-center justify-between gap-x-6 gap-y-1">
            <span>Al lid? Reserveren, de agenda en je gegevens vind je na het inloggen.</span>
            <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 min-h-11 font-semibold text-rzvg-600 hover:text-rzvg-700">
                Naar Mijn RZVG <span aria-hidden="true">→</span>
            </a>
        </div>
    </div>
@endguest
