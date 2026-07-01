<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MakeAdmin extends Command
{
    protected $signature = 'rzvg:make-admin {email : E-mailadres van de bestaande user}';

    protected $description = 'Wijs de "Beheerder"-rol toe aan een user; koppelt indien nodig een Person aan het account.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error("Geen user gevonden met e-mail [{$email}].");

            return self::FAILURE;
        }

        $role = Role::query()->where('name', 'Beheerder')->first();
        if ($role === null) {
            $this->error('Rol "Beheerder" bestaat nog niet — draai eerst `php artisan db:seed --class=RoleSeeder`.');

            return self::FAILURE;
        }

        [$person, $created] = DB::transaction(function () use ($user, $role) {
            $person = $user->person;
            $created = false;
            if ($person === null) {
                [$firstName, $lastName] = $this->splitName((string) $user->name);
                $person = Person::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $user->email,
                    'account_id' => $user->id,
                    'status' => 'active',
                ]);
                $created = true;
            }

            $existing = RoleAssignment::query()
                ->where('person_id', $person->id)
                ->where('role_id', $role->id)
                ->where('status', 'active')
                ->whereNull('deactivated_at')
                ->first();

            if ($existing === null) {
                RoleAssignment::create([
                    'person_id' => $person->id,
                    'role_id' => $role->id,
                    'status' => 'active',
                    'assigned_at' => Carbon::now(),
                ]);
            }

            return [$person, $created];
        });

        if ($created) {
            $this->info("Nieuwe Person aangemaakt voor [{$email}] (#{$person->id}).");
        }
        $this->info("OK — [{$email}] is nu Beheerder.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        if ($parts === [] || $parts === ['']) {
            return ['Beheerder', ''];
        }
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        return [array_shift($parts), implode(' ', $parts)];
    }
}
