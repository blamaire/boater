<?php

namespace App\Livewire\Admin;

use App\Enums\MediaType;
use App\Enums\PageVisibility;
use App\Models\MediaAsset;
use App\Models\MediaTag;
use App\Services\Media\MediaUploadService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MediaLibrary extends Component
{
    use WithFileUploads;
    use WithPagination;

    public bool $open = false;

    public bool $standalone = false;

    public string $contextId = '';

    public string $search = '';

    public string $typeFilter = '';

    /** @var array<int, int> */
    public array $tagFilter = [];

    public ?TemporaryUploadedFile $uploadFile = null;

    public string $uploadAlt = '';

    public string $uploadTagsInput = '';

    public string $uploadVisibility = 'publiek';

    public ?string $uploadError = null;

    public function mount(): void
    {
        if ($this->standalone) {
            $this->open = true;
        }
    }

    #[On('open-media-library')]
    public function openLibrary(string $contextId = ''): void
    {
        $this->contextId = $contextId;
        $this->open = true;
        $this->uploadError = null;
    }

    public function close(): void
    {
        $this->open = false;
        $this->reset(['uploadFile', 'uploadAlt', 'uploadTagsInput', 'uploadError']);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function upload(MediaUploadService $service): void
    {
        $this->uploadError = null;

        if ($this->uploadFile === null) {
            $this->uploadError = 'Kies eerst een bestand.';

            return;
        }

        try {
            $asset = $service->store(
                file: $this->uploadFile->getPathname() !== ''
                    ? $this->wrapForUploadedFile($this->uploadFile)
                    : throw new \RuntimeException('Ongeldig bestand.'),
                uploadedBy: auth()->user()?->person,
                visibility: PageVisibility::from($this->uploadVisibility),
                alt: $this->uploadAlt ?: null,
                tagNames: $this->parseTags($this->uploadTagsInput),
            );

            $this->dispatch('media-uploaded', assetId: $asset->id);
        } catch (\Throwable $e) {
            $this->uploadError = $e->getMessage();

            return;
        }

        $this->reset(['uploadFile', 'uploadAlt', 'uploadTagsInput']);
        $this->resetPage();
    }

    public function selectAsset(int $assetId): void
    {
        $asset = MediaAsset::query()->findOrFail($assetId);

        $this->dispatch('media-selected',
            contextId: $this->contextId,
            assetId: $asset->id,
            url: $asset->displayUrl(),
            thumbnailUrl: $asset->thumbnailUrl(),
            alt: $asset->alt,
            originalName: $asset->original_name,
        );

        $this->close();
    }

    public function deleteAsset(int $assetId): void
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->can('media.delete'), 403);

        $asset = MediaAsset::query()->findOrFail($assetId);
        $disk = Storage::disk($asset->disk);
        $disk->delete($asset->path);
        if ($asset->thumbnail_path !== null) {
            $disk->delete($asset->thumbnail_path);
        }
        $asset->delete();
    }

    public function render(): View
    {
        return view('livewire.admin.media-library', [
            'assets' => $this->assets(),
            'allTags' => MediaTag::query()->orderBy('name')->get(),
            'types' => MediaType::cases(),
            'visibilities' => PageVisibility::cases(),
        ]);
    }

    private function assets(): LengthAwarePaginator
    {
        $query = MediaAsset::query()
            ->with(['tags'])
            ->orderByDesc('id');

        if ($this->search !== '') {
            $needle = '%'.$this->search.'%';
            $query->where(function ($q) use ($needle) {
                $q->where('original_name', 'like', $needle)
                    ->orWhere('alt', 'like', $needle);
            });
        }

        if ($this->typeFilter !== '') {
            $query->where('type', $this->typeFilter);
        }

        if ($this->tagFilter !== []) {
            $query->whereHas('tags', function ($q) {
                $q->whereIn('media_tags.id', $this->tagFilter);
            });
        }

        return $query->paginate(20);
    }

    /**
     * @return array<int, string>
     */
    private function parseTags(string $raw): array
    {
        return collect(preg_split('/[,;]/', $raw) ?: [])
            ->map(fn ($s) => trim((string) $s))
            ->filter()
            ->values()
            ->all();
    }

    private function wrapForUploadedFile(TemporaryUploadedFile $temp): UploadedFile
    {
        return new UploadedFile(
            path: $temp->getPathname(),
            originalName: $temp->getClientOriginalName(),
            mimeType: $temp->getMimeType(),
            error: null,
            test: true,
        );
    }
}
