<?php

use App\Enums\MediaType;
use App\Enums\PageVisibility;
use App\Models\MediaAsset;
use App\Models\Person;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

beforeEach(function () {
    Storage::fake('media');
});

it('stores an image with dimensions and a thumbnail', function () {
    $file = UploadedFile::fake()->image('foto.jpg', 800, 600);

    $person = Person::create(['first_name' => 'U', 'last_name' => 'Ploader']);
    $asset = app(MediaUploadService::class)->store(
        file: $file,
        uploadedBy: $person,
        alt: 'Test-foto',
        tagNames: ['activiteit', 'foto'],
    );

    expect($asset->type)->toBe(MediaType::Image)
        ->and($asset->alt)->toBe('Test-foto')
        ->and($asset->dimensions)->toBe(['width' => 800, 'height' => 600])
        ->and($asset->uploaded_by_person_id)->toBe($person->id)
        ->and($asset->tags)->toHaveCount(2);

    Storage::disk('media')->assertExists($asset->path);
});

it('rejects a file that is too large', function () {
    $file = UploadedFile::fake()->create('groot.pdf', 15 * 1024, 'application/pdf');

    expect(fn () => app(MediaUploadService::class)->store($file))
        ->toThrow(FileException::class, 'groter dan 10 MB');
});

it('rejects a disallowed mime type', function () {
    $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

    expect(fn () => app(MediaUploadService::class)->store($file))
        ->toThrow(FileException::class, 'wordt niet ondersteund');
});

it('accepts a whitelisted document type', function () {
    $file = UploadedFile::fake()->create('notulen.pdf', 100, 'application/pdf');

    $asset = app(MediaUploadService::class)->store($file);

    expect($asset->type)->toBe(MediaType::Document)
        ->and($asset->thumbnail_path)->toBeNull();
});

it('creates tags on the fly and syncs them', function () {
    $file = UploadedFile::fake()->image('a.jpg');
    $asset = app(MediaUploadService::class)->store(file: $file, tagNames: ['nieuw', 'anders']);

    expect(MediaAsset::find($asset->id)->tags->pluck('name')->sort()->values()->all())
        ->toBe(['anders', 'nieuw']);
});

it('marks assets private and requires signed URL', function () {
    $file = UploadedFile::fake()->image('geheim.png');
    $asset = app(MediaUploadService::class)->store(
        file: $file,
        visibility: PageVisibility::Members,
    );

    expect($asset->isPublic())->toBeFalse();
    expect($asset->displayUrl())->toContain('/media/'.$asset->id.'/download');
    expect($asset->displayUrl())->toContain('signature=');
});
