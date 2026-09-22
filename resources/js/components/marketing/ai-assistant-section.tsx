import { Lock, Sparkles } from 'lucide-react';

import { ZoomableImage } from '@/components/marketing/zoomable-image';
import { useMarketingReveal } from '@/lib/marketing-motion';

const EXAMPLE_QUESTIONS = [
    'What stock is currently on hand?',
    'Which items are running low?',
    'Show recent purchase orders.',
    'Which suppliers appear in recent purchasing records?',
];

const GUARDRAILS = [
    'Reads your organization data only, never another organization',
    'Answers from stock, purchasing, and supplier records, not guesses',
    'Available on plans that include the AI Assistant feature',
];

/** Render the split AI Assistant marketing section. */
export function AiAssistantSection() {
    const { ref, isVisible } = useMarketingReveal<HTMLDivElement>();

    return (
        <section className="border-b border-marketing-border/15 bg-marketing-surface-muted">
            <div
                ref={ref}
                className={`marketing-reveal mx-auto grid max-w-[1280px] items-center gap-12 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[1fr_1fr] lg:gap-16 lg:px-12 lg:py-28 ${isVisible ? 'is-visible' : ''}`}
            >
                <figure className="mx-auto w-full max-w-[460px] overflow-hidden rounded-xl border border-marketing-border/25 bg-marketing-surface p-2 shadow-[0_22px_56px_rgba(16,40,58,0.14)] sm:p-2.5 lg:max-w-[520px]">
                    <ZoomableImage
                        src="/images/marketing/ai-assistant.png"
                        alt="MiseLedger AI Assistant drawer answering a question about which inventory items are running low."
                        width={576}
                        height={1000}
                        className="block h-auto w-full rounded-lg border border-marketing-border/15 bg-marketing-surface"
                    />
                </figure>

                <div>
                    <p className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.16em] text-marketing-muted uppercase">
                        <Sparkles
                            className="size-4 text-marketing-accent"
                            aria-hidden="true"
                        />
                        AI Assistant
                    </p>
                    <h2 className="mt-3 font-serif text-4xl tracking-[-0.035em] text-marketing-ink sm:text-5xl">
                        Ask MiseLedger about your operation
                    </h2>
                    <p className="mt-5 max-w-md text-base leading-7 text-marketing-muted">
                        The AI Assistant answers from your organization's own
                        stock, purchasing, and supplier records so you get a
                        straight answer instead of running a report yourself.
                    </p>

                    <ul className="mt-7 space-y-2.5">
                        {EXAMPLE_QUESTIONS.map((question) => (
                            <li
                                key={question}
                                className="border border-marketing-border/15 bg-marketing-surface px-4 py-2.5 text-sm text-marketing-ink"
                            >
                                {question}
                            </li>
                        ))}
                    </ul>

                    <ul className="mt-7 space-y-2 border-t border-marketing-border/15 pt-6 text-xs leading-5 text-marketing-muted">
                        {GUARDRAILS.map((guardrail) => (
                            <li key={guardrail} className="flex gap-2">
                                <Lock
                                    className="mt-0.5 size-3.5 shrink-0 text-[#75818a]"
                                    aria-hidden="true"
                                />
                                <span>{guardrail}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </section>
    );
}
