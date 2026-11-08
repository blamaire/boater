<?php

namespace Database\Seeders;

use App\Enums\BandBackground;
use App\Enums\BlockType;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Zaait voor lokale ontwikkeling de wervende sportpagina's Roeien, Zeilen en
 * Suppen (`/pagina/roeien` enz.), opgebouwd zoals de homepage: hero, voor
 * wie, zo begin je, wat kost het, varianten, veelgestelde vragen en een rode
 * afsluitband. De feiten komen van de huidige website roeienzeil.nl (tarieven
 * 2026); wat daar niet staat, staat als [placeholder] voor een redacteur.
 *
 * Alleen bij `APP_ENV=local` en idempotent: een pagina die al een
 * gepubliceerde versie heeft, blijft ongemoeid. Wordt aangeroepen vanuit
 * {@see LocalPublicMenuSeeder}, vóórdat die de overige menupagina's vult.
 */
class LocalSportPagesSeeder extends Seeder
{
    use BuildsPageBands;

    public function run(): void
    {
        if (app()->environment() !== 'local') {
            return;
        }

        $template = Template::query()->orderBy('id')->first();
        if ($template === null) {
            return;
        }

        $pages = [
            'roeien' => ['Roeien', 'Roeien bij de Roei- en Zeilvereniging Gouda: recreatief, wedstrijd, marathon, roeivalidatie en jeugd. Begin met de Basis Roei Opleiding.', $this->rowingBands()],
            'zeilen' => ['Zeilen', 'Zeilen bij de Roei- en Zeilvereniging Gouda: zwaardboot, kielboot, wedstrijd en jeugd, met ruim 55 clubboten en erkende opleidingen.', $this->sailingBands()],
            'suppen' => ['Suppen', 'Suppen bij de Roei- en Zeilvereniging Gouda: na één introductieles op een clubboard over de Reeuwijkse plassen.', $this->supBands()],
        ];

        DB::transaction(function () use ($template, $pages): void {
            foreach ($pages as $slug => [$title, $metaDescription, $bands]) {
                $page = Page::query()->firstOrCreate(
                    ['slug' => $slug, 'parent_id' => null, 'type' => PageType::Content->value],
                    ['title' => $title, 'visibility' => PageVisibility::Public, 'template_id' => $template->id],
                );

                if ($page->published_version_id !== null) {
                    continue;
                }

                $version = PageVersion::query()->create([
                    'page_id' => $page->id,
                    'version_no' => ((int) $page->versions()->max('version_no')) + 1,
                    'status' => PageVersionStatus::Published,
                    'meta_description' => $metaDescription,
                ]);

                $this->createBands($version, $bands);

                $page->update(['published_version_id' => $version->id]);
            }
        });
    }

    /** @return list<array{0: BandBackground, 1: list<list<array{0: BlockType, 1: array<string, mixed>}>>}> */
    private function rowingBands(): array
    {
        $white = BandBackground::White;
        $sand = BandBackground::Sand;

        return [
            $this->hero('Roeien bij RZVG', 'Roeien op de Reeuwijkse plassen', 'Toeren door het plassengebied, fit blijven of wedstrijden varen. Je leert het van ervaren leden, in een ploeg die bij je past.'),

            // Voor wie
            [$white, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Roeien, iets voor jou?']],
                [BlockType::Text, ['html' => '<p>Ruim de helft van onze meer dan 1000 leden roeit. De meesten recreatief, op de plassen en door de stad. Daarnaast varen zo’n 150 leden wedstrijden en roeien er ongeveer 50 marathons. Roei je al? Dan helpen we je aan een ploeg die past bij je ambitie.</p>']],
            ]]],
            [$white, [
                [[BlockType::IconText, ['icon' => 'users', 'title' => 'Samen in de boot', 'body' => 'Je begint in een vijfmansboot: vier roeiers en een stuur. Later kun je door naar kleinere boten en de skiff.']]],
                [[BlockType::IconText, ['icon' => 'map-pin', 'title' => 'Prachtig vaarwater', 'body' => 'Het Reeuwijkse plassengebied, de grachten van Gouda en de Breevaart, een rechte baan van zo’n 2 km.']]],
                [[BlockType::IconText, ['icon' => 'heart', 'title' => 'Voor iedereen', 'body' => 'Jeugd vanaf 10 jaar, volwassenen van elke leeftijd en roeivalidatie voor mensen met een beperking.']]],
            ]],

