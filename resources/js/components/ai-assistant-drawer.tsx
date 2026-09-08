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
    SheetTrigger,
} from '@/components/ui/sheet';
import { index as aiIndex } from '@/routes/ai';
import type { AiAssistantData, OrganizationContext } from '@/types';

/** Renders the global AI Assistant launcher and its controlled conversation drawer. */
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

    /** Loads server-authoritative AI Assistant data for the active organization and conversation. */
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

    /** Refreshes the currently selected conversation without changing its selection. */
    const refresh = useCallback((): void => {
        load(data?.conversation?.id);
    }, [data?.conversation?.id, load]);

    /** Loads the user-selected conversation while preserving server-authoritative conversation data. */
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

    // Keep the existing two-second refresh behavior only while an opened conversation has active work.
    useEffect(() => {
        if (!open || !hasActiveRun) {
            return;
        }

        const interval = window.setInterval(refresh, 2_000);

        return () => window.clearInterval(interval);
    }, [hasActiveRun, open, refresh]);

    /** Synchronizes the Radix Sheet state and refreshes data whenever the assistant is opened. */
    function changeOpen(nextOpen: boolean): void {
        setOpen(nextOpen);

        if (nextOpen) {
            refresh();
        }
    }

    return (
        <Sheet open={open} onOpenChange={changeOpen}>
            <SheetTrigger asChild>
                <Button
                    type="button"
                    variant="default"
                    size="lg"
                    aria-label="Open AI Assistant"
                    className="h-12 min-w-12 rounded-full border border-ai-assistant-launcher-foreground/20 bg-ai-assistant-launcher text-ai-assistant-launcher-foreground shadow-lg transition-[background-color,box-shadow] hover:bg-ai-assistant-launcher/90 hover:shadow-xl focus-visible:border-ai-assistant-launcher-ring focus-visible:ring-ai-assistant-launcher-ring/80 focus-visible:ring-offset-2 focus-visible:ring-offset-background active:bg-ai-assistant-launcher/80 active:shadow-md disabled:shadow-none motion-reduce:transition-none"
                    disabled={organizationContext.active === null}
                >
                    <Bot aria-hidden="true" className="size-5" />
                    <span className="hidden min-[360px]:inline">Ask AI</span>
                </Button>
            </SheetTrigger>

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
