import { Head } from '@inertiajs/react';
import { Activity, ExternalLink, Gauge } from 'lucide-react';

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

type Props = {
    pulse: {
        enabled: boolean;
        url: string;
    };
    horizon: {
        url: string;
    };
};

/**
 * Read-only landing page for MiseLedger's application/queue observability.
 * Links to the native Pulse and Horizon dashboards rather than recreating
 * their charts and tables. Both native dashboards independently enforce the
 * same platform-admin boundary as this page.
 */
export default function PlatformObservability({ pulse, horizon }: Props) {
    return (
        <>
            <Head title="Observability" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Observability"
                    description="Application performance and queue-processing insight for platform administrators. This page links to the native Pulse and Horizon dashboards; it does not duplicate their charts or tables, and offers no restart, deploy, flush, or purge controls."
                />

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between gap-2">
                                <CardTitle className="flex items-center gap-2">
                                    <Gauge
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                    Pulse
                                </CardTitle>
                                <Badge
                                    variant={
                                        pulse.enabled ? 'default' : 'secondary'
                                    }
                                >
                                    {pulse.enabled ? 'Enabled' : 'Disabled'}
                                </Badge>
                            </div>
                            <CardDescription>
                                Application performance: requests, exceptions,
                                slow queries, slow outgoing requests, and
                                queue/job throughput. No secrets, credentials,
                                or raw request bodies are recorded.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button asChild>
                                <a
                                    href={pulse.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Open Pulse dashboard
                                    <ExternalLink aria-hidden="true" />
                                </a>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Activity
                                    className="size-5"
                                    aria-hidden="true"
                                />
                                Horizon
                            </CardTitle>
                            <CardDescription>
                                Normal Redis queue processing: supervisors,
                                throughput, wait times, and failed jobs. The
                                hardened AI worker and AI login worker are not
                                managed by Horizon and do not appear here; they
                                run on their own dedicated queues.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button asChild>
                                <a
                                    href={horizon.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Open Horizon dashboard
                                    <ExternalLink aria-hidden="true" />
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
