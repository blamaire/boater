<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Het antwoord van een inschrijver op één `ActivityRegistrationField`.
 * Precies één van text_value/option_id/count_value is gevuld, afhankelijk
 * van het veldtype.
 *
 * @property int $id
 * @property int $enrollment_id
 * @property int $field_id
 * @property string|null $text_value
 * @property int|null $option_id
 * @property int|null $count_value
 * @property-read Enrollment $enrollment
 * @property-read ActivityRegistrationField $field
 * @property-read ActivityRegistrationFieldOption|null $option
 */
class EnrollmentFieldValue extends Model
{
    protected $fillable = [
        'enrollment_id',
        'field_id',
        'text_value',
        'option_id',
        'count_value',
    ];

    protected function casts(): array
    {
        return [
            'count_value' => 'int',
        ];
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<ActivityRegistrationField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(ActivityRegistrationField::class, 'field_id');
    }

    /** @return BelongsTo<ActivityRegistrationFieldOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ActivityRegistrationFieldOption::class, 'option_id');
    }

    /**
     * Indicatieve prijs van dit ene antwoord (Fase C — nog geen echte Charge).
     */
    public function indicativePrice(): float
    {
        return match ($this->field->type) {
            ActivityRegistrationField::TYPE_CHOICE => $this->option !== null ? ($this->option->price ?? 0.0) : 0.0,
            ActivityRegistrationField::TYPE_COUNT => ($this->field->price_per_unit ?? 0.0) * ($this->count_value ?? 0),
            default => 0.0,
        };
    }
}
