<?php

class VendorRecommendationEngine
{
    private const DEFAULT_CONVERSION_MONTHS = 3;
    private const INACTIVE_STATUSES = [
        'Convertido',
        'Descartado',
        'Inativo',
        'Cliente Ativo',
        'Cliente ativo',
        'Pausa',
    ];

    private PDO $pdo;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $vendorCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array<string, mixed> $prospeccaoData
     *
     * @return array<int, array<string, mixed>>
     */
    public function recommendVendors(array $prospeccaoData, int $limit = 3): array
    {
        $tipoServico = trim((string) ($prospeccaoData['tipo_servico'] ?? ''));
        $limit = max(0, $limit);

        $vendors = $this->fetchActiveVendors();
        if (empty($vendors)) {
            return [];
        }

        $scoredVendors = [];

        foreach ($vendors as $vendor) {
            $vendorId = (int) $vendor['vendor_id'];
            $this->vendorCache[$vendorId] = $vendor;

            $specialtyScore = $this->calculateSpecialtyScore($vendorId, $tipoServico);
            $workloadScore = $this->calculateWorkloadScore($vendorId);
            $conversionRate = $this->getConversionRate($vendorId, self::DEFAULT_CONVERSION_MONTHS);
            $conversionScore = min(20.0, round($conversionRate * 20, 2));
            $responseMinutes = $this->getAverageResponseTime($vendorId);
            $responseScore = $this->scoreResponseTime($responseMinutes);
            $averageRating = $this->getAverageRating($vendorId);
            $ratingScore = min(5.0, round(($averageRating / 5) * 5, 2));

            $totalScore = round($specialtyScore + $workloadScore + $conversionScore + $responseScore + $ratingScore, 2);
            $totalScore = min(100.0, $totalScore);

            $scoredVendors[] = [
                'vendor_id' => $vendorId,
                'name' => $vendor['name'],
                'score' => $totalScore,
                'specialty_score' => $specialtyScore,
                'workload_score' => $workloadScore,
                'conversion_rate' => $conversionRate,
                'response_minutes' => $responseMinutes,
                'rating_score' => $ratingScore,
                'average_rating' => $averageRating,
                'current_load' => $this->vendorCache[$vendorId]['current_load'] ?? 0,
                'max_concurrent_leads' => (int) ($vendor['max_concurrent_leads'] ?? 0),
                'reason' => $this->buildReason(
                    $vendorId,
                    $tipoServico,
                    $conversionRate,
                    $responseMinutes,
                    $averageRating
                ),
            ];
        }

        usort($scoredVendors, static function (array $a, array $b): int {
            $scoreComparison = $b['score'] <=> $a['score'];
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return strcmp($a['name'], $b['name']);
        });

        $badgeThreshold = min(3, count($scoredVendors));
        foreach ($scoredVendors as $index => &$vendor) {
            if ($index < $badgeThreshold) {
                $vendor['badge'] = sprintf('⭐ Recomendado (%d%%)', (int) round($vendor['score']));
            }
        }
        unset($vendor);

        if ($limit > 0) {
            $scoredVendors = array_slice($scoredVendors, 0, $limit);
        }

        return $scoredVendors;
    }

    private function fetchActiveVendors(): array
    {
        $sql = "SELECT
                    u.id AS vendor_id,
                    u.nome_completo AS name,
                    COALESCE(v.specialties, '[]') AS specialties,
                    COALESCE(v.max_concurrent_leads, 0) AS max_concurrent_leads
                FROM users u
                LEFT JOIN vendedores v ON v.user_id = u.id
                WHERE u.perfil = 'vendedor'
                  AND u.ativo = 1
                  AND u.id <> 17";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($vendors) ? $vendors : [];
    }

    private function getVendorRow(int $vendorId): array
    {
        if (!isset($this->vendorCache[$vendorId])) {
            $sql = "SELECT
                        u.id AS vendor_id,
                        u.nome_completo AS name,
                        COALESCE(v.specialties, '[]') AS specialties,
                        COALESCE(v.max_concurrent_leads, 0) AS max_concurrent_leads
                    FROM users u
                    LEFT JOIN vendedores v ON v.user_id = u.id
                    WHERE u.id = :vendor_id
                      AND u.perfil = 'vendedor'";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':vendor_id', $vendorId, PDO::PARAM_INT);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return [];
            }

            $this->vendorCache[$vendorId] = $row;
        }

