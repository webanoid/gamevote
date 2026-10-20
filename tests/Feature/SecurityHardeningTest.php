<?php

use App\Models\User;

test('one ip address is limited across many usernames', function () {
    User::factory()->create(['username' => 'real_user']);

    foreach (range(1, 20) as $attempt) {
        $this->post(route('login.store'), ['username' => "guess_{$attempt}", 'password' => 'wrong-password']);
    }

    $this->post(route('login.store'), ['username' => 'real_user', 'password' => 'password'])
        ->assertTooManyRequests();

    $this->assertGuest();
});

test('a different ip address is not affected by another ip being limited', function () {
    foreach (range(1, 20) as $attempt) {
        $this->post(route('login.store'), ['username' => "guess_{$attempt}", 'password' => 'wrong-password']);
    }

    User::factory()->create(['username' => 'real_user']);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
        ->post(route('login.store'), ['username' => 'real_user', 'password' => 'password']);

    $this->assertAuthenticated();
});

test('an unexpected appearance cookie falls back to the system theme', function () {
    $response = $this->withUnencryptedCookie('appearance', "x';alert(1)//\\")
        ->get(route('login'));

    $response->assertOk();
    expect($response->getContent())
        ->toContain("const appearance = 'system';")
        ->not->toContain('alert(1)');
});

test('a known appearance cookie is used', function () {
    $this->withUnencryptedCookie('appearance', 'dark')
        ->get(route('login'))
        ->assertSee("const appearance = 'dark';", escape: false);
});

test('the content security policy allows only scripts carrying the request nonce', function () {
    config(['app.content_security_policy' => true]);

    $response = $this->get(route('login'));

    $policy = $response->headers->get('Content-Security-Policy');
    expect($policy)->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'self'");

    preg_match("/script-src 'self' 'nonce-([^']+)'/", (string) $policy, $match);
    expect($match)->toHaveCount(2);

    preg_match_all('/<script\b[^>]*>/', $response->getContent(), $scripts);
    $executable = array_filter($scripts[0], fn (string $tag) => ! str_contains($tag, 'application/json'));

    expect($executable)->not->toBeEmpty();
    foreach ($executable as $tag) {
        expect($tag)->toContain('nonce="'.$match[1].'"');
    }
});

test('no content security policy is sent when it is turned off', function () {
    config(['app.content_security_policy' => false]);

    $this->get(route('login'))->assertHeaderMissing('Content-Security-Policy');
});
