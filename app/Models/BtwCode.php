<?php

namespace App\Models;

use App\Enums\BtwCodeDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $percentage
 * @property BtwCodeDirection $direction
 * @property int $ledger_account_id
 * @property Carbon $valid_from
 * @property Carbon|null $valid_until
 * @property-read LedgerAccount $ledgerAccount
 */
class BtwCode extends Model
{
    protected $fillable = [
        'name',
        'percentage',
        'direction',
        'ledger_account_id',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'direction' => BtwCodeDirection::class,
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function isActiveOn(Carbon $date): bool
    {
        if ($this->valid_from->gt($date)) {
            return false;
        }

        return $this->valid_until === null || $this->valid_until->gte($date);
    }
}
