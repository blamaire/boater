<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $hoofdverdichting_id
 * @property string $code
 * @property string $name
 * @property-read Hoofdverdichting $hoofdverdichting
 * @property-read Collection<int, LedgerAccount> $ledgerAccounts
 */
class Verdichting extends Model
{
    protected $table = 'verdichtingen';

    protected $fillable = [
        'hoofdverdichting_id',
        'code',
        'name',
    ];

    /** @return BelongsTo<Hoofdverdichting, $this> */
    public function hoofdverdichting(): BelongsTo
    {
        return $this->belongsTo(Hoofdverdichting::class);
    }

    /** @return HasMany<LedgerAccount, $this> */
    public function ledgerAccounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class);
    }
}
