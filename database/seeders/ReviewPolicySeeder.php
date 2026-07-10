<?php

namespace Database\Seeders;

use App\Enums\AssigneeType;
use App\Enums\ResubmitBehavior;
use App\Models\ReviewPolicy;
use App\Models\Role;
use App\Services\Proposals\Handlers\MembershipApplicationHandler;
use App\Services\Proposals\Handlers\PageVersionProposalHandler;
use App\Services\Proposals\Handlers\PersonFieldUpdateHandler;
use App\Services\Proposals\Handlers\ReservationProposalHandler;
use Illuminate\Database\Seeder;

class ReviewPolicySeeder extends Seeder
{
    public function run(): void
    {
        ReviewPolicy::updateOrCreate(
            ['subject_type' => PageVersionProposalHandler::SUBJECT_TYPE],
            [
                'name' => 'Publicatie van een pagina',
                'auto_apply' => false,
                'steps' => [
                    ['assignee_type' => AssigneeType::Role->value, 'assignee_id' => $this->editorRoleId()],
                ],
                'bypass_permission' => 'pages.publish',
                'resubmit_behavior' => ResubmitBehavior::Restart,
                'reminder_after_days' => 7,
            ],
        );

        ReviewPolicy::updateOrCreate(
            ['subject_type' => MembershipApplicationHandler::SUBJECT_TYPE],
            [
                'name' => 'Aanvraag lidmaatschap',
                'auto_apply' => false,
                'steps' => [
                    ['assignee_type' => AssigneeType::Role->value, 'assignee_id' => $this->beheerderRoleId()],
                ],
                'bypass_permission' => 'memberships.approve',
                'resubmit_behavior' => ResubmitBehavior::Restart,
                'reminder_after_days' => 7,
            ],
        );

        ReviewPolicy::updateOrCreate(
            ['subject_type' => PersonFieldUpdateHandler::SUBJECT_TYPE],
            [
                'name' => 'Wijziging van een gevoelig persoonsgegeven',
                'auto_apply' => false,
                'steps' => [
                    ['assignee_type' => AssigneeType::Role->value, 'assignee_id' => $this->beheerderRoleId()],
                ],
                'bypass_permission' => 'persons.update',
                'resubmit_behavior' => ResubmitBehavior::Restart,
                'reminder_after_days' => 7,
            ],
        );

        // §18.4 — reserveringen komen alleen op deze policy als ze een
        // drempel overschrijden of voor een ander zijn zonder machtiging.
        // De "clean" flow gaat direct via `ReservationService::reserve()`
        // en ziet deze policy nooit.
        ReviewPolicy::updateOrCreate(
            ['subject_type' => ReservationProposalHandler::SUBJECT_TYPE],
            [
                'name' => 'Reserveringsaanvraag boven drempel of voor een ander',
                'auto_apply' => false,
                'steps' => [
                    ['assignee_type' => AssigneeType::Role->value, 'assignee_id' => $this->beheerderRoleId()],
                ],
                'bypass_permission' => 'reservations.approve',
                'resubmit_behavior' => ResubmitBehavior::Restart,
                'reminder_after_days' => 3,
            ],
        );
    }

    private function editorRoleId(): int
    {
        $role = Role::query()->where('name', 'Redacteur')->first();
        if ($role) {
            return $role->id;
        }

        return Role::create(['name' => 'Redacteur'])->id;
    }

    private function beheerderRoleId(): int
    {
        return (int) Role::query()->where('name', 'Beheerder')->value('id');
    }
}
