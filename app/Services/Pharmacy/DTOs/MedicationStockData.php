<?php

namespace App\Services\Pharmacy\DTOs;

/**
 * Data Transfer Object representing medication stock information from an external inventory provider.
 */
class MedicationStockData
{
    public function __construct(
        public string $medicationName,
        public int $availableQuantity,
        public string $lotNumber,
        public string $expiryDate,
        public bool $isAvailable = true,
        public ?string $errorMessage = null,
        public string $code = '',
        public string $genericName = '',
        public string $brandName = '',
        public string $category = 'Default',
        public string $strength = '',
        public string $dosageForm = 'Tablet',
        public string $unit = 'unit',
        public float $unitPrice = 15.00,
        public int $reorderLevel = 20,
        public string $barcode = ''
    ) {}

    /**
     * Check if requested quantity is available in stock.
     */
    public function hasSufficientStock(int $requestedQuantity): bool
    {
        return $this->isAvailable && $this->availableQuantity >= $requestedQuantity;
    }

    /**
     * Check if medication batch is unexpired.
     */
    public function isUnexpired(): bool
    {
        if (empty($this->expiryDate)) {
            return false;
        }

        return strtotime($this->expiryDate) > strtotime(date('Y-m-d'));
    }

    /**
     * Array representation for views and logs.
     */
    public function toArray(): array
    {
        return [
            'code'               => $this->code,
            'medication_name'    => $this->medicationName,
            'generic_name'       => $this->genericName,
            'brand_name'         => $this->brandName,
            'category'           => $this->category,
            'strength'           => $this->strength,
            'dosage_form'        => $this->dosageForm,
            'unit'               => $this->unit,
            'unit_price'         => $this->unitPrice,
            'reorder_level'      => $this->reorderLevel,
            'barcode'            => $this->barcode,
            'available_quantity' => $this->availableQuantity,
            'lot_number'         => $this->lotNumber,
            'expiry_date'        => $this->expiryDate,
            'is_available'       => $this->isAvailable,
            'error_message'      => $this->errorMessage,
        ];
    }
}
