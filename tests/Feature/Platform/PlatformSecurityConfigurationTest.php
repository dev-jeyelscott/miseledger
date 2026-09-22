<?php

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

/**
 * POC-V10.4 / POC-V10.7: `platform:verify-security-config` is the objective,
 * scriptable deployment gate for session/cookie/debug/URL settings that
 * would otherwise weaken the privileged platform boundary in production.
 */
test('the security config command fails closed when settings are unsafe', function () {
    Config::set('app.debug', true);
    Config::set('session.secure', false);
    Config::set('session.http_only', true);
    Config::set('session.same_site', 'lax');
    Config::set('app.url', 'http://mise-ledger.example.com');

    $this->artisan('platform:verify-security-config')
        ->assertExitCode(Command::FAILURE);
});

test('the security config command passes when settings are production-safe', function () {
    Config::set('app.debug', false);
    Config::set('session.secure', true);
    Config::set('session.http_only', true);
    Config::set('session.same_site', 'lax');
    Config::set('app.url', 'https://mise-ledger.example.com');

    $this->artisan('platform:verify-security-config')
        ->assertExitCode(Command::SUCCESS);
});

test('the security config command never echoes the actual configured URL or debug value', function () {
    Config::set('app.debug', true);
    Config::set('app.url', 'http://leaked-internal-hostname.example.com');

    Artisan::call('platform:verify-security-config');

    expect(Artisan::output())
        ->not->toContain('leaked-internal-hostname')
        ->toContain('APP_URL must use https');
});
