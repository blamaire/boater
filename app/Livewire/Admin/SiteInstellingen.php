<?php

namespace App\Livewire\Admin;

use App\Models\Page;
use App\Models\SiteSettings;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer-UI voor de site-brede instellingen (footer-contactblok, sociale
 * media, verwijzingen naar CMS-pagina's voor disclaimer/AVG).
 */
#[Layout('layouts.app')]
class SiteInstellingen extends Component
{
    public string $contact_name = '';

    public string $contact_address = '';

    public string $contact_email = '';

    public string $contact_phone = '';

    public string $facebook_url = '';

    public string $instagram_url = '';

    public string $youtube_url = '';

    public ?int $privacy_page_id = null;

    public ?int $terms_page_id = null;

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $settings = SiteSettings::current();

        $this->contact_name = (string) ($settings->contact_name ?? '');
        $this->contact_address = (string) ($settings->contact_address ?? '');
        $this->contact_email = (string) ($settings->contact_email ?? '');
        $this->contact_phone = (string) ($settings->contact_phone ?? '');
        $this->facebook_url = (string) ($settings->facebook_url ?? '');
        $this->instagram_url = (string) ($settings->instagram_url ?? '');
        $this->youtube_url = (string) ($settings->youtube_url ?? '');
        $this->privacy_page_id = $settings->privacy_page_id;
        $this->terms_page_id = $settings->terms_page_id;
    }

    public function save(AuditLogger $audit): void
    {
        $this->validate([
            'contact_name' => 'nullable|string|max:200',
            'contact_address' => 'nullable|string|max:500',
            'contact_email' => 'nullable|email|max:200',
            'contact_phone' => 'nullable|string|max:50',
            'facebook_url' => 'nullable|url|max:500',
            'instagram_url' => 'nullable|url|max:500',
            'youtube_url' => 'nullable|url|max:500',
            'privacy_page_id' => 'nullable|integer|exists:pages,id',
            'terms_page_id' => 'nullable|integer|exists:pages,id',
        ]);

        $settings = SiteSettings::current();

        $before = $settings->only($settings->getFillable());

        DB::transaction(function () use ($settings, $before, $audit) {
            $settings->fill([
                'contact_name' => $this->emptyToNull($this->contact_name),
                'contact_address' => $this->emptyToNull($this->contact_address),
                'contact_email' => $this->emptyToNull($this->contact_email),
                'contact_phone' => $this->emptyToNull($this->contact_phone),
                'facebook_url' => $this->emptyToNull($this->facebook_url),
                'instagram_url' => $this->emptyToNull($this->instagram_url),
                'youtube_url' => $this->emptyToNull($this->youtube_url),
                'privacy_page_id' => $this->privacy_page_id,
                'terms_page_id' => $this->terms_page_id,
            ]);
            $settings->save();

            $audit->log(
                'site_settings.updated',
                $settings,
                before: $before,
                after: $settings->only($settings->getFillable()),
            );
        });

        $this->statusMessage = 'Site-instellingen opgeslagen.';
    }

    private function emptyToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public function render(): View
    {
        $pages = Page::query()->orderBy('title')->get(['id', 'title', 'slug']);

        return view('livewire.admin.site-instellingen', ['pages' => $pages]);
    }
}
