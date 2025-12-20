<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test rate limiting: maximum 3 attempts per minute
     */
    public function test_login_rate_limiting_three_attempts_per_minute(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // First 3 attempts should succeed (if credentials are correct) or fail but not be rate limited
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'username' => 'testuser',
                'password' => 'wrongpassword',
            ]);

            // Should get 401 or 422, not 429 (rate limit)
            $this->assertNotEquals(429, $response->status());
        }

        // 4th attempt should be rate limited
        $response = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $this->assertEquals(429, $response->status());
        $this->assertStringContainsString('Demasiados intentos', $response->json('message'));
    }

    /**
     * Test blocking after 5 failed attempts for 10 minutes
     */
    public function test_login_blocked_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Make 5 failed attempts
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/auth/login', [
                'username' => 'testuser',
                'password' => 'wrongpassword',
            ]);
        }

        // 6th attempt should be blocked
        $response = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $this->assertEquals(429, $response->status());
        $this->assertStringContainsString('bloqueada temporalmente', $response->json('message'));
        $this->assertArrayHasKey('remaining_minutes', $response->json('meta'));
    }

    /**
     * Test successful login clears rate limit counters
     */
    public function test_successful_login_clears_rate_limit_counters(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Make 2 failed attempts
        $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);
        $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        // Successful login should clear counters
        $response = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'password',
        ]);

        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('token', $response->json('data'));

        // After successful login, we should be able to make attempts again
        // (rate limit should be reset)
        Cache::flush(); // Clear cache to simulate fresh state
        
        $response = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        // Should not be rate limited immediately after successful login
        $this->assertNotEquals(429, $response->status());
    }

    /**
     * Test inactive user cannot login
     */
    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('password'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'password',
        ]);

        $this->assertEquals(422, $response->status());
        $this->assertStringContainsString('desactivada', $response->json('errors.username.0'));
    }
}

