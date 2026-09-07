import { Link, router, useHttp, usePoll } from '@inertiajs/react';
import {
    Bot,
    LoaderCircle,
    MessageSquarePlus,
    Send,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
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

type CodexLoginStatus = {
    status: 'idle' | 'starting' | 'pending' | 'connected' | 'failed';
    verification_url?: string;
    user_code?: string;
    error_code?: string;
};

export function AiAssistant({
    access,
    compact = false,
    onConversationChange,
    onRefresh,
    ...data
}: Props) {
    const [content, setContent] = useState('');
    const [loginStatus, setLoginStatus] = useState<CodexLoginStatus | null>(
        null,
    );
    const conversationRequest = useHttp<
        Record<string, never>,
        { conversation: { id: number } }
    >({});
    const messageRequest = useHttp<
        { content: string },
        { run: { id: number } }
    >({ content: '' });
    const codexRequest = useHttp<Record<string, never>, CodexLoginStatus>({});
    const connectionRequest = useHttp<
        Record<string, never>,
        { connected: boolean }
    >({});
    const statusRequest = useHttp<Record<string, never>, CodexLoginStatus>(
        {},
    );
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

        router.reload({ only: ['conversation', 'conversations', 'codex'] });
    }

    const applyLoginStatus = useCallback(
        (result: CodexLoginStatus): void => {
            setLoginStatus(result);

            if (result.status === 'connected') {
                refreshAssistant();
            }
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [onRefresh],
    );

    useEffect(() => {
        if (
            loginStatus === null ||
            loginStatus.status === 'connected' ||
            loginStatus.status === 'failed'
        ) {
            return;
        }

        const timeout = window.setTimeout(() => {
            statusRequest.get(CodexConnectionController.loginStatus.url(), {
                onSuccess: applyLoginStatus,
            });
        }, 2_000);

        return () => window.clearTimeout(timeout);
    }, [applyLoginStatus, loginStatus, statusRequest]);

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
            onSuccess: setLoginStatus,
        });
    }

    function checkCodexLoginNow(): void {
        statusRequest.get(CodexConnectionController.loginStatus.url(), {
            onSuccess: applyLoginStatus,
        });
    }

    function disconnectCodex(): void {
        connectionRequest.setData({});
        connectionRequest.delete(CodexConnectionController.destroy.url(), {
            onSuccess: refreshAssistant,
            onError: refreshAssistant,
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
            {!unavailable && !data.codex.connected && loginStatus === null && (
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
            {loginStatus && loginStatus.status === 'starting' && (
                <Alert>
                    <Bot aria-hidden="true" />
                    <AlertTitle>Starting Codex sign-in</AlertTitle>
                    <AlertDescription>
                        Requesting a sign-in code from Codex...
                    </AlertDescription>
                </Alert>
            )}
            {loginStatus && loginStatus.status === 'pending' && (
                <Alert>
                    <Bot aria-hidden="true" />
                    <AlertTitle>Complete Codex sign-in</AlertTitle>
                    <AlertDescription>
                        <p>
                            Visit{' '}
                            <a
                                href={loginStatus.verification_url}
                                target="_blank"
                                rel="noreferrer"
                                className="underline"
                            >
                                {loginStatus.verification_url}
                            </a>{' '}
                            and enter code{' '}
                            <strong className="font-mono">
                                {loginStatus.user_code}
                            </strong>
                            . This page checks automatically once you're
                            done.
                        </p>
                        <Button
                            size="sm"
                            className="mt-2"
                            onClick={checkCodexLoginNow}
                            disabled={statusRequest.processing}
                        >
                            I completed sign-in
                        </Button>
                    </AlertDescription>
                </Alert>
            )}
            {loginStatus && loginStatus.status === 'failed' && (
                <Alert variant="destructive">
                    <TriangleAlert aria-hidden="true" />
                    <AlertTitle>Codex sign-in failed</AlertTitle>
                    <AlertDescription className="gap-3">
                        {runErrorCopy[loginStatus.error_code ?? ''] ??
                            'Codex could not complete the sign-in. Try again.'}
                        <Button
                            size="sm"
                            onClick={startCodexLogin}
                            disabled={codexRequest.processing}
                        >
                            Try again
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
