import { Delete } from 'lucide-react';

import { Button } from '@/components/ui/button';

type QuantityKeypadProps = {
    value: string;
    onChange: (value: string) => void;
    unitLabel?: string;
    step?: string;
    min?: string;
    scale?: number;
};

const KEY_ROWS = [
    ['1', '2', '3'],
    ['4', '5', '6'],
    ['7', '8', '9'],
    ['.', '0', 'back'],
] as const;

/**
 * Convert a decimal string to integer "units" at a fixed scale using BigInt,
 * so increment/decrement never introduces floating-point error into a
 * quantity that may end up in a stock movement (decision #3.A).
 */
function toUnits(value: string, scale: number): bigint {
    const trimmed = value === '' || value === '-' ? '0' : value;
    const negative = trimmed.startsWith('-');
    const unsigned = negative ? trimmed.slice(1) : trimmed;
    const [wholePart, fractionPart = ''] = unsigned.split('.');
    const paddedFraction = (fractionPart + '0'.repeat(scale)).slice(0, scale);
    const units =
        BigInt(wholePart === '' ? '0' : wholePart) * 10n ** BigInt(scale) +
        BigInt(paddedFraction === '' ? '0' : paddedFraction);

    return negative ? -units : units;
}

function fromUnits(units: bigint, scale: number): string {
    const negative = units < 0n;
    const absolute = negative ? -units : units;
    const divisor = 10n ** BigInt(scale);
    const wholePart = absolute / divisor;
    const fractionPart = (absolute % divisor)
        .toString()
        .padStart(scale, '0')
        .replace(/0+$/, '');
    const magnitude = fractionPart
        ? `${wholePart}.${fractionPart}`
        : `${wholePart}`;

    return negative && absolute !== 0n ? `-${magnitude}` : magnitude;
}

/** Numeric keypad plus a large increment/decrement control for quantity entry, shared by every scan-context workflow. */
function QuantityKeypad({
    value,
    onChange,
    unitLabel,
    step = '1',
    min = '0',
    scale = 3,
}: QuantityKeypadProps) {
    function pressKey(key: string) {
        if (key === 'back') {
            onChange(value.length > 1 ? value.slice(0, -1) : '0');

            return;
        }

        if (key === '.') {
            if (value.includes('.')) {
                return;
            }

            onChange(`${value}.`);

            return;
        }

        onChange(value === '0' ? key : `${value}${key}`);
    }

    function adjust(direction: 1 | -1) {
        const delta = toUnits(step, scale) * BigInt(direction);
        const next = toUnits(value, scale) + delta;
        const floor = toUnits(min, scale);

        onChange(fromUnits(next < floor ? floor : next, scale));
    }

    return (
        <div className="space-y-3" role="group" aria-label="Quantity entry">
            <div className="flex items-center justify-center gap-3">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-12 text-xl"
                    onClick={() => adjust(-1)}
                    aria-label={`Decrease quantity by ${step}`}
                >
                    &minus;
                </Button>

                <div className="min-w-24 text-center">
                    <span className="text-3xl font-semibold tabular-nums">
                        {value || '0'}
                    </span>
                    {unitLabel ? (
                        <span className="ml-1 text-sm text-muted-foreground">
                            {unitLabel}
                        </span>
                    ) : null}
                </div>

                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-12 text-xl"
                    onClick={() => adjust(1)}
                    aria-label={`Increase quantity by ${step}`}
                >
                    +
                </Button>
            </div>

            <div className="grid grid-cols-3 gap-2">
                {KEY_ROWS.flat().map((key) => (
                    <Button
                        key={key}
                        type="button"
                        variant="secondary"
                        className="h-12 text-lg"
                        onClick={() => pressKey(key)}
                        aria-label={key === 'back' ? 'Delete digit' : undefined}
                    >
                        {key === 'back' ? (
                            <Delete className="size-5" aria-hidden="true" />
                        ) : (
                            key
                        )}
                    </Button>
                ))}
            </div>
        </div>
    );
}

export { QuantityKeypad };
export type { QuantityKeypadProps };
