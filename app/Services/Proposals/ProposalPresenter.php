<?php

namespace App\Services\Proposals;

use App\Models\MembershipType;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\ReservableObject;
use App\Services\Proposals\Handlers\MembershipApplicationHandler;
use App\Services\Proposals\Handlers\PageVersionProposalHandler;
use App\Services\Proposals\Handlers\PersonFieldUpdateHandler;
use App\Services\Proposals\Handlers\ReservationProposalHandler;
use Illuminate\Support\Carbon;

/**
 * Vertaalt een Proposal naar leesbare weergavegegevens, los van de
 * mutatielogica in ProposalHandler (die gaat over toepassen/hervalideren,
 * niet over tonen). Eén plek per subject_type die weet welke Blade-partial
 * de wijziging toont en welke gerelateerde modellen daarvoor nodig zijn —
 * zodat views geen ruwe payload-ID's hoeven te tonen of zelf te resolven.
 */
class ProposalPresenter
{
    /** @var array<string, string> */
    private const array FIELD_LABELS = [
        'first_name' => 'Voornaam',
        'last_name_prefix' => 'Tussenvoegsel',
        'last_name' => 'Achternaam',
        'date_of_birth' => 'Geboortedatum',
        'membership_type_id' => 'Lidmaatschapsvorm',
    ];

    public function summary(Proposal $proposal): string
    {
        return match ($proposal->subject_type) {
            PageVersionProposalHandler::SUBJECT_TYPE => $this->pageVersionDiffContext($proposal)['label'] ?? 'Paginawijziging',
            PersonFieldUpdateHandler::SUBJECT_TYPE => 'Wijziging van '.mb_strtolower($this->fieldLabel($proposal)),
            MembershipApplicationHandler::SUBJECT_TYPE => 'Lidmaatschapsaanvraag: '.$this->applicantName($proposal),
            ReservationProposalHandler::SUBJECT_TYPE => 'Reservering: '.$this->reservationObjectName($proposal),
            default => class_basename($proposal->subject_type),
        };
    }

