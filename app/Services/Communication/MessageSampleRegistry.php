<?php

namespace App\Services\Communication;

use App\Enums\EnrollmentStatus;
use App\Models\Activity;
use App\Models\ContactRequest;
use App\Models\DamageReport;
use App\Models\Enrollment;
use App\Models\Invoice;

/**
 * Per sjabloon-sleutel: is er een bestaand record dat als representatief
 * voorbeeld kan dienen voor een testmail (§24, Fase B2/C)? Alleen sleutels met
 * een reëel, doorzoekbaar model — de overige (`password_reset`,
 * `email_verification`, `account_invitation`, `membership_application_received`)
 * draaien om een eenmalig token/formulier-invoer zonder zinvol na te slaan
 * voorbeeld, en krijgen daarom geen testmail-actie in de UI.
 */
class MessageSampleRegistry
{
    /** @var array<int, string> */
    private const SUPPORTED_KEYS = [
        'damage_report_submitted',
        'contact_request_submitted',
        'activity_changed',
        'activity_enrollment_changed',
        'enrollment_confirmed',
        'enrollment_waitlisted',
        'enrollment_waitlist_promoted',
        'invoice_created',
    ];

    public function supports(string $templateKey): bool
    {
        return in_array($templateKey, self::SUPPORTED_KEYS, true);
    }

    /**
     * Null betekent: sleutel wordt ondersteund, maar er bestaat (nog) geen
     * record om als voorbeeld te gebruiken.
     *
     * @return array<string, string>|null
     */
    public function sampleVariables(string $templateKey): ?array
    {
        return match ($templateKey) {
            'damage_report_submitted' => $this->fromLatestReport(),
            'contact_request_submitted' => $this->fromLatestContactRequest(),
            'activity_changed' => $this->fromLatestActivity(),
            'activity_enrollment_changed' => $this->fromLatestEnrollmentAsActivityChange(),
            'enrollment_confirmed' => $this->fromLatestEnrollment(EnrollmentStatus::Enrolled),
            'enrollment_waitlisted' => $this->fromLatestEnrollment(EnrollmentStatus::Waitlist),
            'enrollment_waitlist_promoted' => $this->fromLatestEnrollment(EnrollmentStatus::Enrolled),
            'invoice_created' => $this->fromLatestInvoice(),
            default => null,
        };
    }

    /** @return array<string, string>|null */
    private function fromLatestReport(): ?array
    {
        $report = DamageReport::query()->latest('id')->first();

        return $report !== null ? MessageVariableBuilders::damageReportSubmitted($report) : null;
    }

    /** @return array<string, string>|null */
    private function fromLatestContactRequest(): ?array
    {
        $request = ContactRequest::query()->latest('id')->first();

        return $request !== null ? MessageVariableBuilders::contactRequestSubmitted($request) : null;
    }

    /** @return array<string, string>|null */
    private function fromLatestActivity(): ?array
    {
        $activity = Activity::query()->latest('id')->first();

        return $activity !== null ? MessageVariableBuilders::activityChanged($activity) : null;
    }

    /** @return array<string, string>|null */
    private function fromLatestEnrollmentAsActivityChange(): ?array
    {
        $enrollment = Enrollment::query()->latest('id')->first();

        return $enrollment !== null
            ? MessageVariableBuilders::activityEnrollmentChanged($enrollment->activity, $enrollment->person, true)
            : null;
    }

    /** @return array<string, string>|null */
    private function fromLatestEnrollment(EnrollmentStatus $status): ?array
    {
        $enrollment = Enrollment::query()->where('status', $status->value)->latest('id')->first();

        return $enrollment !== null ? MessageVariableBuilders::enrollmentConfirmedOrWaitlisted($enrollment) : null;
    }

    /** @return array<string, string>|null */
    private function fromLatestInvoice(): ?array
    {
        $invoice = Invoice::query()->latest('id')->first();

        return $invoice !== null ? MessageVariableBuilders::invoiceCreated($invoice) : null;
    }
}
