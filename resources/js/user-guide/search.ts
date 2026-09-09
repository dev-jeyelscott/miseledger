import type { GuideModule } from './types';

export type GuideSearchResult = {
    anchor: string | null;
    module: GuideModule;
    topic: string;
};

export function searchGuideTopics(
    modules: GuideModule[],
    query: string,
): GuideSearchResult[] {
    const normalizedQuery = normalizeSearchTerm(query);

    if (normalizedQuery === '') {
        return [];
    }

    return modules.flatMap((module) => searchModule(module, normalizedQuery));
}

function searchModule(module: GuideModule, query: string): GuideSearchResult[] {
    const results: GuideSearchResult[] = [];
    const addResult = (
        topic: string,
        anchor: string | null,
        terms: string[],
    ): void => {
        if (terms.some((term) => normalizeSearchTerm(term).includes(query))) {
            results.push({ anchor, module, topic });
        }
    };

    addResult(module.title, null, [
        module.title,
        module.description,
        module.overview,
        ...module.keywords,
        ...module.navigationLabels,
    ]);

    if (!module.pages) {
        module.tutorials.forEach((tutorial) =>
            addResult(tutorial.title, tutorial.id, [
                tutorial.title,
                ...tutorial.steps,
            ]),
        );
        module.fields.forEach((field) =>
            addResult(field.name, null, [field.name, field.description]),
        );
        module.troubleshooting.forEach((tip) => addResult(tip, null, [tip]));
    }

    module.pages?.forEach((page) => {
        addResult(page.title, page.id, [
            page.title,
            page.summary,
            page.whenToUse ?? '',
            ...(page.controls?.flatMap((control) => [
                control.label,
                control.description,
            ]) ?? []),
            ...(page.fields?.flatMap((field) => [
                field.name,
                field.description,
            ]) ?? []),
            ...(page.whatHappensNext ?? []),
            ...(page.notes?.flatMap((note) => [note.title, note.description]) ??
                []),
            ...(page.troubleshooting?.flatMap((item) => [
                item.question,
                item.answer,
            ]) ?? []),
        ]);
        page.tutorials?.forEach((tutorial) =>
            addResult(tutorial.title, tutorial.id, [
                tutorial.title,
                ...tutorial.steps,
            ]),
        );
    });

    return results;
}

function normalizeSearchTerm(term: string): string {
    return term.normalize('NFKC').trim().toLocaleLowerCase();
}
