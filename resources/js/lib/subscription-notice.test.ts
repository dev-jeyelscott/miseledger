import assert from 'node:assert/strict';
import { test } from 'node:test';
import type {
    OrganizationContext,
    OrganizationSubscriptionContext,
} from '@/types/organization';
import { resolveCallToAction, resolveNotice } from './subscription-notice.ts';

const baseSubscription: OrganizationSubscriptionContext = {
    plan: 'starter',
    status: 'active',
    accessMode: 'writable',
    onTrial: false,
    trialEndsAt: null,
    endsAt: null,
    billingWarning: false,
    planName: 'Starter',
    interval: 'monthly',
    nextBillingAt: null,
    management: 'portal',
    collectionMethod: 'automatic',
};

function membership(
    organizationId: number,
    permissions: OrganizationContext['memberships'][number]['permissions'],
): OrganizationContext['memberships'][number] {
    return {
        organization: {
            id: organizationId,
            name: `Organization ${organizationId}`,
            slug: `organization-${organizationId}`,
        },
        role: 'owner',
        permissions,
    };
}

test('a past_due organization receives a persistent high-priority warning that retains write-access messaging', () => {
    const notice = resolveNotice({
        ...baseSubscription,
        status: 'past_due',
        billingWarning: true,
    });

    assert.equal(notice?.variant, 'destructive');
    assert.equal(notice?.dismissible, false);
    assert.match(notice!.description, /write access is retained/i);
});

test('an unpaid organization receives a persistent explanation that mutations are unavailable', () => {
    const notice = resolveNotice({
        ...baseSubscription,
        status: 'unpaid',
        accessMode: 'read_only',
    });

    assert.equal(notice?.variant, 'destructive');
    assert.equal(notice?.dismissible, false);
    assert.match(notice!.description, /mutations are unavailable/i);
});

test('a read-only organization receives a persistent explanation that mutations are unavailable', () => {
    const notice = resolveNotice({
        ...baseSubscription,
        status: null,
        accessMode: 'read_only',
    });

    assert.equal(notice?.variant, 'destructive');
    assert.equal(notice?.dismissible, false);
    assert.match(notice!.description, /mutations are unavailable/i);
});

test('a scheduled cancellation surfaces the ends-at date while remaining writable', () => {
    const notice = resolveNotice({
        ...baseSubscription,
        endsAt: '2026-09-30T00:00:00Z',
    });

    assert.equal(notice?.variant, 'default');
    assert.equal(notice?.dismissible, false);
    assert.match(notice!.description, /scheduled to end/i);
});

test('a trial ending soon surfaces the trial-ends-at date', () => {
    const notice = resolveNotice({
        ...baseSubscription,
        onTrial: true,
        trialEndsAt: '2026-09-30T00:00:00Z',
    });

    assert.equal(notice?.variant, 'default');
    assert.equal(notice?.dismissible, true);
    assert.match(notice!.description, /trial ends/i);
});

test('a subscription with no notable state produces no notice', () => {
    assert.equal(resolveNotice(baseSubscription), null);
});

test('resolveCallToAction targets only the active organization, never another organization', () => {
    const organizationContext: Pick<
        OrganizationContext,
        'active' | 'memberships'
    > = {
        active: { id: 2, name: 'Active Org', slug: 'active-org' },
        memberships: [
            membership(1, ['billing.manage']),
            membership(2, ['billing.manage']),
        ],
    };

    const callToAction = resolveCallToAction(organizationContext);

    assert.deepEqual(callToAction, {
        type: 'billing_link',
        organizationId: 2,
    });
});

test('a billing-authorized member receives a direct Billing call to action', () => {
    const organizationContext: Pick<
        OrganizationContext,
        'active' | 'memberships'
    > = {
        active: { id: 1, name: 'Active Org', slug: 'active-org' },
        memberships: [membership(1, ['billing.manage'])],
    };

    assert.deepEqual(resolveCallToAction(organizationContext), {
        type: 'billing_link',
        organizationId: 1,
    });
});

test('a non-billing role receives the non-sensitive owner-contact call to action', () => {
    const organizationContext: Pick<
        OrganizationContext,
        'active' | 'memberships'
    > = {
        active: { id: 1, name: 'Active Org', slug: 'active-org' },
        memberships: [membership(1, ['inventory.view'])],
    };

    assert.deepEqual(resolveCallToAction(organizationContext), {
        type: 'contact_owner',
    });
});

test('a billing-authorized member for another organization does not receive a Billing call to action for the active organization', () => {
    const organizationContext: Pick<
        OrganizationContext,
        'active' | 'memberships'
    > = {
        active: { id: 1, name: 'Active Org', slug: 'active-org' },
        memberships: [membership(2, ['billing.manage'])],
    };

    assert.deepEqual(resolveCallToAction(organizationContext), {
        type: 'contact_owner',
    });
});
