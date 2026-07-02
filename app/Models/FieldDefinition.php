<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * §21.2 — Centrale metadata voor Person-/Membership-velden.
 *
 * @property int $id
 * @property string $field_key
 * @property string $label
 * @property bool $is_hideable
 * @property bool $is_searchable
 * @property bool $is_sensitive
 * @property bool $default_visible
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FieldDefinition extends Model
{
    protected $fillable = [
        'field_key',
        'label',
        'is_hideable',
        'is_searchable',
        'is_sensitive',
        'default_visible',
    ];

    protected function casts(): array
    {
        return [
            'is_hideable' => 'bool',
            'is_searchable' => 'bool',
            'is_sensitive' => 'bool',
            'default_visible' => 'bool',
        ];
    }
}
