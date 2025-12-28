<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ValidateCsvFormat;
use App\Services\Csv\CsvParser;
use App\Services\Csv\CsvSanitizer;
use App\Services\Csv\CsvValidator;
use Illuminate\Http\Request;
use Tests\TestCase;

class ValidateCsvFormatTest extends TestCase
{
    private CsvParser $parser;
    private CsvSanitizer $sanitizer;
    private CsvValidator $validator;
    private ValidateCsvFormat $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->parser = $this->createMock(CsvParser::class);
        $this->sanitizer = $this->createMock(CsvSanitizer::class);
        $this->validator = $this->createMock(CsvValidator::class);
        $this->middleware = new ValidateCsvFormat($this->parser, $this->sanitizer, $this->validator);
    }

    public function test_passes_through_requests_without_csv_content()
    {
        $request = Request::create('/api/other', 'POST', [
            'some_field' => 'value'
        ]);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_rejects_empty_csv_content()
    {
        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => ''
        ]);

        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not reach next middleware');
        });

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('INVALID_CSV_FORMAT', $data['error']);
        $this->assertStringContainsString('non-empty string', $data['message']);
    }

    public function test_rejects_non_string_csv_content()
    {
        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => ['array' => 'not string']
        ]);

        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not reach next middleware');
        });

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('INVALID_CSV_FORMAT', $data['error']);
    }

    public function test_rejects_csv_exceeding_5mb_limit()
    {
        $largeCsv = str_repeat('a', 5 * 1024 * 1024 + 1); // 5MB + 1 byte

        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => $largeCsv
        ]);

        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not reach next middleware');
        });

        $this->assertEquals(413, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('PAYLOAD_TOO_LARGE', $data['error']);
        $this->assertArrayHasKey('file_size', $data['details']);
        $this->assertArrayHasKey('max_size', $data['details']);
    }

    public function test_accepts_csv_within_5mb_limit()
    {
        $validCsv = $this->generateValidCsv();

        $this->parser->expects($this->once())
            ->method('parse')
            ->with($validCsv)
            ->willReturn([
                'metadata' => [
                    'version' => 'v1',
                    'signature' => 'sig123',
                    'timestamp' => time(),
                    'session_id' => 'sess123',
                    'row_count' => '1',
                    'nonce' => 'nonce123',
                    'expires_at' => date('Y-m-d H:i:s', time() + 3600)
                ],
                'data_rows' => [
                    ['issue_key' => 'TEST-1', 'summary' => 'Test', 'description' => 'Description']
                ]
            ]);

        $this->validator->expects($this->once())
            ->method('validateSchema')
            ->willReturn(true);

        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => $validCsv
        ]);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_rejects_csv_without_metadata()
    {
        $csvContent = "issue_key,summary\nTEST-1,Test";

        $this->parser->expects($this->once())
            ->method('parse')
            ->with($csvContent)
            ->willReturn([
                'metadata' => [], // Empty metadata
                'data_rows' => [['issue_key' => 'TEST-1']]
            ]);

        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => $csvContent
        ]);

        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not reach next middleware');
        });

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('INVALID_CSV_FORMAT', $data['error']);
        $this->assertStringContainsString('metadata section', $data['message']);
    }

    public function test_rejects_csv_with_missing_metadata_field_version()
    {
        $csvContent = $this->generateValidCsv();

        $this->parser->expects($this->once())
            ->method('parse')
            ->willReturn([
                'metadata' => [
                    // 'version' => 'v1', // Missing version
                    'signature' => 'sig123',
                    'timestamp' => time(),
                    'session_id' => 'sess123',
                    'row_count' => '1',
                    'nonce' => 'nonce123',
                    'expires_at' => date('Y-m-d H:i:s')
                ],
                'data_rows' => [['issue_key' => 'TEST-1']]
            ]);

        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => $csvContent
        ]);

        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not reach next middleware');
        });

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('missing required field: version', $data['message']);
    }

    public function test_rejects_csv_with_invalid_version()
    {
        $csvContent = $this->generateValidCsv();

        $this->parser->expects($this->once())
            ->method('parse')
            ->willReturn([
                'metadata' => [
                    'version' => 'v2', // Invalid version
                    'signature' => 'sig123',
                    'timestamp' => time(),
                    'session_id' => 'sess123',
                    'row_count' => '1',
                    'nonce' => 'nonce123',
                    'expires_at' => date('Y-m-d H:i:s')
                ],
                'data_rows' => [['issue_key' => 'TEST-1']]
            ]);

        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => $csvContent
        ]);

        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not reach next middleware');
        });

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('version must be v1', $data['message']);
    }

    public function test_rejects_csv_with_row_count_mismatch()
    {
        $csvContent = $this->generateValidCsv();

        $this->parser->expects($this->once())
            ->method('parse')
            ->willReturn([
                'metadata' => [
                    'version' => 'v1',
                    'signature' => 'sig123',
                    'timestamp' => time(),
                    'session_id' => 'sess123',
                    'row_count' => '5', // Says 5 rows
                    'nonce' => 'nonce123',
                    'expires_at' => date('Y-m-d H:i:s')
                ],
                'data_rows' => [ // But only has 2
                    ['issue_key' => 'TEST-1'],
                    ['issue_key' => 'TEST-2']
                ]
            ]);

        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => $csvContent
        ]);

        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not reach next middleware');
        });

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('INVALID_CSV_FORMAT', $data['error']);
        $this->assertStringContainsString('Row count', $data['message']);
        $this->assertEquals(5, $data['details']['expected']);
        $this->assertEquals(2, $data['details']['actual']);
    }

    public function test_rejects_csv_with_zero_data_rows()
    {
        $csvContent = $this->generateValidCsv();

        $this->parser->expects($this->once())
            ->method('parse')
            ->willReturn([
                'metadata' => [
                    'version' => 'v1',
                    'signature' => 'sig123',
                    'timestamp' => (string)time(),
                    'session_id' => 'sess123',
                    'row_count' => '1', // Says 1 row
                    'nonce' => 'nonce123',
                    'expires_at' => date('Y-m-d H:i:s')
                ],
                'data_rows' => [] // But has 0 rows - mismatch AND below minimum
            ]);

        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => $csvContent
        ]);

        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not reach next middleware');
        });

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        // Will fail on row count mismatch first
        $this->assertEquals('INVALID_CSV_FORMAT', $data['error']);
        $this->assertStringContainsString('Row count', $data['message']);
    }

    public function test_rejects_csv_with_more_than_50_rows()
    {
        $csvContent = $this->generateValidCsv();
        $dataRows = [];
        for ($i = 1; $i <= 51; $i++) {
            $dataRows[] = ['issue_key' => "TEST-{$i}"];
        }

        $this->parser->expects($this->once())
            ->method('parse')
            ->willReturn([
                'metadata' => [
                    'version' => 'v1',
                    'signature' => 'sig123',
                    'timestamp' => time(),
                    'session_id' => 'sess123',
                    'row_count' => '51',
                    'nonce' => 'nonce123',
                    'expires_at' => date('Y-m-d H:i:s')
                ],
                'data_rows' => $dataRows
            ]);

        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => $csvContent
        ]);

        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not reach next middleware');
        });

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('TOO_MANY_ROWS', $data['error']);
        $this->assertEquals(51, $data['details']['row_count']);
        $this->assertEquals(50, $data['details']['max_rows']);
    }

    public function test_rejects_csv_with_invalid_data_schema()
    {
        $csvContent = $this->generateValidCsv();

        $this->parser->expects($this->once())
            ->method('parse')
            ->willReturn([
                'metadata' => [
                    'version' => 'v1',
                    'signature' => 'sig123',
                    'timestamp' => time(),
                    'session_id' => 'sess123',
                    'row_count' => '2',
                    'nonce' => 'nonce123',
                    'expires_at' => date('Y-m-d H:i:s')
                ],
                'data_rows' => [
                    ['issue_key' => 'TEST-1', 'summary' => 'Valid summary text', 'description' => 'Valid description text here', 'reporter' => 'valid@example.com'],
                    ['issue_key' => 'INVALID', 'summary' => 'Hi', 'description' => 'Short', 'reporter' => 'not-email'] // Invalid: all fields wrong
                ]
            ]);

        $this->validator->expects($this->once())
            ->method('validateSchema')
            ->willThrowException(new \App\Exceptions\ValidationException('CSV validation failed', [
                'row_2' => ['issue_key' => 'Invalid format', 'summary' => 'Too short', 'description' => 'Too short', 'reporter' => 'Invalid email']
            ]));

        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => $csvContent
        ]);

        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not reach next middleware');
        });

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('VALIDATION_FAILED', $data['error']);
        $this->assertArrayHasKey('errors', $data['details']);
    }

    public function test_handles_parse_exception_gracefully()
    {
        $csvContent = "malformed csv content";

        $this->parser->expects($this->once())
            ->method('parse')
            ->willThrowException(new \Exception('Parse error: invalid format'));

        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => $csvContent
        ]);

        $response = $this->middleware->handle($request, function ($req) {
            $this->fail('Should not reach next middleware');
        });

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('INVALID_CSV_FORMAT', $data['error']);
        $this->assertStringContainsString('Failed to parse CSV', $data['message']);
    }

    public function test_validates_all_required_metadata_fields()
    {
        $csvContent = $this->generateValidCsv();
        $requiredFields = ['version', 'signature', 'timestamp', 'session_id', 'row_count', 'nonce', 'expires_at'];

        foreach ($requiredFields as $fieldToRemove) {
            $metadata = [
                'version' => 'v1',
                'signature' => 'sig123',
                'timestamp' => time(),
                'session_id' => 'sess123',
                'row_count' => '1',
                'nonce' => 'nonce123',
                'expires_at' => date('Y-m-d H:i:s')
            ];
            
            unset($metadata[$fieldToRemove]);

            $this->parser->expects($this->once())
                ->method('parse')
                ->willReturn([
                    'metadata' => $metadata,
                    'data_rows' => [['issue_key' => 'TEST-1']]
                ]);

            $request = Request::create('/api/classify', 'POST', [
                'csv_content' => $csvContent
            ]);

            $response = $this->middleware->handle($request, function ($req) {
                $this->fail('Should not reach next middleware');
            });

            $this->assertEquals(422, $response->getStatusCode(), "Failed for missing field: {$fieldToRemove}");
            $data = json_decode($response->getContent(), true);
            $this->assertStringContainsString($fieldToRemove, $data['message']);

            // Reset mock for next iteration
            $this->setUp();
        }
    }

    private function generateValidCsv(): string
    {
        return "# METADATA\nversion: v1\nrow_count: 1\n\nissue_key,summary\nTEST-1,Test Summary";
    }
}
