<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Map voor berichtsjablonen (§24.4) — geneste boom. De twee root-mappen
 * ("Systeemberichten"/"Mailings", `is_system = true`) liggen vast: niet
 * aan te maken, te hernoemen of te verwijderen via de beheer-UI. Een
 * sjabloon in de Systeemberichten-boom is transactioneel en niet door een
 * beheerder aan te maken of te verwijderen; alles onder Mailings is
 * redactioneel en vrij te beheren.
 *
 * @property int $id
 * @property string $name
 * @property int|null $parent_id
 * @property bool $is_system
 * @property-read MessageTemplateFolder|null $parent
 * @property-read Collection<int, MessageTemplateFolder> $children
 * @property-read Collection<int, MessageTemplate> $templates
 */
class MessageTemplateFolder extends Model
{
    protected $fillable = ['name', 'parent_id', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'bool'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class, 'message_template_folder_id');
    }

    /**
     * Loopt van deze map omhoog via de parent-chain. Cycle-safety-cap van 10
     * niveaus (zelfde patroon als App\Models\ObjectCategory::ancestors()).
     *
     * @return array<int, self>
     */
    public function ancestors(): array
    {
        $chain = [];
        $node = $this->parent;
        $safety = 10;
        while ($node !== null && $safety-- > 0) {
            $chain[] = $node;
            $node = $node->parent;
        }

        return $chain;
    }

    /**
     * De wortel van de boom waarin deze map hangt — bepaalt of een sjabloon in deze map
     * (of een afstammeling ervan) systeembericht of mailing is.
     */
    public function root(): self
    {
        $ancestors = $this->ancestors();

        return $ancestors === [] ? $this : end($ancestors);
    }
}
