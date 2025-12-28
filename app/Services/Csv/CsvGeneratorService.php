<?php

namespace App\Services\Csv;

class CsvGeneratorService
{
    public const TEMPLATE_COUNT = 10;

    /**
     * Generate CSV content with realistic sample tickets
     */
    public function generate(int $ticketCount): string
    {
        $tickets = $this->generateSampleTickets($ticketCount);
        return $this->buildCsvContent($tickets);
    }

    /**
     * Generate array of realistic sample tickets in Portuguese
     */
    private function generateSampleTickets(int $count): array
    {
        $templates = $this->getTicketTemplates();
        $tickets = [];

        for ($i = 1; $i <= $count; $i++) {
            $template = $templates[($i - 1) % count($templates)];

            $tickets[] = [
                'issue_key' => sprintf('DEMO-%03d', $i),
                'issue_type' => $template['issue_type'],
                'summary' => $template['summary'],
                'description' => $template['description'],
                'reporter' => $template['reporter'],
                'assignee' => $template['assignee'] ?? '',
                'priority' => $template['priority'],
                'status' => $template['status'],
                'created' => now()->toIso8601String(),
                'labels' => $template['labels'],
            ];
        }

        return $tickets;
    }

    /**
     * Get predefined ticket templates with realistic Portuguese content
     */
    private function getTicketTemplates(): array
    {
        return [
            // Technical Support - Login Issues
            [
                'issue_type' => 'Support',
                'summary' => 'Não consigo acessar minha conta após redefinição de senha',
                'description' => 'Usuário reporta que não consegue fazer login na conta após redefinir a senha. Aparece mensagem de erro "Credenciais inválidas". O usuário tentou múltiplas vezes com a senha correta. Navegador: Chrome 120, SO: Windows 11.',
                'reporter' => 'joao.silva@empresa.com',
                'assignee' => 'suporte@empresa.com',
                'priority' => 'High',
                'status' => 'Open',
                'labels' => 'login;acesso;autenticacao',
            ],
            // Billing Issue
            [
                'issue_type' => 'Bug',
                'summary' => 'Processamento de pagamentos falha para valores acima de R$ 1000',
                'description' => 'Gateway de pagamento retorna erro 500 ao processar cartões de crédito com valores superiores a R$ 1000. Afeta assinaturas premium. Múltiplos clientes reportaram o mesmo problema nas últimas 2 horas. IDs de transação: TXN-001, TXN-002.',
                'reporter' => 'maria.santos@billing.com',
                'priority' => 'High',
                'status' => 'Open',
                'labels' => 'pagamento;faturamento;gateway',
            ],
            // Feature Request
            [
                'issue_type' => 'Story',
                'summary' => 'Adicionar modo escuro no aplicativo mobile',
                'description' => 'Múltiplos usuários solicitaram recurso de modo escuro para melhor uso noturno. Melhoraria significativamente a experiência do usuário. Recurso similar já existe na versão web. Esforço estimado: 2 sprints.',
                'reporter' => 'carlos.oliveira@users.com',
                'priority' => 'Medium',
                'status' => 'Open',
                'labels' => 'mobile;ui;melhoria',
            ],
            // General Question
            [
                'issue_type' => 'Task',
                'summary' => 'Como alterar configurações de notificações por email',
                'description' => 'Usuário quer reduzir a frequência de notificações por email. Não consegue encontrar a página de configurações. Guia do usuário não tem instruções claras.',
                'reporter' => 'ana.pereira@cliente.com',
                'priority' => 'Low',
                'status' => 'Open',
                'labels' => 'configuracoes;email;documentacao',
            ],
            // Critical System Outage
            [
                'issue_type' => 'Incident',
                'summary' => 'Falha de conexão com banco de dados de produção',
                'description' => 'Todos os usuários estão enfrentando erros 503. Pool de conexões do banco de dados esgotado. Iniciado às 14:30 UTC. Impacto estimado em R$ 10.000/hora. Equipe de operações investigando.',
                'reporter' => 'ops.team@empresa.com',
                'priority' => 'Critical',
                'status' => 'In Progress',
                'labels' => 'outage;banco;critico',
            ],
            // Performance Issue
            [
                'issue_type' => 'Bug',
                'summary' => 'Página de dashboard carrega muito lentamente',
                'description' => 'Página do dashboard está demorando mais de 10 segundos para carregar. Afeta experiência do usuário. Problema relatado por 50+ usuários nas últimas 24 horas. Possível problema de performance no backend.',
                'reporter' => 'performance.team@empresa.com',
                'priority' => 'Medium',
                'status' => 'Open',
                'labels' => 'performance;dashboard;backend',
            ],
            // Security Concern
            [
                'issue_type' => 'Bug',
                'summary' => 'Possível vulnerabilidade de injeção SQL em formulário de contato',
                'description' => 'Equipe de segurança identificou possível vulnerabilidade de injeção SQL no formulário de contato. Parâmetros não estão sendo sanitizados adequadamente. Necessária correção urgente para prevenir exploração.',
                'reporter' => 'security@empresa.com',
                'priority' => 'Critical',
                'status' => 'Open',
                'labels' => 'seguranca;sql;vulnerabilidade',
            ],
            // Integration Issue
            [
                'issue_type' => 'Bug',
                'summary' => 'Integração com API externa falhando intermitentemente',
                'description' => 'API de integração com serviço terceiro está falhando com erro 502 aproximadamente 15% das vezes. Logs mostram timeout na comunicação. Afeta sincronização de dados críticos.',
                'reporter' => 'integration.team@empresa.com',
                'priority' => 'High',
                'status' => 'In Progress',
                'labels' => 'integracao;api;timeout',
            ],
            // Documentation Request
            [
                'issue_type' => 'Task',
                'summary' => 'Atualizar documentação da API com novos endpoints',
                'description' => 'Documentação da API precisa ser atualizada para incluir os novos endpoints adicionados na versão 2.0. Exemplos de código e casos de uso devem ser incluídos.',
                'reporter' => 'docs.team@empresa.com',
                'priority' => 'Low',
                'status' => 'Open',
                'labels' => 'documentacao;api;atualizacao',
            ],
            // Data Migration Issue
            [
                'issue_type' => 'Bug',
                'summary' => 'Migração de dados corrompeu registros de usuários antigos',
                'description' => 'Processo de migração executado ontem corrompeu dados de usuários criados antes de 2020. Informações de contato e preferências foram perdidas. Impacto em aproximadamente 5% da base de usuários.',
                'reporter' => 'data.team@empresa.com',
                'priority' => 'High',
                'status' => 'Open',
                'labels' => 'migracao;dados;corrupcao',
            ],
        ];
    }

