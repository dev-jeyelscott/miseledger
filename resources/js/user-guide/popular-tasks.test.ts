import assert from 'node:assert/strict';
import { test } from 'node:test';
import { guideModules } from '../pages/user-guide/content.ts';
import { popularGuideTasks } from './popular-tasks.ts';

/** Verify the four landing quick links always resolve to tutorials rendered by the canonical User Guide detail page. */
test('popular guide tasks target existing module tutorials', () => {
    assert.equal(popularGuideTasks.length, 4);

    for (const task of popularGuideTasks) {
        const guideModule = guideModules.find(
            (module) => module.slug === task.module,
        );

        assert.ok(
            guideModule,
            `Unknown User Guide module referenced by Popular Tasks: ${task.module}`,
        );

        const renderedTutorials =
            guideModule.pages && guideModule.pages.length > 0
                ? guideModule.pages.flatMap((page) => page.tutorials ?? [])
                : guideModule.tutorials;

        const tutorialExists = renderedTutorials.some(
            (tutorial) => tutorial.id === task.tutorialId,
        );

        assert.ok(
            tutorialExists,
            `Unknown rendered User Guide tutorial referenced by Popular Tasks: ${task.module}#${task.tutorialId}`,
        );
    }
});
