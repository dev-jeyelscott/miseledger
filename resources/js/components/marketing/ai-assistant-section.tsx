import { Bot, Lock, Sparkles } from 'lucide-react';

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

/** Render a sanitized, illustrative preview of one AI Assistant answer. */
function AiAssistantPreview() {
    return (
        <div className="overflow-hidden border border-marketing-border/15 bg-marketing-surface shadow-[0_16px_44px_rgba(16,40,58,0.1)]">
            <div className="flex items-center gap-2 border-b border-marketing-border/12 bg-marketing-surface-muted px-4 py-3">
                <Bot
                    className="size-4 text-marketing-accent"
                    aria-hidden="true"
                />
                <p className="text-xs font-bold tracking-[0.1em] text-marketing-muted uppercase">
                    AI Assistant · Illustrative
                </p>
            </div>

            <div className="space-y-4 p-5">
                <div className="ml-auto max-w-[80%] border border-marketing-border/15 bg-marketing-canvas px-3 py-2 text-sm text-marketing-ink">
                    Which items are running low?
                </div>

                <div className="max-w-[88%] border border-marketing-accent/20 bg-[#edf4ef] px-3 py-2.5 text-sm leading-6 text-[#2c4a3d]">
                    <p className="font-semibold">
                        3 items are below their low-stock threshold:
                    </p>
                    <ul className="mt-2 space-y-1 text-[13px]">
                        <li className="flex justify-between">
                            <span>Basil</span>
                            <span>0.3 kg on hand</span>
                        </li>
                        <li className="flex justify-between">
                            <span>Olive Oil</span>
                            <span>4 L on hand</span>
                        </li>
                        <li className="flex justify-between">
                            <span>Chicken Breast</span>
                            <span>2.5 kg on hand</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    );
}

/** Render the split AI Assistant marketing section. */
export function AiAssistantSection() {
    const { ref, isVisible } = useMarketingReveal<HTMLDivElement>();

    return (
        <section className="border-b border-marketing-border/15 bg-marketing-surface-muted">
            <div
                ref={ref}
                className={`marketing-reveal mx-auto grid max-w-[1440px] items-center gap-12 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[1fr_0.9fr] lg:gap-16 lg:px-12 ${isVisible ? 'is-visible' : ''}`}
            >
                <AiAssistantPreview />

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