            // Zo begin je
            ...$this->steps(
                'Ervaring is niet nodig. Je hebt wel zwemdiploma A en B nodig.',
                ['Probeer het', 'Vraag een proefles roeien aan, of kom kennismaken op zaterdag- of zondagochtend.'],
                ['Word lid', 'Met een A-lidmaatschap mag je roeien en krijg je instructie. Binnen vier weken bedacht? Dan betaal je geen contributie.'],
                ['Volg de Basis Roei Opleiding', 'In één seizoen leer je roeien en sturen. Met je diploma roei je zelfstandig op de plassen.'],
            ),

            // Ontwikkelpad
            ...$this->pathway(
                'Na de Basis Roei Opleiding bepaal je zelf welke kant je op gaat. Klik op een stap voor meer informatie.',
                [
                    ['Basis Roei Opleiding (BRO)', 'je eerste roeilessen, A-diploma'],
                    ['Recreatief roeien', 'toertochten in gestuurde boten'],
                    ['Voortgezette Roei Opleiding (VRO)', 'C-diploma'],
                    ['Cursus Ongestuurd Roeien (COR)', 'boten zonder stuurman'],
                    ['Marathonroeien', 'lange afstanden, C-diploma vereist'],
                    ['Skiff 1-instructie', 'E-diploma, zelfstandig in smalle boten'],
                    ['Skiff 2-instructie', 'F-diploma'],
                    ['Wedstrijdroeien', 'in ploegverband'],
                ],
                [
                    ['Basis Roei Opleiding (BRO)', 'Recreatief roeien'],
                    ['Basis Roei Opleiding (BRO)', 'Voortgezette Roei Opleiding (VRO)'],
                    ['Voortgezette Roei Opleiding (VRO)', 'Cursus Ongestuurd Roeien (COR)'],
                    ['Voortgezette Roei Opleiding (VRO)', 'Marathonroeien'],
                    ['Voortgezette Roei Opleiding (VRO)', 'Skiff 1-instructie'],
                    ['Skiff 1-instructie', 'Skiff 2-instructie'],
                    ['Skiff 1-instructie', 'Wedstrijdroeien'],
                    ['Skiff 2-instructie', 'Wedstrijdroeien'],
                ],
            ),

            // Wat kost het?
            ...$this->prices(
                'Contributie per jaar, tarieven 2026. Bij aanmelding betaal je eenmalig €35 entreegeld. De Basis Roei Opleiding kost [bedrag].',
                [
                    ['Volwassene', '€390', 'Lid A, vanaf 21 jaar. Roeien met instructie.'],
                    ['Jeugd', '€165', 'Van 12 tot 21 jaar. Jonger dan 12? Dan word je aspirant-lid voor €105.'],
                    ['Student', '€165', 'Vanaf 21 jaar met een voltijdstudie en een geldige studentenkaart.'],
                ],
                $sand,
            ),

