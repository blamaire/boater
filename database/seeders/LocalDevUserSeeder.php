<?php

namespace Database\Seeders;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Person;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Zaait drie test-accounts voor lokale ontwikkeling. Wordt alleen aangeroepen
 * als `APP_ENV=local` — zo komen deze accounts nooit per ongeluk op test/acc/
 * productie terecht.
 *
 * Alle drie krijgen wachtwoord `password`.
 */
class LocalDevUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment() !== 'local') {
            return;
        }

        $this->seedUser(
            email: 'beheerder@rzvg.local',
            firstName: 'Bianca',
            lastName: 'Beheerder',
            membershipKey: 'a',
            rolNames: ['Beheerder'],
        );

        $this->seedUser(
            email: 'lid@rzvg.local',
            firstName: 'Lars',
            lastName: 'Lid',
            membershipKey: 'a',
            rolNames: [],
        );

        $this->seedUser(
            email: 'jeugd@rzvg.local',
            firstName: 'Jelte',
            lastName: 'Jeugd',
            dateOfBirth: '2010-04-01',
            membershipKey: 'jeugd',
            rolNames: [],
        );
    }

    /**
     * @param  array<int, string>  $rolNames
     */
    private function seedUser(
        string $email,
        string $firstName,
        string $lastName,
        string $membershipKey,
        array $rolNames,
        ?string $dateOfBirth = null,
    ): void {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $firstName.' '.$lastName,
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::now(),
            ],
        );

        $person = Person::query()->firstOrCreate(
            ['account_id' => $user->id],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'date_of_birth' => $dateOfBirth,
                'status' => 'active',
            ],
        );

        $type = MembershipType::query()->where('key', $membershipKey)->first();
        if ($type !== null && ! $person->memberships()->exists()) {
            Membership::query()->create([
                'person_id' => $person->id,
                'membership_type_id' => $type->id,
                'status' => MembershipStatus::Active,
                'start_date' => Carbon::now()->toDateString(),
                'billing_person_id' => $person->id,
            ]);
        }

        foreach ($rolNames as $rolName) {
            $role = Role::query()->where('name', $rolName)->first();
            if ($role === null) {
                continue;
            }
            $alreadyAssigned = RoleAssignment::query()
                ->where('person_id', $person->id)
                ->where('role_id', $role->id)
                ->where('status', 'active')
                ->exists();
            if ($alreadyAssigned) {
                continue;
            }
            RoleAssignment::query()->create([
                'person_id' => $person->id,
                'role_id' => $role->id,
                'status' => 'active',
                'assigned_at' => Carbon::now(),
            ]);
        }
    }
}
