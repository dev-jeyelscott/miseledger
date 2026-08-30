import { Form, Head } from '@inertiajs/react';
import {
    Building2,
    CircleDollarSign,
    Clock3,
    Hash,
    Link2,
    Save,
} from 'lucide-react';
import { useRef, useState } from 'react';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { dashboard } from '@/routes';

type Props = {
    organization: {
        id: number;
        name: string;
        slug: string;
        timezone: string;
        currency: string;
        active: boolean;
    };
    timezoneOptions: string[];
    currencyOptions: string[];
};

export default function OrganizationSettings({
    organization,
    timezoneOptions,
    currencyOptions,
}: Props) {
    const [pendingActive, setPendingActive] = useState(organization.active);
    const [confirmDeactivateOpen, setConfirmDeactivateOpen] = useState(false);
    const confirmedSubmission = useRef(false);

    /** Submit only after the user explicitly confirms deactivating an active organization. */
    function confirmDeactivation(): void {
        const form = document.getElementById('organization-settings-form');

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        confirmedSubmission.current = true;
        setConfirmDeactivateOpen(false);
        form.requestSubmit();
    }
    const overviewItems = [
        {
            label: 'Organization name',
            value: organization.name,
            icon: Building2,
        },
        {
            label: 'Organization ID',
            value: `#${organization.id}`,
            icon: Hash,
        },
        {
            label: 'Slug',
            value: organization.slug,
            icon: Link2,
        },
        {
            label: 'Timezone',
            value: organization.timezone,
            icon: Clock3,
        },
        {
            label: 'Currency',
            value: organization.currency,
            icon: CircleDollarSign,
        },
    ];

    return (
        <>
            <Head title="Organization settings" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="mx-auto w-full max-w-7xl">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Organization settings
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Manage the configuration for {organization.name}.
                    </p>
                </div>

                <div className="mx-auto grid w-full max-w-7xl gap-6 xl:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
                    <Card className="h-fit">
                        <CardHeader>
                            <div className="flex items-start gap-3">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted">
                                    <Building2
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </div>

                                <div className="grid gap-1">
                                    <CardTitle>Organization overview</CardTitle>
                                    <CardDescription>
                                        These details identify the active
                                        organization across MiseLedger.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            {overviewItems.map(
                                ({ label, value, icon: Icon }) => (
                                    <div
                                        key={label}
                                        className="flex items-start gap-3"
                                    >
                                        <Icon
                                            className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />

                                        <div className="min-w-0">
                                            <p className="text-xs font-medium text-muted-foreground">
                                                {label}
                                            </p>
                                            <p className="mt-0.5 text-sm font-medium break-words">
                                                {value}
                                            </p>
                                        </div>
                                    </div>
                                ),
                            )}

                            <div className="border-t pt-4">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Status
                                </p>

                                <Badge
                                    variant="outline"
                                    className="mt-1.5 gap-1.5"
                                >
                                    <span
                                        className={
                                            organization.active
                                                ? 'size-1.5 rounded-full bg-emerald-500'
                                                : 'size-1.5 rounded-full bg-muted-foreground'
                                        }
                                        aria-hidden="true"
                                    />
                                    {organization.active
                                        ? 'Active'
                                        : 'Inactive'}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Organization details</CardTitle>
                            <CardDescription>
                                Update the configuration used throughout this
                                organization.
                            </CardDescription>
                        </CardHeader>

                        <Form
                            id="organization-settings-form"
                            {...OrganizationController.update.form(
                                organization.id,
                            )}
                            className="flex flex-col gap-6"
                            onSubmit={(event) => {
                                if (confirmedSubmission.current) {
                                    confirmedSubmission.current = false;

                                    return;
                                }

                                if (organization.active && !pendingActive) {
                                    event.preventDefault();
                                    setConfirmDeactivateOpen(true);
                                }
                            }}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <CardContent className="space-y-6">
                                        <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(280px,420px)] md:gap-8">
                                            <div>
                                                <Label htmlFor="name">
                                                    Name
                                                </Label>
                                                <p
                                                    id="name-help"
                                                    className="mt-1 text-xs leading-relaxed text-muted-foreground"
                                                >
                                                    The name shown throughout
                                                    MiseLedger.
                                                </p>
                                            </div>

                                            <div className="grid gap-2">
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    defaultValue={
                                                        organization.name
                                                    }
                                                    required
                                                    maxLength={160}
                                                    aria-describedby="name-help"
                                                    aria-invalid={Boolean(
                                                        errors.name,
                                                    )}
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>
                                        </div>

                                        <div className="border-t" />

                                        <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(280px,420px)] md:gap-8">
                                            <div>
                                                <Label htmlFor="slug">
                                                    Slug
                                                </Label>
                                                <p
                                                    id="slug-help"
                                                    className="mt-1 text-xs leading-relaxed text-muted-foreground"
                                                >
                                                    Used as the organization's
                                                    unique URL-friendly
                                                    identifier.
                                                </p>
                                            </div>

                                            <div className="grid gap-2">
                                                <Input
                                                    id="slug"
                                                    name="slug"
                                                    defaultValue={
                                                        organization.slug
                                                    }
                                                    required
                                                    maxLength={160}
                                                    autoComplete="off"
                                                    aria-describedby="slug-help slug-format-help"
                                                    aria-invalid={Boolean(
                                                        errors.slug,
                                                    )}
                                                />
                                                <p
                                                    id="slug-format-help"
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    Letters, numbers, dashes,
                                                    and underscores are accepted
                                                    and normalized to lowercase.
                                                </p>
                                                <InputError
                                                    message={errors.slug}
                                                />
                                            </div>
                                        </div>

                                        <div className="border-t" />

                                        <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(280px,420px)] md:gap-8">
                                            <div>
                                                <Label htmlFor="timezone">
                                                    Timezone
                                                </Label>
                                                <p
                                                    id="timezone-help"
                                                    className="mt-1 text-xs leading-relaxed text-muted-foreground"
                                                >
                                                    Used when displaying and
                                                    interpreting operational
                                                    dates and times. Use a valid
                                                    IANA timezone, for example
                                                    Asia/Manila.
                                                </p>
                                            </div>

                                            <div className="grid gap-2">
                                                <NativeSelect
                                                    id="timezone"
                                                    name="timezone"
                                                    defaultValue={
                                                        organization.timezone
                                                    }
                                                    required
                                                    aria-describedby="timezone-help"
                                                    aria-invalid={Boolean(
                                                        errors.timezone,
                                                    )}
                                                >
                                                    {timezoneOptions.map(
                                                        (timezone) => (
                                                            <option
                                                                key={timezone}
                                                                value={timezone}
                                                            >
                                                                {timezone}
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                                <InputError
                                                    message={errors.timezone}
                                                />
                                            </div>
                                        </div>

                                        <div className="border-t" />

                                        <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(280px,420px)] md:gap-8">
                                            <div>
                                                <Label htmlFor="currency">
                                                    Currency
                                                </Label>
                                                <p
                                                    id="currency-help"
                                                    className="mt-1 text-xs leading-relaxed text-muted-foreground"
                                                >
                                                    The default currency used
                                                    for organization monetary
                                                    values. Use a 3-letter ISO
                                                    currency code, for example
                                                    PHP.
                                                </p>
                                            </div>

                                            <div className="grid gap-2">
                                                <NativeSelect
                                                    id="currency"
                                                    name="currency"
                                                    defaultValue={
                                                        organization.currency
                                                    }
                                                    required
                                                    aria-describedby="currency-help"
                                                    aria-invalid={Boolean(
                                                        errors.currency,
                                                    )}
                                                >
                                                    {currencyOptions.map(
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
                                                <InputError
                                                    message={errors.currency}
                                                />
                                            </div>
                                        </div>

                                        <div className="border-t" />

                                        <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(280px,420px)] md:gap-8">
                                            <div>
                                                <p className="text-sm font-medium">
                                                    Status
                                                </p>
                                                <p
                                                    id="status-help"
                                                    className="mt-1 text-xs leading-relaxed text-muted-foreground"
                                                >
                                                    Controls whether this
                                                    organization is active.
                                                </p>
                                            </div>

                                            <div className="grid gap-2">
                                                <fieldset
                                                    aria-describedby="status-help"
                                                    aria-invalid={Boolean(
                                                        errors.active,
                                                    )}
                                                >
                                                    <legend className="sr-only">
                                                        Organization status
                                                    </legend>

                                                    <div className="grid grid-cols-2 gap-2">
                                                        <label className="cursor-pointer">
                                                            <input
                                                                type="radio"
                                                                name="active"
                                                                value="1"
                                                                defaultChecked={
                                                                    organization.active
                                                                }
                                                                onChange={() =>
                                                                    setPendingActive(
                                                                        true,
                                                                    )
                                                                }
                                                                className="peer sr-only"
                                                                aria-invalid={Boolean(
                                                                    errors.active,
                                                                )}
                                                            />
                                                            <span className="flex h-9 items-center justify-center gap-2 rounded-md border border-input bg-background px-3 text-sm font-medium transition-[color,background-color,border-color,box-shadow] peer-checked:border-primary peer-checked:bg-accent peer-checked:text-accent-foreground peer-focus-visible:border-ring peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50">
                                                                <span
                                                                    className="size-2 rounded-full bg-emerald-500"
                                                                    aria-hidden="true"
                                                                />
                                                                Active
                                                            </span>
                                                        </label>

                                                        <label className="cursor-pointer">
                                                            <input
                                                                type="radio"
                                                                name="active"
                                                                value="0"
                                                                defaultChecked={
                                                                    !organization.active
                                                                }
                                                                onChange={() =>
                                                                    setPendingActive(
                                                                        false,
                                                                    )
                                                                }
                                                                className="peer sr-only"
                                                                aria-invalid={Boolean(
                                                                    errors.active,
                                                                )}
                                                            />
                                                            <span className="flex h-9 items-center justify-center gap-2 rounded-md border border-input bg-background px-3 text-sm font-medium transition-[color,background-color,border-color,box-shadow] peer-checked:border-primary peer-checked:bg-accent peer-checked:text-accent-foreground peer-focus-visible:border-ring peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50">
                                                                <span
                                                                    className="size-2 rounded-full bg-muted-foreground"
                                                                    aria-hidden="true"
                                                                />
                                                                Inactive
                                                            </span>
                                                        </label>
                                                    </div>
                                                </fieldset>

                                                <InputError
                                                    message={errors.active}
                                                />
                                            </div>
                                        </div>
                                    </CardContent>

                                    <CardFooter className="flex-wrap justify-end gap-2 border-t pt-6">
                                        <PreviousPageButton
                                            fallback={dashboard.url()}
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Cancel
                                        </PreviousPageButton>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <Save
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {processing
                                                ? 'Saving...'
                                                : 'Save changes'}
                                        </Button>
                                    </CardFooter>
                                </>
                            )}
                        </Form>
                    </Card>
                </div>
            </div>

            <Dialog
                open={confirmDeactivateOpen}
                onOpenChange={setConfirmDeactivateOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Deactivate this organization?</DialogTitle>
                        <DialogDescription>
                            Deactivating {organization.name} immediately blocks
                            operational access for its members until it is
                            reactivated. This does not affect billing.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Keep editing</Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            onClick={confirmDeactivation}
                        >
                            Deactivate organization
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

OrganizationSettings.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
