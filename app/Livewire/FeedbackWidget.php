<?php

namespace App\Livewire;

use App\Enums\FeedbackCategory;
use App\Enums\FeedbackStatus;
use App\Models\Feedback;
use App\Services\Audit\AuditLogger;
use App\Services\System\AppVersionResolver;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Terugkoppelknopje, overal beschikbaar voor ingelogde gebruikers (portaal,
 * beheer, en publieke pagina's — zie `layouts/app.blade.php` en
 * `components/public-layout.blade.php`). Legt naast het bericht ook de
 * context vast: URL, applicatieversie, en — indien meegegeven vanaf een
 * publieke CMS-pagina — welke `Page`/`PageVersion` daadwerkelijk werd
 * bekeken (`$pageId`/`$pageVersionId` blijven `null` op portaal-/
 * beheerschermen, die zijn geen CMS-pagina's).
 */
class FeedbackWidget extends Component
{
    public ?int $pageId = null;

    public ?int $pageVersionId = null;

    public string $category = '';

    public string $message = '';

    public bool $submitted = false;

    public function submit(AuditLogger $audit, AppVersionResolver $versionResolver): void
    {
        $this->validate([
            'category' => ['required', Rule::enum(FeedbackCategory::class)],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $person = auth()->user()?->person;
        if ($person === null) {
            $this->addError('message', 'Je account is nog niet gekoppeld aan een persoon. Neem contact op met de beheerder.');

            return;
        }

        $feedback = Feedback::create([
            'person_id' => $person->id,
            'category' => $this->category,
            'message' => $this->message,
            'url' => request()->fullUrl(),
            'app_version' => $versionResolver->current(),
            'page_id' => $this->pageId,
            'page_version_id' => $this->pageVersionId,
            'status' => FeedbackStatus::Nieuw,
        ]);

        $audit->log('feedback.submitted', $feedback, after: [
            'category' => $feedback->category->value,
            'url' => $feedback->url,
            'page_id' => $feedback->page_id,
        ]);

        $this->submitted = true;
    }

    public function close(): void
    {
        $this->reset(['category', 'message', 'submitted']);
    }

    public function render(): View
    {
        return view('livewire.feedback-widget', [
            'categories' => FeedbackCategory::cases(),
        ]);
    }
}
