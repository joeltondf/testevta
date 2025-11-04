<?php
/**
 * @file /app/controllers/ClientesController.php
 * @description Controller para gerir as requisições da entidade 'Cliente'.
 */

require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../models/CategoriaFinanceira.php';
require_once __DIR__ . '/../services/OmieService.php';
require_once __DIR__ . '/../models/Configuracao.php';
require_once __DIR__ . '/../utils/PhoneUtils.php';
require_once __DIR__ . '/../utils/OmiePayloadBuilder.php';
require_once __DIR__ . '/../utils/DocumentValidator.php';

class ClientesController
{
    private const SESSION_KEY_CLIENT_FORM = 'cliente_form_data';
    private const DEFAULT_PHONE_DDI = '55';

    private $clienteModel;
    private $pdo;
    private $omieService;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->clienteModel = new Cliente($pdo);

        // Instancia o OmieService para uso automático
        $configModel = new Configuracao($pdo);
        $this->omieService = new OmieService($configModel, $pdo);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // ... (index, create, edit, delete, sendWelcomeEmail - permanecem inalterados) ...
    public function index()
    {
        $pageTitle = "Gestão de Clientes";
        $clientes = $this->clienteModel->getAppClients();
        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/clientes/lista.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    public function create()
    {
        $pageTitle = "Novo Cliente";
        $formData = $this->consumeClientFormInput();
        $cliente = !empty($formData) ? $formData : [];
        $isEdit = false;
        $return_url = $_GET['return_to'] ?? 'clientes.php';

        $categoriaModel = new CategoriaFinanceira($this->pdo);
        $produtos_orcamento = $categoriaModel->getProdutosOrcamento(false);

        $servicos_mensalista = isset($formData['servicos_mensalistas']) && is_array($formData['servicos_mensalistas'])
            ? $formData['servicos_mensalistas']
            : [];

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/clientes/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function edit($id)
    {
        $pageTitle = "Editar Cliente";
        $cliente = $this->clienteModel->getById($id);
        $isEdit = true;
        $return_url = $_GET['return_to'] ?? 'clientes.php';

        $formData = $this->consumeClientFormInput();
        if (!empty($formData)) {
            $cliente = array_merge($cliente ?? [], $formData);
        }

        $categoriaModel = new CategoriaFinanceira($this->pdo);
        $produtos_orcamento = $categoriaModel->getProdutosOrcamento(false);
        $servicos_mensalista = $this->clienteModel->getServicosMensalista($id);
        if (isset($formData['servicos_mensalistas']) && is_array($formData['servicos_mensalistas'])) {
            $servicos_mensalista = $formData['servicos_mensalistas'];
        }

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/clientes/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function delete($id)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if ($this->clienteModel->delete($id)) {
            $_SESSION['success_message'] = "Cliente excluído com sucesso!";
        } else {
            $_SESSION['error_message'] = "Não é possível excluir o cliente, pois ele está associado a um ou mais processos.";
        }

        header('Location: clientes.php');
        exit();
    }

    private function sendWelcomeEmail($clientName, $recipientEmail, $password)
    {
        try {
            $emailService = new EmailService($this->clienteModel->getPdo());
            $assunto = "Seu acesso ao Portal do Cliente foi criado!";
            $loginUrl = "https://teste.cliente.pro/login.php";
            $corpo = "
                <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <h2>Olá, " . htmlspecialchars($clientName) . "!</h2>
                    <p>Seu acesso ao nosso Portal do Cliente foi criado com sucesso. Agora você pode acompanhar seus processos online.</p>
                    <p>Utilize os dados abaixo para acessar:</p>
                    <div style='background-color: #f2f2f2; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p><strong>Site para Acesso:</strong> <a href='" . $loginUrl . "'>" . $loginUrl . "</a></p>
                        <p><strong>Login:</strong> " . htmlspecialchars($recipientEmail) . "</p>
                        <p><strong>Sua Senha:</strong> " . htmlspecialchars($password) . "</p>
                    </div>
                    <p>Atenciosamente,<br>Equipe FATTO</p>
                </div>
            ";
            $emailService->sendEmail($recipientEmail, $assunto, $corpo);
        } catch (Exception $e) {
            error_log('Falha ao enviar e-mail de boas-vindas para ' . $recipientEmail . ': ' . $e->getMessage());
        }
    }

    /**
     * Processa e armazena o novo cliente, e depois sincroniza com a Omie.
     */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $returnTo = $data['return_to'] ?? 'clientes.php';

            if (isset($data['tipo_pessoa']) && $data['tipo_pessoa'] === 'Física') {
                $data['nome_responsavel'] = $data['nome_cliente'];
            }

            $validationErrors = $this->validateClientData($data, true);
            if (!empty($validationErrors)) {
                $_SESSION['error_message'] = implode('<br>', $validationErrors);
                $this->rememberClientFormInput($_POST);
                header('Location: clientes.php?action=create&return_to=' . urlencode($returnTo));
                exit();
            }

            $data = $this->normalizeClientFields($data);

            try {
                $data = $this->normalizePhoneData($data);
            } catch (InvalidArgumentException $exception) {
                $_SESSION['error_message'] = $exception->getMessage();
                $this->rememberClientFormInput($_POST);
                header('Location: clientes.php?action=create&return_to=' . urlencode($returnTo));
                exit();
            }

            $result = $this->clienteModel->create($data);

            if ($result && is_numeric($result)) {
                $newClientId = (int) $result;
                $_SESSION['success_message'] = "Cliente cadastrado com sucesso!";

                $this->syncNewClientWithOmie($newClientId);

                if (($data['tipo_assessoria'] ?? '') === 'Mensalista') {
                    $this->clienteModel->salvarServicosMensalista($newClientId, $data['servicos_mensalistas'] ?? []);
                }

                if (!empty($data['criar_login']) && !empty($data['login_email'])) {
                    $this->sendWelcomeEmail($data['nome_cliente'], $data['login_email'], $data['login_senha']);
                }

                $separator = parse_url($returnTo, PHP_URL_QUERY) === null ? '?' : '&';
                $this->clearClientFormInput();
                header('Location: ' . $returnTo . $separator . 'new_client_id=' . $newClientId);
                exit();
            }

            $_SESSION['error_message'] = ($result === 'error_duplicate_cpf_cnpj')
                ? "O CPF/CNPJ informado já está em uso por outro cliente."
                : "Erro ao cadastrar cliente.";
            $this->rememberClientFormInput($_POST);
            header('Location: clientes.php?action=create&return_to=' . urlencode($returnTo));
            exit();
        }
    }

