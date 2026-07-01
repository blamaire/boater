<?php

namespace App\Models;

use App\Enums\BlockType;
use App\Enums\PageVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $band_id
 * @property int $column_index
 * @property int $sort_order
 * @property BlockType $type
 * @property array<string, mixed> $content
 * @property PageVisibility $visibility
 * @property-read Band $band
 */
class Block extends Model
{
    protected $fillable = [
        'band_id',
        'column_index',
        'sort_order',
        'type',
        'content',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'type' => BlockType::class,
            'visibility' => PageVisibility::class,
            'content' => 'array',
            'column_index' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Band, $this> */
    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }
}
