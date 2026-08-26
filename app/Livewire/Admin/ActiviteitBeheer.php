<?php

namespace App\Livewire\Admin;

use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use App\Enums\PageVisibility;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityPage;
use App\Models\ActivityRegistrationField;
use App\Models\ActivitySeries;
use App\Models\ApproverGroup;
use App\Models\MediaAsset;
use App\Models\Person;
use App\Services\Activities\ActivityManagerNotifier;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaUploadService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Eén scherm voor alles rond activiteiten (§17.3/17.4):
 * - platte lijst van alle voorkomens (filteren, bewerken, afgelasten,
 *   verwijderen, beheerders, bestanden);
 * - een nieuwe activiteit aanmaken gaat altijd via de datumlijst-flow
 *   (minimaal één datum, meer optioneel — handmatig en/of gegenereerd);
 * - bij meerdere data kies je expliciet **bundel** (elk voorkomen apart
 *   aanmelden, wel gezamenlijk te bewerken) of **reeks** (in één keer
 *   aanmelden voor alles) — `EnrollmentLevel`;
 * - groep-brede bewerking kent twee reikwijdtes: de hele groep, of "dit en
 *   volgende" (splitst af — `split_from_id`). Voorkomens die al los zijn
 *   aangepast (`is_exception`) blijven altijd buiten schot.
 *
 * Er is precies één modus tegelijk actief: een los voorkomen bewerken
 * (`editingActivityId`), een nieuwe groep aanmaken (`creatingGroup`), een
 * bestaande groep bewerken (`editingGroupId`), of voorkomens toevoegen aan
 * een bestaande groep (`addingOccurrencesToId`). `resetForm()` sluit alle
 * modi en wist de gedeelde velden, zodat er nooit stale data blijft hangen.
 *
 * Toegang tot de lijst loopt via `activities.view`; alles wat wijzigt via
 * `activities.update`.
 */
#[Layout('layouts.app', ['header' => 'Activiteiten'])]
class ActiviteitBeheer extends Component
{
    use WithFileUploads;

    // Modus-vlaggen — precies één tegelijk actief.
    public ?int $editingActivityId = null;

    public bool $creatingGroup = false;

    public ?int $editingGroupId = null;

    public ?int $addingOccurrencesToId = null;

    // Gedeelde velden — hergebruikt door zowel los-voorkomen- als groepsvormen.
    public ?int $categoryId = null;

    public string $title = '';

    public string $description = '';

    public string $location = '';

    public ?int $capacity = null;

    public ?int $minCapacity = null;

    public ?int $minAge = null;

    public ?int $maxAge = null;

    public string $publishFrom = '';

    public string $publishUntil = '';

    // Fase B: los van het publicatievenster — wanneer inschrijven mag, en
    // tot wanneer een inschrijving geannuleerd mag worden.
    public string $enrollmentOpensAt = '';

    public string $enrollmentClosesAt = '';

    public string $cancellationDeadline = '';

    public string $visibility = 'members';

    public string $status = 'gepubliceerd';

    // Alleen voor een los voorkomen, of bij het aanmaken van een losse
    // activiteit (`creationMode === 'los'`) — kan meerdere dagen beslaan.
    public ?int $activityPageId = null;

    public string $startsAt = '';

    public string $endsAt = '';

    // Alleen voor een groep.
    public string $enrollmentLevel = 'bundel';

    public string $editScope = 'hele_reeks';

    public ?int $splitFromActivityId = null;

    // Alleen tijdens het aanmaken: stuurt of het simpele begin/eind-datumveld
    // (losse activiteit, kan meerdere dagen duren) of de volledige
    // datumlijst-werkbalk (handmatig + generator) getoond wordt. Bij 'los' is
    // enrollmentLevel altijd 'bundel' (maakt met één voorkomen geen verschil).
    public string $creationMode = 'los';

    /** @var array<int, array{starts_at: string, ends_at: ?string}> */
    public array $pendingDates = [];

    public string $manualDate = '';

    public string $manualStartTime = '10:00';

    public string $manualEndTime = '';

    // Datum-invoer bij reeks/bundel: eerst een tag kiezen. 'specific' gebruikt
    // manualDate/addManualDate(); weekly/monthly/quarterly gebruiken de
    // generator hieronder.
    public string $genMode = 'specific';

    // Bij monthly/quarterly: 'fixed' (vaste dag-van-de-maand, genDayOfMonth)
    // of 'weekday' (bv. "tweede dinsdag", genOrdinal + genWeekday).
    public string $genMonthlyDayMode = 'fixed';

    public ?int $genDayOfMonth = null;

    // 1..4 = eerste..vierde, -2 = voorlaatste, -1 = laatste.
    public string $genOrdinal = '1';

    public int $genWeekday = 1;

    public string $genStartDate = '';

    // 'until' (stopt op genEndDate) of 'count' (stopt na genCount keer) —
    // altijd maar één van beide actief, de ander wordt bij wisselen gewist.
    public string $genBoundMode = 'until';

    public string $genEndDate = '';

    public ?int $genCount = null;

    public string $genStartTime = '10:00';

    public string $genEndTime = '';

    // Overzicht-filters (los van modus).
    public string $filterStatus = 'all';

    public bool $hideHistory = true;

    // Bij het aanmaken van een nieuwe activiteit alvast gedelegeerde
    // beheerders kiezen; worden bij createOccurrence() op elk voorkomen
    // gezet. Los van expandedManagersId/addManagerPersonId hieronder, die
    // gelden voor een al bestaand voorkomen via de platte lijst.
    /** @var array<int, array{person_id: int, notify: bool}> */
    public array $pendingManagers = [];

    public ?int $pendingManagerPersonId = null;

    /** @var array<int, array{approver_group_id: int, notify: bool}> */
    public array $pendingManagerGroups = [];

    public ?int $pendingManagerGroupId = null;

    // Extra inschrijfvelden (tekst/keuze/aantal, Fase C): tijdens het
    // aanmaken opgebouwd in pendingRegistrationFields (toegepast op elk
    // voorkomen bij createOccurrence()); dezelfde new*-eigenschappen worden
    // hergebruikt voor het toevoegen van een veld aan een bestaand voorkomen
    // via het "Velden"-paneel in de platte lijst (nooit tegelijk actief).
    /** @var array<int, array{type: string, label: string, required: bool, price_per_unit: ?float, max_count: ?int, options: array<int, array{label: string, price: ?float}>}> */
    public array $pendingRegistrationFields = [];

    public string $newFieldType = 'text';