    /**
     * Processa a atualização do cliente, e depois sincroniza com a Omie.
     */
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $data = $_POST;
            $returnTo = trim((string)($data['return_to'] ?? 'clientes.php'));

            if (isset($data['tipo_pessoa']) && $data['tipo_pessoa'] === 'Física') {
                $data['nome_responsavel'] = $data['nome_cliente'];
            }

            $redirectToEdit = 'clientes.php?action=edit&id=' . $id;
            if ($returnTo !== '' && $returnTo !== 'clientes.php') {
                $redirectToEdit .= '&return_to=' . urlencode($returnTo);
            }

            $validationErrors = $this->validateClientData($data);
            if (!empty($validationErrors)) {
                $_SESSION['error_message'] = implode('<br>', $validationErrors);
                $this->rememberClientFormInput($_POST);
                header('Location: ' . $redirectToEdit);
                exit();
            }

            $data = $this->normalizeClientFields($data);

            try {
                $data = $this->normalizePhoneData($data);
            } catch (InvalidArgumentException $exception) {
                $_SESSION['error_message'] = $exception->getMessage();
                $this->rememberClientFormInput($_POST);
                header('Location: ' . $redirectToEdit);
                exit();
            }

            $result = $this->clienteModel->update($id, $data);

            if ($result === true) {
                $_SESSION['success_message'] = "Cliente atualizado com sucesso!";

                $sincronizarOmie = isset($_POST['sincronizar_omie']) ? (bool)$_POST['sincronizar_omie'] : false;
                $this->syncUpdatedClientWithOmie((int)$id, $sincronizarOmie);

                if ($_POST['tipo_assessoria'] === 'Mensalista') {
                    $this->clienteModel->salvarServicosMensalista($id, $_POST['servicos_mensalistas'] ?? []);
                } else {
                    $this->clienteModel->salvarServicosMensalista($id, []);
                }

                if (isset($_POST['continue_prospeccao_id'])) {
                    $queryParams = http_build_query([
                        'cliente_id' => $id,
                        'titulo' => $_POST['continue_nome_servico'],
                        'valor_total' => $_POST['continue_valor_inicial']
                    ]);
                    $this->clearClientFormInput();
                    header('Location: ' . APP_URL . '/processos.php?action=create&' . $queryParams);
                    exit();
                }

                $this->clearClientFormInput();
                $shouldRedirectToReturn = $returnTo !== '' && !preg_match('/^clientes\.php(\?.*)?$/', $returnTo);
                if ($shouldRedirectToReturn) {
                    $separator = parse_url($returnTo, PHP_URL_QUERY) === null ? '?' : '&';
                    header('Location: ' . $returnTo . $separator . 'updated_client_id=' . $id);
                } else {
                    header('Location: clientes.php');
                }
                exit();
            } else {
                $_SESSION['error_message'] = ($result === 'error_duplicate_cpf_cnpj')
                    ? "O CPF/CNPJ informado já está em uso por outro cliente."
                    : "Erro ao atualizar cliente.";
                $this->rememberClientFormInput($_POST);
                header('Location: ' . $redirectToEdit);
                exit();
            }
        }
    }

    private function validateClientData(array $data, bool $requirePhone = false): array
    {
        $errors = [];
        $nomeCliente = trim((string) ($data['nome_cliente'] ?? ''));
        if ($nomeCliente === '') {
            $errors[] = 'Informe o nome do cliente.';
        } elseif (mb_strlen($nomeCliente, 'UTF-8') > 60) {
            $errors[] = 'O nome do cliente deve ter no máximo 60 caracteres.';
        }

        $nomeResponsavel = trim((string) ($data['nome_responsavel'] ?? ''));
        if ($nomeResponsavel !== '' && mb_strlen($nomeResponsavel, 'UTF-8') > 60) {
            $errors[] = 'O nome do responsável deve ter no máximo 60 caracteres.';
        }
        $tipoPessoa = $data['tipo_pessoa'] ?? 'Jurídica';
        $documento = DocumentValidator::sanitizeNumber((string) ($data['cpf_cnpj'] ?? ''));

        if ($tipoPessoa === 'Física') {
            if ($documento === '') {
                $errors[] = 'Informe o CPF.';
            } elseif (!DocumentValidator::isValidCpf($documento)) {
                $errors[] = 'Informe um CPF válido.';
            }
        } else {
            if ($documento === '') {
                $errors[] = 'Informe o CNPJ.';
            } elseif (!DocumentValidator::isValidCnpj($documento)) {
                $errors[] = 'Informe um CNPJ válido.';
            }
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            $errors[] = 'Informe o e-mail.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail válido.';
        }

        $cep = DocumentValidator::sanitizeNumber((string) ($data['cep'] ?? ''));
        if ($cep === '') {
            $errors[] = 'Informe o CEP.';
        } elseif (strlen($cep) !== 8) {
            $errors[] = 'Informe um CEP válido.';
        }

        $endereco = trim((string) ($data['endereco'] ?? ''));
        if ($endereco === '') {
            $errors[] = 'Informe o endereço.';
        } elseif (mb_strlen($endereco, 'UTF-8') > 60) {
            $errors[] = 'O endereço deve ter no máximo 60 caracteres.';
        }

        $bairro = trim((string) ($data['bairro'] ?? ''));
        if ($bairro === '') {
            $errors[] = 'Informe o bairro.';
        }

        $cidade = trim((string) ($data['cidade'] ?? ''));
        $estado = strtoupper(trim((string) ($data['estado'] ?? '')));
        $cityValidationSource = strtolower(trim((string) ($data['city_validation_source'] ?? '')));
        $allowCityValidationFallback = $cityValidationSource !== 'api';

        if ($cidade === '') {
            $errors[] = 'Selecione uma cidade.';
        }

        if ($estado === '') {
            $errors[] = 'Informe a UF (estado).';
        } elseif (!preg_match('/^[A-Z]{2}$/', $estado)) {
            $errors[] = 'Informe uma UF válida.';
        }

        if ($cidade !== '' && preg_match('/^[A-Z]{2}$/', $estado)) {
            if (!$this->isValidCityFromApi($cidade, $estado, $allowCityValidationFallback)) {
                $errors[] = 'Selecione uma cidade válida da lista.';
            }
        }

        $telefone = trim((string) ($data['telefone'] ?? ''));
        $telefoneDdi = trim((string) ($data['telefone_ddi'] ?? ''));

        if ($requirePhone && $telefone === '') {
            $errors[] = 'Informe o telefone.';
        }

        if ($telefone !== '') {
            $ddiToValidate = $telefoneDdi !== '' ? $telefoneDdi : self::DEFAULT_PHONE_DDI;

            try {
                normalizeDDI($ddiToValidate);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if (isset($data['prazo_acordado_dias'])) {
            $prazoAcordado = trim((string) $data['prazo_acordado_dias']);
            if ($prazoAcordado !== '') {
                if (!ctype_digit($prazoAcordado) || (int) $prazoAcordado <= 0) {
                    $errors[] = 'Informe um prazo acordado válido (número inteiro maior que zero).';
                }
            }
        }

        return $errors;
    }

    private function isValidCityFromApi(string $cidade, string $estado, bool $allowFallback = false): bool
    {
        try {
            $cidades = $this->omieService->pesquisarCidades($cidade, $estado);
        } catch (Throwable $exception) {
            error_log('Erro ao validar cidade na Omie: ' . $exception->getMessage());
            if ($allowFallback) {
                $this->registerCityValidationWarning('Não foi possível validar a cidade com a Omie. Os dados informados foram mantidos.');
                return true;
            }

            return false;
        }

        $cidadeNormalizada = $this->normalizeCityName($cidade);

        foreach ($cidades as $cidadeOmie) {
            $nomeOmieNormalizado = $this->normalizeCityName($cidadeOmie->nome);

            if ($nomeOmieNormalizado === $cidadeNormalizada

                && mb_strtoupper($cidadeOmie->uf) === mb_strtoupper($estado)) {
                return true;
            }
        }

        if ($allowFallback) {
            $this->registerCityValidationWarning('Não foi possível validar a cidade com a Omie. Os dados informados foram mantidos.');
            return true;
        }

        return false;
    }

    private function registerCityValidationWarning(string $message): void
    {
        if (!isset($_SESSION['warning_message']) || trim((string) $_SESSION['warning_message']) === '') {
            $_SESSION['warning_message'] = $message;
            return;
        }

        if (strpos((string) $_SESSION['warning_message'], $message) === false) {
            $_SESSION['warning_message'] .= '<br>' . $message;
        }
    }

    private function normalizeCityName(string $value): string
    {
        $normalized = mb_strtoupper(trim($value));

        if ($normalized === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if ($transliterated !== false && $transliterated !== null) {
            $normalized = $transliterated;
        }

        $collapsedWhitespace = preg_replace('/\s+/', ' ', $normalized);

        return is_string($collapsedWhitespace) ? trim($collapsedWhitespace) : trim($normalized);
    }

    private function rememberClientFormInput(array $data): void
    {
        $_SESSION[self::SESSION_KEY_CLIENT_FORM] = $data;
    }

    private function consumeClientFormInput(): array
    {
        $sessionData = $_SESSION[self::SESSION_KEY_CLIENT_FORM] ?? ($_SESSION['form_data'] ?? []);

        unset($_SESSION[self::SESSION_KEY_CLIENT_FORM], $_SESSION['form_data']);

        return is_array($sessionData) ? $sessionData : [];
    }

    private function clearClientFormInput(): void
    {
        unset($_SESSION[self::SESSION_KEY_CLIENT_FORM], $_SESSION['form_data']);
    }


    private function normalizeClientFields(array $data): array
    {
        if (array_key_exists('telefone', $data)) {
            $data['telefone'] = trim((string) $data['telefone']);
        }

        if (array_key_exists('telefone_ddi', $data)) {
            $data['telefone_ddi'] = trim((string) $data['telefone_ddi']);
        }

        $data['nome_cliente'] = trim((string) ($data['nome_cliente'] ?? ''));
        if (array_key_exists('nome_responsavel', $data)) {
            $data['nome_responsavel'] = trim((string) $data['nome_responsavel']);
        }

        $data['email'] = trim((string) ($data['email'] ?? ''));
        $data['cidade'] = trim((string) ($data['cidade'] ?? ''));
        $data['estado'] = strtoupper(trim((string) ($data['estado'] ?? '')));
        $data['cep'] = trim((string) ($data['cep'] ?? ''));
        $data['cpf_cnpj'] = trim((string) ($data['cpf_cnpj'] ?? ''));
        $data['endereco'] = trim((string) ($data['endereco'] ?? ''));
        $data['bairro'] = trim((string) ($data['bairro'] ?? ''));

        $numero = trim((string) ($data['numero'] ?? ''));
        $data['numero'] = $numero === '' ? 'N/A' : $numero;

        if (array_key_exists('prazo_acordado_dias', $data)) {
            $prazoAcordado = trim((string) $data['prazo_acordado_dias']);
            $data['prazo_acordado_dias'] = $prazoAcordado === '' ? null : (int) $prazoAcordado;
        }

        return $data;
    }

    private function syncNewClientWithOmie(int $clientId): void
    {
        try {
            $cliente = $this->clienteModel->getById($clientId);
            if (!$cliente) {
                throw new Exception("Cliente local com ID {$clientId} não encontrado para sincronização.");
            }

            $cliente = $this->ensureIntegrationIdentifiers($cliente);
            $payload = OmiePayloadBuilder::buildIncluirClientePayload($cliente);
            $response = $this->omieService->incluirCliente($payload);

            if (!empty($response['codigo_cliente_omie'])) {
                $this->clienteModel->updateIntegrationIdentifiers(
                    $clientId,
                    $cliente['codigo_cliente_integracao'],
                    (int)$response['codigo_cliente_omie']
                );
                $_SESSION['info_message'] = $_SESSION['info_message'] ?? 'Cliente sincronizado com a Omie com sucesso.';
            }
        } catch (Exception $exception) {
            error_log("Falha ao sincronizar cliente ID {$clientId} com a Omie: " . $exception->getMessage());
            $_SESSION['warning_message'] = "O cliente foi salvo localmente, mas falhou ao sincronizar com a Omie. Verifique as configurações da API.";
        }
    }

    private function syncUpdatedClientWithOmie(int $clientId, bool $shouldSync): void
    {
        if (!$shouldSync) {
            return;
        }

        try {
            $cliente = $this->clienteModel->getById($clientId);
            if (!$cliente) {
                throw new Exception("Cliente local com ID {$clientId} não encontrado para sincronização.");
            }

            if (empty($cliente['omie_id'])) {
                $this->syncNewClientWithOmie($clientId);
                return;
            }

            $cliente = $this->ensureIntegrationIdentifiers($cliente);
            $payload = OmiePayloadBuilder::buildAlterarClientePayload($cliente);
            $response = $this->omieService->alterarCliente($payload);
            if (!empty($response['codigo_cliente_omie'])) {
                $this->clienteModel->updateIntegrationIdentifiers(
                    $clientId,
                    $cliente['codigo_cliente_integracao'],
                    (int)$response['codigo_cliente_omie']
                );
            }

            $_SESSION['info_message'] = $_SESSION['info_message'] ?? 'Cadastro do cliente atualizado na Omie.';
        } catch (Exception $exception) {
            error_log("Falha ao atualizar cliente ID {$clientId} na Omie: " . $exception->getMessage());
            $_SESSION['warning_message'] = "Não foi possível sincronizar o cliente com a Omie: " . $exception->getMessage();
        }
    }

    private function ensureIntegrationIdentifiers(array $cliente): array
    {
        $integrationCode = $cliente['codigo_cliente_integracao'] ?? '';
        if ($integrationCode === '' || $integrationCode === null) {
            $integrationCode = $this->generateClientIntegrationCode((int)$cliente['id']);
            if ($this->clienteModel->supportsIntegrationCodeColumn()) {
                $this->clienteModel->updateIntegrationIdentifiers(
                    (int)$cliente['id'],
                    $integrationCode,
                    isset($cliente['omie_id']) ? (int)$cliente['omie_id'] : null
                );
            }
            $cliente['codigo_cliente_integracao'] = $integrationCode;
        }

        return $cliente;
    }

    private function normalizePhoneData(array $data): array
    {
        if (!array_key_exists('telefone', $data)) {
            return $data;
        }

        $telefone = trim((string) $data['telefone']);
        $telefoneDdi = trim((string) ($data['telefone_ddi'] ?? ''));

        if ($telefone === '') {
            $data['telefone'] = null;
            $data['telefone_ddi'] = null;
            $data['telefone_ddd'] = null;
            $data['telefone_numero'] = null;

            return $data;
        }

        $parts = extractPhoneParts($telefone);
        $ddd = $parts['ddd'];
        $phone = $parts['phone'];

        $ddi = $telefoneDdi !== '' ? normalizeDDI($telefoneDdi) : self::DEFAULT_PHONE_DDI;

        $data['telefone'] = $ddi . ($ddd ?? '') . ($phone ?? '');
        $data['telefone_ddi'] = $ddi;
        $data['telefone_ddd'] = $ddd;
        $data['telefone_numero'] = $phone;

        return $data;
    }

    private function generateClientIntegrationCode(int $clientId): string
    {
        return 'CLI-' . str_pad((string)$clientId, 6, '0', STR_PAD_LEFT);
    }

    // O método syncOmie manual pode ser mantido ou removido, já que agora é automático.
    public function syncOmie($id) { /* ... */ }
    public function createOmieSale() { /* ... */ }
}