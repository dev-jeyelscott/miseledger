import { Form, Head, Link } from '@inertiajs/react';
import { ReceiptText, Search } from 'lucide-react';

import PlatformBillingController from '@/actions/App/Http/Controllers/Platform/PlatformBillingController';
import PlatformOrganizationController from '@/actions/App/Http/Controllers/Platform/PlatformOrganizationController';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import { StatusBadge } from '@/components/status-badge';
import type { StatusBadgeProps } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';

type FilterOption = {
    value: string;
    label: string;
};

type PaymentListItem = {
    id: number;
    organization: {
        id: number;
        name: string;
    };
    provider: string;
    providerLabel: string;
    paymentMethod: string;
    paymentMethodLabel: string;
    currency: string;
    amountMinor: string;
    status: string;
    statusLabel: string;
    captured: boolean;
    livemode: boolean;
    paidAt: string | null;
    failedAt: string | null;
    providerErrorCode: string | null;
    createdAt: string | null;
};

type Pagination = {
    current_page: number;
    from: number | null;
    last_page: number;
    next_page_url: string | null;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

type Filters = {
    search: string;
    status: string | null;
    provider: string | null;
    currency: string | null;
    mode: 'live' | 'test' | null;
    perPage: number;
};

type Props = {
    payments: PaymentListItem[];
    pagination: Pagination;
    filters: Filters;
    filterOptions: {
        statuses: FilterOption[];
        providers: FilterOption[];
        currencies: string[];
    };
};

/** Group an exact integer string for readable display without numeric coercion. */
function groupIntegerDigits(value: string): string {
    return value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/** Format supported two-decimal currencies from exact minor-unit strings without floats. */
function formatMinorAmount(amountMinor: string, currency: string): string {
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

/** Format an absolute server timestamp in the platform operator's local timezone. */
function formatDateTime(value: string | null): string {
    if (value === null) {
        return 'Not available';
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZoneName: 'short',
    }).format(new Date(value));
}

/** Map durable payment statuses to established semantic badge tones. */
function paymentStatusVariant(
    status: string,
): NonNullable<StatusBadgeProps['variant']> {
    switch (status) {
        case 'paid':
            return 'success';
        case 'failed':
            return 'danger';
        case 'pending':
        case 'awaiting_payment':
            return 'info';
        case 'expired':
        case 'cancelled':
        default:
            return 'neutral';
    }
}

/** Render the bounded read-only payment-attempt directory. */
export default function PlatformBillingPayments({
    payments,
    pagination,
    filters,
    filterOptions,
}: Props) {
    const hasQueryState =
        filters.search !== '' ||
        filters.status !== null ||
        filters.provider !== null ||
        filters.currency !== null ||
        filters.mode !== null ||
        filters.perPage !== 25;

    return (
        <>
            <Head title="Platform Payments" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Payments"
                    description="Read-only local payment-attempt history. Failed attempts remain historical evidence and are never treated as captured revenue."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={PlatformBillingController.index()}>
                                    Billing overview
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link
                                    href={PlatformBillingController.subscriptions()}
                                >
                                    Subscriptions
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="rounded-xl border border-info-border bg-info-subtle px-4 py-3 text-sm text-info-foreground">
                    <p className="font-medium">
                        Capture requires two local facts.
                    </p>
                    <p className="mt-1 leading-6">
                        A payment is captured only when status is paid and
                        paid_at is present. Provider request keys, QR URLs,
                        secrets, and external payment identifiers stay
                        server-only.
                    </p>
                </div>

                <section className="overflow-hidden rounded-xl border border-border bg-card">
                    <Form
                        action={PlatformBillingController.payments().url}
                        method="get"
                    >
                        {({ processing }) => (
                            <FilterToolbar className="rounded-b-none border-x-0 border-t-0 shadow-none">
                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(16rem,1fr)_minmax(11rem,13rem)_minmax(10rem,12rem)_minmax(8rem,9rem)_minmax(8rem,9rem)_minmax(8rem,10rem)_auto]">
                                    <div className="relative md:col-span-2 xl:col-span-1">
                                        <label
                                            htmlFor="platform-payment-search"
                                            className="sr-only"
                                        >
                                            Search payments by organization name
                                        </label>
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            id="platform-payment-search"
                                            type="search"
                                            name="search"
                                            defaultValue={filters.search}
                                            placeholder="Search organization…"
                                            className="pl-9"
                                        />
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-payment-status"
                                            className="sr-only"
                                        >
                                            Payment status
                                        </label>
                                        <NativeSelect
                                            id="platform-payment-status"
                                            name="status"
                                            defaultValue={filters.status ?? ''}
                                        >
                                            <option value="">
                                                All statuses
                                            </option>
                                            {filterOptions.statuses.map(
                                                (option) => (
                                                    <option
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ),
                                            )}
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-payment-provider"
                                            className="sr-only"
                                        >
                                            Billing provider
                                        </label>
                                        <NativeSelect
                                            id="platform-payment-provider"
                                            name="provider"
                                            defaultValue={
                                                filters.provider ?? ''
                                            }
                                        >
                                            <option value="">
                                                All providers
                                            </option>
                                            {filterOptions.providers.map(
                                                (option) => (
                                                    <option
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ),
                                            )}
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-payment-currency"
                                            className="sr-only"
                                        >
                                            Currency
                                        </label>
                                        <NativeSelect
                                            id="platform-payment-currency"
                                            name="currency"
                                            defaultValue={
                                                filters.currency ?? ''
                                            }
                                        >
                                            <option value="">
                                                All currencies
                                            </option>
                                            {filterOptions.currencies.map(
                                                (currency) => (
                                                    <option
                                                        key={currency}
                                                        value={currency}
                                                    >
                                                        {currency}
                                                    </option>
                                                ),
                                            )}
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-payment-mode"
                                            className="sr-only"
                                        >
                                            Billing mode
                                        </label>
                                        <NativeSelect
                                            id="platform-payment-mode"
                                            name="mode"
                                            defaultValue={filters.mode ?? ''}
                                        >
                                            <option value="">All modes</option>
                                            <option value="live">Live</option>
                                            <option value="test">Test</option>
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-payment-per-page"
                                            className="sr-only"
                                        >
                                            Results per page
                                        </label>
                                        <NativeSelect
                                            id="platform-payment-per-page"
                                            name="per_page"
                                            defaultValue={String(
                                                filters.perPage,
                                            )}
                                        >
                                            <option value="15">
                                                15 per page
                                            </option>
                                            <option value="25">
                                                25 per page
                                            </option>
                                            <option value="50">
                                                50 per page
                                            </option>
                                        </NativeSelect>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2 md:col-span-2 xl:col-span-1 xl:justify-end">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Applying…'
                                                : 'Apply filters'}
                                        </Button>

                                        {hasQueryState ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={PlatformBillingController.payments()}
                                                >
                                                    Reset
                                                </Link>
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>
                            </FilterToolbar>
                        )}
                    </Form>

                    {payments.length === 0 ? (
                        <EmptyState
                            className="px-4 py-14"
                            icon={ReceiptText}
                            title={
                                hasQueryState
                                    ? 'No payments match these filters'
                                    : 'No payment attempts found'
                            }
                            description={
                                hasQueryState
                                    ? 'Adjust or clear the current filters to broaden the results.'
                                    : 'There are no locally synchronized payment attempts to display.'
                            }
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1180px] text-sm">
                                <caption className="sr-only">
                                    Local billing payment-attempt history
                                </caption>
                                <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="px-4 py-3">
                                            Organization
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Status
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Capture evidence
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right"
                                        >
                                            Amount
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Provider
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Mode
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Method
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Evidence timestamp
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Failure evidence
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-border">
                                    {payments.map((payment) => {
                                        const evidenceTimestamp =
                                            payment.captured
                                                ? payment.paidAt
                                                : payment.status === 'failed'
                                                  ? payment.failedAt
                                                  : payment.createdAt;

                                        return (
                                            <tr
                                                key={payment.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={PlatformOrganizationController.show(
                                                            payment.organization
                                                                .id,
                                                        )}
                                                        className="rounded-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    >
                                                        {
                                                            payment.organization
                                                                .name
                                                        }
                                                    </Link>
                                                </td>

                                                <td className="px-4 py-3">
                                                    <StatusBadge
                                                        label={
                                                            payment.statusLabel
                                                        }
                                                        variant={paymentStatusVariant(
                                                            payment.status,
                                                        )}
                                                    />
                                                </td>

                                                <td className="px-4 py-3">
                                                    <StatusBadge
                                                        label={
                                                            payment.captured
                                                                ? 'Confirmed'
                                                                : payment.status ===
                                                                    'paid'
                                                                  ? 'Paid, unconfirmed'
                                                                  : 'Not captured'
                                                        }
                                                        variant={
                                                            payment.captured
                                                                ? 'success'
                                                                : payment.status ===
                                                                    'paid'
                                                                  ? 'warning'
                                                                  : 'neutral'
                                                        }
                                                    />
                                                </td>

                                                <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                    {formatMinorAmount(
                                                        payment.amountMinor,
                                                        payment.currency,
                                                    )}
                                                </td>

                                                <td className="px-4 py-3">
                                                    {payment.providerLabel}
                                                </td>

                                                <td className="px-4 py-3">
                                                    <StatusBadge
                                                        label={
                                                            payment.livemode
                                                                ? 'Live'
                                                                : 'Test'
                                                        }
                                                        variant={
                                                            payment.livemode
                                                                ? 'success'
                                                                : 'neutral'
                                                        }
                                                    />
                                                </td>

                                                <td className="px-4 py-3">
                                                    {payment.paymentMethodLabel}
                                                </td>

                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {formatDateTime(
                                                        evidenceTimestamp,
                                                    )}
                                                </td>

                                                <td className="px-4 py-3">
                                                    {payment.status ===
                                                    'failed' ? (
                                                        <div>
                                                            <p className="font-mono text-xs">
                                                                {payment.providerErrorCode ??
                                                                    'No provider error code'}
                                                            </p>
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                Failed{' '}
                                                                {formatDateTime(
                                                                    payment.failedAt,
                                                                )}
                                                            </p>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            Not applicable
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <PaginationControls
                        currentPage={pagination.current_page}
                        from={pagination.from}
                        lastPage={pagination.last_page}
                        nextPageUrl={pagination.next_page_url}
                        previousPageUrl={pagination.prev_page_url}
                        to={pagination.to}
                        total={pagination.total}
                        itemLabel="payment attempts"
                        preserveScroll
                        preserveState
                    />
                </section>
            </div>
        </>
    );
}
