<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\AiClassificationService;
use App\Services\Ai\AllModelsFailedException;
use App\Services\Ai\ClassificationPrompt;
use App\Services\Ai\ModelDiscoveryService;
use App\Services\Ai\OpenRouterClient;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AiClassificationServiceTest extends TestCase
{
    private AiClassificationService $service;
    private OpenRouterClient $mockClient;
    private ClassificationPrompt $mockPrompt;
    private ModelDiscoveryService $mockDiscovery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = $this->createMock(OpenRouterClient::class);
        $this->mockPrompt = $this->createMock(ClassificationPrompt::class);
        $this->mockDiscovery = $this->createMock(ModelDiscoveryService::class);

        $this->service = new AiClassificationService(
            $this->mockClient,
            $this->mockPrompt,
            $this->mockDiscovery
        );
    }

    public function test_classify_returns_first_successful_model()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test ticket',
            'description' => 'Test description',
            'reporter' => 'test@example.com'
        ];

        $expectedClassification = [
            'category' => 'Technical',
            'sentiment' => 'Negative',
            'impact' => 'High',
            'urgency' => 'High',
            'reasoning' => 'Test reasoning',
            'model_used' => 'google/gemini-2.0-flash-exp:free'
        ];

        $this->mockPrompt->expects($this->once())
            ->method('build')
            ->with($ticket)
            ->willReturn('Test prompt');

        $this->mockClient->expects($this->once())
            ->method('callApi')
            ->with('google/gemini-2.0-flash-exp:free', [['role' => 'user', 'content' => 'Test prompt']])
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($expectedClassification)
                        ]
                    ]
                ]
            ]);

        Log::shouldReceive('info')
            ->once()
            ->with('AI classification successful', $this->callback(function ($context) use ($ticket) {
                return $context['ticket_key'] === $ticket['issue_key'] &&
                       isset($context['processing_time_ms']);
            }));

        $result = $this->service->classify($ticket);

        $this->assertEquals($expectedClassification, $result);
    }

    public function test_classify_fallback_to_second_model_on_failure()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test ticket',
            'description' => 'Test description',
            'reporter' => 'test@example.com'
        ];

        $expectedClassification = [
            'category' => 'Technical',
            'sentiment' => 'Negative',
            'impact' => 'High',
            'urgency' => 'High',
            'reasoning' => 'Test reasoning'
        ];

        $this->mockPrompt->expects($this->exactly(3))
            ->method('build')
            ->willReturn('Test prompt');

        // Mock to fail on first two models, succeed on third
        $this->mockClient->expects($this->exactly(3))
            ->method('callApi')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \Exception('First model failed')),
                $this->throwException(new \Exception('Second model failed')),
                [
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode($expectedClassification)
                            ]
                        ]
                    ]
                ]
            );

        Log::shouldReceive('warning')->times(2);
        Log::shouldReceive('info')->once();

        $result = $this->service->classify($ticket);

        $this->assertEquals($expectedClassification['category'], $result['category']);
        $this->assertArrayHasKey('model_used', $result);
        $this->assertNotEmpty($result['model_used']);
    }

    public function test_classify_throws_exception_when_all_models_fail()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test ticket',
            'description' => 'Test description',
            'reporter' => 'test@example.com'
        ];

        $this->mockPrompt->expects($this->exactly(3))
            ->method('build')
            ->willReturn('Test prompt');

        $this->mockClient->expects($this->exactly(3))
            ->method('callApi')
            ->willThrowException(new \Exception('All models failed'));

        // Mock discovery to return empty array (no models discovered)
        $this->mockDiscovery->expects($this->once())
            ->method('discoverFreeModels')
            ->willReturn([]);

        Log::shouldReceive('warning')->times(3);
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        $this->expectException(AllModelsFailedException::class);
        $this->expectExceptionMessage('All default models failed and no models discovered');

        $this->service->classify($ticket);
    }

    public function test_classify_validates_response_structure()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test ticket',
            'description' => 'Test description',
            'reporter' => 'test@example.com'
        ];

        $this->mockPrompt->expects($this->once())
            ->method('build')
            ->willReturn('Test prompt');

        $this->mockClient->expects($this->once())
            ->method('callApi')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'invalid' => 'response'
                            ])
                        ]
                    ]
                ]
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required field: category');

        $this->service->classify($ticket);
    }

    public function test_classify_validates_category_enum()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test ticket',
            'description' => 'Test description',
            'reporter' => 'test@example.com'
        ];

        $this->mockPrompt->expects($this->once())
            ->method('build')
            ->willReturn('Test prompt');

        $this->mockClient->expects($this->once())
            ->method('callApi')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'category' => 'InvalidCategory',
                                'sentiment' => 'Negative',
                                'impact' => 'High',
                                'urgency' => 'High',
                                'reasoning' => 'Test'
                            ])
                        ]
                    ]
                ]
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid category: InvalidCategory');

        $this->service->classify($ticket);
    }

    public function test_classify_validates_sentiment_enum()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test ticket',
            'description' => 'Test description',
            'reporter' => 'test@example.com'
        ];

        $this->mockPrompt->expects($this->once())
            ->method('build')
            ->willReturn('Test prompt');

        $this->mockClient->expects($this->once())
            ->method('callApi')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'category' => 'Technical',
                                'sentiment' => 'InvalidSentiment',
                                'impact' => 'High',
                                'urgency' => 'High',
                                'reasoning' => 'Test'
                            ])
                        ]
                    ]
                ]
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid sentiment: InvalidSentiment');

        $this->service->classify($ticket);
    }

    public function test_classify_handles_invalid_json_response()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test ticket',
            'description' => 'Test description',
            'reporter' => 'test@example.com'
        ];

        $this->mockPrompt->expects($this->once())
            ->method('build')
            ->willReturn('Test prompt');

        $this->mockClient->expects($this->once())
            ->method('callApi')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Invalid JSON response'
                        ]
                    ]
                ]
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid JSON response from AI model');

        $this->service->classify($ticket);
    }
}
