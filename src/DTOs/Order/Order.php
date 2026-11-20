<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\DTOs\Order;

use Daika7ana\Bookurier\Enums\ServiceType;
use Daika7ana\Bookurier\Support\Xml;

/**
 * A shipment as expected by `add_cmd.php` / `add_cmds.php`.
 */
final readonly class Order
{
    /**
     * @param string|null $district Sector ("Sect 1") or county, as Bookurier expects it.
     * @param float|null $insuredValue Declared value, in RON.
     * @param float|null $cashOnDelivery Cash on delivery amount, in RON.
     */
    public function __construct(
        public string $uniqueId,
        public string $recipientName,
        public string $phone,
        public string $city,
        public string $street,
        public string $number,
        public ServiceType $service,
        public float $weight,
        public int $packages = 1,
        public string $country = 'Romania',
        public ?string $zip = null,
        public ?string $district = null,
        public ?string $email = null,
        public ?string $block = null,
        public ?string $entrance = null,
        public ?string $floor = null,
        public ?string $apartment = null,
        public ?string $interphone = null,
        public bool $exchangePackage = false,
        public ?float $cashOnDelivery = null,
        public bool $confirmation = false,
        public bool $returnDocument = false,
        public ?string $returnDocumentDescription = null,
        public ?float $insuredValue = null,
        public ?string $notes = null,
        public ?int $clientCode = null,
    ) {}

    /**
     * @param int|null $defaultClientCode Used for `client` when this order has no explicit code.
     */
    public function toXml(?int $defaultClientCode = null): string
    {
        return Xml::cmd([
            'client' => $this->clientCode ?? $defaultClientCode,
            'unq' => $this->uniqueId,
            'recv' => $this->recipientName,
            'phone' => $this->phone,
            'email' => $this->email,
            'country' => $this->country,
            'city' => $this->city,
            'zip' => $this->zip,
            'district' => $this->district,
            'street' => $this->street,
            'no' => $this->number,
            'bl' => $this->block,
            'ent' => $this->entrance,
            'floor' => $this->floor,
            'apt' => $this->apartment,
            'interphone' => $this->interphone,
            'service' => $this->service->value,
            'packs' => $this->packages,
            'exchange_pack' => $this->exchangePackage ? 1 : 0,
            'weight' => $this->weight,
            'rbs_val' => $this->cashOnDelivery,
            'confirmation' => $this->confirmation ? 1 : 0,
            'ret_doc' => $this->returnDocument ? 1 : 0,
            'ret_doc_desc' => $this->returnDocumentDescription,
            'insurance_val' => $this->insuredValue,
            'notes' => $this->notes,
        ]);
    }
}
