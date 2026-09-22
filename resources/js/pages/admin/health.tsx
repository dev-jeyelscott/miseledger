import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Database,
    HardDrive,
    HelpCircle,
    History,
    RefreshCw,
    Server,
    Timer,
    XCircle,
} from 'lucide-react';
import type { ComponentType } from 'react';
import { useState } from 'react';

import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

type HealthStatus = 'healthy' | 'warning' | 'down' | 'unknown' | 'configured';

type DatabaseHealth = {
    status: HealthStatus;
    source: string;
    checkedAt: string;
    serverVersion: string | null;
    databaseSizeBytes: number | null;
    activeConnections: number | null;
    maxConnections: number | null;
};

type MigrationHealth = {
    status: HealthStatus;
    source: string;
    checkedAt: string;
    appliedCount: number | null;
    pendingCount: number | null;
    pendingMigrations: string[];
};

type SlowQuery = {
    sql: string;
    location: string | null;
    maxDurationMs: number;
    count: number;
};

type SlowQueryHealth = {
    status: HealthStatus;
    source: string;
    checkedAt: string;
    windowHours: number;
    queries: SlowQuery[];
};

type TableGrowthRow = {
    schemaName: string;
    tableName: string;
    currentBytes: number;
    deltaBytes: number | null;
    capturedOn: string;
};

type TableGrowthHealth = {
    status: HealthStatus;
    source: string;
    checkedAt: string;
    lookbackDays: number;
    hasHistory: boolean;
    tables: TableGrowthRow[];
};

type BackupHealth = {
    status: HealthStatus;
    source: string;
    configured: boolean;
    verificationSource: string;
    lastVerifiedRestore: null;
    workflowPath: string;
};

type RedisHealth = {
    status: HealthStatus;
    source: string;
    checkedAt: string;
    latencyMs: number | null;
};

type QueueHealth = {
    status: HealthStatus;
    source: string;
    checkedAt: string;
    activeMasters: number | null;
    pendingJobs: number | null;
    recentlyFailedJobs: number | null;
};

type PlatformHealthProps = {
    database: DatabaseHealth;
    migrations: MigrationHealth;
    slowQueries: SlowQueryHealth;
    tableGrowth: TableGrowthHealth;
    backup: BackupHealth;
    redis: RedisHealth;
    queues: QueueHealth;
};

const statusSeverity: Record<HealthStatus, number> = {
    down: 0,
    warning: 1,
    unknown: 2,
    healthy: 3,
    configured: 3,
};

const statusPresentation: Record<
    HealthStatus,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
        icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    }
> = {
    healthy: { label: 'Healthy', variant: 'default', icon: CheckCircle2 },
    configured: { label: 'Configured', variant: 'default', icon: CheckCircle2 },
    warning: { label: 'Warning', variant: 'secondary', icon: AlertTriangle },
    down: { label: 'Down', variant: 'destructive', icon: XCircle },
    unknown: { label: 'Unknown', variant: 'outline', icon: HelpCircle },
};

function StatusBadge({ status }: { status: HealthStatus }) {
    const { label, variant, icon: Icon } = statusPresentation[status];

    return (
        <Badge variant={variant}>
            <Icon aria-hidden className="size-3" />
            {label}
        </Badge>
    );
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZoneName: 'short',
    }).format(new Date(value));
}