            // Varianten
            [$white, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Zo veel manieren om te roeien']],
                [BlockType::Text, ['html' => '<p>Na je opleiding kies je zelf hoe je verder gaat: rustig toeren, lange tochten of wedstrijden.</p>']],
            ]]],
            [$white, [
                [$this->variant('compass', 'Recreatief en toeren', 'Roei met je eigen ploegje, plan een tocht met de datumprikker of doe mee aan toer- en prestatietochten. In de winter houdt Fifty-Fit je fit.')],
                [$this->variant('users', 'Wedstrijdroeien', 'Masters vanaf 27 jaar varen zo’n twintig wedstrijden per jaar, in achten tot skiffs. Van beginner tot oud-wereldkampioen.')],
                [$this->variant('map-pin', 'Marathonroeien', 'Tochten van minstens 50 km, in binnen- en buitenland. Voor wie zijn roeiopleiding heeft afgerond en van lange afstanden houdt.')],
            ]],
            [$white, [
                [$this->variant('heart', 'Roeivalidatie', 'Roeien met een beperking, in een extra brede, stabiele boot. Ervaren instructeurs roeien mee, van maart tot en met oktober.')],
                [$this->variant('users', 'Jeugdroeien', 'Van 10 tot 17 jaar. Je leert roeien in een skiff, op woensdagmiddag of zaterdagochtend.', '/pagina/jeugd', 'Informatie voor ouders')],
                [$this->variant('document', 'Verder leren', 'Na de Basis Roei Opleiding volgen de Voortgezette Roei Opleiding, ongestuurd roeien en skiff-instructie.')],
            ]],

            // Veelgestelde vragen
            $this->faq([
                ['Heb ik ervaring nodig?', 'Nee. De meeste nieuwe leden beginnen met de Basis Roei Opleiding en leren in één seizoen roeien en sturen. Instructie krijg je van ervaren leden.'],
                ['Welk zwemdiploma heb ik nodig?', 'Iedereen die lid wordt, heeft minimaal zwemdiploma A en B.'],
                ['Kan ik eerst proberen?', 'Ja. Vraag een proefles roeien aan, of kom kennismaken op zaterdag- of zondagochtend. Op de open dag ben je ook welkom; die staat in de agenda.'],
                ['Wanneer begint de Basis Roei Opleiding?', '[Startdatum en lestijden van de Basis Roei Opleiding.]'],
                ['Wat trek ik aan?', '[Advies over sportkleding voor je eerste roeiles.]'],
                ['Ik roei bij een andere vereniging. Kan ik bij jullie komen roeien?', 'Zeker. Door de week kun je als gast een boot huren voor €10 per persoon per dag, inclusief koffie bij aankomst en een drankje na afloop. Word je lid, dan helpen we je een ploeg te vinden.'],
            ], $sand),

            $this->closing(),
        ];
    }

    /** @return list<array{0: BandBackground, 1: list<list<array{0: BlockType, 1: array<string, mixed>}>>}> */
    private function sailingBands(): array
    {
        $white = BandBackground::White;
        $sand = BandBackground::Sand;

        return [
            $this->hero('Zeilen bij RZVG', 'Zeilen op de Reeuwijkse plassen', 'Van Optimist tot kielboot. Je leert het van gediplomeerde instructeurs en vaart daarna met de boten van de club.'),

            // Voor wie
            [$white, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Zeilen, iets voor jou?']],
                [BlockType::Text, ['html' => '<p>Nog nooit gezeild of al jaren op het water? Bij RZVG is er instructie voor jeugd en volwassenen, voor beginners en gevorderden. Met je diploma vaar je in de boten van de club. Een eigen boot heb je dus niet nodig.</p>']],
            ]]],
            [$white, [
                [[BlockType::IconText, ['icon' => 'sail', 'title' => 'Ruim 55 clubboten', 'body' => 'Van de kleine Optimist tot de Randmeer en de Centaur.']]],
                [[BlockType::IconText, ['icon' => 'check-circle', 'title' => 'Erkende opleiding', 'body' => 'Erkende opleidingslocatie van de Watersport Academy, voor de diploma’s I, II en III.']]],
                [[BlockType::IconText, ['icon' => 'users', 'title' => 'Les van eigen leden', 'body' => 'Onze instructeurs zijn leden, minimaal 16 jaar en in het bezit van een instructeursdiploma.']]],
            ]],

            // Zo begin je
            ...$this->steps(
                'Ervaring is niet nodig. Je hebt wel zwemdiploma A en B nodig.',
                ['Probeer het', 'Vraag een proefles zeilen aan, of kom kennismaken op zaterdag- of zondagochtend.'],
                ['Word lid', 'Met een A-lidmaatschap mag je zeilen en krijg je instructie. Binnen vier weken bedacht? Dan betaal je geen contributie.'],
                ['Haal je zeildiploma', 'Leer zeilen in de zwaardboot of de kielboot. Met je diploma vaar je zelfstandig in de boten van de club.'],
            ),

            // Ontwikkelpad
            ...$this->pathway(
                'Van je eerste lessen tot de wedstrijdbaan: zo kun je als zeiler doorgroeien. Klik op een stap voor meer informatie.',
                [
                    ['Jeugdzeilen', '8–17 jaar, jeugdzeiltraining', '/pagina/jeugd'],
                    ['Kielbootzeilen', 'instap voor volwassenen, diploma I–III'],
                    ['Clubzeilen', 'zelfstandig varen met clubboten'],
                    ['Zwaardbootzeilen', 'sneller en sportiever'],
                    ['Wedstrijdzeilen', 'clubwedstrijden en regatta’s'],
                ],
                [
                    ['Jeugdzeilen', 'Clubzeilen'],
                    ['Kielbootzeilen', 'Clubzeilen'],
                    ['Kielbootzeilen', 'Zwaardbootzeilen'],
                    ['Zwaardbootzeilen', 'Wedstrijdzeilen'],
                ],
            ),

            // Wat kost het?
            ...$this->prices(
                'Contributie per jaar, tarieven 2026. Bij aanmelding betaal je eenmalig €35 entreegeld. Een zeilopleiding kost [bedrag].',
                [
                    ['Volwassene', '€390', 'Lid A, vanaf 21 jaar. Zeilen met instructie.'],
                    ['Jeugd', '€165', 'Van 12 tot 21 jaar. Jonger dan 12? Dan word je aspirant-lid voor €105.'],
                    ['Student', '€165', 'Vanaf 21 jaar met een voltijdstudie en een geldige studentenkaart.'],
                ],
                $sand,
            ),

            // Varianten
            [$white, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Zo veel manieren om te zeilen']],
                [BlockType::Text, ['html' => '<p>Rustig toeren over de plassen of de strijd aangaan op de wedstrijdbaan: jij kiest.</p>']],
            ]]],
            [$white, [
                [$this->variant('sail', 'Zwaardboot', 'Wendbaar en sportief, alleen in de Laser of Splash of met z’n tweeën in de Laser Vago.')],
                [$this->variant('sail', 'Kielboot', 'Stabiel en ruim. Vaar mee met het clubzeilen of de plassentocht met kielboten.')],
                [$this->variant('compass', 'Recreatief zeilen', 'Clubzeilen, het Frieslandweekend en jachtzeilen op groot water. In de WhatsApp-groep zie je wie er meevaart.')],
            ]],
            [$white, [
                [$this->variant('check-circle', 'Wedstrijdzeilen', 'Dinsdagavondwedstrijden, de Goudse Zeilweek met Pinksteren en wedstrijden door het hele land, in wedstrijdgetrimde clubboten.')],
                [$this->variant('users', 'Jeugdzeilen', 'Les in de Optimist en de RS Feva, laagdrempelige wedstrijden in de Combi Rotterdam en vanaf april een wekelijkse jeugdtraining.', '/pagina/jeugd', 'Informatie voor ouders')],
                [$this->variant('calendar', 'Zeilweken en -weekenden', 'De Minizeilweek, de Zeilinstructieweek, YOUNG ONES voor Optimisten en het Zeilweekend Zeeland.')],
            ]],

            // Veelgestelde vragen
            $this->faq([
                ['Heb ik ervaring nodig?', 'Nee. Er is instructie voor beginners en gevorderden, voor jeugd en volwassenen.'],
                ['Welk zwemdiploma heb ik nodig?', 'Iedereen die lid wordt, heeft minimaal zwemdiploma A en B.'],
                ['Moet ik een eigen boot hebben?', 'Nee. De club heeft ruim 55 open zeilboten. Heb je het juiste diploma, dan mag je ze als lid gebruiken.'],
                ['Wat heb ik aan een zeildiploma?', 'Je mag ermee in de boten van de club varen. En huur je elders in Nederland een boot, dan laat je er je zeilervaring mee zien.'],
                ['Kan ik eerst proberen?', 'Ja. Vraag een proefles zeilen aan, of kom kennismaken op zaterdag- of zondagochtend.'],
                ['Wanneer zijn de zeillessen?', '[Startdata en lestijden van de zeilopleidingen.]'],
            ], $sand),

            $this->closing(),
        ];
    }

    /** @return list<array{0: BandBackground, 1: list<list<array{0: BlockType, 1: array<string, mixed>}>>}> */
    private function supBands(): array
    {
        $white = BandBackground::White;
        $sand = BandBackground::Sand;

        return [
            $this->hero('Suppen bij RZVG', 'Suppen op de Reeuwijkse plassen', 'Staand of zittend op een board over het water. Snel onder de knie, en je kunt in principe het hele jaar het water op.'),

            // Voor wie
            [$white, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Suppen, iets voor jou?']],
                [BlockType::Text, ['html' => '<p>Sinds 2021 kun je bij RZVG suppen. Het is laagdrempelig en niet moeilijk: na één introductieles ga je zelfstandig het water op, op een board van de club.</p>']],
            ]]],
            [$white, [
                [[BlockType::IconText, ['icon' => 'compass', 'title' => 'Zoals jij wilt', 'body' => 'Staand of zittend peddelen, wat jou het beste ligt.']]],
                [[BlockType::IconText, ['icon' => 'calendar', 'title' => 'Het hele jaar', 'body' => 'Suppen kan in principe het hele jaar door.']]],
                [[BlockType::IconText, ['icon' => 'map-pin', 'title' => 'Plassen en Breevaart', 'body' => 'Je suppt op de Reeuwijkse plassen en op de Breevaart.']]],
            ]],

            // Zo begin je
            ...$this->steps(
                'Ervaring is niet nodig. Je hebt wel zwemdiploma A en B nodig, net als ieder lid.',
                ['Maak kennis', 'Kom kijken op zaterdag- of zondagochtend, of vraag ons om meer informatie.'],
                ['Word lid', 'Wil je alleen suppen? Dan is een B-lidmaatschap genoeg. Binnen vier weken bedacht? Dan betaal je geen contributie.'],
                ['Volg SUP-Basis', 'In een introductieles van 1 tot 1,5 uur leer je alles over materiaal, houding, sturen en veiligheid. Daarna leen je zelf een board.'],
            ),

            // Wat kost het?
            ...$this->prices(
                'Contributie per jaar, tarieven 2026. Bij aanmelding betaal je eenmalig €35 entreegeld. De introductieles SUP-Basis kost [bedrag].',
                [
                    ['Alleen suppen', '€170', 'Lid B, vanaf 21 jaar. Na de introductieles suppen op de boards van de club.'],
                    ['Jeugd', '€165', 'Van 12 tot 21 jaar. Onder de 16 sup je onder begeleiding van een lid van 16 jaar of ouder.'],
                    ['Ook roeien of zeilen', '€390', 'Lid A, vanaf 21 jaar. Daarmee kun je ook roeien en zeilen, met instructie.'],
                ],
            ),

            // Varianten
            [$sand, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Meer dan alleen peddelen']],
                [BlockType::Text, ['html' => '<p>Gecertificeerde SUP-instructeurs organiseren als vrijwilliger allerlei activiteiten.</p>']],
            ]]],
            [$sand, [
                [$this->variant('compass', 'SUP-Tour', 'Met een groep op tocht over de plassen.')],
                [$this->variant('users', 'SUP-Polo', 'Een teamspel: met een speciale peddel gooi je de bal in het doel van de tegenpartij.')],
            ]],
            [$sand, [
                [$this->variant('check-circle', 'SUP-Techniek', 'Een vervolgles om je techniek te verbeteren, in een wetsuit.')],
                [$this->variant('heart', 'SUP Clean Up', 'Suppen en onderweg zwerfafval uit het water halen.')],
            ]],

            // Veelgestelde vragen
            $this->faq([
                ['Is suppen moeilijk?', 'Nee. Suppen is laagdrempelig: je peddelt staand of zittend en na de introductieles SUP-Basis ga je zelfstandig het water op.'],
                ['Moet ik een eigen board hebben?', 'Nee. De club heeft all-round supboards. Ben je 16 jaar of ouder en heb je SUP-Basis gevolgd, dan schrijf je zelf een board af.'],
                ['Wat trek ik aan?', 'Kleding waarin je goed kunt bewegen én zwemmen, zoals roei- of hardloopkleding. Regenkleding dus liever niet. Neem droge kleren mee voor op de kant.'],
                ['Mag mijn kind ook suppen?', 'Ja, onder begeleiding van een lid van 16 jaar of ouder dat de introductieles heeft gevolgd.'],
                ['Waar mag ik suppen?', 'Op de plassen en op de Breevaart, niet in de haven. Zorg dat je altijd op eigen kracht terug kunt peddelen en let op afstand, windkracht en windrichting.'],
                ['Wanneer is de introductieles?', '[Data en tijden van de introductieles SUP-Basis.]'],
            ]),

            $this->closing(),
        ];
    }

    /**
     * "Jouw ontwikkelpad": kop, korte intro en het Pathway-blok, op wit.
     *
     * @param  list<array{0: string, 1: string}|array{0: string, 1: string, 2: string}>  $steps  label, toelichting, optioneel link
     * @param  list<array{0: string, 1: string}>  $arrows  label van, label naar
     * @return list<array{0: BandBackground, 1: list<list<array{0: BlockType, 1: array<string, mixed>}>>}>
     */
    private function pathway(string $intro, array $steps, array $arrows): array
    {
        $nodes = array_map(fn (array $s): array => [
            'key' => Str::slug($s[0]),
            'label' => $s[0],
            'sub' => $s[1],
            'href' => $s[2] ?? null,
        ], $steps);
        $edges = array_map(fn (array $a): array => ['from' => Str::slug($a[0]), 'to' => Str::slug($a[1])], $arrows);

        return [
            [BandBackground::White, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Jouw ontwikkelpad']],
                [BlockType::Text, ['html' => '<p>'.e($intro).'</p>']],
            ]]],
            [BandBackground::White, [[
                [BlockType::Pathway, ['nodes' => $nodes, 'edges' => $edges]],
            ]]],
        ];
    }

    /** @return array{0: BandBackground, 1: list<list<array{0: BlockType, 1: array<string, mixed>}>>} */
    private function hero(string $kicker, string $title, string $subtitle): array
    {
        return [BandBackground::White, [[
            [BlockType::Hero, [
                'kicker' => $kicker,
                'title' => $title,
                'subtitle' => $subtitle,
                'cta_label' => 'Proefles aanvragen',
                'cta_href' => '/contact',
                'cta2_label' => 'Lid worden',
                'cta2_href' => '/lid-worden',
            ]],
        ]]];
    }

    /**
     * "Zo begin je": kop + intro en drie genummerde stappen, op zand.
     *
     * @param  array{0: string, 1: string}  $step1
     * @param  array{0: string, 1: string}  $step2
     * @param  array{0: string, 1: string}  $step3
     * @return list<array{0: BandBackground, 1: list<list<array{0: BlockType, 1: array<string, mixed>}>>}>
     */
    private function steps(string $intro, array $step1, array $step2, array $step3): array
    {
        $columns = [];
        foreach ([$step1, $step2, $step3] as $i => [$title, $body]) {
            $columns[] = [[BlockType::Step, ['number' => (string) ($i + 1), 'title' => $title, 'body' => $body]]];
        }

        return [
            [BandBackground::Sand, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Zo begin je']],
                [BlockType::Text, ['html' => '<p>'.e($intro).'</p>']],
            ]]],
            [BandBackground::Sand, $columns],
        ];
    }

    /**
     * "Wat kost het?": kop + intro, prijsblokken, de vaste kortingsnoten en
     * een knop naar het aanmeldformulier.
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $prices
     * @param  BandBackground  $white  achtergrond van alle prijsbanden (zand na het ontwikkelpad)
     * @return list<array{0: BandBackground, 1: list<list<array{0: BlockType, 1: array<string, mixed>}>>}>
     */
    private function prices(string $intro, array $prices, BandBackground $white = BandBackground::White): array
    {
        return [
            [$white, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Wat kost het?']],
                [BlockType::Text, ['html' => '<p>'.e($intro).'</p>']],
            ]]],
            [$white, array_map(
                fn (array $price): array => [[BlockType::Price, ['label' => $price[0], 'amount' => $price[1], 'body' => $price[2]]]],
                $prices,
            )],
            [$white, [
                [[BlockType::IconText, ['icon' => 'check-circle', 'title' => 'Lid na 1 juli?', 'body' => 'Dan betaal je dat jaar de helft van de contributie.']]],
                [[BlockType::IconText, ['icon' => 'check-circle', 'title' => 'Met het hele gezin?', 'body' => 'Je gezin betaalt samen maximaal €975 contributie en €110 entreegeld.']]],
                [[BlockType::IconText, ['icon' => 'check-circle', 'title' => 'Krap bij kas?', 'body' => 'Via het Volwassenenfonds betaalt de gemeente Gouda €275 van je jaarabonnement.']]],
            ]],
            [$white, [[
                [BlockType::Button, ['label' => 'Welk lidmaatschap past bij mij?', 'href' => '/lid-worden', 'style' => 'secondary']],
            ]]],
        ];
    }

    /** @return array{0: BlockType, 1: array<string, mixed>} */
    private function variant(string $icon, string $title, string $body, ?string $href = null, string $linkLabel = ''): array
    {
        return [BlockType::Card, ['title' => $title, 'body' => $body, 'href' => $href, 'link_label' => $linkLabel, 'variant' => 'compact', 'icon' => $icon]];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $items
     * @return array{0: BandBackground, 1: list<list<array{0: BlockType, 1: array<string, mixed>}>>}
     */
    private function faq(array $items, BandBackground $background = BandBackground::White): array
    {
        return [$background, [[
            [BlockType::Heading, ['level' => 2, 'text' => 'Veelgestelde vragen']],
            [BlockType::Accordion, ['items' => array_map(
                fn (array $item): array => ['question' => $item[0], 'answer' => $item[1]],
                $items,
            )]],
        ]]];
    }

    /** @return array{0: BandBackground, 1: list<list<array{0: BlockType, 1: array<string, mixed>}>>} */
    private function closing(): array
    {
        return [BandBackground::Red, [[
            [BlockType::Heading, ['level' => 2, 'text' => 'Zin om het te proberen?']],
            [BlockType::Text, ['html' => '<p>Vraag een proefles aan, dan nemen we je mee het water op. Of loop gewoon binnen op zaterdag- of zondagochtend.</p>']],
            [BlockType::Button, ['label' => 'Proefles aanvragen', 'href' => '/contact', 'style' => 'wit', 'label2' => 'Bel/mail mij terug', 'href2' => '/contact', 'style2' => 'contact']],
        ]]];
    }
}
