<?php

namespace App\Support;

class Money
{
    /**
     * Format an amount the way every page shows it (SPEC §0): "Rs. 12,500", or "Rs. 12,500.50" when it has cents.
     */
    public static function format(float|int|string|null $amount): string
    {
        $value = (float) $amount;
        $decimals = round($value, 2) == round($value) ? 0 : 2;

        return 'Rs. '.number_format($value, $decimals);
    }
}
