<?php

namespace Tests\Unit\Services\Security;

use App\Services\Security\HmacSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HmacSignatureServiceTest extends TestCase
{
    use RefreshDatabase;

    private HmacSignatureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HmacSignatureService('test_csv_signing_key_for_testing_only');
    }

    /** @test */
    public function it_generates_signature_for_data()
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $signature = $this->service->generate($data);

        $this->assertIsString($signature);
        $this->assertEquals(64, strlen($signature)); // SHA256 produces 64 char hex string
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);
    }

    /** @test */
    public function it_validates_correct_signature()
    {
        $data = ['test' => 'data', 'version' => 'v1'];
        $signature = $this->service->generate($data);

        $isValid = $this->service->validate($data, $signature);

        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_rejects_tampered_data()
    {
        $data = ['original' => 'data'];
        $signature = $this->service->generate($data);

        // Tamper with data
        $tamperedData = ['original' => 'modified'];

        $isValid = $this->service->validate($tamperedData, $signature);

        $this->assertFalse($isValid);
    }

    /** @test */
    public function it_rejects_wrong_signature()
    {
        $data = ['test' => 'data'];
        $wrongSignature = 'invalid_signature_hash';

        $isValid = $this->service->validate($data, $wrongSignature);

        $this->assertFalse($isValid);
    }

    /** @test */
    public function it_handles_empty_data()
    {
        $data = [];
        $signature = $this->service->generate($data);

        $this->assertIsString($signature);
        $this->assertEquals(64, strlen($signature));

        $isValid = $this->service->validate($data, $signature);
        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_handles_numeric_values()
    {
        $data = ['count' => 42, 'active' => true];
        $signature = $this->service->generate($data);

        $isValid = $this->service->validate($data, $signature);
        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_ensures_consistent_ordering()
    {
        $data1 = ['z' => 'last', 'a' => 'first'];
        $data2 = ['a' => 'first', 'z' => 'last'];

        $signature1 = $this->service->generate($data1);
        $signature2 = $this->service->generate($data2);

        // Signatures should be identical due to key sorting
        $this->assertEquals($signature1, $signature2);
    }

    /** @test */
    public function it_returns_correct_algorithm()
    {
        $algorithm = $this->service->getAlgorithm();

        $this->assertEquals('sha256', $algorithm);
    }

    /** @test */
    public function it_throws_exception_when_app_key_not_configured()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CSV_SIGNING_KEY is not configured');

        new HmacSignatureService('');
    }
}
