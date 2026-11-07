<?php

namespace Database\Seeders;

use App\Enums\BandBackground;
use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Http\Controllers\PublicPageController;
use App\Models\Band;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Zaait de home-systeempagina (§CMS: type=system, slug=home, root-level).
 * Deze pagina wordt op `/` gerenderd door {@see PublicPageController::home()}
 * en is bewust niet verwijderbaar via de admin-UI.
 *
 * De inhoud volgt het wervende homepage-ontwerp: per sport een ingang voor
 * wie al weet wat hij wil, een tweede rij voor jeugd/roeivalidatie/twijfelaars,
 * "Zo begin je", de tarieven, ledenverhalen, open activiteiten en een
 * afsluitende oproep. Placeholders tussen [blokhaken] vult een redacteur in
 * via de paginabewerker; foto's kiest hij uit de mediabibliotheek.
 *
 * Was oorspronkelijk een migratie, maar die kon stilletjes overgeslagen worden
 * als hij liep vóór `TemplateSeeder`. In een seeder is de volgorde expliciet
 * (na `TemplateSeeder`) en 100% herhaalbaar.
 */
class HomeSystemPageSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: als hij al bestaat, niets doen.
        $exists = Page::query()
            ->whereNull('parent_id')
            ->where('slug', 'home')
            ->where('type', PageType::System->value)
            ->exists();

        if ($exists) {
            return;
        }

        $template = Template::query()->orderBy('id')->first();
        if ($template === null) {
            return;
        }

        DB::transaction(function () use ($template): void {
            $page = Page::query()->create([
                'slug' => 'home',
                'title' => 'Welkom',
                'type' => PageType::System,
                'visibility' => PageVisibility::Public,
                'parent_id' => null,
                'template_id' => $template->id,
            ]);

            $version = PageVersion::query()->create([
                'page_id' => $page->id,
                'version_no' => 1,
                'status' => PageVersionStatus::Published,
                'meta_description' => 'Roeien, zeilen en suppen bij de Roei- en Zeilvereniging Gouda. Leer het van ervaren leden, voor jong en oud.',
            ]);

            foreach ($this->bands() as $sortOrder => [$background, $columns]) {
                $band = Band::query()->create([
                    'page_version_id' => $version->id,
                    'zone' => 'hoofd',
                    'layout' => BandLayout::from(count($columns)),
                    'background' => $background,
                    'sort_order' => $sortOrder,
                ]);

                foreach ($columns as $columnIndex => $blocks) {
                    foreach ($blocks as $blockOrder => [$type, $content]) {
                        Block::query()->create([
                            'band_id' => $band->id,
                            'column_index' => $columnIndex,
                            'sort_order' => $blockOrder,
                            'type' => $type,
                            'content' => array_merge($type->defaultContent(), $content),
                            'visibility' => PageVisibility::Public,
                        ]);
                    }
                }
            }

            $page->update(['published_version_id' => $version->id]);
        });
    }

    /**
     * Banden van boven naar beneden: [achtergrond, [kolom => [[bloktype, inhoud], …]]].
     *
     * @return list<array{0: BandBackground, 1: list<list<array{0: BlockType, 1: array<string, mixed>}>>}>
     */
    private function bands(): array
    {
        $white = BandBackground::White;
        $sand = BandBackground::Sand;

        return [
            // 1. Hero
            [$white, [[
                [BlockType::Hero, [
                    'kicker' => 'Roei- en Zeilvereniging Gouda',
                    'title' => 'Roeien, zeilen en suppen op de Reeuwijkse plassen',
                    'subtitle' => 'Een club voor jong en oud. Je leert het van ervaren leden, in je eigen tempo, en na afloop drink je wat in onze sociëteit.',
                    'cta_label' => 'Proefles aanvragen',
                    'cta_href' => '/contact',
                    'cta2_label' => 'Lid worden',
                    'cta2_href' => '/lid-worden',
                ]],
            ]]],

            // 2. Wat wil jij doen? — wie al weet welke sport
            [$white, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Wat wil jij doen?']],
                [BlockType::Text, ['html' => '<p>Kies je sport. Per sport lees je wat je kunt verwachten, hoe je begint en wat het kost.</p>']],
            ]]],
            [$white, [
                [$this->sportCard('Roeien', 'Recreatief toeren, fit blijven of wedstrijden varen. Je begint met de Basis Roei Opleiding.', '/pagina/roeien', 'Zo begin je met roeien')],
                [$this->sportCard('Zeilen', 'Van zwaardboot tot kielboot. Haal je CWO-diploma bij gecertificeerde instructeurs.', '/pagina/zeilen', 'Zo begin je met zeilen')],
                [$this->sportCard('Suppen', 'Staand op een board over de plassen. Snel onder de knie, en je gaat het water op wanneer het jou uitkomt.', '/pagina/suppen', 'Zo begin je met suppen')],
            ]],

            // 3. Of zoek je iets anders? — andere categorie bezoekers
            [$white, [[
                [BlockType::Heading, ['level' => 3, 'text' => 'Of zoek je iets anders?']],
            ]]],
            [$white, [
                [$this->tile('users', 'Voor kinderen en jongeren', 'Jeugdroeien en jeugdzeilen, van Optimist tot wedstrijdboot, met een eigen jeugdbestuur.', '/pagina/jeugd', 'Informatie voor ouders')],
                [$this->tile('heart', 'Roeivalidatie', 'Roeien met extra begeleiding, afgestemd op wat jouw lichaam aankan.', '/pagina/roeien', 'Meer over roeivalidatie')],
                [$this->tile('compass', 'Weet je het nog niet?', 'Kom op zaterdag- of zondagochtend kijken, of vraag een proefles aan. We helpen je kiezen.', '/contact', 'Proefles aanvragen')],
            ]],

            // 4. Zo begin je
            [$sand, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Zo begin je']],
                [BlockType::Text, ['html' => '<p>Ervaring is niet nodig. Je hebt alleen zwemdiploma A en B nodig.</p>']],
            ]]],
            [$sand, [
                [[BlockType::Step, ['number' => '1', 'title' => 'Kom kijken of probeer het', 'body' => 'Loop op zaterdag- of zondagochtend binnen, of vraag een proefles roeien of zeilen aan.']]],
                [[BlockType::Step, ['number' => '2', 'title' => 'Word lid', 'body' => 'Meld je online aan. Binnen vier weken bedacht? Dan stop je zonder contributie te betalen.']]],
                [[BlockType::Step, ['number' => '3', 'title' => 'Leer het van onze instructeurs', 'body' => 'Je volgt de Basis Roei Opleiding of een zeilopleiding, gegeven door ervaren leden.']]],
            ]],

            // 5. Wat kost het?
            [$white, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Wat kost het?']],
                [BlockType::Text, ['html' => '<p>Contributie per jaar, tarieven 2026. Bij aanmelding betaal je eenmalig €35 entreegeld.</p>']],
            ]]],
            [$white, [
                [[BlockType::Price, ['label' => 'Volwassene', 'amount' => '€390', 'body' => 'Vanaf 21 jaar. Roeien en zeilen, met instructie.']]],
                [[BlockType::Price, ['label' => 'Jeugd', 'amount' => '€165', 'body' => 'Van 12 tot 21 jaar. Voor jongere kinderen is er een aspirantlidmaatschap.']]],
                [[BlockType::Price, ['label' => 'Hele gezin', 'amount' => 'max. €975', 'body' => 'Hoeveel gezinsleden ook. Entreegeld maximaal €110.']]],
            ]],
            [$white, [
                [[BlockType::IconText, ['icon' => 'check-circle', 'title' => 'Lid na 1 juli?', 'body' => 'Dan betaal je dat jaar de helft.']]],
                [[BlockType::IconText, ['icon' => 'check-circle', 'title' => 'Krap bij kas?', 'body' => 'Via het Volwassenenfonds betaalt de gemeente Gouda €275 van je jaarabonnement.']]],
                [[BlockType::IconText, ['icon' => 'check-circle', 'title' => 'Student?', 'body' => 'Er is een speciaal studentlidmaatschap.']]],
            ]],
            [$white, [[
                [BlockType::Button, ['label' => 'Welk lidmaatschap past bij mij?', 'href' => '/lid-worden', 'style' => 'secondary']],
            ]]],

            // 6. Leden aan het woord — een roeier én een zeiler
            [$sand, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Leden aan het woord']],
            ]]],
            [$sand, [
                [[BlockType::Quote, ['style' => 'verhaal', 'text' => '[Citaat van een roeier over de eerste keer op het water, twee of drie zinnen.]', 'source' => '[Naam]', 'role' => 'roeit sinds [jaar]']]],
                [[BlockType::Quote, ['style' => 'verhaal', 'text' => '[Citaat van een zeiler over de opleiding of het clubzeilen, twee of drie zinnen.]', 'source' => '[Naam]', 'role' => 'zeilt sinds [jaar]']]],
            ]],

            // 7. Binnenkort — open activiteiten (de agenda toont gasten alleen publieke activiteiten)
            [$white, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Binnenkort, ook voor niet-leden']],
                [BlockType::Text, ['html' => '<p>Open activiteiten waar je zonder lidmaatschap aan mee kunt doen.</p>']],
                [BlockType::Agenda, ['title' => '', 'period_days' => 60, 'limit' => 3, 'allow_user_filter' => false]],
            ]]],

            // 8. Waarom RZVG?
            [$sand, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Waarom RZVG?']],
            ]]],
            [$sand, [
                [
                    [BlockType::IconText, ['icon' => 'users', 'title' => 'Les van eigen leden', 'body' => 'Ervaren roeiers en gecertificeerde zeilinstructeurs leren het je, als vrijwilliger.']],
                    [BlockType::IconText, ['icon' => 'cup', 'title' => 'Gezellige sociëteit', 'body' => 'Na het varen napraten op het terras, en eetavonden en feesten door het jaar heen.']],
                ],
                [
                    [BlockType::IconText, ['icon' => 'sail', 'title' => 'Een eigen vloot', 'body' => 'Ruim 55 zeilboten en een eigen roeivloot. Je hoeft geen boot te kopen.']],
                    [BlockType::IconText, ['icon' => 'shield-check', 'title' => 'Veilig op het water', 'body' => 'Duidelijke vaarregels, een vertrouwenscontactpersoon en aandacht voor een sociaal veilige club.']],
                ],
            ]],

            // 9. Afsluitende oproep
            [BandBackground::Red, [[
                [BlockType::Heading, ['level' => 2, 'text' => 'Zin om het te proberen?']],
                [BlockType::Text, ['html' => '<p>Vraag een proefles aan, dan nemen we je mee het water op. Of loop gewoon binnen op zaterdag- of zondagochtend.</p>']],
                [BlockType::Button, ['label' => 'Proefles aanvragen', 'href' => '/contact', 'style' => 'wit', 'label2' => 'Bel 06 8340 4501', 'href2' => 'tel:+31683404501', 'style2' => 'wit_omlijnd']],
            ]]],
        ];
    }

    /** @return array{0: BlockType, 1: array<string, mixed>} */
    private function sportCard(string $title, string $body, string $href, string $linkLabel): array
    {
        return [BlockType::Card, ['title' => $title, 'body' => $body, 'href' => $href, 'link_label' => $linkLabel, 'variant' => 'foto']];
    }

    /** @return array{0: BlockType, 1: array<string, mixed>} */
    private function tile(string $icon, string $title, string $body, string $href, string $linkLabel): array
    {
        return [BlockType::Card, ['title' => $title, 'body' => $body, 'href' => $href, 'link_label' => $linkLabel, 'variant' => 'compact', 'icon' => $icon]];
    }
}
