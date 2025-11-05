# Dashboards por perfil

Este documento descreve as novas rotas e componentes adicionados para os painéis de SDR, vendedores e gerência.

## Rotas

- `/sdr_dashboard.php`
  - Exibe o painel Kanban para SDRs com filtros por etapa, vendedor delegado e perfil de pagamento.
  - Consome `SdrDashboardController::index` que, por sua vez, delega a recuperação de dados para `DashboardKanbanService` e `QualificacaoController`.
- `/dashboard_vendedor.php`
  - Painel do vendedor com overview de processos e um Kanban filtrável por SDR, etapa e perfil de pagamento.
  - A rota usa `VendedorDashboardController::index`.
- `/gerente_dashboard.php`
  - Painel de gestão consolidado com o Kanban global e fila de distribuição de leads.
  - Implementado em `GerenteDashboardController::index`.

## Serviços

- `app/services/DashboardKanbanService.php`
  - Centraliza a montagem dos boards Kanban para cada perfil (SDR, vendedor, gerência).
  - Faz uso dos modelos `Prospeccao`, `User` e do serviço `LeadDistributor` para enriquecer os dados (distribuição e fila pendente).
- `app/services/NotificationCleanupService.php`
  - Responsável por limpar notificações resolvidas com base em uma janela de retenção configurável.

## Integração com Qualificação

`QualificacaoController` ganhou o método `getQualificationMetrics()` que retorna totais de leads qualificados, convertidos e descartados. Esses dados alimentam cards de desempenho nos três painéis.

## Console / Cron

- `bin/console` expõe comandos de manutenção:
  - `leads:distribute` distribui automaticamente os leads aguardando responsável.
  - `notifications:cleanup [dias]` remove notificações resolvidas além do período informado.
  - `omie:reprocess [support|products]` reprocessa integrações com a Omie.
- Os crons existentes foram atualizados para utilizar os novos serviços (ex.: `cron_limpeza_notificacoes.php`).

## Modelos e consultas auxiliares

- `Prospeccao` ganhou métodos auxiliares para:
  - Recuperar status ativos (`getActiveKanbanStatuses`).
  - Listar SDRs relacionados a um vendedor (`getSdrDirectoryForVendor`).
  - Normalizar perfis de pagamento (`getAllowedPaymentProfiles`).

Essas consultas são utilizadas pelos filtros dos dashboards para garantir consistência entre as visões de SDR, vendas e gestão.
