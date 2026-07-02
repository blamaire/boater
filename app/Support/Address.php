<?php

namespace App\Support;

/**
 * Waarde-object voor een resolved adres uit BAG.
 */
readonly class Address
{
    public function __construct(
        public string $street,
        public string $houseNumber,
        public ?string $houseNumberAddition,
        public string $postalCode,
        public string $city,
    ) {}

    public function fullHouseNumber(): string
    {
        return $this->houseNumberAddition !== null
            ? $this->houseNumber.' '.$this->houseNumberAddition
            : $this->houseNumber;
    }
}
