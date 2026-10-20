<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;

/**
 * Re-run the provider so it reads the trusted proxies setting set by the test.
 */
function trustProxies(?string $setting): void
{
    config(['app.trusted_proxies' => $setting]);
    (new AppServiceProvider(app()))->boot();
}

afterEach(function () {
    TrustProxies::flushState();
});

$proxyHeaders = [
    'X-Forwarded-Proto' => 'https',
    'X-Forwarded-Host' => 'gamevote.example.com',
    'X-Forwarded-Port' => '443',
];

test('behind a trusted proxy, generated urls use the forwarded https host', function () use ($proxyHeaders) {
    trustProxies('*');

    $this->get(route('dashboard'), $proxyHeaders)
        ->assertRedirect('https://gamevote.example.com/login');
});

test('forwarded headers are ignored when no proxy is trusted', function () use ($proxyHeaders) {
    trustProxies(null);
    $untrusted = config('app.url').'/login';

    $this->get(route('dashboard'), $proxyHeaders)
        ->assertRedirect($untrusted);
});

test('a list of proxy addresses only trusts those addresses', function () use ($proxyHeaders) {
    trustProxies('10.0.0.1, 10.0.0.2');
    $untrusted = config('app.url').'/login';

    $this->get(route('dashboard'), $proxyHeaders)
        ->assertRedirect($untrusted);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->get(route('dashboard'), $proxyHeaders)
        ->assertRedirect('https://gamevote.example.com/login');
});
