<?php

namespace App\Services\Finance;

use App\Models\Membership;

/**
 * Eén regel in de contributie-run-preview (§23, B2): wat een lidmaatschap dit
 * verenigingsjaar zou kosten, en of dat al gefactureerd is.
 */
final readonly class ContributionRunLine
{
    public function __construct(
        public Membership $membership,
        public ?string $amount,
        public bool $isHalfRate,
        public bool $alreadyCharged,
    ) {}
}
