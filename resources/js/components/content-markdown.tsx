import ReactMarkdown from 'react-markdown';
import type { Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';

/**
 * Safe link protocols. Anything else (e.g. `javascript:`) is rendered as
 * inert plain text instead of an anchor.
 */
const SAFE_HREF_PATTERN = /^(https?:|mailto:|\/|#)/i;

function isSafeHref(href: string | undefined): href is string {
    return typeof href === 'string' && SAFE_HREF_PATTERN.test(href);
}

const components: Components = {
    p: ({ children }) => (
        <p className="mb-4 leading-relaxed last:mb-0">{children}</p>
    ),
    ul: ({ children }) => (
        <ul className="mb-4 list-disc space-y-1 pl-6 last:mb-0">{children}</ul>
    ),
    ol: ({ children }) => (
        <ol className="mb-4 list-decimal space-y-1 pl-6 last:mb-0">
            {children}
        </ol>
    ),
    li: ({ children }) => <li>{children}</li>,
    a: ({ children, href, ...props }) => {
        if (!isSafeHref(href)) {
            return <span>{children}</span>;
        }

        const isExternal = /^https?:/i.test(href);

        return (
            <a
                {...props}
                href={href}
                {...(isExternal
                    ? { target: '_blank', rel: 'noopener noreferrer' }
                    : {})}
                className="underline underline-offset-2 hover:text-primary"
            >
                {children}
            </a>
        );
    },
    code: ({ children, className }) => (
        <code
            className={`rounded bg-muted px-1 py-0.5 font-mono text-sm ${className ?? ''}`}
        >
            {children}
        </code>
    ),
    pre: ({ children }) => (
        <pre className="mb-4 overflow-x-auto rounded-md bg-muted p-3 font-mono text-sm last:mb-0">
            {children}
        </pre>
    ),
    table: ({ children }) => (
        <div className="mb-4 overflow-x-auto last:mb-0">
            <table className="w-full border-collapse text-sm">{children}</table>
        </div>
    ),
    thead: ({ children }) => (
        <thead className="border-b border-border">{children}</thead>
    ),
    th: ({ children }) => (
        <th className="border border-border px-3 py-2 text-left font-semibold">
            {children}
        </th>
    ),
    td: ({ children }) => (
        <td className="border border-border px-3 py-2">{children}</td>
    ),
    h1: ({ children }) => (
        <h1 className="mt-6 mb-3 text-2xl font-semibold first:mt-0">
            {children}
        </h1>
    ),
    h2: ({ children }) => (
        <h2 className="mt-6 mb-3 text-xl font-semibold first:mt-0">
            {children}
        </h2>
    ),
    h3: ({ children }) => (
        <h3 className="mt-5 mb-2 text-lg font-semibold first:mt-0">
            {children}
        </h3>
    ),
    h4: ({ children }) => (
        <h4 className="mt-4 mb-2 font-semibold first:mt-0">{children}</h4>
    ),
    blockquote: ({ children }) => (
        <blockquote className="mb-4 border-l-2 border-border pl-4 text-muted-foreground italic last:mb-0">
            {children}
        </blockquote>
    ),
    hr: () => <hr className="my-6 border-border" />,
};

type ContentMarkdownProps = {
    body: string;
    className?: string;
};

/**
 * The single shared renderer for CMS Markdown, used identically by the
 * platform draft preview and public published pages (POC-V6.4). Raw HTML in
 * the source is never executed: `rehype-raw` is intentionally not used, so
 * `react-markdown` renders any literal HTML tags as inert escaped text
 * rather than DOM nodes, and `dangerouslySetInnerHTML` is never used here.
 */
export function ContentMarkdown({ body, className }: ContentMarkdownProps) {
    return (
        <div className={className}>
            <ReactMarkdown remarkPlugins={[remarkGfm]} components={components}>
                {body}
            </ReactMarkdown>
        </div>
    );
}
