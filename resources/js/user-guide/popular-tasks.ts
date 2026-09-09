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
        tutorialId: 'receive-a-purchase-order',
    },
    {
        label: 'Perform Stock Count',
        module: 'stock-counts',
        tutorialId: 'complete-a-stock-count',
    },
    {
        label: 'Record Waste',
        module: 'waste',
        tutorialId: 'record-waste',
    },
    {
        label: 'Transfer Stock',
        module: 'stock-transfers',
        tutorialId: 'transfer-stock-between-locations',
    },
] as const satisfies readonly PopularGuideTask[];
