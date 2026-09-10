import type { ReleaseNoteEntry } from './types';

export const entries: ReleaseNoteEntry[] = [
    {
        id: 'release-notes-launch',
        publishedOn: '2026-09-10',
        type: 'new',
        title: 'Release Notes and User Guide Launch',
        summary:
            'Discover product updates and learn MiseLedger features from the account menu.',
        details:
            'Access Release Notes and User Guide directly from the account menu to stay informed about improvements and get help using MiseLedger.',
        relatedGuideSlugs: ['getting-started'],
    },
    {
        id: 'internal-improvements-september',
        publishedOn: '2026-09-08',
        type: 'improved',
        title: 'Improved Reporting Performance',
        summary: 'Report generation is now faster for large datasets.',
        details:
            'Optimized database queries and caching to reduce report load times by up to 40%.',
    },
];
