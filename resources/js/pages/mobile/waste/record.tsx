import { Head, router } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useState } from 'react';

import { QuantityKeypad } from '@/components/mobile/quantity-keypad';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import mobileWaste from '@/routes/mobile/waste';
import type { MobileActiveLocation, ScannedItem } from '@/types/mobile';

type WasteReasonOption = {
    id: number;
    name: string;
};

type StorageLocationOption = {
    id: number;
    name: string;
};

type WasteRecordProps = {
    activeLocation: MobileActiveLocation;
    item: ScannedItem;
    storageLocationOptions: StorageLocationOption[];
    wasteReasonOptions: WasteReasonOption[];
    operationId: string;
};

type Step = 'quantity' | 'storage' | 'reason';

/** Scan (already resolved) → Qty → Reason → Save, a single connected screen sequence (Spec 5, decision Waste A / #2 stay-in-scanner). */
export default function WasteRecord({
    activeLocation,
    item,
    storageLocationOptions,
    wasteReasonOptions,
    operationId,
}: WasteRecordProps) {
    const [step, setStep] = useState<Step>('quantity');
    const [quantity, setQuantity] = useState('0');
    const [storageLocationId, setStorageLocationId] = useState<number | null>(
        storageLocationOptions.length === 1
            ? storageLocationOptions[0].id
            : null,
    );
    const [wasteReasonId, setWasteReasonId] = useState<number | null>(null);
    const [notes, setNotes] = useState('');
    const [submitting, setSubmitting] = useState(false);

    function goToNextAfterQuantity() {
        setStep(storageLocationId === null ? 'storage' : 'reason');
    }

    function chooseStorageLocation(id: number) {
        setStorageLocationId(id);
        setStep('reason');
    }

    function save() {
        if (
            submitting ||
            storageLocationId === null ||
            wasteReasonId === null
        ) {
            return;
        }

        setSubmitting(true);

        router.post(
            mobileWaste.store.url(),
            {
                operation_id: operationId,
                storage_location_id: storageLocationId,
                inventory_item_id: item.inventoryItemId,
                waste_reason_id: wasteReasonId,
                quantity,
                unit_id: item.matchedUnit.id,
                notes: notes.trim() === '' ? null : notes,
            },
            { onFinish: () => setSubmitting(false) },
        );
    }

    return (
        <>
            <Head title="Record waste" />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold">{item.name}</h1>
                    <p className="text-sm text-muted-foreground">
                        {item.sku ?? 'No SKU'}
                        {activeLocation ? ` · ${activeLocation.name}` : ''}
                    </p>
                </div>

                {step === 'quantity' ? (
                    <div className="space-y-4">
                        <QuantityKeypad
                            value={quantity}
                            onChange={setQuantity}
                            unitLabel={item.matchedUnit.symbol}
                        />

                        <Button
                            type="button"
                            className="w-full"
                            disabled={quantity === '0' || quantity === ''}
                            onClick={goToNextAfterQuantity}
                        >
                            Next
                        </Button>
                    </div>
                ) : null}

                {step === 'storage' ? (
                    <div
                        className="space-y-2"
                        role="group"
                        aria-label="Storage location"
                    >
                        {storageLocationOptions.map((storageLocation) => (
                            <button
                                key={storageLocation.id}
                                type="button"
                                onClick={() =>
                                    chooseStorageLocation(storageLocation.id)
                                }
                                className="flex min-h-[44px] w-full items-center rounded-md border border-input px-4 py-3 text-left text-sm font-medium hover:bg-accent"
                            >
                                {storageLocation.name}
                            </button>
                        ))}
                    </div>
                ) : null}

                {step === 'reason' ? (
                    <div className="space-y-4">
                        <div
                            className="space-y-2"
                            role="radiogroup"
                            aria-label="Waste reason"
                        >
                            {wasteReasonOptions.map((reason) => {
                                const selected = reason.id === wasteReasonId;

                                return (
                                    <button
                                        key={reason.id}
                                        type="button"
                                        role="radio"
                                        aria-checked={selected}
                                        onClick={() =>
                                            setWasteReasonId(reason.id)
                                        }
                                        className={`flex min-h-[44px] w-full items-center justify-between rounded-md border px-4 py-3 text-left text-sm font-medium hover:bg-accent ${
                                            selected
                                                ? 'border-primary bg-accent'
                                                : 'border-input'
                                        }`}
                                    >
                                        {reason.name}
                                        {selected ? (
                                            <CheckCircle2
                                                className="size-4 text-primary"
                                                aria-hidden="true"
                                            />
                                        ) : null}
                                    </button>
                                );
                            })}
                        </div>

                        <div className="space-y-1">
                            <label
                                htmlFor="waste-notes"
                                className="text-sm font-medium"
                            >
                                Note (optional)
                            </label>
                            <Textarea
                                id="waste-notes"
                                value={notes}
                                onChange={(event) =>
                                    setNotes(event.target.value)
                                }
                                maxLength={2000}
                            />
                        </div>

                        <Button
                            type="button"
                            className="w-full"
                            disabled={submitting || wasteReasonId === null}
                            onClick={save}
                        >
                            Save waste
                        </Button>
                    </div>
                ) : null}
            </div>
        </>
    );
}
