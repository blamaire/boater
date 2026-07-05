<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Externe RZVG-omgeving (test, acceptatie, productie) waarnaar we CMS-pagina's
 * kunnen pushen. Token wordt encrypted at rest opgeslagen.
 *
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string $api_token
 * @property bool $is_active
 */
class Environment extends Model
{
    protected $fillable = [
        'name',
        'url',
        'api_token',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'api_token' => 'encrypted',
        ];
    }

    /**
     * Basis-URL zonder trailing slash — handig bij het bouwen van API-endpoints.
     */
    public function baseUrl(): string
    {
        return rtrim($this->url, '/');
    }
}
