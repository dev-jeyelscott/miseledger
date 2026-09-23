type ScanDebouncer = {
    accept: (value: string) => boolean;
    clear: () => void;
};

/**
 * Require a decoded value to repeat on `requiredRepeats` consecutive frames
 * before it is accepted, so a single blurry/partial frame never triggers a
 * false-positive scan (Spec 2 §"Behavior and Flow" step 2).
 */
function createScanDebouncer(requiredRepeats = 2): ScanDebouncer {
    let lastValue: string | null = null;
    let repeatCount = 0;

    return {
        accept(value: string): boolean {
            if (value === lastValue) {
                repeatCount += 1;
            } else {
                lastValue = value;
                repeatCount = 1;
            }

            return repeatCount >= requiredRepeats;
        },
        clear(): void {
            lastValue = null;
            repeatCount = 0;
        },
    };
}

export { createScanDebouncer };
export type { ScanDebouncer };
