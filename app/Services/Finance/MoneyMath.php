<?php

namespace App\Services\Finance;

class MoneyMath
{
    /**
     * Compute line tax using bcmath for decimal safety.
     * Scale 4 for intermediate calculations, scale 2 for final currency amounts.
     */
    public static function calculateTax(string|float|int $amount, string|float|int $taxRate): string
    {
        $amountStr  = number_format((float) $amount, 4, '.', '');
        $taxRateStr = number_format((float) $taxRate, 4, '.', '');

        if (function_exists('bcmul')) {
            $tax = bcmul($amountStr, $taxRateStr, 4);
            return number_format((float) $tax, 2, '.', '');
        }

        // Integer-cents fallback
        $cents = (int) round(((float) $amount) * 100);
        $taxCents = (int) round($cents * ((float) $taxRate));
        return number_format($taxCents / 100, 2, '.', '');
    }

    /**
     * Compute total with tax and service charge.
     */
    public static function calculateTotal(
        string|float|int $subtotal,
        string|float|int $taxRate = '0.0000',
        string|float|int $serviceChargeRate = '0.0000'
    ): array {
        $tax = self::calculateTax($subtotal, $taxRate);
        $serviceCharge = self::calculateTax($subtotal, $serviceChargeRate);

        if (function_exists('bcadd')) {
            $sub = number_format((float) $subtotal, 2, '.', '');
            $total = bcadd(bcadd($sub, $tax, 2), $serviceCharge, 2);
        } else {
            $total = number_format(((float) $subtotal) + ((float) $tax) + ((float) $serviceCharge), 2, '.', '');
        }

        return [
            'subtotal'             => number_format((float) $subtotal, 2, '.', ''),
            'tax'                  => $tax,
            'service_charge'       => $serviceCharge,
            'total'                => $total,
        ];
    }
}
