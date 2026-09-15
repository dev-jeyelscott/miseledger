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
    {
        id: 'password-visibility-toggle-keyboard-fix',
        publishedOn: '2026-09-14',
        type: 'fixed',
        title: 'Password Visibility Toggle Keyboard Access',
        summary:
            'The Show/Hide password button is now reachable and operable using only the keyboard.',
    },
    {
        id: 'problem-report-screenshot-remove-touch-target-fix',
        publishedOn: '2026-09-15',
        type: 'fixed',
        title: 'Problem Report Screenshot Removal Touch Target',
        summary:
            'The remove button on screenshot previews is now easier to tap and has a visible focus state.',
    },
    {
        id: 'problem-report-copy-reference-feedback-fix',
        publishedOn: '2026-09-15',
        type: 'fixed',
        title: 'Problem Report Copy Reference Feedback',
        summary:
            'Copying a report reference now shows a confirmation, or an error if the copy fails.',
    },
    {
        id: 'problem-report-rate-limit-message-fix',
        publishedOn: '2026-09-15',
        type: 'fixed',
        title: 'Problem Report Rate Limit Message',
        summary:
            'Hitting the hourly report limit now shows a clear message on the form with retry guidance instead of a raw error page.',
    },
];
