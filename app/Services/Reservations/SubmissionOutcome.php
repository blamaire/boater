<?php

namespace App\Services\Reservations;

use App\Models\Proposal;
use App\Models\Reservation;
use Illuminate\Support\Collection;

/**
 * Uitkomst van {@see ReservationSubmissionService::submit()}. Óf een
 * direct bevestigde reservering, óf een ingediend proposal. Nooit beide.
 */
final class SubmissionOutcome
{
    /**
     * @param  Collection<int, RuleViolation>  $violations
     */
    private function __construct(
        public readonly ?Reservation $reservation,
        public readonly ?Proposal $proposal,
        public readonly bool $needsReviewForOther,
        public readonly Collection $violations,
    ) {}

    public static function forDirect(Reservation $reservation): self
    {
        return new self($reservation, null, false, collect());
    }

    /**
     * @param  Collection<int, RuleViolation>  $violations
     */
    public static function forProposal(Proposal $proposal, bool $needsReviewForOther, Collection $violations): self
    {
        return new self(null, $proposal, $needsReviewForOther, $violations);
    }

    public function wasReviewed(): bool
    {
        return $this->proposal !== null;
    }
}
