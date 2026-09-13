/** Group an exact integer string for readable display without numeric coercion. */
export function groupIntegerDigits(value: string): string {
    const negative = value.startsWith('-');
    const unsigned = negative ? value.slice(1) : value;
    const grouped = unsigned.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return negative ? `-${grouped}` : grouped;
}

/** Format supported two-decimal currencies from exact minor-unit strings. */
export function formatBillingMinorAmount(
    amountMinor: string,
    currency: string,
): string {
    if (!/^-?\d+$/.test(amountMinor)) {
        return `${currency} invalid amount`;
    }

    if (currency !== 'PHP' && currency !== 'USD') {
        return `${currency} ${groupIntegerDigits(amountMinor)} minor units`;
    }

    const negative = amountMinor.startsWith('-');
    const unsigned = negative ? amountMinor.slice(1) : amountMinor;
    const padded = unsigned.padStart(3, '0');
    const major = padded.slice(0, -2);
    const fraction = padded.slice(-2);
    const prefix = negative ? '-' : '';

    return `${currency} ${prefix}${groupIntegerDigits(major)}.${fraction}`;
}
