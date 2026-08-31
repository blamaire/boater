<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $field_id
 * @property string $label
 * @property float|null $price
 * @property int $sort_order
 * @property-read ActivityRegistrationField $field
 */
class ActivityRegistrationFieldOption extends Model
{
    protected $fillable = [
        'field_id',
        'label',
        'price',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'sort_order' => 'int',
        ];
    }

    /** @return BelongsTo<ActivityRegistrationField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(ActivityRegistrationField::class, 'field_id');
    }
}
