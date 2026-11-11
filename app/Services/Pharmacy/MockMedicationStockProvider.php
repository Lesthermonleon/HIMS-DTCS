<?php

namespace App\Services\Pharmacy;

use App\Models\PrescriptionItem;
use App\Services\Pharmacy\Contracts\MedicationStockProviderInterface;
use App\Services\Pharmacy\DTOs\MedicationStockData;

/**
 * MockMedicationStockProvider — Provides realistic mock medication stock, lot, and expiry data for PMS development.
 * Represents the read-only integration boundary with the external Inventory / Supply Chain subsystem.
 */
class MockMedicationStockProvider implements MedicationStockProviderInterface
{
    /** @var array<int|string, MedicationStockData> Custom overrides for unit/feature tests */
    protected static array $overrides = [];

    /** @var array<int|string, string> Error overrides for unit/feature tests */
    protected static array $errorOverrides = [];

    /**
     * Set explicit mock stock override for a specific medication name or item ID (for testing).
     */
    public static function setMockStock(string|int $key, MedicationStockData $stockData): void
    {
        self::$overrides[$key] = $stockData;
    }

    /**
     * Set explicit stock error override for a specific medication name or item ID (for testing).
     */
    public static function setMockError(string|int $key, string $errorMessage): void
    {
        self::$errorOverrides[$key] = $errorMessage;
    }

    /**
     * Reset test overrides.
     */
    public static function reset(): void
    {
        self::$overrides = [];
        self::$errorOverrides = [];
    }

