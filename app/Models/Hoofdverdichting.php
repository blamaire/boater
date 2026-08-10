<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hoogste groeperingsniveau boven het grootboek (RGS-achtig):
 * hoofdverdichting > verdichting > grootboekrekening.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property-read Collection<int, Verdichting> $verdichtingen
 */
class Hoofdverdichting extends Model
{
    protected $table = 'hoofdverdichtingen';

    protected $fillable = [
        'code',
        'name',
    ];

    /** @return HasMany<Verdichting, $this> */
    public function verdichtingen(): HasMany
    {
        return $this->hasMany(Verdichting::class);
    }
}
