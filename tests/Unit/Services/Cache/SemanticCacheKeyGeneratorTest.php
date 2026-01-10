<?php

namespace Tests\Unit\Services\Cache;

use App\Services\Cache\SemanticCacheKeyGenerator;
use Tests\TestCase;

class SemanticCacheKeyGeneratorTest extends TestCase
{
    private SemanticCacheKeyGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new SemanticCacheKeyGenerator();
    }

    public function test_normaliza_texto_corretamente(): void
    {
        $input = "Cannot Access Dashboard!!!";
        $expected = "cannot access dashboard";

        $result = $this->generator->normalizeText($input);

        $this->assertEquals($expected, $result);
    }

    public function test_tickets_similares_geram_mesma_chave(): void
    {
        $ticket1 = [
            'summary' => 'Cannot access dashboard',
            'description' => 'User cannot login'
        ];

        $ticket2 = [
            'summary' => 'cannot access Dashboard!!!',
            'description' => 'user cannot login'
        ];

        $key1 = $this->generator->generateKey($ticket1);
        $key2 = $this->generator->generateKey($ticket2);

        $this->assertEquals($key1, $key2);
    }

    public function test_tickets_diferentes_geram_chaves_diferentes(): void
    {
        $ticket1 = [
            'summary' => 'Login problem',
            'description' => 'Cannot authenticate'
        ];

        $ticket2 = [
            'summary' => 'Payment issue',
            'description' => 'Transaction failed'
        ];

        $key1 = $this->generator->generateKey($ticket1);
        $key2 = $this->generator->generateKey($ticket2);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_remove_timestamps_e_numeros(): void
    {
        $input = "Error on 2025-01-09 at 14:30 with code 500";
        $expected = "error code";

        $result = $this->generator->normalizeText($input);

        $this->assertEquals($expected, $result);
    }

    public function test_remove_stopwords(): void
    {
        $input = "The user cannot access the dashboard";
        $expected = "user cannot access dashboard";

        $result = $this->generator->normalizeText($input);

        $this->assertEquals($expected, $result);
    }

    public function test_mantem_palavras_importantes(): void
    {
        $input = "Database connection failed";
        $expected = "database connection failed";

        $result = $this->generator->normalizeText($input);

        $this->assertEquals($expected, $result);
        $this->assertStringContainsString('database', $result);
        $this->assertStringContainsString('connection', $result);
        $this->assertStringContainsString('failed', $result);
    }
}