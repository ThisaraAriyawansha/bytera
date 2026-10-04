/**
 * Format an amount the way every page shows it (mirrors App\Support\Money::format):
 * "Rs. 12,500", or "Rs. 12,500.50" when it has cents.
 */
export function formatMoney(amount) {
    const value = Number(amount) || 0;
    const hasCents = Math.round(value * 100) % 100 !== 0;

    return 'Rs. ' + value.toLocaleString('en-US', {
        minimumFractionDigits: hasCents ? 2 : 0,
        maximumFractionDigits: hasCents ? 2 : 0,
    });
}
