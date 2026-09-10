import { entries as entriesList } from './entries.ts';
import type { ReleaseNoteEntry } from './types.ts';

export type { ReleaseNoteEntry, ReleaseNoteType } from './types';

export const entries: ReleaseNoteEntry[] = entriesList.sort((a, b) => {
    return (
        new Date(b.publishedOn).getTime() - new Date(a.publishedOn).getTime()
    );
});
