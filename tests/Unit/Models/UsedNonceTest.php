<?php

namespace Tests\Unit\Models;

use App\Models\UsedNonce;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsedNonceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_nonce_as_primary_key(): void
    {
        $nonce = UsedNonce::create([
            'nonce' => 'test-nonce-123',
            'expires_at' => now()->addHour()
        ]);
        
        $found = UsedNonce::where('nonce', 'test-nonce-123')->first();
        $this->assertNotNull($found);
        $this->assertEquals('test-nonce-123', $found->nonce);
    }

    public function test_primary_key_is_not_auto_incrementing(): void
    {
        $nonce1 = UsedNonce::create(['nonce' => 'nonce-1', 'expires_at' => now()->addHour()]);
        $nonce2 = UsedNonce::create(['nonce' => 'nonce-2', 'expires_at' => now()->addHour()]);
        
        // Nonces must be strings, not sequential integers
        $this->assertIsString($nonce1->nonce);
        $this->assertIsString($nonce2->nonce);
        $this->assertEquals('nonce-1', $nonce1->nonce);
        $this->assertEquals('nonce-2', $nonce2->nonce);
    }

    public function test_expired_scope(): void
    {
        UsedNonce::create(['nonce' => 'old', 'expires_at' => now()->subHour()]);
        UsedNonce::create(['nonce' => 'new', 'expires_at' => now()->addHour()]);
        
        $expired = UsedNonce::expired()->get();
        
        $this->assertCount(1, $expired);
        $this->assertEquals('old', $expired->first()->nonce);
    }

    public function test_can_delete_by_nonce(): void
    {
        $nonce = UsedNonce::create(['nonce' => 'to-delete', 'expires_at' => now()->addHour()]);
        
        $nonce->delete();
        
        $this->assertNull(UsedNonce::where('nonce', 'to-delete')->first());
    }

    public function test_uses_string_primary_key_type(): void
    {
        $nonce = UsedNonce::create(['nonce' => 'string-key-test', 'expires_at' => now()->addHour()]);
        
        $this->assertIsString($nonce->nonce);
        $this->assertEquals('string-key-test', $nonce->nonce);
    }

    public function test_does_not_use_timestamps(): void
    {
        $nonce = UsedNonce::create(['nonce' => 'timestamp-test', 'expires_at' => now()->addHour()]);
        
        // Model tem $timestamps = false
        $this->assertObjectNotHasProperty('created_at', $nonce);
        $this->assertObjectNotHasProperty('updated_at', $nonce);
    }
}
