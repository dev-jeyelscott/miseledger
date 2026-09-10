import assert from 'node:assert/strict';
import { test } from 'node:test';
import { entries } from './index.ts';
import type { ReleaseNoteType } from './types.ts';

test('all entries have unique IDs', () => {
    const ids = entries.map((e) => e.id);
    const uniqueIds = new Set(ids);
    assert.strictEqual(ids.length, uniqueIds.size);
});

test('all entries have valid ISO dates', () => {
    entries.forEach((entry) => {
        const date = new Date(entry.publishedOn);
        assert.strictEqual(isNaN(date.getTime()), false);
        assert.strictEqual(/^\d{4}-\d{2}-\d{2}$/.test(entry.publishedOn), true);
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