    public function partial(Proposal $proposal): string
    {
        return match ($proposal->subject_type) {
            PageVersionProposalHandler::SUBJECT_TYPE => 'proposals.changes.page-version',
            PersonFieldUpdateHandler::SUBJECT_TYPE => 'proposals.changes.person-field-update',
            MembershipApplicationHandler::SUBJECT_TYPE => 'proposals.changes.membership-application',
            ReservationProposalHandler::SUBJECT_TYPE => 'proposals.changes.reservation',
            default => 'proposals.changes.unknown',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Proposal $proposal): array
    {
        return match ($proposal->subject_type) {
            PageVersionProposalHandler::SUBJECT_TYPE => $this->pageVersionData($proposal),
            PersonFieldUpdateHandler::SUBJECT_TYPE => $this->personFieldUpdateData($proposal),
            MembershipApplicationHandler::SUBJECT_TYPE => $this->membershipApplicationData($proposal),
            ReservationProposalHandler::SUBJECT_TYPE => $this->reservationData($proposal),
            default => ['proposal' => $proposal],
        };
    }

    /**
     * Page/versie/vorige-versie-resolutie voor een `cms.page_version`-voorstel.
     * Wordt zowel hier als door `Admin\ProposalController` gebruikt — elke
     * aanroeper bouwt zelf de (admin- vs. portal-)route-URL naar de diff.
     *
     * @return array{page: Page, version: PageVersion, previous: ?PageVersion, label: string}|null
     */
    public function pageVersionDiffContext(Proposal $proposal): ?array
    {
        if ($proposal->subject_type !== PageVersionProposalHandler::SUBJECT_TYPE || $proposal->subject_id === null) {
            return null;
        }

        $version = PageVersion::query()->with(['page', 'baseVersion'])->find($proposal->subject_id);
        if ($version === null) {
            return null;
        }

        $page = $version->page;
        // De versie waarop de bewerking is gebaseerd is doorgaans de toen
        // gepubliceerde pagina; valt terug op de nu gepubliceerde versie.
        $previous = $version->baseVersion ?? $page->publishedVersion;
        if ($previous !== null && $previous->id === $version->id) {
            $previous = null;
        }

        return [
            'page' => $page,
            'version' => $version,
            'previous' => $previous,
            'label' => "{$page->title} — v{$version->version_no}",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageVersionData(Proposal $proposal): array
    {
        $ctx = $this->pageVersionDiffContext($proposal);

        return [
            'page' => $ctx['page'] ?? null,
            'version' => $ctx['version'] ?? null,
            'diffUrl' => $ctx !== null && $ctx['previous'] !== null
                ? route('portal.wijzigingsvoorstellen.diff', ['proposal' => $proposal])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personFieldUpdateData(Proposal $proposal): array
    {
        $payload = $proposal->payload;
        $field = (string) ($payload['field'] ?? '');
        $oldValue = $payload['old_value'] ?? null;
        $newValue = $payload['new_value'] ?? null;

        if ($field === 'membership_type_id') {
            $oldValue = $this->membershipTypeName($oldValue);
            $newValue = $this->membershipTypeName($newValue);
        } elseif ($field === 'date_of_birth') {
            $oldValue = $oldValue !== null ? Carbon::parse((string) $oldValue)->format('d-m-Y') : null;
            $newValue = $newValue !== null ? Carbon::parse((string) $newValue)->format('d-m-Y') : null;
        }

        return [
            'fieldLabel' => $this->fieldLabel($proposal),
            'oldValue' => $oldValue,
            'newValue' => $newValue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipApplicationData(Proposal $proposal): array
    {
        $payload = $proposal->payload;
        $guardian = $payload['guardian'] ?? null;
        $typeKey = $payload['membership_type_key'] ?? null;

        $hasGuardianDetails = is_array($guardian)
            && (($guardian['existing_person_id'] ?? null) !== null || ($guardian['first_name'] ?? null) !== null);

        return [
            'person' => $payload['person'] ?? [],
            'address' => $payload['address'] ?? [],
            'membershipTypeName' => is_string($typeKey)
                ? (MembershipType::query()->where('key', $typeKey)->value('name') ?? $typeKey)
                : null,
            'overrideReason' => $payload['membership_type_override_reason'] ?? null,
            'isMinor' => (bool) ($payload['is_minor'] ?? false),
            'guardian' => $hasGuardianDetails ? $guardian : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationData(Proposal $proposal): array
    {
        $payload = $proposal->payload;

        return [
            'object' => ReservableObject::query()->find($payload['reservable_object_id'] ?? null),
            'beneficiary' => Person::query()->find($payload['person_id'] ?? null),
            'requestedBy' => isset($payload['requested_by_person_id'])
                ? Person::query()->find($payload['requested_by_person_id'])
                : null,
            'startsAt' => isset($payload['starts_at']) ? Carbon::parse((string) $payload['starts_at']) : null,
            'endsAt' => isset($payload['ends_at']) ? Carbon::parse((string) $payload['ends_at']) : null,
            'note' => $payload['note'] ?? null,
            'violations' => $payload['violations'] ?? [],
        ];
    }

    private function fieldLabel(Proposal $proposal): string
    {
        $field = (string) ($proposal->payload['field'] ?? '');

        return self::FIELD_LABELS[$field] ?? $field;
    }

    private function applicantName(Proposal $proposal): string
    {
        $person = $proposal->payload['person'] ?? [];
        $name = trim(collect([
            $person['first_name'] ?? null,
            $person['last_name_prefix'] ?? null,
            $person['last_name'] ?? null,
        ])->filter()->implode(' '));

        return $name !== '' ? $name : 'onbekend';
    }

    private function reservationObjectName(Proposal $proposal): string
    {
        $objectId = $proposal->payload['reservable_object_id'] ?? null;

        return ReservableObject::query()->whereKey($objectId)->value('name') ?? 'onbekend object';
    }

    private function membershipTypeName(mixed $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        return MembershipType::query()->whereKey($id)->value('name') ?? "#{$id}";
    }
}
