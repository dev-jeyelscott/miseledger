type MobileLocationOption = {
    id: number;
    name: string;
    code: string;
};

type MobileActiveLocation = MobileLocationOption | null;

type MobileOrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

export type {
    MobileActiveLocation,
    MobileLocationOption,
    MobileOrganizationSummary,
};
