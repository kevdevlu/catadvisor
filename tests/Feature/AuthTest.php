<?php

namespace Tests\Feature;

use App\Models\Advisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The demo login and the session gate.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_signed_out_visitors_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/students/anything')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_the_api_refuses_signed_out_requests(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_the_login_page_lists_advisors_then_the_admin(): void
    {
        $this->getJson('/login/accounts')
            ->assertOk()
            ->assertJsonPath('data.*.display_name', ['Lindqvist', 'Okafor', 'Reyes', 'Admin']);
    }

    public function test_the_right_password_signs_in(): void
    {
        $this->postJson('/login', ['username' => 'reyes', 'password' => 'test-password'])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Reyes')
            ->assertJsonPath('data.is_admin', false);

        $this->assertAuthenticatedAs(Advisor::where('username', 'reyes')->first());
    }

    public function test_the_session_cookie_expires_in_the_future_in_real_time(): void
    {
        // The dataset's "today" (Oct 5) must not leak into cookie expiry: Oct 5
        // plus 14 days is already past in real time, and the browser would
        // drop the cookie, so no one could stay signed in.
        $response = $this->postJson('/login', ['username' => 'reyes', 'password' => 'test-password']);

        $cookie = collect($response->headers->getCookies())
            ->firstWhere(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertGreaterThan(time(), $cookie->getExpiresTime());
    }

    public function test_a_wrong_password_gets_one_message_for_any_mistake(): void
    {
        $this->postJson('/login', ['username' => 'reyes', 'password' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'That password is not right.']);

        $this->postJson('/login', ['username' => 'nobody', 'password' => 'nope'])
            ->assertJsonValidationErrors(['password' => 'That password is not right.']);

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_tries(): void
    {
        foreach (range(1, 5) as $try) {
            $this->postJson('/login', ['username' => 'reyes', 'password' => 'nope'])->assertUnprocessable();
        }

        $this->postJson('/login', ['username' => 'reyes', 'password' => 'test-password'])->assertTooManyRequests();
    }

    public function test_signing_out_ends_the_session(): void
    {
        $this->actingAs(Advisor::where('username', 'reyes')->first());

        $this->postJson('/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_me_returns_the_signed_in_admin(): void
    {
        $this->actingAs(Advisor::where('username', 'admin')->first());

        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.is_admin', true);
    }
}
