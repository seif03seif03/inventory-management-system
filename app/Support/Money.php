<?php

namespace App\Support;

/**
 * Money formatting.
 *
 * Amounts appear on product pages, on receipts, in the reports and in the CSV
 * export, and the currency has already changed once. Keeping the formatting in
 * one place means the next change is a config edit, and means a receipt and a
 * report can never disagree about how the same figure is written.
 *
 * Blade reaches this through the @money directive registered in
 * AppServiceProvider; JavaScript reads the symbol from Money::symbol().
 */
class Money
{
    /**
     * The currency symbol, localised. lang/ar.json maps EGP to ج.م.
     */
    public static function symbol(): string
    {
        return __(config('inventory.currency.symbol', 'EGP'));
    }

    /**
     * An amount with its thousands separators and currency symbol, ready to
     * display. Null is treated as zero so a missing cost renders as an amount
     * rather than a bare symbol.
     */
    public static function format(float|int|string|null $amount): string
    {
        return static::amount($amount) . ' ' . static::symbol();
    }

    /**
     * The number alone — no symbol. For a column that already carries the
     * currency in its heading, so a long report table does not repeat the
     * symbol on every row.
     *
     * Note this groups thousands, so it is not what a CSV cell wants — an
     * export should keep writing a raw, unseparated number.
     */
    public static function amount(float|int|string|null $amount): string
    {
        $decimals = (int) config('inventory.currency.decimals', 2);

        return number_format((float) $amount, $decimals);
    }
}
