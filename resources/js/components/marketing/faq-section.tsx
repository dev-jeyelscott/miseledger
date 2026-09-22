import { useMarketingReveal } from '@/lib/marketing-motion';

const FAQS: Array<{ question: string; answer: string }> = [
    {
        question: 'What is MiseLedger?',
        answer: 'MiseLedger is inventory and purchasing software for restaurants, cafés, and other food operations. It helps you track stock, purchasing, receiving, counts, waste, and recipe costs from one workspace.',
    },
    {
        question: 'Can it manage multiple locations?',
        answer: 'Yes. Organize kitchens, outlets, and storage areas under one organization, see stock on hand per location, and record transfers between them.',
    },
    {
        question: 'Can it manage purchasing and receiving?',
        answer: 'Yes. Create purchase orders with suppliers, track approval status, and record goods receipts that update stock on hand.',
    },
    {
        question: 'Can it record counts and waste?',
        answer: 'Yes. Run stock counts against system quantities to review variance, and record waste with a clear reason so it can be reviewed later.',
    },
    {
        question: 'Can it calculate recipe costs?',
        answer: 'Yes. Build recipes from your inventory items and see the resulting cost per recipe as ingredient costs change.',
    },
    {
        question: 'What can the AI Assistant access?',
        answer: "The AI Assistant reads your organization's own stock, purchasing, and supplier records to answer questions. It never writes changes, places orders, or accesses another organization.",
    },
    {
        question: 'How does the trial work?',
        answer: 'New organizations get a free trial period before a subscription is required. You can subscribe from your organization billing settings once you are ready.',
    },
];

/** Render the two-column FAQ section at `#faq`. */
export function FaqSection() {
    const { ref, isVisible } = useMarketingReveal<HTMLDivElement>();

    return (
        <section
            id="faq"
            className="scroll-mt-24 border-b border-marketing-border/15 bg-marketing-canvas"
        >
            <div
                ref={ref}
                className={`marketing-reveal mx-auto grid max-w-[1440px] gap-10 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[0.4fr_0.6fr] lg:gap-16 lg:px-12 ${isVisible ? 'is-visible' : ''}`}
            >
                <div>
                    <p className="text-xs font-bold tracking-[0.16em] text-marketing-muted uppercase">
                        FAQ
                    </p>
                    <h2 className="mt-3 max-w-[14ch] font-serif text-4xl tracking-[-0.035em] text-marketing-ink sm:text-5xl">
                        Questions food teams ask us
                    </h2>
                    <p className="mt-5 max-w-sm text-sm leading-6 text-marketing-muted">
                        Can't find what you're looking for? Create an account
                        and reach out once you're signed in.
                    </p>
                </div>

                <div className="divide-y divide-marketing-border/15 border-y border-marketing-border/15">
                    {FAQS.map(({ question, answer }) => (
                        <details key={question} className="group py-5">
                            <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-semibold text-marketing-ink focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-marketing-focus [&::-webkit-details-marker]:hidden">
                                {question}
                                <span
                                    className="shrink-0 text-lg text-marketing-muted transition group-open:rotate-45"
                                    aria-hidden="true"
                                >
                                    +
                                </span>
                            </summary>
                            <p className="mt-3 max-w-xl text-sm leading-6 text-marketing-muted">
                                {answer}
                            </p>
                        </details>
                    ))}
                </div>
            </div>
        </section>
    );
}
