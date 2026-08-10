<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageVersionStatus;
use App\Models\Band;
use App\Models\Block;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;

beforeEach(function () {
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
    $this->page = Page::create(['slug' => 'p', 'title' => 'P', 'template_id' => $this->template->id]);
});

function withIdenticalBand(PageVersion $version): void
{
    $band = Band::create([
        'page_version_id' => $version->id,
        'zone' => 'hoofd',
        'layout' => BandLayout::OneColumn,
        'sort_order' => 0,
    ]);
    Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Text,
        'content' => ['html' => '<p>a</p>'],
    ]);
}

it('detecteert een niet-gepubliceerde wijziging bij uitsluitend een gewijzigde meta_description', function () {
    $published = PageVersion::create([
        'page_id' => $this->page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Published,
        'meta_description' => 'oude omschrijving',
    ]);
    withIdenticalBand($published);
    $this->page->update(['published_version_id' => $published->id]);

    $draft = PageVersion::create([
        'page_id' => $this->page->id,
        'version_no' => 2,
        'status' => PageVersionStatus::Draft,
        'meta_description' => 'nieuwe omschrijving',
    ]);
    withIdenticalBand($draft);

    expect($draft->hasUnpublishedChanges())->toBeTrue();
});

it('detecteert een niet-gepubliceerde wijziging bij uitsluitend een gewijzigde og_image_media_asset_id', function () {
    $asset = MediaAsset::create([
        'disk' => 'public',
        'path' => 'og.jpg',
        'original_name' => 'og.jpg',
        'mime_type' => 'image/jpeg',
        'type' => 'afbeelding',
        'file_size' => 100,
        'visibility' => 'publiek',
    ]);

    $published = PageVersion::create([
        'page_id' => $this->page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Published,
    ]);
    withIdenticalBand($published);
    $this->page->update(['published_version_id' => $published->id]);

    $draft = PageVersion::create([
        'page_id' => $this->page->id,
        'version_no' => 2,
        'status' => PageVersionStatus::Draft,
        'og_image_media_asset_id' => $asset->id,
    ]);
    withIdenticalBand($draft);

    expect($draft->hasUnpublishedChanges())->toBeTrue();
});

it('meldt geen niet-gepubliceerde wijziging bij een identieke kloon inclusief meta-velden', function () {
    $asset = MediaAsset::create([
        'disk' => 'public',
        'path' => 'og.jpg',
        'original_name' => 'og.jpg',
        'mime_type' => 'image/jpeg',
        'type' => 'afbeelding',
        'file_size' => 100,
        'visibility' => 'publiek',
    ]);

    $published = PageVersion::create([
        'page_id' => $this->page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Published,
        'meta_description' => 'zelfde',
        'og_title' => 'zelfde titel',
        'og_description' => 'zelfde omschrijving',
        'og_image_media_asset_id' => $asset->id,
    ]);
    withIdenticalBand($published);
    $this->page->update(['published_version_id' => $published->id]);

    $draft = PageVersion::create([
        'page_id' => $this->page->id,
        'version_no' => 2,
        'status' => PageVersionStatus::Draft,
        'meta_description' => 'zelfde',
        'og_title' => 'zelfde titel',
        'og_description' => 'zelfde omschrijving',
        'og_image_media_asset_id' => $asset->id,
    ]);
    withIdenticalBand($draft);

    expect($draft->hasUnpublishedChanges())->toBeFalse();
});
