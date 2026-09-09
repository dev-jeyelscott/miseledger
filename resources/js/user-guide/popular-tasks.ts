import type { GuideModuleSlug } from './types';

type PopularGuideTask = {
    label: string;
    module: GuideModuleSlug;
    tutorialId: string;
};

export const popularGuideTasks = [
    {
        label: 'Receive Stock',
        module: 'purchasing',
        tutorialId: 'record-a-complete-receipt',
    },
    {
        label: 'Perform Stock Count',
        module: 'stock-counts',
        tutorialId: 'create-a-stock-count',
    },
    {
        label: 'Record Waste',
        module: 'waste',
        tutorialId: 'record-waste',
    },
    {
        label: 'Transfer Stock',
        module: 'stock-transfers',
        tutorialId: 'create-a-transfer-draft',
    },
] as const satisfies readonly PopularGuideTask[];
