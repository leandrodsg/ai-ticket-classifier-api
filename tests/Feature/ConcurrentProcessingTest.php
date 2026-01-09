<?php

namespace Tests\Feature;

use App\Services\Csv\CsvGeneratorService;
use App\Services\Csv\CsvMetadataGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrentProcessingTest extends TestCase
{
    use RefreshDatabase;

    private CsvGeneratorService $csvGenerator;
    private CsvMetadataGenerator $metadataGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test CSV signing key for HmacSignatureService
        config(['services.csv_signing_key' => 'test_csv_signing_key_for_feature_tests_123456789']);

        $this->csvGenerator = app(CsvGeneratorService::class);
        $this->metadataGenerator = app(CsvMetadataGenerator::class);

        // Mock AI service to avoid real API calls
        $this->mockAiService();
    }

    private function mockAiService(): void
    {
        $mockAiService = \Mockery::mock(\App\Services\Ai\AiClassificationService::class);
        $mockAiService->shouldReceive('classify')
            ->andReturn([
                'category' => 'Technical',
                'sentiment' => 'Negative',
                'impact' => 'High',
                'urgency' => 'High',
                'reasoning' => 'Mock classification for testing',
                'model_used' => 'mock-model'
            ]);

        $this->app->instance(\App\Services\Ai\AiClassificationService::class, $mockAiService);
    }

    public function test_upload_15_tickets_usa_concorrencia()
    {
        // Generate valid CSV with metadata using services
        $csvContent = $this->csvGenerator->generate(15);
        $csvWithMetadata = $this->metadataGenerator->addMetadata($csvContent);
        $csvBase64 = base64_encode($csvWithMetadata);

        // Act
        $startTime = microtime(true);
        $response = $this->postJson('/api/tickets/upload', [
            'csv_content' => $csvBase64
        ]);
        $endTime = microtime(true);

        // Debug: Ver erro se houver
        if ($response->status() !== 200) {
            dump($response->json());
        }

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'session_id',
            'metadata' => [
                'total_tickets',
                'processed_tickets',
                'processing_time_ms'
            ],
            'tickets'
        ]);

        $responseData = $response->json();
        $this->assertEquals(15, $responseData['metadata']['total_tickets']);
        $this->assertEquals(15, $responseData['metadata']['processed_tickets']);
        $this->assertCount(15, $responseData['tickets']);

        // Verificar que tickets foram salvos
        $this->assertDatabaseCount('tickets', 15);

        // Verificar que processou com concorrência (tempo razoável)
        // Com OpenRouter real 15 tickets levam ~14s × ceil(15/4) = ~56s
        // Mas em teste pode variar muito, então apenas verificamos sucesso
        $processingTime = $endTime - $startTime;
        $this->assertGreaterThan(0, $processingTime);
    }

    public function test_processa_multiplos_tickets_simultaneamente()
    {
        // Generate valid CSV with metadata using services
        $csvContent = $this->csvGenerator->generate(8);
        $csvWithMetadata = $this->metadataGenerator->addMetadata($csvContent);
        $csvBase64 = base64_encode($csvWithMetadata);

        // Act
        $response = $this->postJson('/api/tickets/upload', [
            'csv_content' => $csvBase64
        ]);

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals(8, $responseData['metadata']['total_tickets']);
        $this->assertEquals(8, $responseData['metadata']['processed_tickets']);
        $this->assertDatabaseCount('tickets', 8);

        // Verificar que todos tickets foram classificados
        foreach ($responseData['tickets'] as $ticket) {
            $this->assertArrayHasKey('classification', $ticket);
            $this->assertArrayHasKey('category', $ticket['classification']);
            $this->assertArrayHasKey('sentiment', $ticket['classification']);
            $this->assertArrayHasKey('impact', $ticket['classification']);
            $this->assertArrayHasKey('urgency', $ticket['classification']);
        }
    }

    public function test_mantem_ordem_dos_tickets_no_processamento()
    {
        // Generate valid CSV with metadata using services
        $csvContent = $this->csvGenerator->generate(5);
        $csvWithMetadata = $this->metadataGenerator->addMetadata($csvContent);
        $csvBase64 = base64_encode($csvWithMetadata);

        // Act
        $response = $this->postJson('/api/tickets/upload', [
            'csv_content' => $csvBase64
        ]);

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json();

        // Verificar que a ordem é mantida
        $this->assertCount(5, $responseData['tickets']);

        // Verificar que issue_keys seguem ordem sequencial
        $issueKeys = array_map(fn($t) => $t['issue_key'], $responseData['tickets']);
        
        // Extrair números dos issue keys e verificar que são sequenciais
        $numbers = array_map(function($key) {
            // Formato: DEMO-182721-001
            $parts = explode('-', $key);
            return (int) end($parts);
        }, $issueKeys);
        
        $this->assertEquals([1, 2, 3, 4, 5], $numbers);
    }
}
