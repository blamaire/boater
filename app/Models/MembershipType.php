<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property int|null $min_age
 * @property int|null $max_age
 * @property bool $allows_boat_use
 * @property bool $allows_instruction
 * @property int $intro_per_year
 * @property bool $allows_competition
 * @property bool $seasonal_only
 * @property bool $auto_expiry
 * @property bool $requires_proof
 * @property bool $is_partner_type
 * @property string|null $derives_from_key
 * @property int $sort_order
 */
class MembershipType extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'min_age',
        'max_age',
        'allows_boat_use',
        'allows_instruction',
        'intro_per_year',
        'allows_competition',
        'seasonal_only',
        'auto_expiry',
        'requires_proof',
        'is_partner_type',
        'derives_from_key',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_age' => 'int',
            'max_age' => 'int',
            'allows_boat_use' => 'bool',
            'allows_instruction' => 'bool',
            'intro_per_year' => 'int',
            'allows_competition' => 'bool',
            'seasonal_only' => 'bool',
            'auto_expiry' => 'bool',
            'requires_proof' => 'bool',
            'is_partner_type' => 'bool',
            'sort_order' => 'int',
        ];
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return BelongsTo<MembershipType, $this> */
    public function derivesFrom(): BelongsTo
    {
        return $this->belongsTo(MembershipType::class, 'derives_from_key', 'key');
    }
}
