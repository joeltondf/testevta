<?php
// /app/controllers/AdminController.php

// Models e Serviços necessários
require_once __DIR__ . '/../models/Configuracao.php';
require_once __DIR__ . '/../models/AutomacaoModel.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Vendedor.php';
require_once __DIR__ . '/../models/Tradutor.php';
require_once __DIR__ . '/../models/OmieProduto.php';
require_once __DIR__ . '/../models/Processo.php';

require_once __DIR__ . '/../utils/DashboardProcessFormatter.php';

require_once __DIR__ . '/../services/DigicApiService.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../services/OmieSyncService.php';
require_once __DIR__ . '/../services/KanbanConfigService.php';
require_once __DIR__ . '/../models/SmtpConfigModel.php';

class AdminController
{
    private $pdo;
    private $configModel;
    private $automacaoModel;
    private $userModel;
    private $vendedorModel;
    private $tradutorModel;
    private $omieSyncService;
    private $omieProdutoModel;
    private KanbanConfigService $kanbanConfigService;
    private $processoModel;
    private ?array $availableProcessStatusesCache = null;

    private const TV_PANEL_SETTINGS_KEY = 'tv_panel_settings';

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->configModel = new Configuracao($pdo);
        $this->automacaoModel = new AutomacaoModel($pdo);
        $this->userModel = new User($pdo);
        $this->vendedorModel = new Vendedor($pdo);
        $this->tradutorModel = new Tradutor($pdo);
        $this->omieSyncService = null;
        $this->omieProdutoModel = new OmieProduto($pdo);
        $this->kanbanConfigService = new KanbanConfigService($pdo);
        $this->processoModel = new Processo($pdo);
    }

    // Métodos de Administração Geral...
    public function index()
    {
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/admin/dashboard.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function settings()
    {
        $settings = $this->configModel->getAll();
        $settings['percentual_sdr'] = $this->getCommissionSettingValue('percentual_sdr', 0.5);
        $pageTitle = "Configurações";
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/admin/settings.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function saveSettings()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $payload = $_POST;

            if (array_key_exists('percentual_sdr', $payload)) {
                $normalizedPercentage = $this->normalizeCommissionPercentage($payload['percentual_sdr']);
                $this->saveCommissionSetting('percentual_sdr', $normalizedPercentage);
                unset($payload['percentual_sdr']);
            }

            foreach ($payload as $key => $value) {
                $this->configModel->save($key, $value);
            }
            $_SESSION['success_message'] = "Configurações salvas com sucesso!";
        }
        // Redireciona de volta para a página que fez a requisição
        $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'admin.php?action=settings';
        header('Location: ' . $redirect_url);
        exit();
    }

    private function getTvPanelSettings(): array
    {
        $defaults = [
            'overdue_color' => 'bg-red-200 text-red-800',
            'due_today_color' => 'bg-red-200 text-red-800',
            'due_soon_color' => 'bg-yellow-200 text-yellow-800',
            'on_track_color' => 'text-green-600',
            'refresh_interval' => 60,
            'color_scheme' => 'dark',
            'enable_alert_pulse' => true,
            'boards' => [
                [
                    'id' => 'panel_processes',
                    'title' => 'Processos Ativos',
                    'statuses' => [],
                ],
            ],
        ];

        $stored = $this->configModel->get(self::TV_PANEL_SETTINGS_KEY);
        if (is_string($stored) && $stored !== '') {
            $decoded = json_decode($stored, true);
            if (is_array($decoded)) {
                $defaults = array_merge($defaults, $decoded);
            }
        }

        $validIntervals = [60, 180, 300];
        $defaults['refresh_interval'] = in_array((int) $defaults['refresh_interval'], $validIntervals, true) ? (int) $defaults['refresh_interval'] : 60;
        $defaults['color_scheme'] = in_array($defaults['color_scheme'], ['dark', 'light'], true) ? $defaults['color_scheme'] : 'dark';
        $defaults['enable_alert_pulse'] = (bool) $defaults['enable_alert_pulse'];

        return $defaults;
    }

    private function persistTvPanelSettings(array $settings): void
    {
        $this->configModel->save(self::TV_PANEL_SETTINGS_KEY, json_encode($settings, JSON_UNESCAPED_UNICODE));
    }

    private function getCommissionSettingValue(string $key, float $default): float
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT valor FROM configuracoes_comissao WHERE tipo_regra = :key LIMIT 1'
            );
            $stmt->execute([':key' => $key]);
            $value = $stmt->fetchColumn();

            if ($value !== false && $value !== null) {
                return (float) $value;
            }
        } catch (PDOException $exception) {
            error_log('Erro ao buscar configuração de comissão: ' . $exception->getMessage());
        }

        return $default;
    }

    private function saveCommissionSetting(string $key, float $value): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO configuracoes_comissao (tipo_regra, valor, ativo) VALUES (:key, :value, 1) " .
                "ON DUPLICATE KEY UPDATE valor = VALUES(valor), ativo = VALUES(ativo)"
            );
            $stmt->execute([
                ':key' => $key,
                ':value' => number_format($value, 4, '.', ''),
            ]);
        } catch (PDOException $exception) {
            error_log('Erro ao salvar configuração de comissão: ' . $exception->getMessage());
        }
    }

    private function normalizeCommissionPercentage($value): float
    {
        if (is_string($value)) {
            $value = str_replace(['%', ' '], '', $value);
            $value = str_replace(',', '.', $value);
        }

        $percentage = (float) $value;
        if ($percentage < 0) {
            $percentage = 0.0;
        }

        return round($percentage, 4);
    }

    public function showTvPanel(): void
    {
        $pageTitle = 'Painel de TV';
        $settings = $this->getTvPanelSettings();
        $deadlineColors = [
            'overdue' => $settings['overdue_color'],
            'due_today' => $settings['due_today_color'],
            'due_soon' => $settings['due_soon_color'],
            'on_track' => $settings['on_track_color'],
        ];
        $boards = $this->normalizeTvPanelBoards($settings['boards'] ?? []);

        $panelId = isset($_GET['panel_id']) ? (string) $_GET['panel_id'] : '';
        $selectedBoard = null;

        foreach ($boards as $board) {
            if ($board['id'] === $panelId) {
                $selectedBoard = $board;
                break;
            }
        }

        if ($selectedBoard === null) {
            $selectedBoard = $boards[0] ?? [
                'id' => 'panel_processes',
                'title' => 'Processos Ativos',
                'statuses' => [],
            ];
        }

        $panel = [
            'id' => $selectedBoard['id'],
            'title' => $selectedBoard['title'],
            'statuses' => $selectedBoard['statuses'],
            'processes' => $this->processoModel->getProcessesForTvPanel([
                'statuses' => $selectedBoard['statuses'],
            ]),
        ];

        $panels = $boards;
        $bodyClass = $settings['color_scheme'] === 'light'
            ? 'tv-panel-page bg-white text-slate-900'
            : 'tv-panel-page bg-slate-900 text-white';
        $theme = $settings['color_scheme'];

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/admin/tv_painel.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function showTvPanelConfig(): void
    {
        $pageTitle = 'Configurações do Painel de TV';
        $settings = $this->getTvPanelSettings();
        $availableStatuses = $this->getAvailableProcessStatuses();
        $panels = $this->normalizeTvPanelBoards($settings['boards'] ?? []);

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/admin/tv_painel_config.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function saveTvPanelConfig(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: admin.php?action=tv_panel_config');
            exit();
        }

        $current = $this->getTvPanelSettings();

        $payload = [
            'overdue_color' => trim($_POST['overdue_color'] ?? $current['overdue_color']),
            'due_today_color' => trim($_POST['due_today_color'] ?? $current['due_today_color']),
            'due_soon_color' => trim($_POST['due_soon_color'] ?? $current['due_soon_color']),
            'on_track_color' => trim($_POST['on_track_color'] ?? $current['on_track_color']),
            'refresh_interval' => (int) ($_POST['refresh_interval'] ?? $current['refresh_interval']),
            'color_scheme' => $_POST['color_scheme'] ?? $current['color_scheme'],
            'enable_alert_pulse' => isset($_POST['enable_alert_pulse']),
        ];

        $panelConfig = $_POST['panels'] ?? [];
        if (is_string($panelConfig)) {
            $decodedPanels = json_decode($panelConfig, true);
            $panelConfig = is_array($decodedPanels) ? $decodedPanels : [];
        }

        $payload['boards'] = $this->normalizeTvPanelBoards($panelConfig);

        $validIntervals = [60, 180, 300];
        if (!in_array($payload['refresh_interval'], $validIntervals, true)) {
            $payload['refresh_interval'] = 60;
        }

        if (!in_array($payload['color_scheme'], ['dark', 'light'], true)) {
            $payload['color_scheme'] = 'dark';
        }

        $this->persistTvPanelSettings($payload);
        $_SESSION['success_message'] = 'Configurações do painel atualizadas com sucesso!';

        header('Location: admin.php?action=tv_panel_config');
        exit();
    }

    public function getTvPanelData(): void
    {
        $settings = $this->getTvPanelSettings();
        $boards = $this->normalizeTvPanelBoards($settings['boards'] ?? []);

        $panelId = isset($_GET['panel_id']) ? (string) $_GET['panel_id'] : '';
        $selectedBoard = null;
        foreach ($boards as $board) {
            if ($board['id'] === $panelId) {
                $selectedBoard = $board;
                break;
            }
        }

        if ($selectedBoard === null) {
            $selectedBoard = $boards[0] ?? [
                'id' => 'panel_processes',
                'title' => 'Processos Ativos',
                'statuses' => [],
            ];
        }

        $processes = $this->processoModel->getProcessesForTvPanel([
            'statuses' => $selectedBoard['statuses'],
        ]);

        $deadlineColors = [
            'overdue' => $settings['overdue_color'],
            'due_today' => $settings['due_today_color'],
            'due_soon' => $settings['due_soon_color'],
            'on_track' => $settings['on_track_color'],
        ];

        $processesForView = $processes;
        if (!empty($processesForView)) {
            ob_start();
            $processes = $processesForView;
            $showActions = false;
            $highlightAnimations = $settings['enable_alert_pulse'];
            $showRowStatusColors = $settings['color_scheme'] === 'light';
            require __DIR__ . '/../views/dashboard/partials/process_table_rows.php';
            $htmlRows = ob_get_clean();
        } else {
            $colspan = 8;
            $htmlRows = '<tr><td colspan="' . $colspan . '" class="px-4 py-6 text-center text-lg text-slate-500">Nenhum processo disponível no momento.</td></tr>';
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'html' => $htmlRows,
            'generated_at' => date(DATE_ATOM),
            'total' => count($processesForView),
            'panel_id' => $selectedBoard['id'],
        ]);
        exit();
    }

    private function getAvailableProcessStatuses(): array
    {
        if ($this->availableProcessStatusesCache === null) {
            $statuses = $this->processoModel->getAvailableStatuses();
            $filtered = [];

            foreach ($statuses as $status) {
                if (!is_string($status)) {
                    continue;
                }

                if (in_array($status, Processo::TV_PANEL_EXCLUDED_STATUSES, true)) {
                    continue;
                }

                if (in_array($status, $filtered, true)) {
                    continue;
                }

                $filtered[] = $status;
            }

            $this->availableProcessStatusesCache = $filtered;
        }

        return $this->availableProcessStatusesCache;
    }

    private function normalizeTvPanelBoards(array $boards): array
    {
        $allowedStatuses = $this->getAvailableProcessStatuses();
        $normalized = [];

        foreach ($boards as $board) {
            if (!is_array($board)) {
                continue;
            }

            $title = isset($board['title']) ? trim((string) $board['title']) : '';
            if ($title === '') {
                continue;
            }

            $rawId = isset($board['id']) ? (string) $board['id'] : '';
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $rawId);
            if ($id === '') {
                $id = 'panel_' . substr(sha1($title . microtime(true)), 0, 10);
            }

            $statusesInput = $board['statuses'] ?? [];
            if (!is_array($statusesInput)) {
                $statusesInput = [];
            }

            $statuses = [];
            foreach ($statusesInput as $status) {
                if (!is_string($status)) {
                    continue;
                }

                $status = trim($status);
                if ($status === '') {
                    continue;
                }

                if (!in_array($status, $allowedStatuses, true)) {
                    continue;
                }

                if (in_array($status, $statuses, true)) {
                    continue;
                }

                $statuses[] = $status;
            }

            $normalized[] = [
                'id' => $id,
                'title' => $title,
                'statuses' => $statuses,
            ];
        }

        if (empty($normalized)) {
            $normalized[] = [
                'id' => 'panel_processes',
                'title' => 'Processos Ativos',
                'statuses' => [],
            ];
        }

        return array_values($normalized);
    }

    /**
     * Exibe a página de configurações específicas da API Omie.
     */
    public function omieSettings()
    {
        $pageTitle = "Configurações da Integração Omie";
        $settings = $this->configModel->getAll();

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/admin/omie_settings.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    private function getOmieSyncService(): OmieSyncService
    {
        if ($this->omieSyncService === null) {
            $this->omieSyncService = new OmieSyncService($this->pdo, $this->configModel);
        }

        return $this->omieSyncService;
    }

    private function getOmieSupportDefinitions(): array
    {
        return [
            'produtos' => [
                'title' => 'Produtos e Serviços',
                'model' => $this->omieProdutoModel,
                'columns' => [
                    ['key' => 'descricao', 'label' => 'Descrição'],
                    ['key' => 'codigo_produto', 'label' => 'Código Omie'],
                    ['key' => 'codigo_integracao', 'label' => 'Código de Integração'],
                    ['key' => 'cfop', 'label' => 'CFOP'],
                    ['key' => 'ncm', 'label' => 'NCM'],
                    ['key' => 'unidade', 'label' => 'Unidade'],
                    ['key' => 'valor_unitario', 'label' => 'Valor Unitário'],
                    ['key' => 'ativo', 'label' => 'Ativo'],
                ],
                'fields' => [
                    ['name' => 'descricao', 'label' => 'Descrição', 'type' => 'text', 'required' => true],
                    ['name' => 'codigo_produto', 'label' => 'Código Omie', 'type' => 'text'],
                    ['name' => 'codigo_integracao', 'label' => 'Código de Integração', 'type' => 'text'],
                    ['name' => 'cfop', 'label' => 'CFOP', 'type' => 'text'],
                    ['name' => 'ncm', 'label' => 'NCM', 'type' => 'text'],
                    ['name' => 'unidade', 'label' => 'Unidade', 'type' => 'text'],
                    ['name' => 'valor_unitario', 'label' => 'Valor Unitário', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'ativo', 'label' => 'Ativo', 'type' => 'checkbox'],
                ],
                'syncMethod' => 'syncProdutos',
            ],
        ];
    }

    private function getOmieSupportDefinition(string $type): array
    {
        $definitions = $this->getOmieSupportDefinitions();
        if (!isset($definitions[$type])) {
            throw new InvalidArgumentException('Tipo de tabela Omie inválido.');
        }

        return $definitions[$type];
    }

    public function syncOmieSupportData(): void
    {
        try {
            $type = $_POST['type'] ?? $_GET['type'] ?? null;

            if ($type) {
                $definition = $this->getOmieSupportDefinition($type);
                $syncMethod = $definition['syncMethod'];
                $result = $this->getOmieSyncService()->{$syncMethod}();
                $count = $result['total'] ?? 0;
                $_SESSION['success_message'] = sprintf(
                    '%s sincronizada com sucesso. %d registro(s) atualizado(s).',
                    $definition['title'],
                    $count
                );
                header('Location: admin.php?action=omie_support&type=' . urlencode($type));
                exit();
            }

            $result = $this->getOmieSyncService()->syncSupportTables();
            $count = $result['produtos']['total'] ?? 0;
            $_SESSION['success_message'] = sprintf(
                'Sincronização de produtos concluída. %d registro(s) atualizado(s).',
                $count
            );
        } catch (Exception $exception) {
            $_SESSION['error_message'] = 'Falha ao sincronizar com a Omie: ' . $exception->getMessage();
        }

        header('Location: admin.php?action=omie_settings');
        exit();
    }

    public function listOmieSupport(string $type): void
    {
        try {
            $definition = $this->getOmieSupportDefinition($type);
        } catch (InvalidArgumentException $exception) {
            $_SESSION['error_message'] = $exception->getMessage();
            header('Location: admin.php?action=omie_settings');
            exit();
        }

        $pageTitle = $definition['title'];
        $records = $definition['model']->getAll();
        $supportType = $type;

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/admin/omie_support_list.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function editOmieSupport(string $type, int $id): void
    {
        try {
            $definition = $this->getOmieSupportDefinition($type);
        } catch (InvalidArgumentException $exception) {
            $_SESSION['error_message'] = $exception->getMessage();
            header('Location: admin.php?action=omie_support&type=' . urlencode($type));
            exit();
        }

        $record = $definition['model']->findById($id);
        if (!$record) {
            $_SESSION['error_message'] = 'Registro não encontrado.';
            header('Location: admin.php?action=omie_support&type=' . urlencode($type));
            exit();
        }

        $pageTitle = 'Editar ' . $definition['title'];
        $supportType = $type;

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/admin/omie_support_form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function updateOmieSupport(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: admin.php?action=omie_settings');
            exit();
        }

        $type = $_POST['type'] ?? '';
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        try {
            $definition = $this->getOmieSupportDefinition($type);
        } catch (InvalidArgumentException $exception) {
            $_SESSION['error_message'] = $exception->getMessage();
            header('Location: admin.php?action=omie_settings');
            exit();
        }

        if ($id <= 0) {
            $_SESSION['error_message'] = 'Identificador inválido.';
            header('Location: admin.php?action=omie_support&type=' . urlencode($type));
            exit();
        }

        $data = [];
        foreach ($definition['fields'] as $field) {
            $name = $field['name'];

            if (!empty($field['readonly'])) {
                continue;
            }

            if (($field['type'] ?? 'text') === 'checkbox') {
                $data[$name] = isset($_POST[$name]) ? 1 : 0;
                continue;
            }

            if (!array_key_exists($name, $_POST)) {
                continue;
            }

            $data[$name] = $_POST[$name];
        }

        try {
            $updated = $definition['model']->updateById($id, $data);
            if ($updated) {
                $_SESSION['success_message'] = 'Registro atualizado com sucesso.';
            } else {
                $_SESSION['warning_message'] = 'Nenhuma alteração foi aplicada ao registro.';
            }
        } catch (Exception $exception) {
            $_SESSION['error_message'] = 'Falha ao atualizar o registro: ' . $exception->getMessage();
        }

        header('Location: admin.php?action=omie_support&type=' . urlencode($type));
        exit();
    }
    
    // Gestão de Tradutores
    public function listTradutores()
    {
        $pageTitle = "Gestão de Tradutores";
        $tradutores = $this->tradutorModel->getAll();
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/tradutores/lista.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    
    // Gestão de Usuários
    public function listUsers()
    {
        $pageTitle = "Gestão de Usuários";
        $users = $this->userModel->getAll();
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/users/lista.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    
    public function createUser()
    {
        $pageTitle = "Novo Usuário";
        $user = null; // Para o formulário saber que é um novo registro
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/users/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Salva o novo usuário no banco de dados.
     */
    public function storeUser()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (empty($_POST['senha'])) {
                $_SESSION['error_message'] = "O campo senha é obrigatório para novos usuários.";
                header('Location: admin.php?action=create_user');
                exit();
            }

            $userId = $this->userModel->create(
                $_POST['nome_completo'],
                $_POST['email'],
                $_POST['senha'],
                $_POST['perfil']
            );

            if ($userId) {
                $_SESSION['success_message'] = "Usuário criado com sucesso!";
            } else {
                $_SESSION['error_message'] = "Erro ao criar usuário. O email pode já existir.";
            }
            header('Location: admin.php?action=users');
            exit();
        }
    }

    /**
     * Mostra o formulário para editar um usuário existente.
     */
    public function editUser($id)
    {
        $user = $this->userModel->getById($id);
        if (!$user) {
            $_SESSION['error_message'] = "Usuário não encontrado.";
            header('Location: admin.php?action=users');
            exit();
        }
        $pageTitle = "Editar Usuário";
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/users/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Atualiza os dados de um usuário no banco.
     */
    public function updateUser()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $data = [
                'nome_completo' => $_POST['nome_completo'],
                'email'         => $_POST['email'],
                'perfil'        => $_POST['perfil'],
                'ativo'         => isset($_POST['ativo']) ? 1 : 0
            ];

            $this->userModel->update($id, $data);

            if (!empty($_POST['senha'])) {
                $this->userModel->updatePassword($id, $_POST['senha']);
            }

            $_SESSION['success_message'] = "Usuário atualizado com sucesso!";
            header('Location: admin.php?action=users');
            exit();
        }
    }

    /**
     * Exclui um usuário do sistema.
     */
    public function deleteUser($id)
    {
        if ($id == $_SESSION['user_id']) {
            $_SESSION['error_message'] = "Você não pode excluir seu próprio usuário.";
            header('Location: admin.php?action=users');
            exit();
        }
        
        if ($this->userModel->delete($id)) {
            $_SESSION['success_message'] = "Usuário excluído com sucesso!";
        } else {
            $_SESSION['error_message'] = "Erro ao excluir o usuário.";
        }
        header('Location: admin.php?action=users');
        exit();
    }
    
    // Gestão de Vendedores
    public function listVendedores()
    {
        $pageTitle = "Gestão de Vendedores";
        $vendedores = $this->vendedorModel->getAll();
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/vendedores/lista.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    // Gestão de Aparência
    public function showConfiguracoes()
    {
        $pageTitle = "Configurações de Aparência";
        $theme_color = $this->configModel->get('theme_color');
        $system_logo = $this->configModel->get('system_logo');
        $managementPasswordHash = $this->configModel->get('prospection_management_password_hash');
        $managementPasswordDefined = !empty($managementPasswordHash);
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/admin/configuracoes.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

public function saveConfiguracoes()
{
    // Inicia a sessão para garantir que as mensagens de feedback funcionam
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $changes_made = false;

        // 1. Salva a cor do tema
        if (isset($_POST['theme_color'])) {
            if ($this->configModel->save('theme_color', $_POST['theme_color'])) {
                $changes_made = true;
            }
        }
        
        // 2. Processa o upload do logótipo, apenas se um ficheiro for enviado
        if (isset($_FILES['system_logo']) && $_FILES['system_logo']['error'] != UPLOAD_ERR_NO_FILE) {
            
            if ($_FILES['system_logo']['error'] === UPLOAD_ERR_OK) {
                
                $uploadDir = __DIR__ . '/../../uploads/logos/';
                
                // Diagnóstico de permissões
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0777, true)) {
                        $_SESSION['error_message'] = "ERRO FATAL: Falha ao criar o diretório de uploads. Verifique as permissões do servidor na pasta 'uploads'.";
                        header('Location: admin.php?action=config');
                        exit();
                    }
                } elseif (!is_writable($uploadDir)) {
                    $_SESSION['error_message'] = "ERRO DE PERMISSÃO: O diretório 'uploads/logos/' não tem permissão de escrita. É necessário ajustar as permissões no servidor (ex: chmod 775).";
                    header('Location: admin.php?action=config');
                    exit();
                }

                $fileInfo = pathinfo($_FILES['system_logo']['name']);
                $extension = strtolower($fileInfo['extension']);
                $fileName = 'logo_' . time() . '.' . $extension;
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['system_logo']['tmp_name'], $targetPath)) {
                    if ($this->configModel->save('system_logo', 'uploads/logos/' . $fileName)) {
                        $changes_made = true;
                    }
                } else {
                    $_SESSION['error_message'] = "ERRO: Falha ao mover o ficheiro. Verifique as permissões do servidor.";
                }

            } else {
                // Mensagens de erro de upload mais claras
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE   => "O ficheiro excede o limite de tamanho do servidor (upload_max_filesize).",
                    UPLOAD_ERR_FORM_SIZE  => "O ficheiro excede o limite definido no formulário.",
                    UPLOAD_ERR_PARTIAL    => "O upload do ficheiro foi feito apenas parcialmente.",
                    UPLOAD_ERR_NO_TMP_DIR => "Erro de servidor: Falta uma pasta temporária.",
                    UPLOAD_ERR_CANT_WRITE => "Erro de servidor: Falha ao escrever o ficheiro no disco.",
                    UPLOAD_ERR_EXTENSION  => "Uma extensão do PHP interrompeu o upload.",
                ];
                $error_code = $_FILES['system_logo']['error'];
                $_SESSION['error_message'] = $upload_errors[$error_code] ?? "Erro de upload desconhecido. Código: " . $error_code;
            }
        }

        // 3. Atualiza a senha da gerência, caso informada
        $managementPassword = $_POST['management_password'] ?? '';
        $managementPasswordConfirmation = $_POST['management_password_confirmation'] ?? '';

        if ($managementPassword !== '' || $managementPasswordConfirmation !== '') {
            if ($managementPassword === '' || $managementPasswordConfirmation === '') {
                $_SESSION['error_message'] = 'Preencha e confirme a senha da gerência.';
            } elseif ($managementPassword !== $managementPasswordConfirmation) {
                $_SESSION['error_message'] = 'As senhas da gerência informadas não coincidem.';
            } elseif (strlen($managementPassword) < 6) {
                $_SESSION['error_message'] = 'A senha da gerência deve ter pelo menos 6 caracteres.';
            } else {
                $hashedPassword = password_hash($managementPassword, PASSWORD_DEFAULT);
                if ($this->configModel->save('prospection_management_password_hash', $hashedPassword)) {
                    $changes_made = true;
                } else {
                    $_SESSION['error_message'] = 'Não foi possível atualizar a senha da gerência.';
                }
            }
        }

        // Define a mensagem final para o utilizador
        if (!isset($_SESSION['error_message'])) {
            if ($changes_made) {
                $_SESSION['success_message'] = "Configurações salvas com sucesso!";
            } else {
                // Se não houve erro, mas nada foi alterado (ex: clicou em salvar sem mudar nada)
                $_SESSION['success_message'] = "Nenhuma alteração foi efetuada.";
            }
        }
    }

    // Garante o redirecionamento correto para a página de configurações
    header('Location: admin.php?action=config');
    exit();
}

    // =======================================================================
    // MÉTODOS DE AUTOMAÇÃO (NOVOS E ATUALIZADOS)
    // =======================================================================

    /**
     * Exibe a página de listagem de campanhas de automação.
     */
    public function showAutomacaoCampanhas()
    {
        $regras = $this->automacaoModel->getAllCampanhas();
        $kanbanColumns = $this->kanbanConfigService->getColumns();
        $kanbanDefaultColumns = $this->kanbanConfigService->getDefaultColumns();
        require_once __DIR__ . '/../views/admin/automacao_campanhas.php';
    }

    /**
     * Exibe o formulário de configurações da API de automação.
     */
    public function showAutomacaoSettings()
    {
        $settings = $this->configModel->getAll();
        require_once __DIR__ . '/../views/admin/automacao_settings.php';
    }

    /**
     * Salva as configurações da API de automação.
     */
    public function saveAutomacaoSettings()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            
            // Agora, esta função só salva as configurações da API Digisac.
            $this->configModel->save('digisac_api_url', $data['digisac_api_url'] ?? '');
            
            // Apenas salva o token se um novo for digitado
            if (!empty($data['digisac_api_token'])) {
                $this->configModel->save('digisac_api_token', $data['digisac_api_token']);
            }
            
            $_SESSION['success_message'] = "Configurações da API Digisac salvas com sucesso!";
        }
        header('Location: admin.php?action=automacao_settings');
        exit();
    }

    /**
     * Salva uma nova campanha de automação.
     */
    public function storeAutomacaoCampanha()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'nome_campanha' => $_POST['nome_campanha'] ?? '',
                'crm_gatilhos' => json_encode($_POST['crm_gatilhos'] ?? []),
                'digisac_conexao_id' => $_POST['digisac_conexao_id'] ?? '',
                'digisac_template_id' => $_POST['digisac_template_id'] ?? '',
                'mapeamento_parametros' => $_POST['mapeamento_parametros'] ?? '{}',
                'digisac_user_id' => $_POST['digisac_user_id'] ?? null,
                'email_assunto' => $_POST['email_assunto'] ?? null,
                'email_cabecalho' => $_POST['email_cabecalho'] ?? null,
                'email_corpo' => $_POST['email_corpo'] ?? null,
                'intervalo_reenvio_dias' => $_POST['intervalo_reenvio_dias'] ?? 30,
                'ativo' => isset($_POST['ativo']) ? 1 : 0
            ];
            $this->automacaoModel->createCampanha($data);
            $_SESSION['success_message'] = "Campanha criada com sucesso!";
        }
        header('Location: admin.php?action=automacao_campanhas');
        exit();
    }

    /**
     * Atualiza uma campanha de automação existente.
     */
    public function updateAutomacaoCampanha()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $data = [
                'nome_campanha' => $_POST['nome_campanha'] ?? '',
                'crm_gatilhos' => json_encode($_POST['crm_gatilhos'] ?? []),
                'digisac_conexao_id' => $_POST['digisac_conexao_id'] ?? '',
                'digisac_template_id' => $_POST['digisac_template_id'] ?? '',
                'mapeamento_parametros' => $_POST['mapeamento_parametros'] ?? '{}',
                'digisac_user_id' => $_POST['digisac_user_id'] ?? null,
                'email_assunto' => $_POST['email_assunto'] ?? null,
                'email_cabecalho' => $_POST['email_cabecalho'] ?? null,
                'email_corpo' => $_POST['email_corpo'] ?? null,
                'intervalo_reenvio_dias' => $_POST['intervalo_reenvio_dias'] ?? 30,
                'ativo' => isset($_POST['ativo']) ? 1 : 0
            ];
            $this->automacaoModel->updateCampanha($id, $data);
            $_SESSION['success_message'] = "Campanha atualizada com sucesso!";
        }
        header('Location: admin.php?action=automacao_campanhas');
        exit();
    }

    /**
     * Exclui uma campanha de automação.
     */
    public function deleteAutomacaoCampanha()
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $this->automacaoModel->deleteCampanha($id);
            $_SESSION['success_message'] = "Campanha excluída com sucesso!";
        }
        header('Location: admin.php?action=automacao_campanhas');
        exit();
    }

    /**
     * Retorna os dados de uma campanha específica em JSON.
     */
    public function getAutomacaoCampanhaJson()
    {
        header('Content-Type: application/json');
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $campanha = $this->automacaoModel->getCampanhaById($id);
        
        if ($campanha) {
            $campanha['crm_gatilhos'] = json_decode($campanha['crm_gatilhos'], true);
            echo json_encode(['success' => true, 'data' => $campanha]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Campanha não encontrada.']);
        }
        exit(); // <-- ADICIONE ESTA LINHA
    }

    public function saveKanbanColumns()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: admin.php?action=automacao_campanhas');
            exit();
        }

        $columns = $_POST['kanban_columns'] ?? [];
        if (!is_array($columns)) {
            $columns = [$columns];
        }

        try {
            $this->kanbanConfigService->saveColumns($columns);
            $_SESSION['success_message'] = 'Colunas do Kanban atualizadas com sucesso!';
        } catch (InvalidArgumentException $exception) {
            $_SESSION['error_message'] = $exception->getMessage();
        } catch (Throwable $exception) {
            $_SESSION['error_message'] = 'Não foi possível salvar as colunas do Kanban.';
            error_log('Kanban config save error: ' . $exception->getMessage());
        }

        header('Location: admin.php?action=automacao_campanhas');
        exit();
    }

    // =======================================================================
    // MÉTODOS AJAX DE INTEGRAÇÃO COM A DIGISAC
    // =======================================================================

    /**
     * Retorna a lista de conexões da API Digisac em JSON.
     */
    public function getDigisacConexoesJson()
    {
        header('Content-Type: application/json');
        $apiUrl = $this->configModel->get('digisac_api_url');
        $token = $this->configModel->get('digisac_api_token');
        $digicApi = new DigicApiService($apiUrl, $token);
        $conexoes = $digicApi->getConexoes();
        
        if ($conexoes !== null) {
            echo json_encode(['success' => true, 'data' => $conexoes]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro de conexão com a API Digisac. Verifique as credenciais.']);
        }
        exit();
    }
    
    /**
     * Retorna a lista de templates da API Digisac em JSON.
     */
    public function getDigisacTemplatesJson()
    {
        header('Content-Type: application/json');
        $apiUrl = $this->configModel->get('digisac_api_url');
        $token = $this->configModel->get('digisac_api_token');
        $digicApi = new DigicApiService($apiUrl, $token);
        $templates = $digicApi->getTemplates();
        
        if ($templates !== null) {
            echo json_encode(['success' => true, 'data' => $templates]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro de conexão com a API Digisac. Verifique as credenciais.']);
        }
        exit();
    }
    
    /**
     * Retorna a lista de usuários da API Digisac em JSON.
     */
    public function getDigisacUsersJson()
    {
        header('Content-Type: application/json');
        $apiUrl = $this->configModel->get('digisac_api_url');
        $token = $this->configModel->get('digisac_api_token');
        $digicApi = new DigicApiService($apiUrl, $token);
        $users = $digicApi->getUsers();
        
        if ($users !== null) {
            echo json_encode(['success' => true, 'data' => $users]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro de conexão com a API Digisac. Verifique as credenciais.']);
        }
        exit();
    }
    
    /**
     * Executa um teste de envio de mensagem de automação.
     */
    public function testAutomacaoCampanha()
    {
        header('Content-Type: application/json');
        $log = [];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método de requisição inválido.']);
            exit();
        }

        try {
            $campanhaId = filter_input(INPUT_POST, 'campanha_id', FILTER_VALIDATE_INT);
            $clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
            $testType = $_POST['test_type'] ?? 'whatsapp';
            $testEmail = filter_input(INPUT_POST, 'test_email', FILTER_VALIDATE_EMAIL);
            
            $log[] = "INFO: Iniciando teste para Campanha #$campanhaId.";
            $log[] = "INFO: Canal de teste selecionado: " . strtoupper($testType);
            
            $campanha = $this->automacaoModel->getCampanhaById($campanhaId);
            if (!$campanha) throw new Exception("Campanha não encontrada.");

            $cliente = null;
            if ($clienteId) {
                $clienteModel = new Cliente($this->pdo);
                $cliente = $clienteModel->getById($clienteId);
                $log[] = "INFO: Usando dados do Cliente ID #$clienteId: " . ($cliente['nome_cliente'] ?? 'Não encontrado');
            } else {
                $log[] = "INFO: Nenhum ID de cliente fornecido. Placeholders não serão substituídos.";
                $cliente = ['nome_cliente' => '[NOME DO CLIENTE]', 'nome_responsavel' => '[NOME DO RESPONSAVEL]', 'email' => $testEmail, 'telefone' => '[TELEFONE]'];
            }

            if (!$cliente) throw new Exception("Cliente de teste com ID #$clienteId não encontrado.");

            // Direciona para a função de teste correta
            if ($testType === 'whatsapp') {
                $this->testarCanalWhatsApp($campanha, $cliente, $log);
            } elseif ($testType === 'email') {
                $this->testarCanalEmail($campanha, $cliente, $testEmail, $log);
            } else {
                throw new Exception("Tipo de teste inválido.");
            }

        } catch (Exception $e) {
            $log[] = "ERRO CRÍTICO: " . $e->getMessage();
            echo json_encode(['success' => false, 'message' => $e->getMessage(), 'log' => $log]);
        }
        exit();
    }
    /**
     * Executa o teste de envio para o canal WhatsApp.
     */
    private function testarCanalWhatsApp($campanha, $cliente, &$log) {
        if (empty($cliente['telefone'])) {
            throw new Exception("Cliente não possui um número de telefone cadastrado para o teste de WhatsApp.");
        }

        $apiUrl = $this->configModel->get('digisac_api_url');
        $token = $this->configModel->get('digisac_api_token');
        if (empty($apiUrl) || empty($token)) {
            throw new Exception("API Digisac não configurada corretamente (URL ou Token ausentes).");
        }

        $digicApi = new DigicApiService($apiUrl, $token);
        $mapeamento = json_decode($campanha['mapeamento_parametros'], true);
        $params = [];
        if (is_array($mapeamento)) {
            ksort($mapeamento);
            foreach ($mapeamento as $pos => $campo) {
                $params[] = $cliente[$campo] ?? '';
            }
        }

        $log[] = "DEBUG: Telefone Destino: " . $cliente['telefone'];
        $log[] = "DEBUG: Conexão ID: " . ($campanha['digisac_conexao_id'] ?: 'Nenhum');
        $log[] = "DEBUG: Usuário Remetente ID: " . ($campanha['digisac_user_id'] ?: 'Nenhum');
        $log[] = "DEBUG: Template ID: " . ($campanha['digisac_template_id'] ?: 'Nenhum');
        $log[] = "DEBUG: Parâmetros Mapeados: " . json_encode($params);
        $log[] = "INFO: Enviando para a API Digisac...";
        
        $response = $digicApi->sendMessageByNumber(
            $cliente['telefone'], 
            $campanha['digisac_conexao_id'], 
            $campanha['digisac_template_id'], 
            $params,
            $campanha['digisac_user_id']
        );
        
        if ($response) {
            $log[] = "SUCESSO: A API da Digisac aceitou a requisição.";
            echo json_encode(['success' => true, 'log' => $log]);
        } else {
            throw new Exception("A API da Digisac retornou uma falha no envio.");
        }
    }

    /**
     * Executa o teste de envio para o canal E-mail.
     */
    private function testarCanalEmail($campanha, $cliente, $testEmail, &$log) {
        $destinatario = $testEmail ?: ($cliente['email'] ?? '');
        if (empty($destinatario)) {
            throw new Exception("Nenhum e-mail de destino fornecido ou encontrado no cadastro do cliente.");
        }
        if (empty($campanha['email_assunto']) || empty($campanha['email_corpo'])) {
            throw new Exception("A campanha não está configurada para envio de e-mail (assunto ou corpo vazios).");
        }
        
        $log[] = "INFO: Destinatário do e-mail de teste: " . $destinatario;

        // Substituir placeholders
        $placeholders = [
            '{{nome_cliente}}' => $cliente['nome_cliente'] ?? '',
            '{{nome_responsavel}}' => $cliente['nome_responsavel'] ?? '',
            '{{email}}' => $cliente['email'] ?? '',
            '{{telefone}}' => $cliente['telefone'] ?? ''
        ];
        $assunto = str_replace(array_keys($placeholders), array_values($placeholders), $campanha['email_assunto']);
        $cabecalho = str_replace(array_keys($placeholders), array_values($placeholders), $campanha['email_cabecalho']);
        $corpo = str_replace(array_keys($placeholders), array_values($placeholders), $campanha['email_corpo']);
        $corpo_final = $cabecalho . "<br><br>" . nl2br($corpo);
        
        $log[] = "DEBUG: Assunto Final: " . $assunto;
        $log[] = "INFO: Tentando enviar e-mail via SMTP...";

        // --- INÍCIO DA MELHORIA ---
        // A chamada ao serviço agora está dentro de um bloco try...catch para
        // capturar a exceção detalhada que o EmailService pode lançar.
        try {
            $emailService = new EmailService($this->pdo);
            $enviado = $emailService->sendEmail($destinatario, $assunto, $corpo_final);

            if ($enviado) {
                $log[] = "SUCESSO: E-mail de teste enviado para " . $destinatario;
                echo json_encode(['success' => true, 'log' => $log]);
            } else {
                throw new Exception("O serviço de e-mail retornou uma falha desconhecida.");
            }
        } catch (Exception $e) {
            // Se o EmailService lançar a exceção, nós a capturamos aqui.
            // A mensagem já virá formatada com os detalhes do PHPMailer.
            throw new Exception($e->getMessage());
        }
        // --- FIM DA MELHORIA ---
    }


    // =============================================================
    // LÓGICA PARA A NOVA PÁGINA DE CONFIGURAÇÃO DE SMTP
    // =============================================================

    /**
     * Exibe a página de configurações de SMTP.
     */
    public function showSmtpSettings()
    {
        // 1. Carrega configurações de SMTP
        $smtpConfigModel = new SmtpConfigModel($this->pdo);
        $smtp_config = $smtpConfigModel->getSmtpConfig();
        
        // 2. Carrega configurações de Alertas (da tabela 'configuracoes')
        $configModel = new Configuracao($this->pdo);
        $alert_config = [
            'alert_emails' => $configModel->get('alert_emails'),
            'alert_servico_vencido_enabled' => $configModel->get('alert_servico_vencido_enabled')
        ];

        // 3. Define o título e carrega as views
        $page_title = "Configurações de Notificações e Alertas";
        
        require_once __DIR__ . '/../views/layouts/header.php';
        // Vamos passar ambas as configurações para a view
        require_once __DIR__ . '/../views/admin/smtp_settings.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Salva as configurações de SMTP e Alertas.
     */
    public function saveSmtpSettings()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 1. Salva as configurações de SMTP
            $smtpConfigModel = new SmtpConfigModel($this->pdo);
            $smtp_data = [
                'smtp_host'       => $_POST['smtp_host'] ?? '',
                'smtp_port'       => $_POST['smtp_port'] ?? '',
                'smtp_user'       => $_POST['smtp_user'] ?? '',
                'smtp_pass'       => $_POST['smtp_pass'],
                'smtp_security'   => $_POST['smtp_security'] ?? 'tls',
                'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
                'smtp_from_name'  => $_POST['smtp_from_name'] ?? ''
            ];
            $smtpConfigModel->saveSmtpConfig($smtp_data);

            // 2. Salva as configurações de Alertas na tabela 'configuracoes'
            $configModel = new Configuracao($this->pdo);
            $configModel->save('alert_emails', $_POST['alert_emails'] ?? '');
            
            // Para o checkbox, salvamos 1 se ele foi marcado, ou 0 se não foi
            $servicoVencidoEnabled = isset($_POST['alert_servico_vencido_enabled']) ? '1' : '0';
            $configModel->save('alert_servico_vencido_enabled', $servicoVencidoEnabled);
            
            // 3. Define a mensagem de sucesso
            $_SESSION['message'] = "Configurações de notificações salvas com sucesso!";
            $_SESSION['message_type'] = 'success';
        }

        header('Location: admin.php?action=smtp_settings');
        exit();
    }
        /**
     * Envia um e-mail de teste usando as configurações salvas em smtp_config.
     */
    public function testSmtpConnection()
    {
        require_once __DIR__ . '/../services/EmailService.php';

        try {
            $smtpConfigModel = new SmtpConfigModel($this->pdo);
            $config = $smtpConfigModel->getSmtpConfig();
            $recipient = $config['smtp_from_email'];

            if (empty($recipient)) {
                throw new Exception("Não há um 'E-mail do Remetente' salvo para receber o teste.");
            }

            $emailService = new EmailService($this->pdo);
            $emailService->sendEmail($recipient, 'Teste de Conexão SMTP', '<h1>Teste OK!</h1><p>Suas configurações de SMTP estão funcionando.</p>');

            $_SESSION['message'] = "E-mail de teste enviado com sucesso para " . htmlspecialchars($recipient);
            $_SESSION['message_type'] = 'success';

        } catch (Exception $e) {
            $_SESSION['message'] = "FALHA AO ENVIAR: " . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: admin.php?action=smtp_settings');
        exit();
    }

}