    public function getMedicationStock(PrescriptionItem $item): MedicationStockData
    {
        // 1. Check for test error override
        if (isset(self::$errorOverrides[$item->id])) {
            return new MedicationStockData(
                medicationName: $item->medication_name,
                availableQuantity: 0,
                lotNumber: '',
                expiryDate: '',
                isAvailable: false,
                errorMessage: self::$errorOverrides[$item->id]
            );
        }

        if (isset(self::$errorOverrides[$item->medication_name])) {
            return new MedicationStockData(
                medicationName: $item->medication_name,
                availableQuantity: 0,
                lotNumber: '',
                expiryDate: '',
                isAvailable: false,
                errorMessage: self::$errorOverrides[$item->medication_name]
            );
        }

        // 2. Check for test stock override
        if (isset(self::$overrides[$item->id])) {
            return self::$overrides[$item->id];
        }

        if (isset(self::$overrides[$item->medication_name])) {
            return self::$overrides[$item->medication_name];
        }

        // 3. Return deterministic realistic mock data based on medication name
        $name = trim($item->medication_name);

        if (stripos($name, 'Paracetamol') !== false) {
            return new MedicationStockData(
                medicationName: $item->medication_name,
                availableQuantity: 100,
                lotNumber: 'PARA-2026-001',
                expiryDate: '2027-12-31',
                isAvailable: true,
                code: 'MED-001',
                genericName: 'Paracetamol',
                brandName: 'Biogesic',
                category: 'Best Seller',
                strength: '500mg',
                dosageForm: 'Tablet',
                unit: 'tablet',
                unitPrice: 8.50,
                reorderLevel: 20,
                barcode: '4800016000010'
            );
        }

        if (stripos($name, 'Solmoux') !== false || stripos($name, 'Solmux') !== false) {
            return new MedicationStockData(
                medicationName: $item->medication_name,
                availableQuantity: 50,
                lotNumber: 'SOL-2026-015',
                expiryDate: '2027-08-31',
                isAvailable: true,
                code: 'MED-003',
                genericName: 'Carbocisteine',
                brandName: 'Solmux',
                category: 'Cough & Cold',
                strength: '500mg',
                dosageForm: 'Capsule',
                unit: 'capsule',
                unitPrice: 14.50,
                reorderLevel: 20,
                barcode: '4800016000034'
            );
        }

        if (stripos($name, 'Alaxan') !== false) {
            return new MedicationStockData(
                medicationName: $item->medication_name,
                availableQuantity: 75,
                lotNumber: 'ALA-2026-008',
                expiryDate: '2028-03-31',
                isAvailable: true,
                code: 'MED-004',
                genericName: 'Ibuprofen + Paracetamol',
                brandName: 'Alaxan FR',
                category: 'Best Seller',
                strength: '200mg/325mg',
                dosageForm: 'Tablet',
                unit: 'tablet',
                unitPrice: 12.00,
                reorderLevel: 20,
                barcode: '4800016000041'
            );
        }

        // Default fallback mock stock for arbitrary medication names
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 4)) ?: 'MED';
        $itemHash = abs(crc32($name . $item->id)) % 800 + 100;

        return new MedicationStockData(
            medicationName: $item->medication_name,
            availableQuantity: 100,
            lotNumber: "{$prefix}-2026-{$itemHash}",
            expiryDate: date('Y-m-d', strtotime('+18 months')),
            isAvailable: true,
            code: "MED-{$itemHash}",
            genericName: $item->medication_name,
            brandName: $item->medication_name,
            category: 'Default',
            strength: $item->dosage ?: '500mg',
            dosageForm: 'Tablet',
            unit: 'unit',
            unitPrice: 15.00,
            reorderLevel: 20,
            barcode: "4800016{$itemHash}"
        );
    }

    public function batchGetMedicationStock(iterable $items): array
    {
        $results = [];
        foreach ($items as $item) {
            $results[$item->id] = $this->getMedicationStock($item);
        }
        return $results;
    }

    public function getAllMedicationStock(): array
    {
        $expiringSoonDate = date('Y-m-d', strtotime('+45 days'));
        $expiredDate = date('Y-m-d', strtotime('-30 days'));

        $defaultCatalog = [
            new MedicationStockData(
                medicationName: 'Paracetamol 500mg Tablet',
                availableQuantity: 120,
                lotNumber: 'PARA-2026-001',
                expiryDate: '2027-12-31',
                isAvailable: true,
                code: 'MED-001',
                genericName: 'Paracetamol',
                brandName: 'Biogesic',
                category: 'Best Seller',
                strength: '500mg',
                dosageForm: 'Tablet',
                unit: 'tablet',
                unitPrice: 8.50,
                reorderLevel: 20,
                barcode: '4800016000010'
            ),
            new MedicationStockData(
                medicationName: 'Amoxicillin 500mg Capsule',
                availableQuantity: 85,
                lotNumber: 'AMOX-2026-042',
                expiryDate: '2027-09-30',
                isAvailable: true,
                code: 'MED-002',
                genericName: 'Amoxicillin Trihydrate',
                brandName: 'Amoxil',
                category: 'Best Seller',
                strength: '500mg',
                dosageForm: 'Capsule',
                unit: 'capsule',
                unitPrice: 18.00,
                reorderLevel: 20,
                barcode: '4800016000027'
            ),
            new MedicationStockData(
                medicationName: 'Solmoux 500mg Capsule',
                availableQuantity: 15,
                lotNumber: 'SOL-2026-015',
                expiryDate: '2027-08-31',
                isAvailable: true,
                code: 'MED-003',
                genericName: 'Carbocisteine',
                brandName: 'Solmux',
                category: 'Cough & Cold',
                strength: '500mg',
                dosageForm: 'Capsule',
                unit: 'capsule',
                unitPrice: 14.50,
                reorderLevel: 20,
                barcode: '4800016000034'
            ),
            new MedicationStockData(
                medicationName: 'Alaxan FR Tablet',
                availableQuantity: 75,
                lotNumber: 'ALA-2026-008',
                expiryDate: '2028-03-31',
                isAvailable: true,
                code: 'MED-004',
                genericName: 'Ibuprofen + Paracetamol',
                brandName: 'Alaxan FR',
                category: 'Best Seller',
                strength: '200mg/325mg',
                dosageForm: 'Tablet',
                unit: 'tablet',
                unitPrice: 12.00,
                reorderLevel: 20,
                barcode: '4800016000041'
            ),
            new MedicationStockData(
                medicationName: 'Cefuroxime 500mg Tablet',
                availableQuantity: 8,
                lotNumber: 'CEF-2026-099',
                expiryDate: $expiringSoonDate,
                isAvailable: true,
                code: 'MED-005',
                genericName: 'Cefuroxime Axetil',
                brandName: 'Zinnat',
                category: 'Others / Special',
                strength: '500mg',
                dosageForm: 'Tablet',
                unit: 'tablet',
                unitPrice: 65.00,
                reorderLevel: 20,
                barcode: '4800016000058'
            ),
            new MedicationStockData(
                medicationName: 'Mefenamic Acid 500mg Tablet',
                availableQuantity: 0,
                lotNumber: 'MEF-2025-012',
                expiryDate: '2027-05-15',
                isAvailable: true,
                code: 'MED-006',
                genericName: 'Mefenamic Acid',
                brandName: 'Ponstan',
                category: 'Default',
                strength: '500mg',
                dosageForm: 'Tablet',
                unit: 'tablet',
                unitPrice: 15.00,
                reorderLevel: 20,
                barcode: '4800016000065'
            ),
            new MedicationStockData(
                medicationName: 'Omeprazole 20mg Capsule',
                availableQuantity: 200,
                lotNumber: 'OME-2026-301',
                expiryDate: date('Y-m-d', strtotime('+20 days')),
                isAvailable: true,
                code: 'MED-007',
                genericName: 'Omeprazole',
                brandName: 'Losec',
                category: 'Gastro / Stomach',
                strength: '20mg',
                dosageForm: 'Capsule',
                unit: 'capsule',
                unitPrice: 22.00,
                reorderLevel: 20,
                barcode: '4800016000072'
            ),
            new MedicationStockData(
                medicationName: 'Losec (Omeprazole 40mg)',
                availableQuantity: 25,
                lotNumber: 'LOS-2026-402',
                expiryDate: '2027-12-31',
                isAvailable: true,
                code: 'MED-007B',
                genericName: 'Omeprazole',
                brandName: 'Losec',
                category: 'Gastro / Stomach',
                strength: '40mg',
                dosageForm: 'Capsule',
                unit: 'capsule',
                unitPrice: 55.00,
                reorderLevel: 10,
                barcode: '4800016000073'
            ),
            new MedicationStockData(
                medicationName: 'Cefalexin 500mg Capsule',
                availableQuantity: 0,
                lotNumber: 'CEF-2026-000',
                expiryDate: '2027-12-31',
                isAvailable: false,
                code: 'MED-018',
                genericName: 'Cefalexin',
                brandName: 'Cefalexin',
                category: 'Others / Special',
                strength: '500mg',
                dosageForm: 'Capsule',
                unit: 'capsule',
                unitPrice: 25.00,
                reorderLevel: 20,
                barcode: '4800016000187'
            ),
            new MedicationStockData(
                medicationName: 'Metformin 500mg Tablet',
                availableQuantity: 5,
                lotNumber: 'MET-2025-888',
                expiryDate: $expiredDate,
                isAvailable: true,
                code: 'MED-008',
                genericName: 'Metformin HCl',
                brandName: 'Glucophage',
                category: 'Default',
                strength: '500mg',
                dosageForm: 'Tablet',
                unit: 'tablet',
                unitPrice: 9.00,
                reorderLevel: 20,
                barcode: '4800016000089'
            ),
            new MedicationStockData(
                medicationName: 'Amlodipine 5mg Tablet',
                availableQuantity: 0,
                lotNumber: 'AML-2025-004',
                expiryDate: date('Y-m-d', strtotime('-60 days')),
                isAvailable: true,
                code: 'MED-009',
                genericName: 'Amlodipine Besylate',
                brandName: 'Norvasc',
                category: 'Others / Special',
                strength: '5mg',
                dosageForm: 'Tablet',
                unit: 'tablet',
                unitPrice: 11.50,
                reorderLevel: 20,
                barcode: '4800016000096'
            ),
            new MedicationStockData(
                medicationName: 'Losartan 50mg Tablet',
                availableQuantity: 60,
                lotNumber: 'LOS-2026-112',
                expiryDate: '2027-11-20',
                isAvailable: true,
                code: 'MED-010',
                genericName: 'Losartan Potassium',
                brandName: 'Cozaar',
                category: 'Vitamins & Supplements',
                strength: '50mg',
                dosageForm: 'Tablet',
                unit: 'tablet',
                unitPrice: 16.00,
                reorderLevel: 20,
                barcode: '4800016000102'
            ),
            new MedicationStockData(
                medicationName: 'Cetirizine 10mg Tablet',
                availableQuantity: 18,
                lotNumber: 'CET-2026-077',
                expiryDate: '2027-10-10',
                isAvailable: true,
                code: 'MED-011',
                genericName: 'Cetirizine Dihydrochloride',
                brandName: 'Virlix',
                category: 'Allergy',
                strength: '10mg',
                dosageForm: 'Tablet',
                unit: 'tablet',
                unitPrice: 12.75,
                reorderLevel: 20,
                barcode: '4800016000119'
            ),
            new MedicationStockData(
                medicationName: 'Azithromycin 500mg Tablet',
                availableQuantity: 40,
                lotNumber: 'AZI-2026-204',
                expiryDate: date('Y-m-d', strtotime('+60 days')),
                isAvailable: true,
                code: 'MED-012',
                genericName: 'Azithromycin Dihydrate',
                brandName: 'Zithromax',
                category: 'Cough & Cold',
                strength: '500mg',
                dosageForm: 'Tablet',
                unit: 'tablet',
                unitPrice: 85.00,
                reorderLevel: 20,
                barcode: '4800016000126'
            ),
        ];

        // Apply test overrides if set
        if (!empty(self::$overrides)) {
            foreach (self::$overrides as $key => $overrideData) {
                if ($overrideData instanceof MedicationStockData) {
                    $found = false;
                    foreach ($defaultCatalog as $idx => $stock) {
                        if (
                            strcasecmp($stock->medicationName, (string)$key) === 0
                            || strcasecmp($stock->medicationName, $overrideData->medicationName) === 0
                            || (!empty($stock->code) && strcasecmp($stock->code, (string)$key) === 0)
                        ) {
                            $defaultCatalog[$idx] = $overrideData;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $defaultCatalog[] = $overrideData;
                    }
                }
            }
        }

        return $defaultCatalog;
    }
}
