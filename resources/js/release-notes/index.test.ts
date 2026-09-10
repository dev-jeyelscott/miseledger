import assert from 'node:assert/strict';
import { test } from 'node:test';
import { guideModulesBySlug } from '../pages/user-guide/content.ts';
import { entries } from './index.ts';
import type { ReleaseNoteType } from './types.ts';

test('all entries have unique IDs', () => {
    const ids = entries.map((e) => e.id);
    const uniqueIds = new Set(ids);
    assert.strictEqual(ids.length, uniqueIds.size);
});

test('all entries have valid ISO dates', () => {
    entries.forEach((entry) => {
        const dateRegex = /^(\d{4})-(\d{2})-(\d{2})$/;
        assert.strictEqual(dateRegex.test(entry.publishedOn), true);

        const match = entry.publishedOn.match(dateRegex);
        assert.ok(match, `Date ${entry.publishedOn} does not match ISO format`);

        const date = new Date(`${entry.publishedOn}T00:00:00Z`);
        const roundTrip = date.toISOString().split('T')[0];
        assert.strictEqual(
            roundTrip,
            entry.publishedOn,
            `Date ${entry.publishedOn} does not round-trip correctly (got ${roundTrip})`,
        );
    });
});

test('all entries have valid types', () => {
    const validTypes: ReleaseNoteType[] = ['new', 'improved', 'fixed'];
    entries.forEach((entry) => {
        assert.strictEqual(validTypes.includes(entry.type), true);
    });
});

test('all entries have title and summary', () => {
    entries.forEach((entry) => {
        assert.strictEqual(entry.title.length > 0, true);
        assert.strictEqual(entry.summary.length > 0, true);
    });
});

test('entries are sorted newest first', () => {
    if (entries.length > 1) {
        for (let i = 0; i < entries.length - 1; i++) {
            const current = new Date(entries[i].publishedOn).getTime();
            const next = new Date(entries[i + 1].publishedOn).getTime();
            assert.ok(current >= next);
        }
    }
});

test('related guide slugs are optional', () => {
    entries.forEach((entry) => {
        if (entry.relatedGuideSlugs !== undefined) {
            assert.strictEqual(Array.isArray(entry.relatedGuideSlugs), true);
        }
    });
});

test('all related guide slugs reference valid guide modules', () => {
    // Derive valid slugs from the current guide registry
    const validSlugs = Object.keys(guideModulesBySlug);

    entries.forEach((entry) => {
        if (entry.relatedGuideSlugs) {
            entry.relatedGuideSlugs.forEach((slug) => {
                assert.ok(
                    validSlugs.includes(slug),
                    `Invalid guide slug "${slug}" in entry "${entry.id}" — not found in current guide registry`,
                );
            });
        }
    });
});

test('related guide slugs resolve to valid guide modules', () => {
    entries.forEach((entry) => {
        if (entry.relatedGuideSlugs) {
            entry.relatedGuideSlugs.forEach((slug) => {
                const module = guideModulesBySlug[slug];
                assert.ok(
                    module,
                    `Guide slug "${slug}" in entry "${entry.id}" does not resolve to a module`,
                );
                assert.strictEqual(
                    module.slug,
                    slug,
                    `Module slug mismatch for "${slug}" in entry "${entry.id}"`,
                );
            });
        }
    });
});

test('entries without related guides render cleanly', () => {
    const entriesWithoutGuides = entries.filter(
        (e) => !e.relatedGuideSlugs || e.relatedGuideSlugs.length === 0,
    );
    // Simply verify these entries exist and are valid
    entriesWithoutGuides.forEach((entry) => {
        assert.strictEqual(entry.title.length > 0, true);
        assert.strictEqual(entry.summary.length > 0, true);
    });
});
