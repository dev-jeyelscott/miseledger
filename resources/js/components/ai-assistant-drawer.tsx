import { useHttp, usePage } from '@inertiajs/react';
import { Bot } from 'lucide-react';
import { useState } from 'react';
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
    const [data, setData] = useState<AiAssistantData | null>(null);
    const request = useHttp<Record<string, never>, AiAssistantData>({});

    function changeOpen(nextOpen: boolean): void {
        setOpen(nextOpen);

        if (nextOpen && data === null) {
            request.setData({});
            request.get(aiIndex.url(), { onSuccess: setData });
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
