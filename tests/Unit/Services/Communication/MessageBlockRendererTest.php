<?php

use App\Services\Communication\MessageBlockRenderer;

it('rendert een tekst-blok en saneert de vrije HTML', function () {
    $html = app(MessageBlockRenderer::class)->render([
        ['type' => 'tekst', 'content' => ['html' => '<p>Hallo {{naam}}</p><script>alert(1)</script>']],
    ], ['{{naam}}' => 'Jan']);

    expect($html)->toContain('Hallo Jan')
        ->and($html)->not->toContain('<script>');
});

it('rendert een kop-blok op het gekozen niveau', function () {
    $html = app(MessageBlockRenderer::class)->render([
        ['type' => 'kop', 'content' => ['level' => 1, 'text' => '{{titel}}']],
    ], ['{{titel}}' => 'Welkom']);

    expect($html)->toContain('<h1')
        ->and($html)->toContain('Welkom');
});

it('rendert een knop-blok met gesubstitueerde href, HTML-geëscapet', function () {
    $html = app(MessageBlockRenderer::class)->render([
        ['type' => 'knop', 'content' => ['label' => 'Klik <hier>', 'href' => '{{url}}']],
    ], ['{{url}}' => 'https://example.test/x']);

    expect($html)->toContain('href="https://example.test/x"')
        ->and($html)->toContain('Klik &lt;hier&gt;');
});

it('rendert een afbeelding-blok en laat een leeg blok zonder url weg', function () {
    $metUrl = app(MessageBlockRenderer::class)->render([
        ['type' => 'afbeelding', 'content' => ['url' => 'https://example.test/foto.jpg', 'alt' => 'Een foto']],
    ], []);
    $zonderUrl = app(MessageBlockRenderer::class)->render([
        ['type' => 'afbeelding', 'content' => ['url' => '', 'alt' => '']],
    ], []);

    expect($metUrl)->toContain('src="https://example.test/foto.jpg"')
        ->and($metUrl)->toContain('alt="Een foto"')
        ->and($zonderUrl)->not->toContain('<img');
});

it('rendert een scheiding-blok zonder content', function () {
    $html = app(MessageBlockRenderer::class)->render([
        ['type' => 'scheiding', 'content' => []],
    ], []);

    expect($html)->toContain('border-top');
});

it('rendert een citaat-blok met optionele bron', function () {
    $html = app(MessageBlockRenderer::class)->render([
        ['type' => 'citaat', 'content' => ['text' => 'Mooi gezegd', 'source' => 'Iemand']],
    ], []);

    expect($html)->toContain('Mooi gezegd')
        ->and($html)->toContain('Iemand');
});

it('concateneert meerdere blokken in volgorde', function () {
    $html = app(MessageBlockRenderer::class)->render([
        ['type' => 'kop', 'content' => ['level' => 2, 'text' => 'Eerst']],
        ['type' => 'tekst', 'content' => ['html' => '<p>Dan</p>']],
    ], []);

    expect(strpos($html, 'Eerst'))->toBeLessThan(strpos($html, 'Dan'));
});
