import type { GuideModuleSlug } from '@/user-guide/types';

export type ReleaseNoteType = 'new' | 'improved' | 'fixed';

export type ReleaseNoteEntry = {
    id: string;
    publishedOn: string;
    type: ReleaseNoteType;
    title: string;
    summary: string;
    details?: string;
    relatedGuideSlugs?: GuideModuleSlug[];
};
