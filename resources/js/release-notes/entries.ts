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
        id: 'documentation-announcement',
        publishedOn: '2026-09-10',
        type: 'improved',
        title: 'Documentation System Improvements',
        summary:
            'New integrated documentation system launched for better discoverability.',
    },
];
