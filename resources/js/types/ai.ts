export type AiConversationSummary = {
    id: number;
    title: string | null;
    updated_at: string | null;
};

export type AiConversationDetail = {
    id: number;
    title: string | null;
    messages: Array<{
        id: number;
        role: 'user' | 'assistant' | 'system';
        content: string;
        created_at: string | null;
    }>;
    runs: Array<{
        id: number;
        status: 'queued' | 'running' | 'succeeded' | 'failed' | 'cancelled';
        error_code: string | null;
    }>;
};

export type AiAssistantData = {
    conversations: AiConversationSummary[];
    conversation: AiConversationDetail | null;
    codex: { connected: boolean; accountLabel: string | null };
};
