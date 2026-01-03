<?php

namespace App\Console\Commands;

use App\Services\Ai\ModelDiscoveryService;
use Illuminate\Console\Command;

class DiscoverAiModels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:discover-models 
                            {--fresh : Limpa cache e busca novos modelos}
                            {--json : Exibe resultado em formato JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover available free AI models on OpenRouter';

    /**
     * Execute the console command.
     */
    public function handle(ModelDiscoveryService $discoveryService): int
    {
        $this->info('🔍 Descobrindo modelos AI gratuitos...');
        $this->newLine();

        // Limpa cache se solicitado
        if ($this->option('fresh')) {
            $discoveryService->clearCache();
            $this->comment('✓ Cache limpo');
        }

        // Busca modelos
        $startTime = microtime(true);
        $models = $discoveryService->discoverFreeModels();
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        if (empty($models)) {
            $this->error('✗ Nenhum modelo gratuito encontrado');
            return Command::FAILURE;
        }

        $this->info("✓ " . count($models) . " modelos encontrados em {$duration}ms");
        $this->newLine();

        // Exibe resultado
        if ($this->option('json')) {
            $this->line(json_encode($models, JSON_PRETTY_PRINT));
        } else {
            $this->displayTable($models);
        }

        $this->newLine();
        $this->info('💡 Recommendations:');
        $this->comment('  • Use top 3 models as default_models in config/ai.php');
        $this->comment('  • Test each model before using in production');
        $this->comment('  • Update .env with the best models');

        return Command::SUCCESS;
    }

    /**
     * Exibe tabela com modelos descobertos.
     *
     * @param array $models
     * @return void
     */
    private function displayTable(array $models): void
    {
        $headers = ['#', 'Modelo', 'Nome', 'Ranking', 'Context'];
        $rows = [];

        foreach ($models as $index => $model) {
            $rows[] = [
                $index + 1,
                $model['id'],
                $model['name'],
                $model['ranking'],
                number_format($model['context_length']),
            ];
        }

        $this->table($headers, $rows);
    }
}
