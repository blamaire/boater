<?php

namespace App\Http\Controllers;

use App\Models\CommunicationPreference;
use App\Models\Person;
use App\Services\Audit\AuditLogger;
use Illuminate\View\View;

/**
 * Publieke, ondertekende afmeldlink onderaan redactionele mail (§24.3) —
 * zelfde signed-route-patroon als `MediaDownloadController`. Geen inlog
 * nodig: de handtekening in de URL zelf autoriseert de actie.
 */
class CommunicationPreferenceController extends Controller
{
    public function unsubscribe(Person $person, string $category, AuditLogger $audit): View
    {
        $preference = CommunicationPreference::query()->updateOrCreate(
            ['person_id' => $person->id, 'category' => $category],
            ['opted_in' => false],
        );

        $audit->log('communication_preference.unsubscribed', $preference, after: [
            'category' => $category,
            'opted_in' => false,
        ]);

        return view('communication.unsubscribed', ['categoryLabel' => $this->categoryLabel($category)]);
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'nieuwsbrief' => 'nieuwsbrieven',
            default => 'dit soort e-mails',
        };
    }
}
