import { existsSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const aliasRoot = fileURLToPath(new URL('..', import.meta.url));

function resolveExtensionless(base) {
    return (
        [`${base}.ts`, `${base}.tsx`, path.join(base, 'index.ts')].find(
            (file) => existsSync(file),
        ) ??
        (existsSync(base) && !statSync(base).isDirectory() ? base : undefined)
    );
}

export async function resolve(specifier, context, nextResolve) {
    if (specifier.startsWith('@/')) {
        const candidate = resolveExtensionless(
            path.join(aliasRoot, specifier.slice(2)),
        );

        if (candidate) {
            return nextResolve(pathToFileURL(candidate).href, context);
        }
    }

    if (
        (specifier.startsWith('./') || specifier.startsWith('../')) &&
        !path.extname(specifier) &&
        context.parentURL
    ) {
        const base = fileURLToPath(new URL(specifier, context.parentURL));
        const candidate = resolveExtensionless(base);

        if (candidate) {
            return nextResolve(pathToFileURL(candidate).href, context);
        }
    }

    return nextResolve(specifier, context);
}
