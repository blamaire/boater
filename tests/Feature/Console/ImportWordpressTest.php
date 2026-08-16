<?php

use App\Enums\WordpressContentType;
use App\Enums\WordpressImportStatus;
use App\Models\WordpressImportItem;
use App\Models\WordpressImportMediaItem;

function wxrFixture(string $pageTitle = 'Over ons', string $postTitle = 'Wedstrijdverslag'): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:wp="http://wordpress.org/export/1.2/"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/">
<channel>
    <title>RZVG</title>
    <item>
        <title>{$pageTitle}</title>
        <pubDate>Mon, 01 Jun 2020 10:00:00 +0000</pubDate>
        <content:encoded><![CDATA[<p>Dit is de over-ons-pagina.</p>]]></content:encoded>
        <excerpt:encoded><![CDATA[]]></excerpt:encoded>
        <wp:post_id>101</wp:post_id>
        <wp:post_date>2020-06-01 10:00:00</wp:post_date>
        <wp:post_name>over-ons</wp:post_name>
        <wp:status>publish</wp:status>
        <wp:post_type>page</wp:post_type>
        <wp:post_parent>100</wp:post_parent>
    </item>
    <item>
        <title>{$postTitle}</title>
        <pubDate>Tue, 15 Jul 2021 09:30:00 +0000</pubDate>
        <content:encoded><![CDATA[<p>Uitslag van de wedstrijd.</p>]]></content:encoded>
        <excerpt:encoded><![CDATA[Korte samenvatting]]></excerpt:encoded>
        <wp:post_id>202</wp:post_id>
        <wp:post_date>2021-07-15 09:30:00</wp:post_date>
        <wp:post_name>wedstrijdverslag</wp:post_name>
        <wp:status>publish</wp:status>
        <wp:post_type>post</wp:post_type>
        <category domain="category" nicename="nieuws"><![CDATA[Nieuws]]></category>
        <category domain="post_tag" nicename="uitslag"><![CDATA[Uitslag]]></category>
    </item>
    <item>
        <title>bijlage.pdf</title>
        <pubDate>Tue, 15 Jul 2021 09:30:00 +0000</pubDate>
        <content:encoded><![CDATA[]]></content:encoded>
        <excerpt:encoded><![CDATA[]]></excerpt:encoded>
        <wp:post_id>303</wp:post_id>
        <wp:post_date>2021-07-15 09:30:00</wp:post_date>
        <wp:post_name>bijlage-pdf</wp:post_name>
        <wp:status>inherit</wp:status>
        <wp:post_type>attachment</wp:post_type>
        <wp:post_parent>202</wp:post_parent>
        <wp:attachment_url>https://oud.rzvg.nl/wp-content/uploads/2021/07/bijlage.pdf</wp:attachment_url>
        <wp:post_mime_type>application/pdf</wp:post_mime_type>
    </item>
    <item>
        <title>zwerfbestand.jpg</title>
        <pubDate>Tue, 15 Jul 2021 09:30:00 +0000</pubDate>
        <content:encoded><![CDATA[]]></content:encoded>
        <excerpt:encoded><![CDATA[]]></excerpt:encoded>
        <wp:post_id>305</wp:post_id>
        <wp:post_date>2021-07-15 09:30:00</wp:post_date>
        <wp:post_name>zwerfbestand-jpg</wp:post_name>
        <wp:status>inherit</wp:status>
        <wp:post_type>attachment</wp:post_type>
        <wp:post_parent>0</wp:post_parent>
        <wp:attachment_url>https://oud.rzvg.nl/wp-content/uploads/2021/07/zwerfbestand.jpg</wp:attachment_url>
        <wp:post_mime_type>image/jpeg</wp:post_mime_type>
    </item>
    <item>
        <title>Verwijderd concept</title>
        <pubDate>Wed, 01 Jan 2020 00:00:00 +0000</pubDate>
        <content:encoded><![CDATA[<p>weg</p>]]></content:encoded>
        <excerpt:encoded><![CDATA[]]></excerpt:encoded>
        <wp:post_id>404</wp:post_id>
        <wp:post_date>2020-01-01 00:00:00</wp:post_date>
        <wp:post_name>verwijderd-concept</wp:post_name>
        <wp:status>trash</wp:status>
        <wp:post_type>post</wp:post_type>
    </item>
</channel>
</rss>
XML;
}

