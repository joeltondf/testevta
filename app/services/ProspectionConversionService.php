<?php

require_once __DIR__ . '/../models/Prospeccao.php';
require_once __DIR__ . '/../models/User.php';

class ProspectionConversionService
{
    private PDO $pdo;
    private Prospeccao $prospectionModel;
    private User $userModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->prospectionModel = new Prospeccao($pdo);
        $this->userModel = new User($pdo);
    }

    public function convert(int $prospectionId, int $initiatorId, ?int $authorizedManagerId = null): string
    {
        $prospection = $this->prospectionModel->getById($prospectionId);
        if (!$prospection) {
            throw new RuntimeException('Prospecção não encontrada.');
        }

        $clientId = (int) ($prospection['cliente_id'] ?? 0);
        if ($clientId <= 0) {
            throw new RuntimeException('A prospecção não está vinculada a um lead válido.');
        }

        $this->pdo->beginTransaction();

        try {
            $updateStmt = $this->pdo->prepare(
                "UPDATE prospeccoes SET status = 'Convertido', data_ultima_atualizacao = NOW() WHERE id = :id"
            );
            $updateStmt->bindValue(':id', $prospectionId, PDO::PARAM_INT);
            $updateStmt->execute();

            $managerName = '';

            if ($authorizedManagerId !== null) {
                $manager = $this->userModel->getById($authorizedManagerId);
                $managerName = $manager['nome_completo'] ?? 'Gestor';
                $this->prospectionModel->logInteraction(
                    $prospectionId,
                    $authorizedManagerId,
                    'Autorizou a conversão do lead.',
                    'log_sistema'
                );
            }

            $conversionNote = $authorizedManagerId !== null && $authorizedManagerId !== $initiatorId
                ? sprintf('Lead convertido em cliente com autorização de %s.', $managerName)
                : 'Lead convertido em cliente.';

            $this->prospectionModel->logInteraction(
                $prospectionId,
                $initiatorId,
                $conversionNote,
                'log_sistema'
            );

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $queryParams = [
            'prospeccao_id' => $prospectionId,
            'nome_servico' => $prospection['nome_prospecto'] ?? '',
            'valor_inicial' => $prospection['valor_proposto'] ?? 0,
        ];

        return APP_URL . '/clientes.php?action=edit&id=' . $clientId . '&' . http_build_query($queryParams);
    }
}
