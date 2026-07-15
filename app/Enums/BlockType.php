<?php

namespace App\Enums;

enum BlockType: string
{
    case Text = 'tekst';
    case Heading = 'kop';
    case Image = 'afbeelding';
    case Button = 'knop';
    case Card = 'kaart';
    case IconText = 'icoon_tekst';
    case Gallery = 'gallerij';
    case Accordion = 'accordeon';
    case Quote = 'citaat';
    case VideoEmbed = 'video_embed';
    case FileDownload = 'bestand';
    case Divider = 'scheiding';
    case Hero = 'hero';
    case Video = 'video';
    case FeatureSection = 'feature_sectie';
    case Agenda = 'agenda';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Tekst',
            self::Heading => 'Kop',
            self::Image => 'Afbeelding',
            self::Button => 'Knop',
            self::Card => 'Kaart',
            self::IconText => 'Icoon met tekst',
            self::Gallery => 'Gallerij',
            self::Accordion => 'Accordeon',
            self::Quote => 'Citaat',
            self::VideoEmbed => 'Video / embed',
            self::FileDownload => 'Bestand / download',
            self::Divider => 'Scheiding',
            self::Hero => 'Hero (grote foto met tekst)',
            self::Video => 'Video (uit bibliotheek)',
            self::FeatureSection => 'Feature-sectie (foto + tekst + CTA)',
            self::Agenda => 'Agenda (activiteitenlijst)',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultContent(): array
    {
        return match ($this) {
            self::Text => ['html' => ''],
            self::Heading => ['level' => 2, 'text' => ''],
            self::Image => ['url' => '', 'alt' => '', 'caption' => null],
            self::Button => ['label' => '', 'href' => '', 'style' => 'primary'],
            self::Card => ['title' => '', 'body' => '', 'image_url' => null, 'href' => null],
            self::IconText => ['icon' => 'star', 'title' => '', 'body' => ''],
            self::Gallery => ['images' => []],
            self::Accordion => ['items' => []],
            self::Quote => ['text' => '', 'source' => null],
            self::VideoEmbed => ['provider' => 'youtube', 'embed_url' => ''],
            self::FileDownload => ['url' => '', 'label' => '', 'size' => null],
            self::Divider => ['style' => 'line'],
            self::Hero => [
                'media_asset_id' => null,
                'title' => '',
                'subtitle' => '',
                'cta_label' => '',
                'cta_href' => '',
                'cta2_label' => '',
                'cta2_href' => '',
            ],
            self::Video => ['media_asset_id' => null],
            self::FeatureSection => [
                'media_asset_id' => null,
                'title' => '',
                'body' => '',
                'cta_label' => '',
                'cta_href' => '',
                'image_side' => 'left',
            ],
            self::Agenda => [
                'title' => 'Agenda',
                // Voorfilter — laat leeg om geen restrictie te leggen.
                'category_ids' => [],
                // Aantal dagen vooruit; 0 = geen limiet.
                'period_days' => 30,
                // Historie (starts_at < nu) verbergen; standaard aan.
                'hide_history' => true,
                // Max aantal items dat het block toont.
                'limit' => 20,
                // Sta filter-controls in de browser toe (zoek/categorie/hide-history-toggle).
                'allow_user_filter' => true,
            ],
        };
    }
}