function formatBytes(bytes: number | null): string {
    if (bytes === null) {
        return 'Unavailable';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    return `${value.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

function formatDelta(bytes: number | null): string {
    if (bytes === null) {
        return 'No baseline yet';
    }

    const sign = bytes > 0 ? '+' : bytes < 0 ? '−' : '';

    return `${sign}${formatBytes(Math.abs(bytes))}`;
}

function CheckedAtLine({
    source,
    checkedAt,
}: {
    source: string;
    checkedAt?: string;
}) {
    return (
        <p className="mt-2 text-xs text-muted-foreground">
            Source: {source}
            {checkedAt ? (
                <>
                    {' · '}
                    Checked{' '}
                    <time dateTime={checkedAt}>
                        {formatDateTime(checkedAt)}
                    </time>
                </>
            ) : null}
        </p>
    );
}

/** Read-only PostgreSQL/Redis/queue/migration/backup/growth health surface for platform owners (POC-V8). */
export default function PlatformHealth({
    database,
    migrations,
    slowQueries,
    tableGrowth,
    backup,
    redis,
    queues,
}: PlatformHealthProps) {
    const [refreshing, setRefreshing] = useState(false);

    function refresh(): void {
        if (refreshing) {
            return;
        }

        router.reload({
            only: [
                'database',
                'migrations',
                'slowQueries',
                'tableGrowth',
                'backup',
                'redis',
                'queues',
            ],
            onStart: () => setRefreshing(true),
            onFinish: () => setRefreshing(false),
        });
    }

    const groups = [
        { key: 'database', status: database.status },
        { key: 'redis', status: redis.status },
        { key: 'queues', status: queues.status },
        { key: 'migrations', status: migrations.status },
        { key: 'slowQueries', status: slowQueries.status },
        { key: 'tableGrowth', status: tableGrowth.status },
        { key: 'backup', status: backup.status },
    ].sort((a, b) => statusSeverity[a.status] - statusSeverity[b.status]);

    const order = groups.map((group) => group.key);

    return (
        <>
            <Head title="Platform Health" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Platform Health"
                    description="Read-only database, queue, and infrastructure health signals from bounded, allowlisted probes and existing observability evidence. This page offers no SQL console, deploy, restart, migrate, or repair controls."
                    actions={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={refreshing}
                            onClick={refresh}
                        >
                            <RefreshCw
                                className={cn(
                                    'size-3.5',
                                    refreshing &&
                                        'animate-spin motion-reduce:animate-none',
                                )}
                                aria-hidden="true"
                            />
                            {refreshing ? 'Refreshing…' : 'Refresh'}
                        </Button>
                    }
                />

                <div className="grid gap-4 md:grid-cols-2">
                    {order.includes('database') && (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between gap-2">
                                    <CardTitle className="flex items-center gap-2">
                                        <Database
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                        Database
                                    </CardTitle>
                                    <StatusBadge status={database.status} />
                                </div>
                                <CardDescription>
                                    PostgreSQL connectivity, version, size, and
                                    connection capacity from a fixed,
                                    allowlisted catalog probe.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Server version
                                        </dt>
                                        <dd className="break-words">
                                            {database.serverVersion ??
                                                'Unavailable'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Database size
                                        </dt>
                                        <dd className="tabular-nums">
                                            {formatBytes(
                                                database.databaseSizeBytes,
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Active connections
                                        </dt>
                                        <dd className="tabular-nums">
                                            {database.activeConnections ??
                                                'Unavailable'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Max connections
                                        </dt>
                                        <dd className="tabular-nums">
                                            {database.maxConnections ??
                                                'Unavailable'}
                                        </dd>
                                    </div>
                                </dl>
                                <CheckedAtLine
                                    source={database.source}
                                    checkedAt={database.checkedAt}
                                />
                            </CardContent>
                        </Card>
                    )}

                    {order.includes('redis') && (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between gap-2">
                                    <CardTitle className="flex items-center gap-2">
                                        <Server
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                        Redis
                                    </CardTitle>
                                    <StatusBadge status={redis.status} />
                                </div>
                                <CardDescription>
                                    Bounded PING against the default Redis
                                    connection. Redis health does not imply
                                    business-data health; PostgreSQL remains
                                    authoritative.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm">
                                    Latency:{' '}
                                    <span className="tabular-nums">
                                        {redis.latencyMs !== null
                                            ? `${redis.latencyMs} ms`
                                            : 'Unavailable'}
                                    </span>
                                </p>
                                <CheckedAtLine
                                    source={redis.source}
                                    checkedAt={redis.checkedAt}
                                />
                            </CardContent>
                        </Card>
                    )}

                    {order.includes('queues') && (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between gap-2">
                                    <CardTitle className="flex items-center gap-2">
                                        <Timer
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                        Queues (Horizon)
                                    </CardTitle>
                                    <StatusBadge status={queues.status} />
                                </div>
                                <CardDescription>
                                    Normal Redis queue processing only. The
                                    hardened AI worker and AI login worker run
                                    their own dedicated queues and are not
                                    reported here.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid grid-cols-3 gap-3 text-sm">
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Active masters
                                        </dt>
                                        <dd className="tabular-nums">
                                            {queues.activeMasters ??
                                                'Unavailable'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Pending jobs
                                        </dt>
                                        <dd className="tabular-nums">
                                            {queues.pendingJobs ??
                                                'Unavailable'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Recently failed
                                        </dt>
                                        <dd className="tabular-nums">
                                            {queues.recentlyFailedJobs ??
                                                'Unavailable'}
                                        </dd>
                                    </div>
                                </dl>
                                <CheckedAtLine
                                    source={queues.source}
                                    checkedAt={queues.checkedAt}
                                />
                            </CardContent>
                        </Card>
                    )}

                    {order.includes('migrations') && (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between gap-2">
                                    <CardTitle className="flex items-center gap-2">
                                        <History
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                        Migrations
                                    </CardTitle>
                                    <StatusBadge status={migrations.status} />
                                </div>
                                <CardDescription>
                                    Applied/pending state from the Laravel
                                    migration repository. No migration,
                                    rollback, or schema action runs from this
                                    page.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm">
                                    Applied:{' '}
                                    <span className="tabular-nums">
                                        {migrations.appliedCount ??
                                            'Unavailable'}
                                    </span>{' '}
                                    · Pending:{' '}
                                    <span className="tabular-nums">
                                        {migrations.pendingCount ??
                                            'Unavailable'}
                                    </span>
                                </p>
                                {migrations.pendingMigrations.length > 0 && (
                                    <ul className="mt-2 space-y-1 font-mono text-xs text-muted-foreground">
                                        {migrations.pendingMigrations.map(
                                            (name) => (
                                                <li
                                                    key={name}
                                                    className="break-all"
                                                >
                                                    {name}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                                <CheckedAtLine
                                    source={migrations.source}
                                    checkedAt={migrations.checkedAt}
                                />
                            </CardContent>
                        </Card>
                    )}

                    {order.includes('slowQueries') && (
                        <Card className="md:col-span-2">
                            <CardHeader>
                                <div className="flex items-center justify-between gap-2">
                                    <CardTitle className="flex items-center gap-2">
                                        <Timer
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                        Slow queries
                                    </CardTitle>
                                    <StatusBadge status={slowQueries.status} />
                                </div>
                                <CardDescription>
                                    Bounded top{' '}
                                    {slowQueries.queries.length > 0
                                        ? slowQueries.queries.length
                                        : ''}{' '}
                                    slow queries from Pulse over the last{' '}
                                    {slowQueries.windowHours} hours. Fingerprint
                                    and file location only; no bind values or
                                    customer content.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {slowQueries.status === 'unknown' ? (
                                    <div className="px-4 py-8 text-center">
                                        <p className="font-medium">
                                            No evidence available
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Pulse is disabled or its data is
                                            currently unavailable.
                                        </p>
                                    </div>
                                ) : slowQueries.queries.length === 0 ? (
                                    <div className="px-4 py-8 text-center">
                                        <p className="font-medium">
                                            No slow queries recorded
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            No query exceeded the configured
                                            threshold in this window.
                                        </p>
                                    </div>
                                ) : (
                                    <ul className="divide-y divide-border">
                                        {slowQueries.queries.map(
                                            (query, index) => (
                                                <li
                                                    key={index}
                                                    className="grid gap-2 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate font-mono text-xs">
                                                            {query.sql}
                                                        </p>
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {query.location ??
                                                                'Location unavailable'}
                                                        </p>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground tabular-nums sm:text-right">
                                                        max{' '}
                                                        {query.maxDurationMs}
                                                        ms · {query.count}{' '}
                                                        occurrence
                                                        {query.count === 1
                                                            ? ''
                                                            : 's'}
                                                    </p>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                                <CheckedAtLine
                                    source={slowQueries.source}
                                    checkedAt={slowQueries.checkedAt}
                                />
                            </CardContent>
                        </Card>
                    )}

                    {order.includes('tableGrowth') && (
                        <Card className="md:col-span-2">
                            <CardHeader>
                                <div className="flex items-center justify-between gap-2">
                                    <CardTitle className="flex items-center gap-2">
                                        <HardDrive
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                        Storage growth
                                    </CardTitle>
                                    <StatusBadge status={tableGrowth.status} />
                                </div>
                                <CardDescription>
                                    Current table size and delta over the last{' '}
                                    {tableGrowth.lookbackDays} days, from daily
                                    catalog-metadata snapshots. No table
                                    contents are read.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {tableGrowth.tables.length === 0 ? (
                                    <div className="px-4 py-8 text-center">
                                        <p className="font-medium">
                                            No growth history yet
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            The first daily snapshot has not
                                            been captured yet.
                                        </p>
                                    </div>
                                ) : (
                                    <ul className="divide-y divide-border">
                                        {tableGrowth.tables.map((row) => (
                                            <li
                                                key={`${row.schemaName}.${row.tableName}`}
                                                className="grid gap-2 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start"
                                            >
                                                <p className="font-mono text-xs break-words">
                                                    {row.schemaName}.
                                                    {row.tableName}
                                                </p>
                                                <p className="text-xs text-muted-foreground tabular-nums sm:text-right">
                                                    {formatBytes(
                                                        row.currentBytes,
                                                    )}{' '}
                                                    (
                                                    {tableGrowth.hasHistory
                                                        ? formatDelta(
                                                              row.deltaBytes,
                                                          )
                                                        : 'No growth history yet'}
                                                    )
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <CheckedAtLine
                                    source={tableGrowth.source}
                                    checkedAt={tableGrowth.checkedAt}
                                />
                            </CardContent>
                        </Card>
                    )}

                    {order.includes('backup') && (
                        <Card className="md:col-span-2">
                            <CardHeader>
                                <div className="flex items-center justify-between gap-2">
                                    <CardTitle className="flex items-center gap-2">
                                        <HardDrive
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                        Backup / restore readiness
                                    </CardTitle>
                                    <StatusBadge status={backup.status} />
                                </div>
                                <CardDescription>
                                    Backup capability is configured server-side;
                                    MiseLedger does not fabricate a last-success
                                    value. Verified restore evidence lives in
                                    the monthly/on-demand GitHub Actions
                                    restore-readiness workflow job summary (
                                    <code className="font-mono">
                                        {backup.workflowPath}
                                    </code>
                                    ), not in this application.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm">
                                    Backup destination configured:{' '}
                                    {backup.configured ? 'Yes' : 'No'}
                                </p>
                                <p className="mt-1 text-sm">
                                    Last verified restore: external evidence
                                    (see workflow job summary)
                                </p>
                                <CheckedAtLine source={backup.source} />
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}
