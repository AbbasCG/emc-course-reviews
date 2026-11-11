<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The admin login had no brute-force protection at all (PR-5 audit). */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(string $email, string $password, string $ip = '203.0.113.5', bool $json = false)
    {
        $client = $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        return $json ? $client->postJson(route('login.post'), compact('email', 'password')) : $client->post(route('login.post'), compact('email', 'password'));
    }

    public function test_wrong_passwords_are_stopped_after_the_per_account_limit(): void
    {
        $limit = config('ratelimits.login.per_account_ip');

        foreach (range(1, $limit) as $i) {
            $this->attempt('admin@example.test', "wrong-$i")->assertSessionHasErrors(['email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة']);
        }
        $blocked = $this->attempt('admin@example.test', 'wrong-next');

        $blocked->assertStatus(429)->assertHeader('Retry-After')->assertSee('محاولات تسجيل دخول كثيرة');
        $this->assertGreaterThanOrEqual(1, (int) $blocked->headers->get('Retry-After'));
    }

    public function test_the_correct_password_is_refused_too_while_blocked_so_guessing_cannot_continue(): void
    {
        User::factory()->create(['email' => 'admin@example.test', 'password' => bcrypt('right-password')]);
        foreach (range(1, config('ratelimits.login.per_account_ip')) as $i) {
            $this->attempt('admin@example.test', "wrong-$i");
        }

        $this->attempt('admin@example.test', 'right-password')->assertStatus(429);
        $this->assertGuest();
    }

    public function test_a_remote_attacker_cannot_lock_the_real_admin_out_from_the_admins_own_network(): void
    {
        User::factory()->create(['email' => 'admin@example.test', 'password' => bcrypt('right-password')]);
        foreach (range(1, config('ratelimits.login.per_account_ip') + 5) as $i) {
            $this->attempt('admin@example.test', "guess-$i", '198.51.100.66');   // the attacker's address
        }

        $this->attempt('admin@example.test', 'right-password', '203.0.113.5')->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_one_ip_trying_many_accounts_hits_the_per_ip_limit(): void
    {
        $limit = config('ratelimits.login.per_ip');
        $results = [];
        foreach (range(1, $limit + 3) as $i) {
            $results[] = $this->attempt("user$i@example.test", 'x', '203.0.113.9');   // a different account every time
        }

        $this->assertNull($results[$limit - 1]->headers->get('Retry-After'), 'the last attempt inside the limit is still evaluated');
        $this->assertNotNull($results[$limit]->headers->get('Retry-After'), 'the first attempt above the limit is blocked');
        $this->assertNotNull($results[$limit + 2]->headers->get('Retry-After'));
    }

    public function test_the_limit_applies_to_e_mail_case_variants_and_json_clients_get_a_429(): void
    {
        foreach (range(1, config('ratelimits.login.per_account_ip')) as $i) {
            $this->attempt(($i % 2 ? 'Admin@Example.test' : 'admin@example.test'), "w$i");
        }

        $this->attempt('ADMIN@example.test', 'again', '203.0.113.5', true)->assertStatus(429)->assertHeader('Retry-After')->assertJsonStructure(['message', 'retry_after']);
    }

    public function test_an_array_instead_of_an_e_mail_is_a_validation_error_not_a_crash(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->postJson(route('login.post'), ['email' => ['a@example.test'], 'password' => 'x'])
            ->assertStatus(422);
    }

    public function test_the_limit_resets_after_a_minute(): void
    {
        foreach (range(1, config('ratelimits.login.per_account_ip') + 1) as $i) {
            $this->attempt('admin@example.test', "w$i");
        }
        $this->travel(61)->seconds();

        $this->attempt('admin@example.test', 'w-after')->assertSessionHasErrors(['email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة']);
    }

    public function test_the_login_route_has_the_throttle_middleware(): void
    {
        $middleware = \Illuminate\Support\Facades\Route::getRoutes()->getByName('login.post')->gatherMiddleware();

        $this->assertContains(\App\Http\Middleware\ThrottleLoginAttempts::class, $middleware);
    }
}
