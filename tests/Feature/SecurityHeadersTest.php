<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The four security headers.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_every_response_carries_the_headers(): void
    {
        foreach (['/login', '/api/me', '/login/accounts'] as $path) {
            $this->get($path)
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'same-origin');
        }
    }

    public function test_hsts_is_sent_only_in_production_over_https(): void
    {
        $this->get('https://localhost/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->app['env'] = 'production';

        $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
