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
        };
    }
}
