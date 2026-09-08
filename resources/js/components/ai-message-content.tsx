import ReactMarkdown from 'react-markdown';
import type { Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { cn } from '@/lib/utils';

const components: Components = {
    p: ({ children }) => <p className="mb-2 last:mb-0">{children}</p>,
    ul: ({ children }) => (
        <ul className="mb-2 list-disc space-y-1 pl-5 last:mb-0">{children}</ul>
    ),
    ol: ({ children }) => (
        <ol className="mb-2 list-decimal space-y-1 pl-5 last:mb-0">
            {children}
        </ol>
    ),
    a: ({ children, ...props }) => (
        <a
            {...props}
            target="_blank"
            rel="noreferrer"
            className="underline underline-offset-2"
        >
            {children}
        </a>
    ),
    code: ({ children, className }) => (
        <code
            className={cn(
                'rounded bg-black/10 px-1 py-0.5 font-mono text-xs',
                className,
            )}
        >
            {children}
        </code>
    ),
    pre: ({ children }) => (
        <pre className="my-2 overflow-x-auto rounded-md bg-black/10 p-2 font-mono text-xs">
            {children}
        </pre>
    ),
    table: ({ children }) => (
        <div className="my-2 overflow-x-auto">
            <table className="border-collapse text-xs">{children}</table>
        </div>
    ),
    th: ({ children }) => (
        <th className="border border-border px-2 py-1 text-left font-semibold">
            {children}
        </th>
    ),
    td: ({ children }) => (
        <td className="border border-border px-2 py-1">{children}</td>
    ),
    h1: ({ children }) => (
        <h1 className="mt-2 mb-1 text-base font-semibold">{children}</h1>
    ),
    h2: ({ children }) => (
        <h2 className="mt-2 mb-1 text-sm font-semibold">{children}</h2>
    ),
    h3: ({ children }) => (
        <h3 className="mt-2 mb-1 text-sm font-semibold">{children}</h3>
    ),
    blockquote: ({ children }) => (
        <blockquote className="border-l-2 pl-2 text-muted-foreground italic">
            {children}
        </blockquote>
    ),
};

export function AiMessageContent({ content }: { content: string }) {
    return (
        <ReactMarkdown remarkPlugins={[remarkGfm]} components={components}>
            {content}
        </ReactMarkdown>
    );
}
