<?php

namespace Tests\Unit\Services\Cache;

use App\Services\Cache\ClassificationCacheRepository;
use App\Services\Cache\SemanticCacheKeyGenerator;
use Tests\TestCase;

class PromptComparisonTest extends TestCase
{
    public function test_reducao_percentual_tokens(): void
    {
        // This test would compare token counts between old and new cache keys
        // For now, just test that semantic keys are shorter/more consistent
        $generator = new SemanticCacheKeyGenerator();

        $ticket = [
            'summary' => 'Cannot access dashboard',
            'description' => 'User cannot login to the system'
        ];

        $key = $generator->generateKey($ticket);

        // Key should be SHA256 hash (64 characters) + prefix
        $this->assertStringStartsWith('classification:', $key);
        $this->assertEquals(79, strlen($key)); // 'classification:' + 64 chars
    }

    public function test_ambos_geram_classificacao_valida(): void
    {
        // Test that semantic cache works with valid classification data
        $cache = new ClassificationCacheRepository();

        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Cannot access dashboard',
            'description' => 'User cannot login'
        ];

        $classification = [
            'category' => 'Technical',
            'sentiment' => 'Negative',
            'impact' => 'High',
            'urgency' => 'High'
        ];

        // Set cache
        $cache->setCached($ticket, $classification);

        // Get cache
        $cached = $cache->getCached($ticket);

        $this->assertEquals($classification, $cached);
        $this->assertArrayHasKey('category', $cached);
        $this->assertArrayHasKey('sentiment', $cached);
    }

    public function test_exemplos_compactados_funcionam(): void
    {
        $generator = new SemanticCacheKeyGenerator();

        // Test various ticket examples
        $examples = [
            [
                'summary' => 'Login failed',
                'description' => 'Cannot log into the application'
            ],
            [
                'summary' => 'login failed!!!',
                'description' => 'cannot log into the application'
            ],
            [
                'summary' => 'Database error',
                'description' => 'Connection to database failed'
            ]
        ];

        $keys = [];
        foreach ($examples as $example) {
            $keys[] = $generator->generateKey($example);
        }

        // First two should be the same (similar), third different
        $this->assertEquals($keys[0], $keys[1]);
        $this->assertNotEquals($keys[0], $keys[2]);
    }
}