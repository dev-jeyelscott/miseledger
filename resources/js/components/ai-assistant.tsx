import { Link, router, useHttp, usePoll } from '@inertiajs/react';
import {
    Bot,
    LoaderCircle,
    MessageSquarePlus,
    Send,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import AiConversationController from '@/actions/App/Http/Controllers/Ai/AiConversationController';
import AiMessageController from '@/actions/App/Http/Controllers/Ai/AiMessageController';
import CodexConnectionController from '@/actions/App/Http/Controllers/Ai/CodexConnectionController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { index as aiIndex } from '@/routes/ai';
import type { AiAssistantData, OrganizationAIAccessContext } from '@/types';

type Props = AiAssistantData & {
    access: OrganizationAIAccessContext | null;
    compact?: boolean;
    onConversationChange?: (conversationId: number | null) => void;
    onRefresh?: () => void;
};

const unavailableCopy: Record<
    NonNullable<OrganizationAIAccessContext['reason']>,
    string
> = {
    organization_inactive:
        'This organization is inactive, so the AI Assistant is unavailable.',
    commercial_read_only:
        'Your organization is currently read-only. Restore commercial write access to use the AI Assistant.',
    feature_not_in_plan: 'Your current plan does not include the AI Assistant.',
    member_access_disabled:
        'An organization owner has not enabled AI access for your membership.',
};

const runErrorCopy: Record<string, string> = {
    provider_rate_limited:
        'Codex is rate-limited. Wait for the provider limit to reset, then try again.',
    provider_login_required: 'Reconnect Codex before sending another message.',
    provider_unauthorized:
        'Codex authorization failed. Reconnect your account and try again.',
    access_revoked: 'AI access changed before this response could complete.',
};

export function AiAssistant({
    access,
    compact = false,
    onConversationChange,
    onRefresh,
    ...data
}: Props) {
    const [content, setContent] = useState('');
    const [deviceCode, setDeviceCode] = useState<{
        user_code: string;
        verification_uri: string;
    } | null>(null);
    const conversationRequest = useHttp<
        Record<string, never>,
        { conversation: { id: number } }
    >({});
    const messageRequest = useHttp<
        { content: string },
        { run: { id: number } }
    >({ content: '' });
    const codexRequest = useHttp<
        Record<string, never>,
        { user_code: string; verification_uri: string }
    >({});
    const connectionRequest = useHttp<
        Record<string, never>,
        { connected: boolean }
    >({});
    const hasActiveRun =
        data.conversation?.runs.some(
            (run) => run.status === 'queued' || run.status === 'running',
        ) ?? false;

    const poll = usePoll(
        2_000,
        { only: ['conversation', 'conversations'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (onRefresh !== undefined) {
            return;
        }

        if (hasActiveRun) {
            poll.start();

            return poll.stop;
        }

        poll.stop();
    }, [hasActiveRun, onRefresh, poll]);

    function selectConversation(conversationId: number | null): void {
        if (onConversationChange !== undefined) {
            onConversationChange(conversationId);

            return;
        }

        router.visit(
            aiIndex.url({
                query:
                    conversationId === null
                        ? {}
                        : { conversation: conversationId },
            }),
        );
    }

    function refreshAssistant(): void {
        if (onRefresh !== undefined) {
            onRefresh();

            return;
        }

        router.reload({ only: ['conversation', 'conversations'] });
    }

    function createConversation(): void {
        conversationRequest.setData({});
        conversationRequest.post(AiConversationController.store.url(), {
            onSuccess: (result) => selectConversation(result.conversation.id),
        });
    }

    function sendMessage(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (data.conversation === null || content.trim() === '') {
            return;
        }

        messageRequest.setData({ content: content.trim() });
        messageRequest.post(
            AiMessageController.store.url(data.conversation.id),
            {
                onSuccess: () => {
                    setContent('');
                    refreshAssistant();
                },
            },
        );
    }

    function deleteConversation(id: number): void {
        conversationRequest.delete(AiConversationController.destroy.url(id), {
            onSuccess: () => selectConversation(null),
        });
    }

    function startCodexLogin(): void {
        codexRequest.setData({});
        codexRequest.post(CodexConnectionController.start.url(), {
            onSuccess: setDeviceCode,
        });
    }

    function completeCodexLogin(): void {
        connectionRequest.setData({});
        connectionRequest.post(CodexConnectionController.complete.url(), {
            onSuccess: () => {
                setDeviceCode(null);
                refreshAssistant();
            },
        });
    }

    function disconnectCodex(): void {
        connectionRequest.setData({});
        connectionRequest.delete(CodexConnectionController.destroy.url(), {
            onSuccess: refreshAssistant,
        });
    }

    const unavailable =
        access?.canUse !== true
            ? unavailableCopy[access?.reason ?? 'member_access_disabled']
            : null;
    const failure = data.conversation?.runs.find(
        (run) => run.status === 'failed' || run.status === 'cancelled',
    );

    return (
        <div
            className={cn(
                'flex min-h-0 flex-1 flex-col gap-4',
                compact && 'h-full',
            )}
        >
            {unavailable && (
                <Alert variant="destructive">
                    <TriangleAlert aria-hidden="true" />
                    <AlertTitle>AI Assistant unavailable</AlertTitle>
                    <AlertDescription>{unavailable}</AlertDescription>
                </Alert>
            )}
            {!unavailable && !data.codex.connected && (
                <Alert>
                    <Bot aria-hidden="true" />
                    <AlertTitle>Connect Codex to start</AlertTitle>
                    <AlertDescription className="gap-3">
                        Connect your own Codex account. Credentials are never
                        displayed in MiseLedger.
                        <Button
                            size="sm"
                            onClick={startCodexLogin}
                            disabled={codexRequest.processing}
                        >
                            {codexRequest.processing
                                ? 'Starting...'
                                : 'Connect Codex'}
                        </Button>
                    </AlertDescription>
                </Alert>
            )}
            {deviceCode && (
                <Alert>
                    <Bot aria-hidden="true" />
                    <AlertTitle>Complete Codex sign-in</AlertTitle>
                    <AlertDescription>
                        <p>
                            Visit {deviceCode.verification_uri} and enter code{' '}
                            <strong className="font-mono">
                                {deviceCode.user_code}
                            </strong>
                            .
                        </p>
                        <Button
                            size="sm"
                            className="mt-2"
                            onClick={completeCodexLogin}
                            disabled={connectionRequest.processing}
                        >
                            I completed sign-in
                        </Button>
                    </AlertDescription>
                </Alert>
            )}
            {data.codex.connected && (
                <div className="flex items-center justify-between rounded-xl border border-border bg-card px-4 py-3 text-sm">
                    <span>
                        Codex connected
                        {data.codex.accountLabel
                            ? ` as ${data.codex.accountLabel}`
                            : ''}
                        .
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={disconnectCodex}
                        disabled={connectionRequest.processing}
                    >
                        Disconnect
                    </Button>
                </div>
            )}
            {failure && (
                <Alert variant="destructive">
                    <TriangleAlert aria-hidden="true" />
                    <AlertTitle>Response did not complete</AlertTitle>
                    <AlertDescription>
                        {runErrorCopy[failure.error_code ?? ''] ??
                            'The provider could not complete this request. Try again shortly.'}
                    </AlertDescription>
                </Alert>
            )}
            <div
                className={cn(
                    'grid min-h-0 flex-1 gap-4',
                    compact
                        ? 'grid-cols-1'
                        : 'lg:grid-cols-[15rem_minmax(0,1fr)]',
                )}
            >
                <aside className="min-h-0 rounded-xl border border-border bg-card p-3">
                    <Button
                        className="w-full"
                        size="sm"
                        onClick={createConversation}
                        disabled={
                            !access?.canUse || conversationRequest.processing
                        }
                    >
                        <MessageSquarePlus aria-hidden="true" />
                        New conversation
                    </Button>
                    <nav
                        aria-label="AI conversation history"
                        className="mt-3 space-y-1 overflow-y-auto"
                    >
                        {data.conversations.map((conversation) => (
                            <div
                                key={conversation.id}
                                className="flex items-center gap-1"
                            >
                                {onConversationChange === undefined ? (
                                    <Link
                                        href={aiIndex({
                                            query: {
                                                conversation: conversation.id,
                                            },
                                        })}
                                        className={cn(
                                            'min-w-0 flex-1 rounded-md px-2 py-2 text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            data.conversation?.id ===
                                                conversation.id &&
                                                'bg-muted font-medium',
                                        )}
                                    >
                                        {conversation.title ||
                                            'New conversation'}
                                    </Link>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            selectConversation(conversation.id)
                                        }
                                        className={cn(
                                            'min-w-0 flex-1 rounded-md px-2 py-2 text-left text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            data.conversation?.id ===
                                                conversation.id &&
                                                'bg-muted font-medium',
                                        )}
                                    >
                                        {conversation.title ||
                                            'New conversation'}
                                    </button>
                                )}
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Delete ${conversation.title || 'new conversation'}`}
                                    onClick={() =>
                                        deleteConversation(conversation.id)
                                    }
                                >
                                    <Trash2 aria-hidden="true" />
                                </Button>
                            </div>
                        ))}
                    </nav>
                </aside>
                <section
                    aria-label="AI conversation"
                    className="flex min-h-0 flex-col rounded-xl border border-border bg-card"
                >
                    <div
                        className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4"
                        aria-live="polite"
                    >
                        {data.conversation?.messages.map((message) => (
                            <article
                                key={message.id}
                                className={cn(
                                    'max-w-[85%] rounded-lg px-3 py-2 text-sm leading-6',
                                    message.role === 'user'
                                        ? 'ml-auto bg-primary text-primary-foreground'
                                        : 'bg-muted',
                                )}
                            >
                                <p className="sr-only">
                                    {message.role === 'user'
                                        ? 'You'
                                        : 'Assistant'}
                                </p>
                                {message.content}
                            </article>
                        ))}
                        {hasActiveRun && (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <LoaderCircle
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                                Codex is processing your request.
                            </p>
                        )}
                        {data.conversation === null && (
                            <p className="text-sm text-muted-foreground">
                                Start a new conversation to ask MiseLedger
                                questions.
                            </p>
                        )}
                    </div>
                    <form
                        onSubmit={sendMessage}
                        className="border-t border-border p-3"
                    >
                        <label htmlFor="ai-message" className="sr-only">
                            Message the AI Assistant
                        </label>
                        <div className="flex gap-2">
                            <Input
                                id="ai-message"
                                value={content}
                                onChange={(event) =>
                                    setContent(event.target.value)
                                }
                                disabled={
                                    !access?.canUse ||
                                    !data.codex.connected ||
                                    data.conversation === null ||
                                    hasActiveRun
                                }
                                placeholder="Ask about your inventory..."
                                maxLength={12000}
                            />
                            <Button
                                type="submit"
                                aria-label="Send message"
                                disabled={
                                    !access?.canUse ||
                                    !data.codex.connected ||
                                    messageRequest.processing ||
                                    hasActiveRun ||
                                    content.trim() === ''
                                }
                            >
                                <Send aria-hidden="true" />
                            </Button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    );
}
