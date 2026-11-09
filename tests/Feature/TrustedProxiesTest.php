<?php

namespace Tests\Feature;

use App\Models\ReviewCampaign;
use App\Models\User;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PR-2: the real client IP behind the Hostinger CDN is only taken from
 * X-Forwarded-For when the request comes from a configured trusted proxy;
 * forged headers from anyone else are ignored. Login must be unaffected.
 */
class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    private function diagnose(string $peer, array $headers = [])
    {
        return $this->actingAs(User::factory()->create())
            ->withServerVariables(['REMOTE_ADDR' => $peer])
            ->withHeaders($headers)
            ->getJson(route('admin.diagnostics.client-ip'));
    }

    // ───────────────────────── Default: trust nobody ─────────────────────────

    public function test_by_default_nobody_is_trusted_so_a_forged_header_is_ignored(): void
    {
        $this->assertSame([], config('trustedproxy.proxies'));

        $this->diagnose('203.0.113.5', ['X-Forwarded-For' => '198.51.100.7'])
            ->assertOk()
            ->assertJsonPath('resolved_ip', '203.0.113.5')
            ->assertJsonPath('remote_addr', '203.0.113.5');
    }

    public function test_the_diagnostics_page_requires_a_logged_in_admin(): void
    {
        $this->getJson(route('admin.diagnostics.client-ip'))->assertUnauthorized();
        $this->get(route('admin.diagnostics.client-ip'))->assertRedirect(route('login'));
    }

    // ───────────────────────── Trusted proxy (CDN) configured ─────────────────────────

    public function test_client_ip_is_read_from_x_forwarded_for_when_the_peer_is_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.0/8']]);

        $this->diagnose('10.1.2.3', ['X-Forwarded-For' => '198.51.100.7'])
            ->assertJsonPath('resolved_ip', '198.51.100.7')
            ->assertJsonPath('remote_addr', '10.1.2.3');
    }

    public function test_an_ip_forged_by_the_visitor_in_front_of_the_real_one_is_ignored(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.0/8']]);

        // The visitor sent "X-Forwarded-For: 1.1.1.1"; the CDN appended the real address.
        $this->diagnose('10.1.2.3', ['X-Forwarded-For' => '1.1.1.1, 198.51.100.7'])
            ->assertJsonPath('resolved_ip', '198.51.100.7');
    }

    public function test_a_peer_outside_the_trusted_list_cannot_forge_its_ip(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.0/8']]);

        $this->diagnose('203.0.113.9', ['X-Forwarded-For' => '10.1.2.3'])
            ->assertJsonPath('resolved_ip', '203.0.113.9');
    }

    public function test_only_x_forwarded_for_is_honoured_so_url_generation_is_unchanged(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.0/8']]);

        $this->diagnose('10.1.2.3', [
            'X-Forwarded-For'   => '198.51.100.7',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host'  => 'evil.example',
            'X-Forwarded-Port'  => '8443',
        ])
            ->assertJsonPath('resolved_ip', '198.51.100.7')
            ->assertJsonPath('is_secure', false);
    }

    public function test_wildcard_is_an_explicit_opt_in_that_trusts_the_calling_proxy(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->diagnose('198.18.0.1', ['X-Forwarded-For' => '192.0.2.50'])
            ->assertJsonPath('resolved_ip', '192.0.2.50');
    }

    public function test_diagnostics_report_what_the_server_received(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.0/8']]);

        $this->diagnose('10.1.2.3', ['X-Forwarded-For' => '198.51.100.7', 'CF-Connecting-IP' => '198.51.100.7'])
            ->assertJsonPath('forwarding_headers_received.X-Forwarded-For', '198.51.100.7')
            ->assertJsonPath('forwarding_headers_received.CF-Connecting-IP', '198.51.100.7')
            ->assertJsonPath('trusted_proxies_config.0', '10.0.0.0/8');
    }

    // ───────────────────────── Rate limiting behind the CDN ─────────────────────────

    public function test_students_behind_a_trusted_cdn_get_their_own_ip_buckets(): void
    {
        config([
            'trustedproxy.proxies'                    => ['10.0.0.0/8'],
            'ratelimits.review_submit.per_ip'         => 3,
            'ratelimits.review_submit.per_session'    => 100,
        ]);
        $this->withoutMiddleware([EncryptCookies::class]);
        $campaign = ReviewCampaign::factory()->create();

        $submit = function (int $student, string $clientIp) use ($campaign) {
            return $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']) // always the CDN edge
                ->withHeaders(['X-Forwarded-For' => $clientIp])
                ->withCredentials()
                ->withUnencryptedCookie(config('session.cookie'), substr(hash('sha256', "s$student"), 0, 40))
                ->postJson(route('review.store', $campaign->token), [
                    'student_name' => "s$student", 'email' => "s$student@example.test", 'overall_rating' => 5, 'course_content_rating' => 4,
                    'instructor_rating' => 5, 'organization_rating' => 4, 'difficulty_level' => 'متوسط',
                    'what_did_you_like' => 'a', 'what_can_be_improved' => 'b', 'would_recommend' => true,
                ]);
        };

        foreach ([1, 2, 3] as $i) {
            $submit($i, '198.51.100.1')->assertOk();
        }
        $submit(4, '198.51.100.1')->assertStatus(429);   // same real client IP: limited
        $submit(5, '198.51.100.2')->assertOk();          // other real client behind the same CDN edge: fine
    }

    // ───────────────────────── Login is unaffected ─────────────────────────

    public function test_login_page_and_login_work_with_the_default_configuration(): void
    {
        $user = User::factory()->create();

        $this->get(route('login'))->assertOk();
        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_still_rejected(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_works_through_a_trusted_cdn_with_forwarded_headers(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.0/8']]);
        $user = User::factory()->create();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.7', 'X-Forwarded-Proto' => 'https'])
            ->post(route('login.post'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_not_blocked_when_the_review_limiter_is_exhausted_for_the_same_ip(): void
    {
        config(['ratelimits.review_submit.per_ip' => 1]);
        $this->withoutMiddleware([EncryptCookies::class]);
        $campaign = ReviewCampaign::factory()->create();
        $user = User::factory()->create();

        $post = fn (int $i) => $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->withCredentials()
            ->withUnencryptedCookie(config('session.cookie'), substr(hash('sha256', "login-s$i"), 0, 40))
            ->postJson(route('review.store', $campaign->token), [
                'student_name' => "s$i", 'email' => "s$i@example.test", 'overall_rating' => 5, 'course_content_rating' => 4,
                'instructor_rating' => 5, 'organization_rating' => 4, 'difficulty_level' => 'متوسط',
                'what_did_you_like' => 'a', 'what_can_be_improved' => 'b', 'would_recommend' => true,
            ]);

        $post(1)->assertOk();
        $post(2)->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->post(route('login.post'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }
}
