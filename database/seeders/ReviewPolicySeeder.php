<?php

namespace Database\Seeders;

use App\Enums\AssigneeType;
use App\Enums\ResubmitBehavior;
use App\Models\ApproverGroup;
use App\Models\ReviewPolicy;
use App\Services\Proposals\Handlers\MembershipApplicationHandler;
use App\Services\Proposals\Handlers\PageVersionProposalHandler;
use App\Services\Proposals\Handlers\PersonFieldUpdateHandler;
use App\Services\Proposals\Handlers\ReservationProposalHandler;
use Illuminate\Database\Seeder;

/**
 * Elke policy wijst naar één van de centrale groepen uit
 * {@see ApproverGroupSeeder}. Beheerders zitten impliciet in alle groepen
 * (afgedwongen in ReviewerResolver) zodat er nooit iets vast komt te
 * zitten wanneer een groep tijdelijk leeg is.
 */
class ReviewPolicySeeder extends Seeder
{
    public function run(): void
    {
        $redactie = $this->groupId('Redactie');
        $ledenAdmin = $this->groupId('Ledenadministratie');
        $materialen = $this->groupId('Materialen');

        ReviewPolicy::updateOrCreate(
            ['subject_type' => PageVersionProposalHandler::SUBJECT_TYPE],
            [
                'name' => 'Publicatie van een pagina',
                'auto_apply' => false,
                'steps' => [
                    ['assignee_type' => AssigneeType::Group->value, 'assignee_id' => $redactie],
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
                    ['assignee_type' => AssigneeType::Group->value, 'assignee_id' => $ledenAdmin],
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
                    ['assignee_type' => AssigneeType::Group->value, 'assignee_id' => $ledenAdmin],
                ],
                'bypass_permission' => 'persons.update',
                'resubmit_behavior' => ResubmitBehavior::Restart,
                'reminder_after_days' => 7,
            ],
        );

        ReviewPolicy::updateOrCreate(
            ['subject_type' => ReservationProposalHandler::SUBJECT_TYPE],
            [
                'name' => 'Reserveringsaanvraag boven drempel of voor een ander',
                'auto_apply' => false,
                'steps' => [
                    ['assignee_type' => AssigneeType::Group->value, 'assignee_id' => $materialen],
                ],
                'bypass_permission' => 'reservations.approve',
                'resubmit_behavior' => ResubmitBehavior::Restart,
                'reminder_after_days' => 3,
            ],
        );
    }

    private function groupId(string $name): int
    {
        return (int) ApproverGroup::query()->where('name', $name)->value('id');
    }
}
