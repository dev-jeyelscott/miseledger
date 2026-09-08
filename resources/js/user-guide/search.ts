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
    const normalizedQuery = query.trim().toLocaleLowerCase();

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
        if (terms.some((term) => term.toLocaleLowerCase().includes(query))) {
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

    return results;
}
