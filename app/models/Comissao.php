<?php
// /app/models/Comissao.php

class Comissao
{
    private $pdo;
    private array $columnCache = [];

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca todas as comissões de um vendedor específico.
     *
     * @param int $vendedor_id O ID do vendedor.
     * @return array
     */
    public function getByVendedor($vendedor_id)
    {
        // ===== CORREÇÃO APLICADA AQUI =====
        // A consulta agora junta 'comissoes' com 'processos' (em vez da inexistente 'vendas').
        // 'p.data_criacao' é usada como a data da venda.
        $sql = "SELECT 
                    c.*, 
                    p.data_criacao as data_venda, 
                    p.valor_total as valor_da_venda
                FROM comissoes c
                JOIN processos p ON c.venda_id = p.id
                WHERE c.vendedor_id = :vendedor_id
                ORDER BY p.data_criacao DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':vendedor_id' => $vendedor_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcula e grava (ou atualiza) a comissão para um processo/venda.
     *
     * @param int $processo_id
     * @param int $vendedor_id
     * @param float $valor_venda
     * @return bool
     */
    public function calcularEGravarComissao($processo_id, $vendedor_id, $valor_venda)
    {
        try {
            $processId = (int) $processo_id;
            $vendorId = (int) $vendedor_id;
            $saleValue = (float) $valor_venda;

            $percentages = $this->fetchCommissionPercentages();
            $percentualPadrao = $percentages['vendedor'];
            $percentualSdr = $percentages['sdr'];

            $valorComissaoTotal = $saleValue * ($percentualPadrao / 100);
            $valorComissaoSdr = $saleValue * ($percentualSdr / 100);
            $valorComissaoVendedor = max($valorComissaoTotal - $valorComissaoSdr, 0.0);
            $percentualLiquidoVendedor = max($percentualPadrao - $percentualSdr, 0.0);

            $this->pdo->beginTransaction();

            $this->persistCommission(
                $processId,
                $vendorId,
                $percentualLiquidoVendedor,
                $valorComissaoVendedor,
                'vendedor'
            );

            $sdrId = $this->fetchSdrIdForProcess($processId);
            if ($sdrId !== null) {
                $this->persistCommission(
                    $processId,
                    $sdrId,
                    $percentualSdr,
                    $valorComissaoSdr,
                    'sdr'
                );
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Erro ao calcular comissão: " . $e->getMessage());
            return false;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erro inesperado ao calcular comissão: ' . $exception->getMessage());
            return false;
        }
    }

    private function fetchCommissionPercentages(): array
    {
        $defaults = [
            'percentual_padrao' => 5.0,
            'percentual_sdr' => 0.5,
        ];

        try {
            $stmt = $this->pdo->prepare(
                "SELECT tipo_regra, valor
                 FROM configuracoes_comissao
                 WHERE tipo_regra IN ('percentual_padrao', 'percentual_sdr')
                   AND ativo = 1"
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $key = $row['tipo_regra'] ?? '';
                if ($key === 'percentual_padrao' || $key === 'percentual_sdr') {
                    $defaults[$key] = (float) $row['valor'];
                }
            }
        } catch (PDOException $exception) {
            error_log('Erro ao buscar percentuais de comissão: ' . $exception->getMessage());
        }

        return [
            'vendedor' => (float) $defaults['percentual_padrao'],
            'sdr' => (float) $defaults['percentual_sdr'],
        ];
    }

    private function persistCommission(int $processId, int $ownerId, float $percentual, float $valor, string $tipo): void
    {
        $sql = "INSERT INTO comissoes (venda_id, vendedor_id, percentual_comissao, valor_comissao, status_comissao, tipo_comissao)
                VALUES (:venda_id, :vendedor_id, :percentual, :valor, 'Pendente', :tipo)
                ON DUPLICATE KEY UPDATE
                    vendedor_id = VALUES(vendedor_id),
                    percentual_comissao = VALUES(percentual_comissao),
                    valor_comissao = VALUES(valor_comissao),
                    status_comissao = 'Pendente'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':venda_id' => $processId,
            ':vendedor_id' => $ownerId,
            ':percentual' => number_format($percentual, 4, '.', ''),
            ':valor' => number_format($valor, 2, '.', ''),
            ':tipo' => $tipo,
        ]);
    }

    private function fetchSdrIdForProcess(int $processId): ?int
    {
        try {
            if ($this->tableHasColumn('processos', 'sdrId')) {
                $stmt = $this->pdo->prepare('SELECT sdrId FROM processos WHERE id = :id');
                $stmt->execute([':id' => $processId]);
                $value = $stmt->fetchColumn();

                if ($value !== false && $value !== null) {
                    $candidate = (int) $value;
                    return $candidate > 0 ? $candidate : null;
                }
            }

            $selectFields = [];
            if ($this->tableHasColumn('processos', 'prospeccao_id')) {
                $selectFields[] = 'prospeccao_id';
            }
            if ($this->tableHasColumn('processos', 'cliente_id')) {
                $selectFields[] = 'cliente_id';
            }

            if (empty($selectFields)) {
                return null;
            }

            $sql = 'SELECT ' . implode(',', $selectFields) . ' FROM processos WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $processId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            if (isset($row['prospeccao_id'])) {
                $prospectionId = (int) $row['prospeccao_id'];
                if ($prospectionId > 0) {
                    $sdrId = $this->fetchSdrIdFromProspection($prospectionId);
                    if ($sdrId !== null) {
                        return $sdrId;
                    }
                }
            }

            if (isset($row['cliente_id'])) {
                $clientId = (int) $row['cliente_id'];
                if ($clientId > 0 && $this->tableHasColumn('prospeccoes', 'cliente_id') && $this->tableHasColumn('prospeccoes', 'sdrId')) {
                    $stmt = $this->pdo->prepare(
                        'SELECT sdrId FROM prospeccoes WHERE cliente_id = :cliente_id ORDER BY data_prospeccao DESC LIMIT 1'
                    );
                    $stmt->execute([':cliente_id' => $clientId]);
                    $value = $stmt->fetchColumn();
                    if ($value !== false && $value !== null) {
                        $candidate = (int) $value;
                        if ($candidate > 0) {
                            return $candidate;
                        }
                    }
                }
            }
        } catch (PDOException $exception) {
            error_log('Erro ao determinar SDR do processo: ' . $exception->getMessage());
        }

        return null;
    }

    private function fetchSdrIdFromProspection(int $prospectionId): ?int
    {
        if (!$this->tableHasColumn('prospeccoes', 'sdrId')) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT sdrId FROM prospeccoes WHERE id = :id');
            $stmt->execute([':id' => $prospectionId]);
            $value = $stmt->fetchColumn();

            if ($value !== false && $value !== null) {
                $candidate = (int) $value;
                return $candidate > 0 ? $candidate : null;
            }
        } catch (PDOException $exception) {
            error_log('Erro ao buscar SDR da prospecção: ' . $exception->getMessage());
        }

        return null;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;

        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }

        try {
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
            $stmt->execute([':column' => $column]);
            $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
            $this->columnCache[$cacheKey] = $exists;

            return $exists;
        } catch (PDOException $exception) {
            error_log('Erro ao verificar coluna ' . $column . ' em ' . $table . ': ' . $exception->getMessage());
            $this->columnCache[$cacheKey] = false;
            return false;
        }
    }
}
