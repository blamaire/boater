<?php

namespace App\Models;

use App\Enums\ReservationConstraintType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * §18.3 RESERVATION_RULE. Categorie-scoped drempel die geldt inclusief
 * subcategorieën. `per_person=true` betekent per lid; `false` betekent
 * over alle leden samen.
 *
 * @property int $id
 * @property string $name
 * @property int $object_category_id
 * @property ReservationConstraintType $constraint_type
 * @property int $limit_value
 * @property bool $per_person
 * @property-read ObjectCategory $category
 */
class ReservationRule extends Model
{
    protected $fillable = [
        'name',
        'object_category_id',
        'constraint_type',
        'limit_value',
        'per_person',
    ];

    protected function casts(): array
    {
        return [
            'constraint_type' => ReservationConstraintType::class,
            'limit_value' => 'integer',
            'per_person' => 'boolean',
        ];
    }

    /** @return BelongsTo<ObjectCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ObjectCategory::class, 'object_category_id');
    }

    public function humanLabel(): string
    {
        $scope = $this->per_person ? 'per persoon' : 'in totaal';

        return match ($this->constraint_type) {
            ReservationConstraintType::Simultaneous => "max {$this->limit_value} gelijktijdig {$scope}",
            ReservationConstraintType::PerDay => "max {$this->limit_value} per dag {$scope}",
            ReservationConstraintType::Duration => "max {$this->limit_value} minuten duur {$scope}",
        };
    }
}