    /**
     * Build CSV content from ticket array
     */
    private function buildCsvContent(array $tickets): string
    {
        $lines = [];

        // Add header row
        $lines[] = 'Issue Key,Issue Type,Summary,Description,Reporter,Assignee,Priority,Status,Created,Labels';

        // Add data rows
        foreach ($tickets as $ticket) {
            $row = [
                $this->escapeCsvField($ticket['issue_key']),
                $this->escapeCsvField($ticket['issue_type']),
                $this->escapeCsvField($ticket['summary']),
                $this->escapeCsvField($ticket['description']),
                $this->escapeCsvField($ticket['reporter']),
                $this->escapeCsvField($ticket['assignee']),
                $this->escapeCsvField($ticket['priority']),
                $this->escapeCsvField($ticket['status']),
                $this->escapeCsvField($ticket['created']),
                $this->escapeCsvField($ticket['labels']),
            ];

            $lines[] = implode(',', $row);
        }

        return implode("\n", $lines);
    }

    /**
     * Escape CSV field for safe output
     */
    private function escapeCsvField(string $value): string
    {
        // If value contains comma, quote, or newline, wrap in quotes
        if (preg_match('/[,"\\n\\r]/', $value)) {
            // Escape quotes by doubling them
            $value = str_replace('"', '""', $value);
            return '"' . $value . '"';
        }

        return $value;
    }
}
