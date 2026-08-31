<?php

namespace App\Enums;

/**
 * Bloktypes voor een e-mailsjabloon-body (§24, `MessageTemplate.body`) —
 * een eigen, bewust kleinere set dan de CMS-`BlockType` (die leunt op
 * Tailwind-grid/flex/video/embedded Livewire-componenten en is daardoor niet
 * bruikbaar in e-mail). Geen "banden"/kolom-layout: e-mail is van nature
 * één kolom.
 */
enum MessageBlockType: string
{
    case Text = 'tekst';
    case Heading = 'kop';
    case Button = 'knop';
    case Image = 'afbeelding';
    case Divider = 'scheiding';
    case Quote = 'citaat';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Tekst',
            self::Heading => 'Kop',
            self::Button => 'Knop',
            self::Image => 'Afbeelding',
            self::Divider => 'Scheiding',
            self::Quote => 'Citaat',
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
            self::Button => ['label' => '', 'href' => ''],
            self::Image => ['url' => '', 'alt' => ''],
            self::Divider => [],
            self::Quote => ['text' => '', 'source' => ''],
        };
    }
}
