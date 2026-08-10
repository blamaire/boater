<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eén BTW-code dekt desgewenst beide richtingen tegelijk: hetzelfde
 * percentage boekt bij verkoop op een andere rekening dan bij inkoop.
 * Minstens één van de twee rekeningen moet gezet zijn.
 *
 * @property int $id
 * @property string $name
 * @property string $percentage
 * @property int|null $af_te_dragen_ledger_account_id
 * @property int|null $voor_te_vorderen_ledger_account_id
 * @property Carbon $valid_from
 * @property Carbon|null $valid_until
 * @property-read LedgerAccount|null $afTeDragenLedgerAccount
 * @property-read LedgerAccount|null $voorTeVorderenLedgerAccount
 */
class BtwCode extends Model
{
    protected $fillable = [
        'name',
        'percentage',
        'af_te_dragen_ledger_account_id',
        'voor_te_vorderen_ledger_account_id',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function afTeDragenLedgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'af_te_dragen_ledger_account_id');
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function voorTeVorderenLedgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'voor_te_vorderen_ledger_account_id');
    }

    public function isActiveOn(Carbon $date): bool
    {
        if ($this->valid_from->gt($date)) {
            return false;
        }

        return $this->valid_until === null || $this->valid_until->gte($date);
    }
}
