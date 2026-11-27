<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\DTOs\Order;

use Daika7ana\Bookurier\Enums\ServiceType;

/**
 * A shipment as expected by add_cmds.php.
 */
final readonly class Order
{
    /**
     * @param string|null $pickupPoint Pickup point number; falls back to ClientConfig::pickupPoint.
     * @param string|null $district County, or "Sector 1".."Sector 6" for Bucharest.
     * @param float|null $cashOnDelivery Cash on delivery amount, in RON.
     * @param float|null $insuredValue Declared value, in RON.
     * @param string|null $number Street number; some partners refuse orders without it.
     */
    public function __construct(
        public string $uniqueId,
        public string $recipientName,
        public string $phone,
        public string $city,
        public string $street,
        public ServiceType $service,
        public float $weight,
        public int $packages = 1,
        public ?string $pickupPoint = null,
        public string $country = 'Romania',
        public ?string $zip = null,
        public ?string $district = null,
        public ?string $email = null,
        public ?string $number = null,
        public ?string $block = null,
        public ?string $entrance = null,
        public ?string $floor = null,
        public ?string $apartment = null,
        public ?string $interphone = null,
        public ?float $cashOnDelivery = null,
        public ?float $insuredValue = null,
        public bool $returnDocument = false,
        public bool $weekend = false,
        public bool $unpack = false,
        public bool $exchangePackage = false,
        public bool $confirmation = false,
        public ?string $notes = null,
        public ?string $ref1 = null,
        public ?string $ref2 = null,
    ) {}

    /**
     * @return array<string, string|int|float>
     */
    public function toArray(): array
    {
        return array_filter([
            'unq' => $this->uniqueId,
            'recv' => $this->recipientName,
            'phone' => $this->phone,
            'email' => $this->email,
            'country' => $this->country,
            'district' => $this->district,
            'city' => $this->city,
            'zip' => $this->zip,
            'street' => $this->street,
            'no' => $this->number,
            'bl' => $this->block,
            'ent' => $this->entrance,
            'floor' => $this->floor,
            'apt' => $this->apartment,
            'interphone' => $this->interphone,
            'service' => $this->service->value,
            'packs' => $this->packages,
            'weight' => $this->weight,
            'rbs_val' => $this->cashOnDelivery,
            'insurance_val' => $this->insuredValue,
            'ret_doc' => $this->returnDocument ? 1 : 0,
            'weekend' => $this->weekend ? 1 : 0,
            'unpack' => $this->unpack ? 1 : 0,
            'exchange_pack' => $this->exchangePackage ? 1 : 0,
            'confirmation' => $this->confirmation ? 1 : 0,
            'notes' => $this->notes,
            'ref1' => $this->ref1,
            'ref2' => $this->ref2,
        ], static fn(mixed $value): bool => $value !== null);
    }
}