        return $this->vendorCache[$vendorId];
    }

    private function calculateSpecialtyScore(int $vendorId, string $tipoServico): float
    {
        if ($tipoServico === '') {
            return 0.0;
        }

        $vendor = $this->getVendorRow($vendorId);
        $specialties = json_decode($vendor['specialties'] ?? '[]', true);
        if (!is_array($specialties) || empty($specialties)) {
            return 0.0;
        }

        $normalizedService = mb_strtolower($tipoServico, 'UTF-8');
        foreach ($specialties as $specialty) {
            if (!is_string($specialty)) {
                continue;
            }
            if (mb_strtolower(trim($specialty), 'UTF-8') === $normalizedService) {
                return 40.0;
            }
        }

        return 0.0;
    }

    private function calculateWorkloadScore(int $vendorId): float
    {
        $vendor = $this->getVendorRow($vendorId);
        $maxConcurrent = (int) ($vendor['max_concurrent_leads'] ?? 0);
        $currentLoad = $this->countActiveLeads($vendorId);
        $this->vendorCache[$vendorId]['current_load'] = $currentLoad;

        if ($maxConcurrent <= 0) {
            return 25.0;
        }

        if ($currentLoad >= $maxConcurrent) {
            return 0.0;
        }

        $availabilityRatio = 1 - ($currentLoad / $maxConcurrent);

        return round($availabilityRatio * 25, 2);
    }

    private function countActiveLeads(int $vendorId): int
    {
        $placeholders = implode(', ', array_fill(0, count(self::INACTIVE_STATUSES), '?'));
        $sql = "SELECT COUNT(*)
                FROM prospeccoes
                WHERE responsavel_id = ?
                  AND (status IS NULL OR status NOT IN ($placeholders))";

        $stmt = $this->pdo->prepare($sql);
        $params = array_merge([$vendorId], self::INACTIVE_STATUSES);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function getConversionRate(int $vendorId, int $months = self::DEFAULT_CONVERSION_MONTHS): float
    {
        $months = max(1, $months);
        $startDate = (new DateTimeImmutable(sprintf('-%d months', $months)))->format('Y-m-d H:i:s');

        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'Convertido' THEN 1 ELSE 0 END) AS converted
                FROM prospeccoes
                WHERE responsavel_id = :vendor_id
                  AND data_prospeccao >= :start_date";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':vendor_id', $vendorId, PDO::PARAM_INT);
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return 0.0;
        }

        $total = (int) ($row['total'] ?? 0);
        $converted = (int) ($row['converted'] ?? 0);

        if ($total <= 0) {
            return 0.0;
        }

        return $converted / $total;
    }

    private function getAverageResponseTime(int $vendorId): ?float
    {
        $startDate = (new DateTimeImmutable('-90 days'))->format('Y-m-d H:i:s');

        $sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, first_contact_at)) AS avg_minutes
                FROM lead_handoffs
                WHERE vendor_id = :vendor_id
                  AND first_contact_at IS NOT NULL
                  AND created_at >= :start_date";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':vendor_id', $vendorId, PDO::PARAM_INT);
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->execute();

        $avg = $stmt->fetchColumn();
        if ($avg === false || $avg === null) {
            return null;
        }

        return (float) $avg;
    }

    private function scoreResponseTime(?float $avgMinutes): float
    {
        if ($avgMinutes === null) {
            return 0.0;
        }

        if ($avgMinutes <= 30) {
            return 10.0;
        }

        if ($avgMinutes <= 60) {
            return 8.0;
        }

        if ($avgMinutes <= 120) {
            return 6.0;
        }

        if ($avgMinutes <= 240) {
            return 4.0;
        }

        if ($avgMinutes <= 480) {
            return 2.0;
        }

        return 0.0;
    }

    private function getAverageRating(int $vendorId): float
    {
        $startDate = (new DateTimeImmutable('-6 months'))->format('Y-m-d H:i:s');

        $sql = "SELECT AVG(quality_score) AS avg_rating
                FROM lead_handoffs
                WHERE vendor_id = :vendor_id
                  AND quality_score IS NOT NULL
                  AND created_at >= :start_date";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':vendor_id', $vendorId, PDO::PARAM_INT);
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->execute();

        $avgRating = $stmt->fetchColumn();
        if ($avgRating === false || $avgRating === null) {
            return 0.0;
        }

        return (float) $avgRating;
    }

    private function buildReason(
        int $vendorId,
        string $tipoServico,
        float $conversionRate,
        ?float $responseMinutes,
        float $averageRating
    ): string {
        $parts = [];

        if ($tipoServico !== '' && $this->calculateSpecialtyScore($vendorId, $tipoServico) >= 40.0) {
            $parts[] = sprintf('Especialista em %s', $tipoServico);
        }

        $currentLoad = $this->vendorCache[$vendorId]['current_load'] ?? 0;
        $maxConcurrent = (int) ($this->vendorCache[$vendorId]['max_concurrent_leads'] ?? 0);
        if ($maxConcurrent > 0) {
            $parts[] = sprintf('Carga atual %d de %d leads', $currentLoad, $maxConcurrent);
        } else {
            $parts[] = sprintf('Carga atual de %d leads (sem limite configurado)', $currentLoad);
        }

        $parts[] = sprintf('Conversão de %.1f%% nos últimos %d meses', $conversionRate * 100, self::DEFAULT_CONVERSION_MONTHS);

        if ($responseMinutes !== null) {
            $parts[] = 'Resposta média em ' . $this->formatMinutes((int) round($responseMinutes));
        }

        if ($averageRating > 0) {
            $parts[] = sprintf('Avaliação média %.1f/5', $averageRating);
        }

        return implode(' • ', $parts);
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes <= 1) {
            return 'menos de 1 minuto';
        }

        if ($minutes < 60) {
            return sprintf('%d minutos', $minutes);
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return sprintf('%d hora%s', $hours, $hours > 1 ? 's' : '');
        }

        return sprintf(
            '%d hora%s e %d minuto%s',
            $hours,
            $hours > 1 ? 's' : '',
            $remainingMinutes,
            $remainingMinutes > 1 ? 's' : ''
        );
    }
}
