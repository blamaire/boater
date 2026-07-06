<?php

namespace App\Livewire\Admin;

use App\Enums\MembershipStatus;
use App\Models\MembershipType;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Users\UserInvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Beheer-UI voor gebruikers (`/beheer/gebruikers`). Combineert User- en
 * Person-lijst en biedt beheerders:
 *  - nieuwe gebruiker aanmaken (User + Person + Membership + optionele rollen)
 *  - uitnodigingsmail (opnieuw) versturen
 *  - account (de)activeren
 *  - doorlink naar rollen- en rechten-bewerking
 *
 * Permissie: `users.manage`.
 */
#[Layout('layouts.app', ['header' => 'Gebruikers'])]
class GebruikerBeheer extends Component
{
    public bool $showForm = false;

    public string $firstName = '';

    public string $lastNamePrefix = '';

    public string $lastName = '';

    public string $email = '';

    public string $phone = '';

    public string $dateOfBirth = '';

    public ?int $membershipTypeId = null;

    /** @var array<int, int> */
    public array $roleIds = [];

    public ?int $relatedPersonId = null;

    public string $relationType = 'ouder_van';

    public bool $sendInvitationMail = true;

    public ?string $statusMessage = null;

    public string $search = '';

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        if (! $this->showForm) {
            $this->resetForm();
        }
    }

    public function resetForm(): void
    {
        $this->reset([
            'firstName', 'lastNamePrefix', 'lastName', 'email', 'phone',
            'dateOfBirth', 'membershipTypeId', 'roleIds', 'relatedPersonId',
        ]);
        $this->relationType = 'ouder_van';
        $this->sendInvitationMail = true;
    }

    public function save(UserInvitationService $service): void
    {
        $rules = [
            'firstName' => ['required', 'string', 'max:100'],
            'lastNamePrefix' => ['nullable', 'string', 'max:30'],
            'lastName' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'dateOfBirth' => ['nullable', 'date', 'before:today'],
            'membershipTypeId' => ['required', 'integer', 'exists:membership_types,id'],
            'roleIds' => ['array'],
            'roleIds.*' => ['integer', 'exists:roles,id'],
            'sendInvitationMail' => ['boolean'],
        ];

        $type = $this->membershipTypeId !== null
            ? MembershipType::query()->find($this->membershipTypeId)
            : null;

        if ($type !== null && $type->key === 'ouder_verzorger') {
            $rules['relatedPersonId'] = ['required', 'integer', 'exists:persons,id'];
            $rules['relationType'] = ['required', 'in:ouder_van,verzorger_van'];
        }

        $this->validate($rules);

        try {
            $relation = null;
            if ($type !== null && $type->key === 'ouder_verzorger' && $this->relatedPersonId !== null) {
                $relation = [
                    'related_person_id' => $this->relatedPersonId,
                    'type' => $this->relationType,
                ];
            }

            $service->invite(
                personData: [
                    'first_name' => $this->firstName,
                    'last_name_prefix' => $this->lastNamePrefix !== '' ? $this->lastNamePrefix : null,
                    'last_name' => $this->lastName,
                    'email' => $this->email,
                    'phone' => $this->phone !== '' ? $this->phone : null,
                    'date_of_birth' => $this->dateOfBirth !== '' ? $this->dateOfBirth : null,
                ],
                membershipTypeId: (int) $this->membershipTypeId,
                roleIds: array_map('intval', $this->roleIds),
                relation: $relation,
                sendInvitationMail: $this->sendInvitationMail,
                assignedByPersonId: auth()->user()?->person?->id,
            );
        } catch (RuntimeException $e) {
            $this->addError('email', $e->getMessage());

            return;
        }

        $this->statusMessage = $this->sendInvitationMail
            ? "Gebruiker [{$this->email}] aangemaakt en uitgenodigd."
            : "Gebruiker [{$this->email}] aangemaakt (geen mail verstuurd).";

        $this->resetForm();
        $this->showForm = false;
    }

    public function resendInvitation(int $userId, UserInvitationService $service, AuditLogger $audit): void
    {
        $user = User::query()->findOrFail($userId);
        $service->sendInvitation($user);
        $audit->log('user.invitation_resent', $user->person ?? $user, after: ['email' => $user->email]);
        $this->statusMessage = "Nieuwe uitnodiging verstuurd naar [{$user->email}].";
    }

    public function toggleActive(int $userId, AuditLogger $audit): void
    {
        $user = User::query()->findOrFail($userId);
        $before = ['disabled_at' => $user->disabled_at?->toIso8601String()];

        DB::transaction(function () use ($user, $before, $audit): void {
            $user->disabled_at = $user->disabled_at === null ? Carbon::now() : null;
            $user->save();

            $audit->log(
                $user->disabled_at === null ? 'user.reactivated' : 'user.deactivated',
                $user->person ?? $user,
                before: $before,
                after: ['disabled_at' => $user->disabled_at?->toIso8601String()],
            );
        });

        $this->statusMessage = $user->disabled_at === null
            ? "Account [{$user->email}] weer geactiveerd."
            : "Account [{$user->email}] gedeactiveerd.";
    }

    public function render(): View
    {
        $query = User::query()
            ->with([
                'person.memberships' => fn ($q) => $q
                    ->where('status', MembershipStatus::Active->value)
                    ->with('type'),
                // Alleen actieve, niet-verlopen roltoewijzingen laten zien in
                // de overzichtstabel; gedeactiveerde assignments blijven voor
                // audit-doeleinden in de DB maar horen niet in dit overzicht.
                'person.roles' => fn ($q) => $q
                    ->wherePivot('status', 'active')
                    ->where(function ($qq): void {
                        $qq->whereNull('role_assignments.ends_at')
                            ->orWhere('role_assignments.ends_at', '>', now());
                    }),
            ])
            ->orderBy('email');

        if ($this->search !== '') {
            $like = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('email', 'like', $like)
                    ->orWhere('name', 'like', $like);
            });
        }

        return view('livewire.admin.gebruiker-beheer', [
            'users' => $query->get(),
            'membershipTypes' => MembershipType::query()->orderBy('sort_order')->get(),
            'roles' => Role::query()->orderBy('name')->get(),
            'jeugdledenVoorKoppeling' => Person::query()
                ->whereHas('memberships', function ($q): void {
                    $q->where('status', MembershipStatus::Active->value)
                        ->whereHas('type', fn ($qt) => $qt->whereIn('key', ['jeugd', 'aspirant']));
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }
}
