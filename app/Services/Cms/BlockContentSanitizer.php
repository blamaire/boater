<?php

namespace App\Services\Cms;

use App\Enums\BlockType;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Saniteert de vrije-HTML-velden van bloktypes die opmaak toestaan (tekst,
 * feature-sectie) — voorkomt stored XSS via het "Broncode"-veld, directe
 * JSON-import, of de handmatige-samenvoeg-functie van de conflictresolver,
 * ongeacht of client-side (Trix) sanering is gepasseerd. `class` op `<img>`
 * gaat door {@see WordpressAlignmentClassSanitizer} — nooit de rauwe waarde,
 * alleen onze eigen vaste align-classes (§25, WordPress-import).
 */
class BlockContentSanitizer
{
    private readonly HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('div')
            ->allowElement('span')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('del')
            ->allowElement('blockquote')
            ->allowElement('pre')
            ->allowElement('code')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('figure')
            ->allowElement('figcaption')
            ->allowElement('a', ['href', 'title'])
            ->allowElement('img', ['src', 'alt', 'title', 'class'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowLinkHosts(null)
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->withAttributeSanitizer(new WordpressAlignmentClassSanitizer);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    /**
     * Saniteert los HTML (bv. de Trix-omschrijving van een activiteit) —
     * zelfde regels als de CMS-tekstblokken, buiten de blok-content-array om.
     */
    public function sanitizeHtml(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function sanitize(BlockType $type, array $content): array
    {
        return match ($type) {
            BlockType::Text => $this->sanitizeKey($content, 'html'),
            BlockType::FeatureSection => $this->sanitizeKey($content, 'body'),
            default => $content,
        };
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function sanitizeKey(array $content, string $key): array
    {
        if (isset($content[$key]) && is_string($content[$key])) {
            $content[$key] = $this->sanitizer->sanitize($content[$key]);
        }

        return $content;
    }
}
