import type { IScannerControls } from '@zxing/browser';
import { BrowserMultiFormatReader } from '@zxing/browser';
import { Flashlight, SwitchCamera } from 'lucide-react';
import {
    forwardRef,
    useEffect,
    useImperativeHandle,
    useRef,
    useState,
} from 'react';

import { createScanDebouncer } from './scan-debounce';

type CameraScannerHandle = {
    /** Clear the accept-debounce so the same physical item can be re-scanned immediately (decision #2). */
    resetScan: () => void;
};

type CameraScannerProps = {
    onDecode: (value: string) => void;
    onPermissionDenied: () => void;
    /** Pause detection while an action hub / sheet is covering the viewfinder. */
    paused?: boolean;
};

type DetectedBarcodeDetector = {
    detect: (source: CanvasImageSource) => Promise<{ rawValue: string }[]>;
};

declare global {
    interface Window {
        BarcodeDetector?: new (options?: {
            formats?: string[];
        }) => DetectedBarcodeDetector;
    }
}

/**
 * Live camera viewfinder with a barcode detection loop: native `BarcodeDetector`
 * when available (Chrome/Android), `@zxing/browser` otherwise (the primary
 * path on iOS Safari, which has not shipped `BarcodeDetector`).
 */
const CameraScanner = forwardRef<CameraScannerHandle, CameraScannerProps>(
    function CameraScanner(
        { onDecode, onPermissionDenied, paused = false },
        ref,
    ) {
        const videoRef = useRef<HTMLVideoElement>(null);
        const streamRef = useRef<MediaStream | null>(null);
        const zxingControlsRef = useRef<IScannerControls | null>(null);
        const rafRef = useRef<number | null>(null);
        const debouncerRef = useRef(createScanDebouncer());
        const onDecodeRef = useRef(onDecode);
        const pausedRef = useRef(paused);

        const [torchOn, setTorchOn] = useState(false);
        const [torchSupported, setTorchSupported] = useState(false);
        const [canSwitchCamera, setCanSwitchCamera] = useState(false);
        const [facingMode, setFacingMode] = useState<'environment' | 'user'>(
            'environment',
        );

        onDecodeRef.current = onDecode;
        pausedRef.current = paused;

        useImperativeHandle(ref, () => ({
            resetScan: () => debouncerRef.current.clear(),
        }));

        useEffect(() => {
            let cancelled = false;

            function handleDecoded(value: string) {
                if (pausedRef.current) {
                    return;
                }

                if (debouncerRef.current.accept(value)) {
                    onDecodeRef.current(value);
                }
            }

            async function startNativeDetection() {
                const Detector = window.BarcodeDetector;

                if (!Detector || !videoRef.current) {
                    return;
                }

                const detector = new Detector();

                const loop = async () => {
                    if (cancelled || !videoRef.current) {
                        return;
                    }

                    try {
                        const results = await detector.detect(videoRef.current);

                        if (results.length > 0) {
                            handleDecoded(results[0].rawValue);
                        }
                    } catch {
                        // Transient detection failures between frames are expected.
                    }

                    rafRef.current = requestAnimationFrame(loop);
                };

                rafRef.current = requestAnimationFrame(loop);
            }

            async function startZxingDetection(stream: MediaStream) {
                if (!videoRef.current) {
                    return;
                }

                const reader = new BrowserMultiFormatReader();

                // Reuse the already-acquired stream (rather than
                // decodeFromVideoDevice, which would open a second one) so
                // torch and camera-switch controls stay consistent across
                // both detection engines.
                const controls = await reader.decodeFromStream(
                    stream,
                    videoRef.current,
                    (result) => {
                        if (result) {
                            handleDecoded(result.getText());
                        }
                    },
                );

                if (cancelled) {
                    controls.stop();

                    return;
                }

                zxingControlsRef.current = controls;
            }

            async function start() {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode },
                    });

                    if (cancelled) {
                        stream.getTracks().forEach((track) => track.stop());

                        return;
                    }

                    streamRef.current = stream;

                    if (videoRef.current) {
                        videoRef.current.srcObject = stream;
                        await videoRef.current.play();
                    }

                    const [track] = stream.getVideoTracks();
                    const capabilities = track?.getCapabilities?.() as
                        | (MediaTrackCapabilities & { torch?: boolean })
                        | undefined;
                    setTorchSupported(Boolean(capabilities?.torch));

                    const devices =
                        await navigator.mediaDevices.enumerateDevices();
                    setCanSwitchCamera(
                        devices.filter((device) => device.kind === 'videoinput')
                            .length > 1,
                    );

                    if (
                        typeof window !== 'undefined' &&
                        'BarcodeDetector' in window &&
                        window.BarcodeDetector
                    ) {
                        await startNativeDetection();
                    } else {
                        await startZxingDetection(stream);
                    }
                } catch {
                    onPermissionDenied();
                }
            }

            start();

            return () => {
                cancelled = true;

                if (rafRef.current !== null) {
                    cancelAnimationFrame(rafRef.current);
                    rafRef.current = null;
                }

                zxingControlsRef.current?.stop();
                zxingControlsRef.current = null;

                streamRef.current?.getTracks().forEach((track) => track.stop());
                streamRef.current = null;
            };
        }, [facingMode, onPermissionDenied]);

        function toggleTorch() {
            const [track] = streamRef.current?.getVideoTracks() ?? [];

            if (!track) {
                return;
            }

            const next = !torchOn;

            track
                .applyConstraints({
                    advanced: [{ torch: next } as MediaTrackConstraintSet],
                })
                .then(() => setTorchOn(next))
                .catch(() => undefined);
        }

        function switchCamera() {
            setFacingMode((current) =>
                current === 'environment' ? 'user' : 'environment',
            );
        }

        return (
            <div className="relative aspect-[3/4] w-full overflow-hidden rounded-lg bg-black">
                <video
                    ref={videoRef}
                    className="size-full object-cover"
                    muted
                    playsInline
                    autoPlay
                />

                <div
                    className="pointer-events-none absolute inset-8 rounded-lg border-2 border-white/70"
                    aria-hidden="true"
                />

                <div className="absolute right-3 bottom-3 flex gap-2">
                    {canSwitchCamera ? (
                        <button
                            type="button"
                            onClick={switchCamera}
                            className="flex size-11 items-center justify-center rounded-full bg-black/60 text-white"
                        >
                            <SwitchCamera
                                className="size-5"
                                aria-hidden="true"
                            />
                            <span className="sr-only">Switch camera</span>
                        </button>
                    ) : null}

                    {torchSupported ? (
                        <button
                            type="button"
                            onClick={toggleTorch}
                            aria-pressed={torchOn}
                            className="flex size-11 items-center justify-center rounded-full bg-black/60 text-white"
                        >
                            <Flashlight className="size-5" aria-hidden="true" />
                            <span className="sr-only">Toggle flashlight</span>
                        </button>
                    ) : null}
                </div>
            </div>
        );
    },
);

export { CameraScanner };
export type { CameraScannerHandle };
