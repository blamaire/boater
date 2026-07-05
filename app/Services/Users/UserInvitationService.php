<?php

namespace App\Services\Users;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Person;
use App\Models\PersonRelation;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Maakt een nieuwe gebruiker aan (User + Person + Membership), koppelt
 * eventueel rollen en/of een person-relation (bv. ouder van een jeugdlid),
 * en stuurt de uitnodigingsmail met een wachtwoord-reset-token.
 *
 * Wordt gebruikt door de beheer-UI `/beheer/gebruikers`.
 */
class UserInvitationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{first_name: string, last_name_prefix: ?string, last_name: string, email: string, phone: ?string, date_of_birth: ?string}  $personData
     * @param  array<int, int>  $roleIds
     * @param  array{related_person_id: int, type: string}|null  $relation
     */
    public function invite(
        array $personData,
        int $membershipTypeId,
        array $roleIds,
        ?array $relation,
        bool $sendInvitationMail,
        ?int $assignedByPersonId,
    ): Person {
        $type = MembershipType::query()->findOrFail($membershipTypeId);

        if ($type->key === 'ouder_verzorger' && $relation === null) {
            throw new RuntimeException('Bij het type "Ouder/verzorger" is een gekoppeld lid verplicht.');
        }

        $email = trim($personData['email']);
        if ($email === '') {
            throw new RuntimeException('E-mailadres is verplicht.');
        }

        if (User::query()->where('email', $email)->exists()) {
            throw new RuntimeException("Er bestaat al een account met e-mailadres [{$email}].");
        }

        return DB::transaction(function () use ($personData, $type, $roleIds, $relation, $sendInvitationMail, $assignedByPersonId, $email): Person {
            $user = User::query()->create([
                'name' => trim($personData['first_name'].' '.($personData['last_name_prefix'] ?? '').' '.$personData['last_name']),
                'email' => $email,
                'password' => bcrypt(Str::random(40)),
            ]);
            $user->markEmailAsVerified();

            $person = Person::query()->create([
                'first_name' => $personData['first_name'],
                'last_name_prefix' => $personData['last_name_prefix'] ?? null,
                'last_name' => $personData['last_name'],
                'email' => $email,
                'phone' => $personData['phone'] ?? null,
                'date_of_birth' => $personData['date_of_birth'] ?? null,
                'account_id' => $user->id,
                'status' => 'active',
            ]);

            Membership::query()->create([
                'person_id' => $person->id,
                'membership_type_id' => $type->id,
                'start_date' => Carbon::now()->toDateString(),
                'status' => MembershipStatus::Active,
                'billing_person_id' => $type->is_member ? $person->id : null,
            ]);

            foreach ($roleIds as $roleId) {
                $role = Role::query()->findOrFail($roleId);
                RoleAssignment::query()->create([
                    'person_id' => $person->id,
                    'role_id' => $role->id,
                    'status' => 'active',
                    'assigned_by' => $assignedByPersonId,
                    'assigned_at' => Carbon::now(),
                ]);
            }

            if ($relation !== null) {
                PersonRelation::query()->create([
                    'person_id' => $person->id,
                    'related_person_id' => $relation['related_person_id'],
                    'type' => $relation['type'],
                ]);
            }

            $this->audit->log('user.invited', $person, after: [
                'user_id' => $user->id,
                'email' => $user->email,
                'membership_type' => $type->key,
                'roles' => $roleIds,
                'relation' => $relation,
                'sent_invitation' => $sendInvitationMail,
            ]);

            if ($sendInvitationMail) {
                $this->sendInvitation($user);
            }

            return $person->fresh(['account']) ?? $person;
        });
    }

    /**
     * Verstuur (of hersturen) een uitnodigingsmail met een verse
     * wachtwoord-reset-token voor deze user.
     */
    public function sendInvitation(User $user): void
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($user);
        $user->notify(new AccountInvitation($token));
    }
}
