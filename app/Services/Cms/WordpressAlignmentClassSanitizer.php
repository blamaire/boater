<?php

namespace App\Services\Cms;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Vertaalt WordPress' align-classes (`alignleft`/`alignright`/`aligncenter`)
 * op `<img>` naar onze eigen, vaste CSS-classes (§25, WordPress-import).
 * Laat nooit de rauwe class-waarde door — alles wat niet op een van de drie
 * bekende tokens matcht wordt verwijderd. Zo kan een `class`-attribuut nooit
 * een willekeurige (mogelijk misleidende) naam uit geïmporteerde of
 * handmatig ingevoerde HTML meesmokkelen.
 */
class WordpressAlignmentClassSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return ['img'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['class'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return match (true) {
            (bool) preg_match('/\balignleft\b/', $value) => 'wp-align-left',
            (bool) preg_match('/\balignright\b/', $value) => 'wp-align-right',
            (bool) preg_match('/\baligncenter\b/', $value) => 'wp-align-center',
            default => null,
        };
    }
}
