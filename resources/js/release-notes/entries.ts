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
    {
        id: 'problem-report-copy-reference-touch-target-fix',
        publishedOn: '2026-09-16',
        type: 'fixed',
        title: 'Problem Report Copy Reference Touch Target',
        summary:
            'The copy reference button is now easier to tap and has a visible focus state.',
    },
    {
        id: 'problem-report-screenshot-upload-focus-visible-fix',
        publishedOn: '2026-09-16',
        type: 'fixed',
        title: 'Problem Report Screenshot Upload Focus Indicator',
        summary:
            'Tabbing to the screenshot upload now shows a clear focus ring on the visible upload area.',
    },
    {
        id: 'problem-report-unsaved-changes-navigation-guard',
        publishedOn: '2026-09-16',
        type: 'fixed',
        title: 'Problem Report Unsaved Changes Protection',
        summary:
            'Navigating away from a report with unsaved text or screenshots now prompts for confirmation.',
    },
    {
        id: 'problem-report-screenshot-limit-state-fix',
        publishedOn: '2026-09-17',
        type: 'fixed',
        title: 'Problem Report Screenshot Limit Feedback',
        summary:
            'The screenshot upload area now clearly shows when the 5-file limit is reached and re-enables automatically once a screenshot is removed.',
    },
    {
        id: 'problem-report-description-required-fix',
        publishedOn: '2026-09-17',
        type: 'fixed',
        title: 'Problem Report Description Required Field',
        summary:
            'The description field on the problem report form now correctly signals to browsers and assistive technology that it is required.',
    },
    {
        id: 'stock-count-line-removal-confirmation-fix',
        publishedOn: '2026-09-20',
        type: 'fixed',
        title: 'Stock Count Line Removal Confirmation',
        summary:
            'Removing a stock count line with an item, quantity, unit, or notes already entered now asks for confirmation before discarding it. Blank extra lines are still removed instantly.',
        relatedGuideSlugs: ['stock-counts'],
    },
];
