<?php

test('configures PostgreSQL and Redis drivers', function () {
    expect(config('database.connections.pgsql.driver'))->toBe('pgsql')
        ->and(config('database.redis.client'))->toBe('phpredis')
        ->and(config('cache.stores.redis.driver'))->toBe('redis')
        ->and(config('queue.connections.redis.driver'))->toBe('redis');
});
