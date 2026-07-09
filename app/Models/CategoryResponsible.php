<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $object_category_id
 * @property int $person_id
 * @property-read ObjectCategory $category
 * @property-read Person $person
 */
class CategoryResponsible extends Model
{
    protected $fillable = [
        'object_category_id',
        'person_id',
    ];

    /** @return BelongsTo<ObjectCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ObjectCategory::class, 'object_category_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
