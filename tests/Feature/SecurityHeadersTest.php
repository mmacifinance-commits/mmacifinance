<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_test/security-headers', fn () => response('ok'));
    }

    public function test_web_responses_include_security_headers(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $response = $this->withServerVariables(['HTTPS' => 'on'])->get('/_test/security-headers');

        $response->assertOk();
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Embedder-Policy', 'require-corp');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=(), usb=()');

        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringContainsString("style-src 'self' https://fonts.bunny.net", $policy);
        $this->assertStringContainsString("style-src-attr 'unsafe-inline'", $policy);
        $this->assertStringNotContainsString("style-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString('https://fonts.bunny.net', $policy);
        $this->assertStringContainsString('upgrade-insecure-requests', $policy);
    }

    public function test_hsts_is_not_sent_over_local_plain_http(): void
    {
        app()->detectEnvironment(fn () => 'local');

        $response = $this->get('/_test/security-headers');

        $response->assertOk();
        $response->assertHeaderMissing('Strict-Transport-Security');

        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'unsafe-eval' http://127.0.0.1:5173", $policy);
        $this->assertStringContainsString('ws://127.0.0.1:5173', $policy);
        $this->assertStringNotContainsString('upgrade-insecure-requests', $policy);
    }

    public function test_security_headers_are_added_to_early_auth_redirects(): void
    {
        app()->detectEnvironment(fn () => 'production');

        Route::middleware(['web', 'auth'])
            ->get('/_test/protected-security-headers', fn () => response('protected'));

        $response = $this->get('/_test/protected-security-headers');

        $response->assertRedirect('/login');
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_xsrf_cookie_remains_readable_while_session_cookie_is_http_only(): void
    {
        config([
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);

        $response = $this->withServerVariables(['HTTPS' => 'on'])->get('/_test/security-headers');
        $cookies = collect($response->headers->getCookies());
        $xsrfCookie = $cookies->first(fn ($cookie) => $cookie->getName() === 'XSRF-TOKEN');
        $sessionCookie = $cookies->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($xsrfCookie);
        $this->assertFalse($xsrfCookie->isHttpOnly());
        $this->assertTrue($xsrfCookie->isSecure());
        $this->assertSame('lax', $xsrfCookie->getSameSite());

        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertSame('lax', $sessionCookie->getSameSite());
    }
}
