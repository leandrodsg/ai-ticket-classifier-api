<?php

namespace Tests\Unit\Services\Security;

use App\Models\UsedNonce;
use App\Services\Security\NonceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class NonceServiceTest extends TestCase
{
    use RefreshDatabase;

    private NonceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NonceService();
    }

    public function test_it_generates_32_character_nonce()
    {
        $nonce = $this->service->generate();

        $this->assertIsString($nonce);
        $this->assertEquals(32, strlen($nonce));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{32}$/', $nonce);
    }

    public function test_it_generates_unique_nonces()
    {
        $nonce1 = $this->service->generate();
        $nonce2 = $this->service->generate();

        $this->assertNotEquals($nonce1, $nonce2);
    }

    public function test_it_validates_first_use_of_nonce()
    {
        $nonce = $this->service->generate();

        $isValid = $this->service->validate($nonce);

        $this->assertTrue($isValid);

        // Verify nonce was stored in database
        $this->assertDatabaseHas('used_nonces', [
            'nonce' => $nonce,
        ]);
    }

    public function test_it_rejects_reuse_of_nonce()
    {
        $nonce = $this->service->generate();

        // First use should be valid
        $firstUse = $this->service->validate($nonce);
        $this->assertTrue($firstUse);

        // Second use should be invalid (replay attack)
        $secondUse = $this->service->validate($nonce);
        $this->assertFalse($secondUse);
    }

    public function test_it_marks_nonce_as_used_with_expiration()
    {
        $nonce = 'test_nonce_12345678901234567890123456789012';
        $expiresAt = Carbon::now()->addHours(2);

        $this->service->markAsUsed($nonce, $expiresAt);

        $this->assertDatabaseHas('used_nonces', [
            'nonce' => $nonce,
            'expires_at' => $expiresAt->toDateTimeString(),
        ]);
    }

    public function test_it_marks_nonce_as_used_with_default_expiration()
    {
        $nonce = $this->service->generate();

        $this->service->validate($nonce);

        $usedNonce = UsedNonce::where('nonce', $nonce)->first();

        // Should expire in 1 hour from creation
        $expectedExpiration = Carbon::now()->addHour();
        $this->assertEquals(
            $expectedExpiration->format('Y-m-d H:i'),
            $usedNonce->expires_at->format('Y-m-d H:i')
        );
    }

    public function test_it_cleans_up_expired_nonces()
    {
        // Create expired nonce
        $expiredNonce = 'expired_nonce_12345678901234567890123456789012';
        UsedNonce::create([
            'nonce' => $expiredNonce,
            'used_at' => Carbon::now()->subHours(2),
            'expires_at' => Carbon::now()->subHour(),
        ]);

        // Create valid nonce
        $validNonce = 'valid_nonce_12345678901234567890123456789012';
        UsedNonce::create([
            'nonce' => $validNonce,
            'used_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addHour(),
        ]);

        // Clean up expired nonces
        $deletedCount = $this->service->cleanup();

        $this->assertEquals(1, $deletedCount);

        // Verify expired nonce was deleted
        $this->assertDatabaseMissing('used_nonces', [
            'nonce' => $expiredNonce,
        ]);

        // Verify valid nonce still exists
        $this->assertDatabaseHas('used_nonces', [
            'nonce' => $validNonce,
        ]);
    }

    public function test_it_returns_zero_when_no_expired_nonces()
    {
        // Create only valid nonces
        $nonce1 = 'valid1_12345678901234567890123456789012';
        UsedNonce::create([
            'nonce' => $nonce1,
            'used_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $nonce2 = 'valid2_12345678901234567890123456789012';
        UsedNonce::create([
            'nonce' => $nonce2,
            'used_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addHours(2),
        ]);

        $deletedCount = $this->service->cleanup();

        $this->assertEquals(0, $deletedCount);
    }

    public function test_it_checks_if_nonce_is_expired()
    {
        // Create expired nonce
        $expiredNonce = 'expired_12345678901234567890123456789012';
        UsedNonce::create([
            'nonce' => $expiredNonce,
            'used_at' => Carbon::now()->subHours(2),
            'expires_at' => Carbon::now()->subHour(),
        ]);

        // Create valid nonce
        $validNonce = 'valid_12345678901234567890123456789012';
        UsedNonce::create([
            'nonce' => $validNonce,
            'used_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $this->assertTrue($this->service->isExpired($expiredNonce));
        $this->assertFalse($this->service->isExpired($validNonce));
    }

    public function test_it_returns_false_for_nonexistent_nonce()
    {
        $nonexistentNonce = 'does_not_exist_12345678901234567890123456789012';

        $this->assertFalse($this->service->isExpired($nonexistentNonce));
    }
}
