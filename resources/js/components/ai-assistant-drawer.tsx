import { useHttp, usePage } from '@inertiajs/react';
import { Bot } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { AiAssistant } from '@/components/ai-assistant';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { index as aiIndex } from '@/routes/ai';
import type { AiAssistantData, OrganizationContext } from '@/types';

export function AiAssistantDrawer() {
    const { organizationContext } = usePage<{
        organizationContext: OrganizationContext;
    }>().props;
    const [open, setOpen] = useState(false);
    const [assistantData, setAssistantData] = useState<{
        organizationId: number | null;
        data: AiAssistantData;
    } | null>(null);
    const request = useHttp<Record<string, never>, AiAssistantData>({});
    const activeOrganizationId = organizationContext.active?.id ?? null;
    const data =
        assistantData?.organizationId === activeOrganizationId
            ? assistantData.data
            : null;

    const load = useCallback(
        (conversationId?: number): void => {
            request.setData({});
            request.get(
                aiIndex.url({
                    query:
                        conversationId === undefined
                            ? {}
                            : { conversation: conversationId },
                }),
                {
                    onSuccess: (nextData) =>
                        setAssistantData({
                            organizationId: activeOrganizationId,
                            data: nextData,
                        }),
                },
            );
        },
        [activeOrganizationId, request],
    );

    const refresh = useCallback((): void => {
        load(data?.conversation?.id);
    }, [data?.conversation?.id, load]);

    const selectConversation = useCallback(
        (conversationId: number | null): void => {
            load(conversationId ?? undefined);
        },
        [load],
    );

    const hasActiveRun =
        data?.conversation?.runs.some(
            (run) => run.status === 'queued' || run.status === 'running',
        ) ?? false;

    useEffect(() => {
        if (!open || !hasActiveRun) {
            return;
        }

        const interval = window.setInterval(refresh, 2_000);

        return () => window.clearInterval(interval);
    }, [hasActiveRun, open, refresh]);

    function changeOpen(nextOpen: boolean): void {
        setOpen(nextOpen);

        if (nextOpen) {
            refresh();
        }
    }

    return (
        <Sheet open={open} onOpenChange={changeOpen}>
            <Button
                variant="ghost"
                size="icon"
                aria-label="Open AI Assistant"
                onClick={() => changeOpen(true)}
                disabled={organizationContext.active === null}
            >
                <Bot aria-hidden="true" />
            </Button>
            <SheetContent className="w-full gap-0 p-0 sm:max-w-xl">
                <SheetHeader className="border-b border-border">
                    <SheetTitle>AI Assistant</SheetTitle>
                    <SheetDescription>
                        Ask questions about the active organization.
                    </SheetDescription>
                </SheetHeader>
                {data ? (
                    <div className="min-h-0 flex-1 p-4">
                        <AiAssistant
                            {...data}
                            access={organizationContext.ai}
                            compact
                            onConversationChange={selectConversation}
                            onRefresh={refresh}
                        />
                    </div>
                ) : (
                    <div className="p-4 text-sm text-muted-foreground">
                        {request.processing
                            ? 'Loading conversations...'
                            : 'Unable to load the AI Assistant.'}
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
