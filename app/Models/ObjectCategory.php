<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $parent_id
 * @property bool $requires_boat_right
 * @property int $sort_order
 * @property-read ObjectCategory|null $parent
 * @property-read Collection<int, ObjectCategory> $children
 * @property-read Collection<int, ReservableObject> $objects
 * @property-read Collection<int, CategoryResponsible> $responsibles
 */
class ObjectCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'requires_boat_right',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_boat_right' => 'bool',
            'sort_order' => 'int',
        ];
    }

    /** @return BelongsTo<ObjectCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ObjectCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<ReservableObject, $this> */
    public function objects(): HasMany
    {
        return $this->hasMany(ReservableObject::class);
    }

    /** @return HasMany<CategoryResponsible, $this> */
    public function responsibles(): HasMany
    {
        return $this->hasMany(CategoryResponsible::class);
    }

    /**
     * Loopt van deze categorie omhoog via parent-chain. Handig voor
     * inheritance zoals CATEGORY_RESPONSIBLE (§22.4) en later voor
     * reserveringsregels (§18.4).
     *
     * @return array<int, self>
     */
    public function ancestors(): array
    {
        $chain = [];
        $node = $this->parent;
        // Bescherm tegen cycles: max 10 niveaus is meer dan realistisch.
        $safety = 10;
        while ($node !== null && $safety-- > 0) {
            $chain[] = $node;
            $node = $node->parent;
        }

        return $chain;
    }
}