    public string $newFieldLabel = '';

    public bool $newFieldRequired = false;

    public ?float $newFieldPricePerUnit = null;

    public ?int $newFieldMaxCount = null;

    /** @var array<int, array{label: string, price: ?float}> */
    public array $newFieldOptions = [];

    public string $newFieldOptionLabel = '';

    public ?float $newFieldOptionPrice = null;

    public ?int $expandedFieldsId = null;

    // Beheerders/bestanden — werken altijd op een los voorkomen, ongeacht modus.
    public ?int $expandedManagersId = null;

    public ?int $addManagerPersonId = null;

    public ?int $addManagerGroupId = null;

    public ?int $expandedFilesId = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $newFiles = [];

    public ?string $statusMessage = null;

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingActivityId', 'creatingGroup', 'editingGroupId', 'addingOccurrencesToId',
            'categoryId', 'title', 'description', 'location', 'capacity',
            'minCapacity', 'minAge', 'maxAge', 'publishFrom', 'publishUntil',
            'enrollmentOpensAt', 'enrollmentClosesAt', 'cancellationDeadline',
            'visibility', 'status', 'activityPageId', 'startsAt', 'endsAt',
            'enrollmentLevel', 'creationMode', 'editScope', 'splitFromActivityId',
            'pendingDates', 'manualDate', 'manualEndTime', 'genStartDate', 'genEndDate', 'genCount',
            'manualStartTime', 'genStartTime', 'genWeekday', 'genMode', 'genMonthlyDayMode',
            'genDayOfMonth', 'genOrdinal', 'genBoundMode', 'pendingManagers', 'pendingManagerPersonId',
            'pendingManagerGroups', 'pendingManagerGroupId', 'pendingRegistrationFields',
            'newFieldType', 'newFieldLabel', 'newFieldRequired', 'newFieldPricePerUnit',
            'newFieldMaxCount', 'newFieldOptions', 'newFieldOptionLabel', 'newFieldOptionPrice',
        ]);
    }

    // ── Los voorkomen bewerken ──────────────────────────────────────────

    public function editActivity(int $id): void
    {
        $this->resetForm();
        $activity = Activity::query()->findOrFail($id);
        $this->editingActivityId = $activity->id;
        $this->categoryId = $activity->activity_category_id;
        $this->activityPageId = $activity->activity_page_id;
        $this->title = $activity->title;
        $this->description = $activity->description ?? '';
        $this->startsAt = $activity->starts_at->format('Y-m-d\TH:i');
        $this->endsAt = $activity->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->location = $activity->location ?? '';
        $this->capacity = $activity->capacity;
        $this->minCapacity = $activity->min_capacity;
        $this->minAge = $activity->min_age;
        $this->maxAge = $activity->max_age;
        $this->publishFrom = $activity->publish_from?->format('Y-m-d\TH:i') ?? '';
        $this->publishUntil = $activity->publish_until?->format('Y-m-d\TH:i') ?? '';
        $this->enrollmentOpensAt = $activity->enrollment_opens_at?->format('Y-m-d\TH:i') ?? '';
        $this->enrollmentClosesAt = $activity->enrollment_closes_at?->format('Y-m-d\TH:i') ?? '';
        $this->cancellationDeadline = $activity->cancellation_deadline?->format('Y-m-d\TH:i') ?? '';
        $this->visibility = $activity->visibility->value;
        $this->status = $activity->status->value;
    }

    public function saveActivity(AuditLogger $audit, ActivityManagerNotifier $notifier): void
    {
        if ($this->editingActivityId === null) {
            return;
        }

        $this->validate($this->activityValidationRules(), [
            'startsAt.required' => 'Startdatum en -tijd zijn verplicht.',
            'endsAt.after_or_equal' => 'De einddatum kan niet vóór de startdatum liggen.',
        ]);

        $activity = Activity::query()->findOrFail($this->editingActivityId);

        DB::transaction(function () use ($activity, $audit): void {
            $before = $activity->only(array_keys($this->activityAttributes()));
            $attributes = $this->activityAttributes();

            // Los bewerken van een gedeeld veld op een groep-voorkomen
            // beschermt het voortaan tegen groep-brede wijzigingen (§17.4).
            if ($activity->series_id !== null && ! $activity->is_exception && $this->sharedFieldsChanged($activity, $attributes)) {
                $attributes['is_exception'] = true;
            }

            $activity->update($attributes);
            $audit->log('activity.updated', $activity, before: $before, after: $attributes);
            $this->statusMessage = "Activiteit [{$activity->title}] bijgewerkt.";
        });

        $notifier->notifyChanged($activity);

        $this->resetForm();
    }

    public function cancelActivity(int $id, AuditLogger $audit): void
    {
        $activity = Activity::query()->findOrFail($id);
        $before = ['status' => $activity->status->value];
        $activity->update(['status' => ActivityStatus::Cancelled]);
        $audit->log('activity.cancelled', $activity, before: $before, after: ['status' => 'afgelast']);
        $this->statusMessage = "Activiteit [{$activity->title}] afgelast.";
    }

    public function deleteActivity(int $id, AuditLogger $audit): void
    {
        $activity = Activity::query()->findOrFail($id);
        $before = $activity->only(array_keys($this->activityAttributes()));
        DB::transaction(function () use ($activity, $before, $audit): void {
            $audit->log('activity.deleted', $activity, before: $before);
            $activity->delete();
        });
        $this->statusMessage = "Activiteit [{$activity->title}] verwijderd.";
    }

    /** @return array<string, mixed> */
    private function activityAttributes(): array
    {
        return [
            'activity_category_id' => $this->categoryId,
            'activity_page_id' => $this->activityPageId,
            'title' => $this->title,
            'description' => $this->description !== '' ? $this->description : null,
            'starts_at' => Carbon::parse($this->startsAt),
            'ends_at' => $this->endsAt !== '' ? Carbon::parse($this->endsAt) : null,
            'location' => $this->location !== '' ? $this->location : null,
            'capacity' => $this->capacity,
            'min_capacity' => $this->minCapacity,
            'min_age' => $this->minAge,
            'max_age' => $this->maxAge,
            'publish_from' => $this->publishFrom !== '' ? Carbon::parse($this->publishFrom) : null,
            'publish_until' => $this->publishUntil !== '' ? Carbon::parse($this->publishUntil) : null,
            'enrollment_opens_at' => $this->enrollmentOpensAt !== '' ? Carbon::parse($this->enrollmentOpensAt) : null,
            'enrollment_closes_at' => $this->enrollmentClosesAt !== '' ? Carbon::parse($this->enrollmentClosesAt) : null,
            'cancellation_deadline' => $this->cancellationDeadline !== '' ? Carbon::parse($this->cancellationDeadline) : null,
            'visibility' => $this->visibility,
            'status' => $this->status,
        ];
    }

    /** @param  array<string, mixed>  $new */
    private function sharedFieldsChanged(Activity $activity, array $new): bool
    {
        $before = [
            'activity_category_id' => $activity->activity_category_id,
            'title' => $activity->title,
            'description' => $activity->description,
            'location' => $activity->location,
            'capacity' => $activity->capacity,
            'min_capacity' => $activity->min_capacity,
            'min_age' => $activity->min_age,
            'max_age' => $activity->max_age,
            'publish_from' => $activity->publish_from?->toDateTimeString(),
            'publish_until' => $activity->publish_until?->toDateTimeString(),
            'enrollment_opens_at' => $activity->enrollment_opens_at?->toDateTimeString(),
            'enrollment_closes_at' => $activity->enrollment_closes_at?->toDateTimeString(),
            'cancellation_deadline' => $activity->cancellation_deadline?->toDateTimeString(),
            'visibility' => $activity->visibility->value,
            'status' => $activity->status->value,
        ];

        $after = [
            'activity_category_id' => $new['activity_category_id'],
            'title' => $new['title'],
            'description' => $new['description'],
            'location' => $new['location'],
            'capacity' => $new['capacity'],
            'min_capacity' => $new['min_capacity'],
            'min_age' => $new['min_age'],
            'max_age' => $new['max_age'],
            'publish_from' => $new['publish_from']?->toDateTimeString(),
            'publish_until' => $new['publish_until']?->toDateTimeString(),
            'enrollment_opens_at' => $new['enrollment_opens_at']?->toDateTimeString(),
            'enrollment_closes_at' => $new['enrollment_closes_at']?->toDateTimeString(),
            'cancellation_deadline' => $new['cancellation_deadline']?->toDateTimeString(),
            'visibility' => $new['visibility'],
            'status' => $new['status'],
        ];

        return $before !== $after;
    }

    // ── Beheerders (§6, gedelegeerd beheer per voorkomen) ───────────────

    public function toggleManagers(int $activityId): void
    {
        $this->expandedManagersId = $this->expandedManagersId === $activityId ? null : $activityId;
        $this->addManagerPersonId = null;
        $this->addManagerGroupId = null;
    }

    public function addManager(int $activityId, AuditLogger $audit): void
    {
        if ($this->addManagerPersonId === null) {
            return;
        }

        $activity = Activity::query()->findOrFail($activityId);
        $person = Person::query()->findOrFail($this->addManagerPersonId);

        if ($activity->managers()->where('persons.id', $person->id)->exists()) {
            $this->statusMessage = "{$person->first_name} is al beheerder van [{$activity->title}].";

            return;
        }

        $activity->managers()->attach($person->id, ['notify' => true]);
        $audit->log('activity.manager_added', $activity, after: ['person_id' => $person->id]);
        $this->addManagerPersonId = null;
        $this->statusMessage = "{$person->first_name} toegevoegd als beheerder van [{$activity->title}].";
    }

    public function removeManager(int $activityId, int $personId, AuditLogger $audit): void
    {
        $activity = Activity::query()->findOrFail($activityId);
        $activity->managers()->detach($personId);
        $audit->log('activity.manager_removed', $activity, before: ['person_id' => $personId]);
        $this->statusMessage = "Beheerder verwijderd van [{$activity->title}].";
    }

    public function toggleManagerNotify(int $activityId, int $personId): void
    {
        $activity = Activity::query()->findOrFail($activityId);
        $current = $activity->managers()->where('persons.id', $personId)->first();
        if ($current === null) {
            return;
        }
        // @phpstan-ignore property.notFound (dynamische pivot-kolom, withPivot('notify'))
        $activity->managers()->updateExistingPivot($personId, ['notify' => ! $current->pivot->notify]);
    }

    public function addManagerGroup(int $activityId, AuditLogger $audit): void
    {
        if ($this->addManagerGroupId === null) {
            return;
        }

        $activity = Activity::query()->findOrFail($activityId);
        $group = ApproverGroup::query()->findOrFail($this->addManagerGroupId);

        if ($activity->managerGroups()->where('approver_groups.id', $group->id)->exists()) {
            $this->statusMessage = "Groep [{$group->name}] is al beheerder van [{$activity->title}].";

            return;
        }

        $activity->managerGroups()->attach($group->id, ['notify' => true]);
        $audit->log('activity.manager_group_added', $activity, after: ['approver_group_id' => $group->id]);
        $this->addManagerGroupId = null;
        $this->statusMessage = "Groep [{$group->name}] toegevoegd als beheerder van [{$activity->title}].";
    }

    public function removeManagerGroup(int $activityId, int $groupId, AuditLogger $audit): void
    {
        $activity = Activity::query()->findOrFail($activityId);
        $activity->managerGroups()->detach($groupId);
        $audit->log('activity.manager_group_removed', $activity, before: ['approver_group_id' => $groupId]);
        $this->statusMessage = "Beheerdersgroep verwijderd van [{$activity->title}].";
    }

    public function toggleManagerGroupNotify(int $activityId, int $groupId): void
    {
        $activity = Activity::query()->findOrFail($activityId);
        $current = $activity->managerGroups()->where('approver_groups.id', $groupId)->first();
        if ($current === null) {
            return;
        }
        // @phpstan-ignore property.notFound (dynamische pivot-kolom, withPivot('notify'))
        $activity->managerGroups()->updateExistingPivot($groupId, ['notify' => ! $current->pivot->notify]);
    }

    // ── Gedelegeerde beheerders kiezen tijdens het aanmaken ──────────────

    public function addPendingManager(): void
    {
        if ($this->pendingManagerPersonId === null) {
            return;
        }

        $personId = $this->pendingManagerPersonId;
        $this->pendingManagerPersonId = null;

        if (collect($this->pendingManagers)->contains('person_id', $personId)) {
            return;
        }

        $this->pendingManagers[] = ['person_id' => $personId, 'notify' => true];
    }

    public function removePendingManager(int $personId): void
    {
        $this->pendingManagers = array_values(array_filter(
            $this->pendingManagers,
            fn (array $pm): bool => $pm['person_id'] !== $personId,
        ));
    }

    public function togglePendingManagerNotify(int $personId): void
    {
        foreach ($this->pendingManagers as $i => $pm) {
            if ($pm['person_id'] === $personId) {
                $this->pendingManagers[$i]['notify'] = ! $pm['notify'];

                return;
            }
        }
    }

    public function addPendingManagerGroup(): void
    {
        if ($this->pendingManagerGroupId === null) {
            return;
        }

        $groupId = $this->pendingManagerGroupId;
        $this->pendingManagerGroupId = null;

        if (collect($this->pendingManagerGroups)->contains('approver_group_id', $groupId)) {
            return;
        }

        $this->pendingManagerGroups[] = ['approver_group_id' => $groupId, 'notify' => true];
    }

    public function removePendingManagerGroup(int $groupId): void
    {
        $this->pendingManagerGroups = array_values(array_filter(
            $this->pendingManagerGroups,
            fn (array $pmg): bool => $pmg['approver_group_id'] !== $groupId,
        ));
    }

    public function togglePendingManagerGroupNotify(int $groupId): void
    {
        foreach ($this->pendingManagerGroups as $i => $pmg) {
            if ($pmg['approver_group_id'] === $groupId) {
                $this->pendingManagerGroups[$i]['notify'] = ! $pmg['notify'];

                return;
            }
        }
    }

    // ── Extra inschrijfvelden (§17.3/17.4, Fase C) ───────────────────────

    public function selectNewFieldType(string $type): void
    {
        $this->newFieldType = $type;
        $this->newFieldPricePerUnit = null;
        $this->newFieldMaxCount = null;
        $this->newFieldOptions = [];
        $this->newFieldOptionLabel = '';
        $this->newFieldOptionPrice = null;
    }

    public function addNewFieldOption(): void
    {
        $this->validate(['newFieldOptionLabel' => ['required', 'string', 'max:255']], [], ['newFieldOptionLabel' => 'optielabel']);

        $this->newFieldOptions[] = ['label' => $this->newFieldOptionLabel, 'price' => $this->newFieldOptionPrice];
        $this->newFieldOptionLabel = '';
        $this->newFieldOptionPrice = null;
    }

    public function removeNewFieldOption(int $index): void
    {
        unset($this->newFieldOptions[$index]);
        $this->newFieldOptions = array_values($this->newFieldOptions);
    }

    /** @return array<string, mixed> */
    private function newFieldValidationRules(): array
    {
        $rules = [
            'newFieldLabel' => ['required', 'string', 'max:255'],
            'newFieldType' => ['required', 'in:text,choice,count'],
        ];

        if ($this->newFieldType === ActivityRegistrationField::TYPE_COUNT) {
            $rules['newFieldPricePerUnit'] = ['nullable', 'numeric', 'min:0'];
            $rules['newFieldMaxCount'] = ['nullable', 'integer', 'min:1'];
        }

        return $rules;
    }

    /** @return array{type: string, label: string, required: bool, price_per_unit: ?float, max_count: ?int, options: array<int, array{label: string, price: ?float}>}|null */
    private function buildNewFieldOrFail(): ?array
    {
        $this->validate($this->newFieldValidationRules());

        if ($this->newFieldType === ActivityRegistrationField::TYPE_CHOICE && count($this->newFieldOptions) === 0) {
            $this->addError('newFieldOptions', 'Voeg minstens één keuzeoptie toe.');

            return null;
        }

        $field = [
            'type' => $this->newFieldType,
            'label' => $this->newFieldLabel,
            'required' => $this->newFieldRequired,
            'price_per_unit' => $this->newFieldType === ActivityRegistrationField::TYPE_COUNT ? $this->newFieldPricePerUnit : null,
            'max_count' => $this->newFieldType === ActivityRegistrationField::TYPE_COUNT ? $this->newFieldMaxCount : null,
            'options' => $this->newFieldType === ActivityRegistrationField::TYPE_CHOICE ? $this->newFieldOptions : [],
        ];

        $this->reset(['newFieldType', 'newFieldLabel', 'newFieldRequired', 'newFieldPricePerUnit', 'newFieldMaxCount', 'newFieldOptions', 'newFieldOptionLabel', 'newFieldOptionPrice']);

        return $field;
    }

    public function addPendingRegistrationField(): void
    {
        $field = $this->buildNewFieldOrFail();
        if ($field === null) {
            return;
        }

        $this->pendingRegistrationFields[] = $field;
    }

    public function removePendingRegistrationField(int $index): void
    {
        unset($this->pendingRegistrationFields[$index]);
        $this->pendingRegistrationFields = array_values($this->pendingRegistrationFields);
    }

    public function toggleRegistrationFields(int $activityId): void
    {
        $this->expandedFieldsId = $this->expandedFieldsId === $activityId ? null : $activityId;
        $this->newFieldType = 'text';
        $this->newFieldLabel = '';
        $this->newFieldRequired = false;
        $this->newFieldPricePerUnit = null;
        $this->newFieldMaxCount = null;
        $this->newFieldOptions = [];
    }

    public function addRegistrationFieldToActivity(int $activityId, AuditLogger $audit): void
    {
        $field = $this->buildNewFieldOrFail();
        if ($field === null) {
            return;
        }

        $activity = Activity::query()->findOrFail($activityId);

        DB::transaction(function () use ($activity, $field, $audit): void {
            $sortOrder = (int) $activity->registrationFields()->max('sort_order') + 1;

            $created = $activity->registrationFields()->create([
                'type' => $field['type'],
                'label' => $field['label'],
                'required' => $field['required'],
                'sort_order' => $sortOrder,
                'price_per_unit' => $field['price_per_unit'],
                'max_count' => $field['max_count'],
            ]);

            foreach ($field['options'] as $i => $option) {
                $created->options()->create(['label' => $option['label'], 'price' => $option['price'], 'sort_order' => $i]);
            }

            $audit->log('activity.registration_field_added', $activity, after: ['field_id' => $created->id, 'label' => $created->label]);
        });

        $this->statusMessage = "Inschrijfveld [{$field['label']}] toegevoegd aan [{$activity->title}].";
    }

    public function removeRegistrationField(int $activityId, int $fieldId, AuditLogger $audit): void
    {
        $activity = Activity::query()->findOrFail($activityId);
        $field = ActivityRegistrationField::query()->where('activity_id', $activityId)->findOrFail($fieldId);
        $label = $field->label;

        $field->delete();
        $audit->log('activity.registration_field_removed', $activity, before: ['field_id' => $fieldId, 'label' => $label]);
        $this->statusMessage = "Inschrijfveld [{$label}] verwijderd van [{$activity->title}].";
    }

    // ── Bestanden per voorkomen ──────────────────────────────────────────

    public function toggleFiles(int $activityId): void
    {
        $this->expandedFilesId = $this->expandedFilesId === $activityId ? null : $activityId;
        $this->newFiles = [];
    }

    public function uploadFiles(int $activityId, MediaUploadService $uploader, AuditLogger $audit): void
    {
        $this->validate([
            'newFiles.*' => ['file', 'max:'.intdiv(MediaUploadService::MAX_BYTES, 1024)],
        ]);

        if ($this->newFiles === []) {
            return;
        }

        $activity = Activity::query()->findOrFail($activityId);
        $person = auth()->user()?->person;

        foreach ($this->newFiles as $upload) {
            $asset = $uploader->store(
                file: $upload,
                uploadedBy: $person,
                visibility: PageVisibility::Restricted,
                context: MediaAsset::CONTEXT_ACTIVITY,
            );
            $activity->files()->attach($asset->id);
            $audit->log('activity.file_added', $activity, after: ['media_asset_id' => $asset->id, 'original_name' => $asset->original_name]);
        }

        $this->newFiles = [];
        $this->statusMessage = 'Bestand(en) toegevoegd aan ['.$activity->title.'].';
    }

    public function removeFile(int $activityId, int $mediaAssetId, AuditLogger $audit): void
    {
        $activity = Activity::query()->findOrFail($activityId);
        $activity->files()->detach($mediaAssetId);
        $audit->log('activity.file_removed', $activity, before: ['media_asset_id' => $mediaAssetId]);
        $this->statusMessage = 'Bestand verwijderd van ['.$activity->title.'].';
    }

    // ── Groep aanmaken/bewerken (§17.3/17.4) ────────────────────────────

    public function startCreateGroup(): void
    {
        $this->resetForm();
        $this->creatingGroup = true;

        // De aanmaker staat standaard zelf al in de beheerderslijst; kan er
        // in het formulier weer uit gehaald worden.
        $creator = auth()->user()?->person;
        if ($creator !== null) {
            $this->pendingManagers[] = ['person_id' => $creator->id, 'notify' => true];
        }
    }

    public function editGroup(int $id): void
    {
        $this->resetForm();
        $series = ActivitySeries::query()->findOrFail($id);
        $this->editingGroupId = $series->id;
        $this->fillFormFromGroup($series);
    }

    public function startAddOccurrences(int $id): void
    {
        $this->resetForm();
        $this->addingOccurrencesToId = $id;
    }

    public function selectCreationMode(string $mode): void
    {
        $this->creationMode = $mode;
        $this->enrollmentLevel = $mode === 'los' ? 'bundel' : $mode;
    }

    public function selectEnrollmentLevel(string $level): void
    {
        $this->enrollmentLevel = $level;
    }

    public function selectGenMode(string $mode): void
    {
        $this->genMode = $mode;
    }

    public function selectMonthlyDayMode(string $mode): void
    {
        $this->genMonthlyDayMode = $mode;
    }

    public function selectGenBoundMode(string $mode): void
    {
        if ($this->genBoundMode === $mode) {
            return;
        }

        $this->genBoundMode = $mode;
        $this->genEndDate = '';
        $this->genCount = null;
    }

    private function fillFormFromGroup(ActivitySeries $series): void
    {
        $this->categoryId = $series->activity_category_id;
        $this->title = $series->title;
        $this->description = $series->description ?? '';
        $this->location = $series->location ?? '';
        $this->capacity = $series->default_capacity;
        $this->minCapacity = $series->min_capacity;
        $this->minAge = $series->min_age;
        $this->maxAge = $series->max_age;
        $this->publishFrom = $series->publish_from?->format('Y-m-d\TH:i') ?? '';
        $this->publishUntil = $series->publish_until?->format('Y-m-d\TH:i') ?? '';
        $this->enrollmentOpensAt = $series->enrollment_opens_at?->format('Y-m-d\TH:i') ?? '';
        $this->enrollmentClosesAt = $series->enrollment_closes_at?->format('Y-m-d\TH:i') ?? '';
        $this->cancellationDeadline = $series->cancellation_deadline?->format('Y-m-d\TH:i') ?? '';
        $this->visibility = $series->visibility->value;
        $this->status = $series->status->value;
        $this->enrollmentLevel = $series->enrollment_level->value;
    }

    /**
     * Voegt één handmatige datum toe aan de datumlijst — werkt net zo goed
     * voor een losse datum als voor een niet-aaneensluitende groep.
     */
    public function addManualDate(): void
    {
        $this->validate([
            'manualDate' => ['required', 'date'],
            'manualStartTime' => ['required', 'date_format:H:i'],
            'manualEndTime' => ['nullable', 'date_format:H:i'],
        ]);

        $this->pendingDates[] = [
            'starts_at' => "{$this->manualDate}T{$this->manualStartTime}",
            'ends_at' => $this->manualEndTime !== '' ? "{$this->manualDate}T{$this->manualEndTime}" : null,
        ];
        $this->manualDate = '';
    }

    /**
     * Voegt één of meerdere datums toe die op de kalender zijn geselecteerd
     * (Alpine, client-side), allemaal met hetzelfde tijdstip.
     *
     * @param  array<int, mixed>  $dates  losse 'Y-m-d'-strings uit de kalender
     */
    public function addManualDates(array $dates): void
    {
        $this->validate([
            'manualStartTime' => ['required', 'date_format:H:i'],
            'manualEndTime' => ['nullable', 'date_format:H:i'],
        ]);

        $validDates = collect($dates)
            ->filter(function ($date): bool {
                if (! is_string($date) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) !== 1) {
                    return false;
                }

                return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
            })
            ->unique()
            ->sort()
            ->values();

        foreach ($validDates as $date) {
            $this->pendingDates[] = [
                'starts_at' => "{$date}T{$this->manualStartTime}",
                'ends_at' => $this->manualEndTime !== '' ? "{$date}T{$this->manualEndTime}" : null,
            ];
        }
    }

    /**
     * Genereert kandidaat-data op een vast interval (wekelijks/maandelijks/
     * per kwartaal) en voegt ze toe aan de datumlijst; individuele rijen zijn
     * daarna nog te verwijderen vóór opslaan.
     */
    public function generateDates(): void
    {
        $rules = [
            'genMode' => ['required', 'in:weekly,monthly,quarterly'],
            'genStartDate' => ['required', 'date'],
            'genBoundMode' => ['required', 'in:until,count'],
            'genStartTime' => ['required', 'date_format:H:i'],
            'genEndTime' => ['nullable', 'date_format:H:i'],
        ];
        $rules[$this->genBoundMode === 'until' ? 'genEndDate' : 'genCount'] = $this->genBoundMode === 'until'
            ? ['required', 'date', 'after_or_equal:genStartDate']
            : ['required', 'integer', 'min:1', 'max:104'];
        if (in_array($this->genMode, ['monthly', 'quarterly'], true)) {
            $rules['genMonthlyDayMode'] = ['required', 'in:fixed,weekday'];
            if ($this->genMonthlyDayMode === 'fixed') {
                $rules['genDayOfMonth'] = ['required', 'integer', 'min:1', 'max:31'];
            } else {
                $rules['genOrdinal'] = ['required', 'in:1,2,3,4,-2,-1'];
                $rules['genWeekday'] = ['required', 'integer', 'between:1,7'];
            }
        }
        $this->validate($rules, [], ['genEndDate' => 'einddatum', 'genCount' => 'aantal keer']);

        $end = $this->genBoundMode === 'until' ? Carbon::parse($this->genEndDate)->endOfDay() : null;

        if ($this->genMode === 'weekly') {
            $this->generateWeekly($end);
        } else {
            $this->generateMonthly($end, $this->genMode === 'quarterly' ? 3 : 1);
        }
    }

    private function generateWeekly(?Carbon $end): void
    {
        $cursor = Carbon::parse($this->genStartDate)->startOfDay();
        while ($cursor->dayOfWeekIso !== $this->genWeekday) {
            $cursor->addDay();
        }

        $count = 0;
        while (true) {
            if ($end !== null && $cursor->gt($end)) {
                break;
            }
            if ($this->genCount !== null && $count >= $this->genCount) {
                break;
            }

            $this->pushGeneratedDate($cursor);
            $cursor->addWeek();
            $count++;
        }
    }

    /**
     * Loopt maand voor maand (stap 1 of 3), bepaalt per maand de vaste
     * dag-van-de-maand of de Nde weekdag, en slaat data vóór genStartDate over.
     */
    private function generateMonthly(?Carbon $end, int $stepMonths): void
    {
        $rangeStart = Carbon::parse($this->genStartDate)->startOfDay();
        $monthCursor = $rangeStart->copy()->startOfMonth();
        $count = 0;
        $safety = 0;

        while ($safety++ < 500) {
            if ($this->genCount !== null && $count >= $this->genCount) {
                break;
            }

            $occurrence = $this->genMonthlyDayMode === 'weekday'
                ? $this->nthWeekdayOfMonth($monthCursor, (int) $this->genOrdinal, $this->genWeekday)
                : $monthCursor->copy()->day(min($this->genDayOfMonth ?? 1, $monthCursor->daysInMonth));

            if ($occurrence !== null && $occurrence->gte($rangeStart)) {
                if ($end !== null && $occurrence->gt($end)) {
                    break;
                }
                $this->pushGeneratedDate($occurrence);
                $count++;
            } elseif ($end !== null && $monthCursor->gt($end)) {
                break;
            }

            $monthCursor = $monthCursor->copy()->addMonthsNoOverflow($stepMonths);
        }
    }

    /**
     * $ordinal: 1..4 = eerste..vierde, -2 = voorlaatste, -1 = laatste
     * weekdag (`$isoWeekday`, 1=maandag..7=zondag) van de maand van
     * `$monthAnchor`. Bestaat altijd voor 1..4/-2/-1 (elke maand heeft
     * minstens 4 volle weken).
     */
    private function nthWeekdayOfMonth(Carbon $monthAnchor, int $ordinal, int $isoWeekday): ?Carbon
    {
        $carbonDayOfWeek = $isoWeekday % 7;

        if ($ordinal === -1) {
            return $monthAnchor->copy()->lastOfMonth($carbonDayOfWeek);
        }
        if ($ordinal === -2) {
            return $monthAnchor->copy()->lastOfMonth($carbonDayOfWeek)->subWeek();
        }

        $result = $monthAnchor->copy()->nthOfMonth($ordinal, $carbonDayOfWeek);

        return $result === false ? null : $result;
    }

    private function pushGeneratedDate(Carbon $date): void
    {
        $formatted = $date->format('Y-m-d');
        $this->pendingDates[] = [
            'starts_at' => "{$formatted}T{$this->genStartTime}",
            'ends_at' => $this->genEndTime !== '' ? "{$formatted}T{$this->genEndTime}" : null,
        ];
    }

    public function removePendingDate(int $index): void
    {
        unset($this->pendingDates[$index]);
        $this->pendingDates = array_values($this->pendingDates);
    }

    /** @return array<string, mixed> */
    private function sharedGroupAttributes(): array
    {
        return [
            'activity_category_id' => $this->categoryId,
            'title' => $this->title,
            'description' => $this->description !== '' ? $this->description : null,
            'location' => $this->location !== '' ? $this->location : null,
            'capacity' => $this->capacity,
            'min_capacity' => $this->minCapacity,
            'min_age' => $this->minAge,
            'max_age' => $this->maxAge,
            'publish_from' => $this->publishFrom !== '' ? Carbon::parse($this->publishFrom) : null,
            'publish_until' => $this->publishUntil !== '' ? Carbon::parse($this->publishUntil) : null,
            'enrollment_opens_at' => $this->enrollmentOpensAt !== '' ? Carbon::parse($this->enrollmentOpensAt) : null,
            'enrollment_closes_at' => $this->enrollmentClosesAt !== '' ? Carbon::parse($this->enrollmentClosesAt) : null,
            'cancellation_deadline' => $this->cancellationDeadline !== '' ? Carbon::parse($this->cancellationDeadline) : null,
            'visibility' => $this->visibility,
            'status' => $this->status,
        ];
    }

    /** @return array<string, mixed> */
    private function seriesAttributes(): array
    {
        $attrs = $this->sharedGroupAttributes();
        $attrs['default_capacity'] = $attrs['capacity'];
        unset($attrs['capacity']);
        $attrs['enrollment_level'] = $this->enrollmentLevel;

        return $attrs;
    }

    /** @return array<string, mixed> */
    private function sharedFieldRules(): array
    {
        return [
            'categoryId' => ['required', 'integer', 'exists:activity_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'minCapacity' => ['nullable', 'integer', 'min:0'],
            'minAge' => ['nullable', 'integer', 'min:0', 'max:120'],
            'maxAge' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:minAge'],
            'publishFrom' => ['nullable', 'date'],
            'publishUntil' => ['nullable', 'date', 'after_or_equal:publishFrom'],
            'enrollmentOpensAt' => ['nullable', 'date'],
            'enrollmentClosesAt' => ['nullable', 'date', 'after_or_equal:enrollmentOpensAt'],
            'cancellationDeadline' => ['nullable', 'date'],
            'visibility' => ['required', 'in:public,members,staff'],
            'status' => ['required', 'in:concept,gepubliceerd,afgelast'],
        ];
    }

    /** @return array<string, mixed> */
    private function activityValidationRules(): array
    {
        return $this->sharedFieldRules() + [
            'activityPageId' => ['nullable', 'integer', 'exists:activity_pages,id'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
        ];
    }

    /** @return array<string, mixed> */
    private function groupValidationRules(): array
    {
        return $this->sharedFieldRules() + [
            'enrollmentLevel' => ['required', 'in:bundel,reeks'],
        ];
    }

    /**
     * Nieuwe groep aanmaken met alle data uit de datumlijst.
     */
    public function createGroup(AuditLogger $audit): void
    {
        $rules = $this->groupValidationRules();
        if ($this->creationMode === 'los') {
            $rules += [
                'startsAt' => ['required', 'date'],
                'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            ];
        }
        $this->validate($rules, [
            'startsAt.required' => 'Begindatum en -tijd zijn verplicht.',
            'endsAt.after_or_equal' => 'De einddatum kan niet vóór de begindatum liggen.',
        ]);

        if ($this->creationMode === 'los') {
            $this->pendingDates = [[
                'starts_at' => $this->startsAt,
                'ends_at' => $this->endsAt !== '' ? $this->endsAt : null,
            ]];
        }

        if (count($this->pendingDates) === 0) {
            $this->addError('pendingDates', 'Voeg minstens één datum toe.');

            return;
        }

        DB::transaction(function () use ($audit): void {
            $series = ActivitySeries::query()->create($this->seriesAttributes() + [
                'created_by_person_id' => auth()->user()?->person?->id,
            ]);

            foreach ($this->pendingDates as $date) {
                $this->createOccurrence($series, $date);
            }

            $audit->log('activity_series.created', $series, after: [
                'title' => $series->title,
                'aantal_voorkomens' => count($this->pendingDates),
                'beheerders' => array_column($this->pendingManagers, 'person_id'),
            ]);
            $this->statusMessage = "Activiteit [{$series->title}] aangemaakt met ".count($this->pendingDates).' voorkomen(s).';
        });

        $this->resetForm();
    }

    /**
     * Voegt de datumlijst-data toe als extra voorkomens aan een bestaande groep.
     */
    public function addOccurrences(AuditLogger $audit): void
    {
        $series = ActivitySeries::query()->findOrFail($this->addingOccurrencesToId);

        if (count($this->pendingDates) === 0) {
            $this->addError('pendingDates', 'Voeg minstens één datum toe.');

            return;
        }

        DB::transaction(function () use ($series, $audit): void {
            foreach ($this->pendingDates as $date) {
                $this->createOccurrence($series, $date);
            }
            $audit->log('activity_series.occurrences_added', $series, after: ['aantal' => count($this->pendingDates)]);
        });

        $this->statusMessage = count($this->pendingDates)." voorkomen(s) toegevoegd aan [{$series->title}].";
        $this->resetForm();
    }

    /** @param  array{starts_at: string, ends_at: ?string}  $date */
    private function createOccurrence(ActivitySeries $series, array $date): Activity
    {
        $activity = Activity::query()->create([
            'activity_category_id' => $series->activity_category_id,
            'series_id' => $series->id,
            'title' => $series->title,
            'description' => $series->description,
            'starts_at' => Carbon::parse($date['starts_at']),
            'ends_at' => $date['ends_at'] !== null ? Carbon::parse($date['ends_at']) : null,
            'location' => $series->location,
            'capacity' => $series->default_capacity,
            'min_capacity' => $series->min_capacity,
            'min_age' => $series->min_age,
            'max_age' => $series->max_age,
            'publish_from' => $series->publish_from,
            'publish_until' => $series->publish_until,
            'enrollment_opens_at' => $series->enrollment_opens_at,
            'enrollment_closes_at' => $series->enrollment_closes_at,
            'cancellation_deadline' => $series->cancellation_deadline,
            'visibility' => $series->visibility->value,
            'status' => $series->status->value,
            'created_by_person_id' => auth()->user()?->person?->id,
        ]);

        foreach ($this->pendingManagers as $pm) {
            $activity->managers()->attach($pm['person_id'], ['notify' => $pm['notify']]);
        }

        foreach ($this->pendingManagerGroups as $pmg) {
            $activity->managerGroups()->attach($pmg['approver_group_id'], ['notify' => $pmg['notify']]);
        }

        foreach ($this->pendingRegistrationFields as $i => $pf) {
            $field = $activity->registrationFields()->create([
                'type' => $pf['type'],
                'label' => $pf['label'],
                'required' => $pf['required'],
                'sort_order' => $i,
                'price_per_unit' => $pf['price_per_unit'],
                'max_count' => $pf['max_count'],
            ]);

            foreach ($pf['options'] as $j => $option) {
                $field->options()->create(['label' => $option['label'], 'price' => $option['price'], 'sort_order' => $j]);
            }
        }

        return $activity;
    }

    public function deleteOccurrence(int $activityId, AuditLogger $audit): void
    {
        $activity = Activity::query()->findOrFail($activityId);
        $seriesTitle = $activity->series !== null ? $activity->series->title : '';
        $audit->log('activity_series.occurrence_deleted', $activity, before: ['title' => $activity->title, 'starts_at' => (string) $activity->starts_at]);
        $activity->delete();
        $this->statusMessage = "Voorkomen verwijderd uit [{$seriesTitle}].";
    }

    /**
     * Past de gedeelde velden toe op een bestaande groep, met de gekozen
     * reikwijdte (§17.4): de hele groep, of "dit en volgende" (splitst af).
     */
    public function applyGroupEdit(AuditLogger $audit): void
    {
        $this->validate($this->groupValidationRules());
        $series = ActivitySeries::query()->findOrFail($this->editingGroupId);

        if ($this->editScope === 'dit_en_volgende') {
            if ($this->splitFromActivityId === null) {
                $this->addError('splitFromActivityId', 'Kies vanaf welk voorkomen de wijziging moet ingaan.');

                return;
            }
            $this->applySplitEdit($series, $audit);
        } else {
            $this->applyWholeGroupEdit($series, $audit);
        }

        $this->resetForm();
    }

    private function applyWholeGroupEdit(ActivitySeries $series, AuditLogger $audit): void
    {
        DB::transaction(function () use ($series, $audit): void {
            $before = ['title' => $series->title];
            $series->update($this->seriesAttributes());

            $series->nonExceptionActivities()->update($this->occurrenceSyncAttributes());

            $audit->log('activity_series.updated', $series, before: $before, after: ['title' => $series->title, 'scope' => 'hele_reeks']);
        });

        $this->statusMessage = "Activiteit [{$this->title}] bijgewerkt (hele groep).";
    }

    private function applySplitEdit(ActivitySeries $series, AuditLogger $audit): void
    {
        $pivot = Activity::query()->findOrFail($this->splitFromActivityId);

        DB::transaction(function () use ($series, $pivot, $audit): void {
            $newSeries = ActivitySeries::query()->create($this->seriesAttributes() + [
                'split_from_id' => $series->id,
                'created_by_person_id' => auth()->user()?->person?->id,
            ]);

            $series->nonExceptionActivities()
                ->where('starts_at', '>=', $pivot->starts_at)
                ->update($this->occurrenceSyncAttributes() + ['series_id' => $newSeries->id]);

            $audit->log('activity_series.split', $series, after: [
                'nieuwe_groep_id' => $newSeries->id,
                'vanaf_voorkomen_id' => $pivot->id,
            ]);

            $this->statusMessage = "Groep gesplitst: [{$newSeries->title}] geldt vanaf {$pivot->starts_at->format('d-m-Y')}.";
        });
    }

    /** @return array<string, mixed> */
    private function occurrenceSyncAttributes(): array
    {
        $shared = $this->sharedGroupAttributes();

        return [
            'activity_category_id' => $shared['activity_category_id'],
            'title' => $shared['title'],
            'description' => $shared['description'],
            'location' => $shared['location'],
            'capacity' => $shared['capacity'],
            'min_capacity' => $shared['min_capacity'],
            'min_age' => $shared['min_age'],
            'max_age' => $shared['max_age'],
            'publish_from' => $shared['publish_from'],
            'publish_until' => $shared['publish_until'],
            'enrollment_opens_at' => $shared['enrollment_opens_at'],
            'enrollment_closes_at' => $shared['enrollment_closes_at'],
            'cancellation_deadline' => $shared['cancellation_deadline'],
            'visibility' => $shared['visibility'],
            'status' => $shared['status'],
        ];
    }

    public function deleteGroup(int $id): void
    {
        $series = ActivitySeries::query()->findOrFail($id);
        if ($series->activities()->exists()) {
            $this->statusMessage = "Groep [{$series->title}] kan niet worden verwijderd — verwijder eerst alle voorkomens.";

            return;
        }
        $title = $series->title;
        $series->delete();
        $this->statusMessage = "Groep [{$title}] verwijderd.";
    }

    public function render(): View
    {
        $query = Activity::query()
            ->with(['category', 'enrollments', 'activityPage.page', 'series', 'managers', 'managerGroups', 'files', 'registrationFields.options'])
            ->orderBy('starts_at');

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->hideHistory) {
            $query->where('starts_at', '>=', Carbon::now()->startOfDay());
        }

        $occurrences = $this->editingGroupId !== null
            ? ActivitySeries::query()->findOrFail($this->editingGroupId)->activities()->withCount('enrollments')->get()
            : collect();

        return view('livewire.admin.activiteit-beheer', [
            'activities' => $query->get(),
            'categories' => ActivityCategory::query()->orderBy('sort_order')->get(),
            'activityPages' => ActivityPage::query()->with('page')->get()->sortBy(fn (ActivityPage $e) => $e->page->title),
            'visibilities' => ActivityVisibility::cases(),
            'statuses' => ActivityStatus::cases(),
            'personsForAssignment' => Person::query()->orderBy('last_name')->orderBy('first_name')->limit(500)->get(),
            'groupsForAssignment' => ApproverGroup::query()->orderBy('name')->get(),
            'occurrences' => $occurrences,
            'weekdays' => [1 => 'Maandag', 2 => 'Dinsdag', 3 => 'Woensdag', 4 => 'Donderdag', 5 => 'Vrijdag', 6 => 'Zaterdag', 7 => 'Zondag'],
            'timelineDates' => $this->timelineDates($occurrences),
            'timelinePublishFrom' => $this->safeParse($this->publishFrom),
            'timelinePublishUntil' => $this->safeParse($this->publishUntil),
            'timelineEnrollmentOpensAt' => $this->safeParse($this->enrollmentOpensAt),
            'timelineEnrollmentClosesAt' => $this->safeParse($this->enrollmentClosesAt),
            'timelineCancellationDeadline' => $this->safeParse($this->cancellationDeadline),
        ]);
    }

    /**
     * Datums voor de tijdlijn-preview in het formulier: het los bewerkte
     * voorkomen, de datumlijst tijdens het aanmaken, of de voorkomens van de
     * groep die bewerkt wordt.
     *
     * @return array<int, array{start: ?Carbon, end: ?Carbon}>
     */
    private function timelineDates(Collection $occurrences): array
    {
        if ($this->editingGroupId !== null) {
            $dates = [];
            foreach ($occurrences as $activity) {
                if ($activity instanceof Activity) {
                    $dates[] = ['start' => $activity->starts_at, 'end' => $activity->ends_at];
                }
            }

            return $dates;
        }

        if ($this->editingActivityId !== null || $this->creationMode === 'los') {
            $start = $this->safeParse($this->startsAt);

            return $start !== null ? [[
                'start' => $start,
                'end' => $this->safeParse($this->endsAt),
            ]] : [];
        }

        return collect($this->pendingDates)
            ->map(fn (array $d): array => [
                'start' => $this->safeParse($d['starts_at']),
                'end' => $d['ends_at'] !== null ? $this->safeParse($d['ends_at']) : null,
            ])
            ->filter(fn (array $d): bool => $d['start'] !== null)
            ->all();
    }

    /**
     * Voor de tijdlijn-preview: een datum/tijd zonder ingevulde tijd (bv.
     * "2026-01-15T") mag de rest van de preview niet laten crashen — dan
     * toont de tijdlijn die datum gewoon niet totdat hij geldig is.
     */
    private function safeParse(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }
}
