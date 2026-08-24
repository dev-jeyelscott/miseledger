<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /** @var resource|null */
    private static $testDatabaseLock = null;

    protected function setUpTheTestEnvironment(): void
    {
        self::acquireTestDatabaseLock();

        parent::setUpTheTestEnvironment();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private static function acquireTestDatabaseLock(): void
    {
        if (is_resource(self::$testDatabaseLock)) {
            return;
        }

        $lock = fopen(sys_get_temp_dir().'/miseledger-testing-database.lock', 'c');

        if ($lock === false) {
            throw new \RuntimeException('Unable to create the MiseLedger test database lock.');
        }

        if (! flock($lock, LOCK_EX)) {
            fclose($lock);

            throw new \RuntimeException('Unable to acquire the MiseLedger test database lock.');
        }

        self::$testDatabaseLock = $lock;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
