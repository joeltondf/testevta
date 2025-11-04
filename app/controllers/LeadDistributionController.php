<?php

require_once __DIR__ . '/../services/LeadDistributor.php';

class LeadDistributionController
{
    private $service;

    public function __construct($pdo)
    {
        $this->service = new LeadDistributor($pdo);
    }

    public function auto(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $allowedProfiles = ['admin', 'gerencia', 'supervisor'];
        if (!isset($_SESSION['user_perfil']) || !in_array($_SESSION['user_perfil'], $allowedProfiles, true)) {
            $_SESSION['error_message'] = 'Você não tem permissão para distribuir leads.';
            header('Location: ' . APP_URL . '/dashboard.php');
            exit();
        }

        try {
            $result = $this->service->distribuirLeads();
            $message = sprintf(
                '%d lead(s) analisados, %d distribuídos com sucesso.',
                $result['leadsProcessados'] ?? 0,
                $result['leadsDistribuidos'] ?? 0
            );
            $_SESSION['success_message'] = $message;
        } catch (\Throwable $exception) {
            error_log('Erro na distribuição de leads: ' . $exception->getMessage());
            $_SESSION['error_message'] = 'Não foi possível concluir a distribuição automática.';
        }

        $redirect = $_SERVER['HTTP_REFERER'] ?? (APP_URL . '/crm/prospeccoes/lista.php');
        header('Location: ' . $redirect);
        exit();
    }
}
