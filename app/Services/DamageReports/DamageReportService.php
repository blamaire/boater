<?php

namespace App\Services\DamageReports;

use App\Enums\DamageReportStatus;
use App\Enums\DamageSeverity;
use App\Enums\ReservableObjectStatus;
use App\Models\DamageReport;
use App\Models\MediaAsset;
use App\Models\Person;
use App\Models\ReservableObject;
use App\Models\Reservation;
use App\Notifications\DamageReportSubmitted;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Schade melden — §22. Loopt bewust niet via de goedkeuringsmotor;
 * §22.2 zegt "een eigen, eenvoudige workflow". Deze service dwingt
 * de twee expliciete gedragsregels uit §22.4 af:
 *
 * 1. `reporter_marked_unusable=true` → object direct op buiten_gebruik
 *    (omkeerbaar door een behandelaar). Bestaande reserveringen op dat
 *    object worden alleen gesignaleerd (in de UI), niet geannuleerd.
 * 2. Toewijzing per objectcategorie via `CATEGORY_RESPONSIBLE`, met
 *    overerving naar bovenliggende categorie als de categorie zelf
 *    geen verantwoordelijke heeft.
 */
class DamageReportService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  Collection<int, MediaAsset>  $photos
     */
    public function submit(
        ReservableObject $object,
        Person $reporter,
        string $description,
        DamageSeverity $severity,
        bool $reporterMarkedUnusable,
        Collection $photos,
        ?Reservation $reservation = null,
    ): DamageReport {
        return DB::transaction(function () use ($object, $reporter, $description, $severity, $reporterMarkedUnusable, $photos, $reservation): DamageReport {
            $report = DamageReport::create([
                'reservable_object_id' => $object->id,
                'reported_by_person_id' => $reporter->id,
                'reservation_id' => $reservation?->id,
                'description' => $description,
                'severity' => $severity,
                'reporter_marked_unusable' => $reporterMarkedUnusable,
                'status' => DamageReportStatus::Reported,
                'reported_at' => Carbon::now(),
            ]);

            if ($photos->isNotEmpty()) {
                $report->photos()->syncWithoutDetaching($photos->pluck('id')->all());
            }

            if ($reporterMarkedUnusable && $object->status === ReservableObjectStatus::Available) {
                $before = ['status' => $object->status->value];
                $object->update(['status' => ReservableObjectStatus::OutOfService]);

                $this->audit->log('damage_report.object_marked_unusable', $object,
                    before: $before,
                    after: ['status' => ReservableObjectStatus::OutOfService->value],
                    context: ['damage_report_id' => $report->id],
                );
            }

            $this->audit->log('damage_report.submitted', $report, after: [
                'object_id' => $object->id,
                'severity' => $severity->value,
                'reporter_marked_unusable' => $reporterMarkedUnusable,
                'photo_count' => $photos->count(),
            ]);

            foreach ($this->resolveRecipients($object) as $recipient) {
                if ($recipient->email === null || $recipient->email === '') {
                    continue;
                }
                Notification::route('mail', $recipient->email)
                    ->notify(new DamageReportSubmitted($report));
            }

            return $report;
        });
    }

    public function assign(DamageReport $report, ?Person $assignee, Person $actor): void
    {
        $before = ['assigned_to_person_id' => $report->assigned_to_person_id];

        $report->update([
            'assigned_to_person_id' => $assignee?->id,
            'status' => $assignee !== null && $report->status === DamageReportStatus::Reported
                ? DamageReportStatus::InProgress
                : $report->status,
        ]);

        $this->audit->log('damage_report.assigned', $report,
            before: $before,
            after: ['assigned_to_person_id' => $assignee?->id],
            context: ['actor_person_id' => $actor->id],
        );
    }

    public function changeStatus(DamageReport $report, DamageReportStatus $to, Person $actor, ?string $resolution = null): void
    {
        $before = [
            'status' => $report->status->value,
            'resolution' => $report->resolution,
        ];

        $report->update([
            'status' => $to,
            'resolution' => $resolution ?? $report->resolution,
            'resolved_at' => in_array($to, [DamageReportStatus::Resolved, DamageReportStatus::Rejected], true)
                ? Carbon::now()
                : null,
        ]);

        $this->audit->log('damage_report.status_changed', $report,
            before: $before,
            after: ['status' => $to->value, 'resolution' => $resolution ?? $report->resolution],
            context: ['actor_person_id' => $actor->id],
        );
    }

    public function restoreObject(ReservableObject $object, Person $actor, DamageReport $report): void
    {
        if ($object->status !== ReservableObjectStatus::OutOfService) {
            return;
        }

        $before = ['status' => $object->status->value];
        $object->update(['status' => ReservableObjectStatus::Available]);

        $this->audit->log('damage_report.object_restored', $object,
            before: $before,
            after: ['status' => ReservableObjectStatus::Available->value],
            context: ['damage_report_id' => $report->id, 'actor_person_id' => $actor->id],
        );
    }

    /**
     * §22.4 — verantwoordelijken van de categorie, met overerving naar
     * bovenliggende categorieën. Geeft unieke Person-records terug.
     *
     * @return Collection<int, Person>
     */
    private function resolveRecipients(ReservableObject $object): Collection
    {
        $category = $object->category;
        $chain = [$category, ...$category->ancestors()];

        foreach ($chain as $node) {
            $persons = Person::query()
                ->whereIn('id', $node->responsibles()->pluck('person_id'))
                ->get();
            if ($persons->isNotEmpty()) {
                return $persons;
            }
        }

        return collect();
    }
}
