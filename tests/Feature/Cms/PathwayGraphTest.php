<?php

use App\Enums\BlockType;
use App\Models\Block;
use App\Services\Cms\PathwayGraph;

/**
 * Ontwikkelpad-blok (BlockType::Pathway): parsen, niveaus, cyclusvalidatie
 * en SVG-layout.
 */
function pathwayContent(): array
{
    return (new PathwayGraph)->parse(
        "Start | begin hier | /pagina/jeugd\nMidden\nLang pad\nEinde | klaar",
        "Start -> Midden\nMidden -> Lang pad\nStart -> Einde\nLang pad -> Einde",
    );
}

it('parst stappen en pijlen naar nodes en edges', function () {
    $content = pathwayContent();

    expect($content['nodes'][0])->toBe(['key' => 'start', 'label' => 'Start', 'sub' => 'begin hier', 'href' => '/pagina/jeugd'])
        ->and($content['nodes'][1]['sub'])->toBeNull()
        ->and($content['edges'])->toContain(['from' => 'midden', 'to' => 'lang-pad']);
});

it('bepaalt het niveau als langste pad vanaf een bron', function () {
    $levels = (new PathwayGraph)->levels(pathwayContent());

    expect($levels)->toBe(['start' => 0, 'midden' => 1, 'lang-pad' => 2, 'einde' => 3]);
});

it('weigert een cyclus', function () {
    (new PathwayGraph)->parse("A\nB\nC", "A -> B\nB -> C\nC -> A");
})->throws(InvalidArgumentException::class, 'cirkel');

it('weigert een onbekend label in een pijl', function () {
    (new PathwayGraph)->parse("A\nB", 'A -> Onbekend');
})->throws(InvalidArgumentException::class, 'Onbekend label');

it('weigert een onveilige link', function () {
    (new PathwayGraph)->parse('A | uitleg | javascript:alert(1)', '');
})->throws(InvalidArgumentException::class, 'link');

it('zet kolommen per niveau op desktop en alles onder elkaar op mobiel', function () {
    $graph = new PathwayGraph;
    $desktop = $graph->layout(pathwayContent(), true);
    $mobile = $graph->layout(pathwayContent(), false);

    $xs = collect($desktop['nodes'])->pluck('x', 'key');
    expect($xs['start'])->toBeLessThan($xs['midden'])
        ->and($xs['midden'])->toBeLessThan($xs['einde'])
        ->and($desktop['edges'])->toHaveCount(4);

    $ys = collect($mobile['nodes'])->pluck('y', 'key');
    expect(collect($mobile['nodes'])->pluck('x')->unique())->toHaveCount(1)
        ->and($ys['start'])->toBeLessThan($ys['midden'])
        ->and($ys['midden'])->toBeLessThan($ys['einde']);
});

it('rendert het blok als twee SVG\'s met tekstversie', function () {
    $block = (new Block)->forceFill([
        'type' => BlockType::Pathway,
        'content' => pathwayContent(),
    ]);

    $html = view('cms.blocks.preview', ['block' => $block])->render();

    expect(substr_count($html, '<svg'))->toBe(2)
        ->and($html)->toContain('role="img"')
        ->and($html)->toContain('href="/pagina/jeugd"')
        ->and($html)->toContain('sr-only')
        ->and($html)->toContain('Daarna: Midden, Einde.');
});
