<?php

declare(strict_types=1);

namespace Tmonier\SyliusEinvoicePennylanePlugin\Buyer;

/** The buyer of a Factur-X invoice (BG-7), as far as Pennylane needs it to find or create the customer. */
final readonly class Buyer
{
    public function __construct(
        public string $name,
        public string $address,
        public string $postalCode,
        public string $city,
        public string $countryCode,
        public ?string $siren = null,
        public ?string $vatId = null,
        public ?string $email = null,
    ) {
    }

    /** A buyer with a SIREN or a VAT number is a company, otherwise an individual. */
    public function isCompany(): bool
    {
        return null !== $this->siren || null !== $this->vatId;
    }

    /**
     * The `external_reference` of the Pennylane customer. The Factur-X document does not carry the Sylius customer
     * code, so the reference comes from the document: the SIREN of a company, a fingerprint of the identity and the
     * address of an individual. The same buyer always gets the same reference, so the customer is never duplicated.
     */
    public function reference(): string
    {
        if (null !== $this->siren) {
            return 'siren-' . $this->siren;
        }

        $identity = mb_strtolower(trim($this->name)) . '|' . mb_strtolower(trim($this->postalCode)) . '|' . strtoupper(trim($this->countryCode));

        return 'buyer-' . substr(hash('sha256', $identity), 0, 32);
    }
}