function writeWxrFixture(string $xml): string
{
    $path = tempnam(sys_get_temp_dir(), 'wxr');
    if ($path === false) {
        throw new RuntimeException('Kon geen tijdelijk bestand aanmaken.');
    }
    file_put_contents($path, $xml);

    return $path;
}

it('importeert pagina\'s en berichten en slaat prullenbak/andere types over', function () {
    $path = writeWxrFixture(wxrFixture());

    $this->artisan('rzvg:import-wordpress', ['file' => $path])->assertExitCode(0);

    expect(WordpressImportItem::count())->toBe(2);

    $page = WordpressImportItem::where('wordpress_id', 101)->firstOrFail();
    expect($page->wordpress_type)->toBe(WordpressContentType::Page)
        ->and($page->title)->toBe('Over ons')
        ->and($page->content_html)->toBe('<p>Dit is de over-ons-pagina.</p>')
        ->and($page->wordpress_published_at)->not->toBeNull()
        ->and($page->wordpress_published_at->format('Y-m-d'))->toBe('2020-06-01')
        ->and($page->wordpress_parent_id)->toBe(100);

    $post = WordpressImportItem::where('wordpress_id', 202)->firstOrFail();
    expect($post->wordpress_type)->toBe(WordpressContentType::Post)
        ->and($post->raw_meta['categories'])->toContain('Nieuws')
        ->and($post->raw_meta['tags'])->toContain('Uitslag')
        ->and($post->wordpress_parent_id)->toBeNull();

    $media = WordpressImportMediaItem::where('wordpress_id', 303)->firstOrFail();
    expect($media->wordpress_import_item_id)->toBe($post->id)
        ->and($media->url)->toBe('https://oud.rzvg.nl/wp-content/uploads/2021/07/bijlage.pdf')
        ->and($media->mime_type)->toBe('application/pdf')
        ->and($media->selected)->toBeNull();

    expect(WordpressImportMediaItem::where('wordpress_id', 305)->exists())->toBeFalse();
});

it('maakt geen duplicaten aan bij een her-run van dezelfde export', function () {
    $path = writeWxrFixture(wxrFixture());

    $this->artisan('rzvg:import-wordpress', ['file' => $path])->assertExitCode(0);
    $this->artisan('rzvg:import-wordpress', ['file' => $path])->assertExitCode(0);

    expect(WordpressImportItem::count())->toBe(2);

    $page = WordpressImportItem::where('wordpress_id', 101)->firstOrFail();
    expect($page->title)->toBe('Over ons');
});

it('overschrijft een al overgenomen item niet, maar wel een gearchiveerd item', function () {
    $path = writeWxrFixture(wxrFixture());
    $this->artisan('rzvg:import-wordpress', ['file' => $path])->assertExitCode(0);

    WordpressImportItem::where('wordpress_id', 101)->update(['status' => WordpressImportStatus::Imported]);
    WordpressImportItem::where('wordpress_id', 202)->update(['status' => WordpressImportStatus::Archived]);

    $updatedPath = writeWxrFixture(wxrFixture(pageTitle: 'Over ons (bijgewerkt)', postTitle: 'Wedstrijdverslag (bijgewerkt)'));
    $this->artisan('rzvg:import-wordpress', ['file' => $updatedPath])->assertExitCode(0);

    expect(WordpressImportItem::count())->toBe(2);

    $page = WordpressImportItem::where('wordpress_id', 101)->firstOrFail();
    expect($page->title)->toBe('Over ons');

    $post = WordpressImportItem::where('wordpress_id', 202)->firstOrFail();
    expect($post->title)->toBe('Wedstrijdverslag (bijgewerkt)');
});

it('behoudt de selectie en gedownloade status van een bijlage bij een her-run', function () {
    $path = writeWxrFixture(wxrFixture());
    $this->artisan('rzvg:import-wordpress', ['file' => $path])->assertExitCode(0);

    $media = WordpressImportMediaItem::where('wordpress_id', 303)->firstOrFail();
    $media->update(['selected' => false, 'media_asset_id' => null, 'download_error' => 'eerder mislukt']);

    $this->artisan('rzvg:import-wordpress', ['file' => $path])->assertExitCode(0);

    $media->refresh();
    expect($media->selected)->toBeFalse()
        ->and($media->download_error)->toBe('eerder mislukt');
});

it('faalt netjes bij een niet-bestaand bestand', function () {
    $this->artisan('rzvg:import-wordpress', ['file' => '/tmp/bestaat-niet.xml'])->assertExitCode(1);
});
