<?php

use App\Actions\Audit\RecordAuditEntry;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function createAppendOnlyAuditLog(Organization $organization, ?User $actor = null): AuditLog
{
    return app(RecordAuditEntry::class)->handle(
        organization: $organization,
        actor: $actor,
        action: 'audit_log.append_only_test',
        entityType: 'test_entity',
        entityId: 1,
        beforeData: null,
        afterData: ['seeded' => true],
    );
}

test('query builder update against audit_logs is rejected by the database', function () {
    $organization = Organization::factory()->create();
    $auditLog = createAppendOnlyAuditLog($organization);

    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')
        ->where('id', $auditLog->id)
        ->update(['action' => 'tampered'])))
        ->toThrow(QueryException::class, 'Audit log entries are immutable.');

    expect($auditLog->fresh()->action)->toBe($auditLog->action);
});

test('query builder delete against audit_logs is rejected by the database', function () {
    $organization = Organization::factory()->create();
    $auditLog = createAppendOnlyAuditLog($organization);

    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->where('id', $auditLog->id)->delete()))
        ->toThrow(QueryException::class, 'Audit log entries cannot be deleted.');

    expect(AuditLog::query()->whereKey($auditLog->id)->exists())->toBeTrue();
});

test('truncating audit_logs is rejected by the database', function () {
    expect(fn () => DB::statement('TRUNCATE TABLE audit_logs'))
        ->toThrow(QueryException::class, 'Audit log entries cannot be truncated.');
});

test('deleting the actor still cascades an actor_id nullification on audit_logs', function () {
    $organization = Organization::factory()->create();
    $actor = User::factory()->create();
    $auditLog = createAppendOnlyAuditLog($organization, $actor);

    $actor->delete();

    expect($auditLog->fresh()->actor_id)->toBeNull();
});
