<?php

namespace App\Services\Communication;

use App\Enums\MessageBlockType;
use App\Services\Cms\BlockContentSanitizer;

/**
 * Rendert de block-array van `MessageTemplate.body` (§24) naar e-mail-veilige
 * HTML: substitueert `{{variabele}}`-tokens per veld, saneert de vrije HTML
 * van een Tekst-block, en rendert elk block via een eigen Blade-partial
 * (`resources/views/mail/blocks/*.blade.php` — inline styles, geen
 * Tailwind/grid/flex, e-mailclients ondersteunen dat niet betrouwbaar).
 */
class MessageBlockRenderer
{
    public function __construct(private readonly BlockContentSanitizer $sanitizer) {}

    /**
     * @param  array<int, array{type: string, content: array<string, mixed>}>  $blocks
     * @param  array<string, string>  $variables
     */
    public function render(array $blocks, array $variables): string
    {
        $html = '';
        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block, $variables);
        }

        return $html;
    }

    /**
     * @param  array{type: string, content: array<string, mixed>}  $block
     * @param  array<string, string>  $variables
     */
    private function renderBlock(array $block, array $variables): string
    {
        $type = MessageBlockType::from($block['type']);
        $content = $this->substitute($block['content'], $variables);

        if ($type === MessageBlockType::Text) {
            $content['html'] = $this->sanitizer->sanitizeHtml((string) ($content['html'] ?? ''));
        }

        return view('mail.blocks.'.$type->value, ['content' => $content])->render();
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, string>  $variables
     * @return array<string, mixed>
     */
    private function substitute(array $content, array $variables): array
    {
        foreach ($content as $key => $value) {
            if (is_string($value)) {
                $content[$key] = strtr($value, $variables);
            }
        }

        return $content;
    }
}
