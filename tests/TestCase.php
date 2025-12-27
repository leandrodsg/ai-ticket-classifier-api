<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Carregar .env.test se existir (para API keys sensíveis que não vão no phpunit.xml)
        $testEnvFile = base_path('.env.test');
        if (file_exists($testEnvFile)) {
            $lines = file($testEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue; // Skip comments
                }
                
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remover aspas se existirem
                    if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                        $value = $matches[2];
                    }
                    
                    // Definir a variável de ambiente se não estiver vazia
                    if (!empty($value)) {
                        putenv("$key=$value");
                        $_ENV[$key] = $value;
                        $_SERVER[$key] = $value;
                    }
                }
            }
        }
    }
}
