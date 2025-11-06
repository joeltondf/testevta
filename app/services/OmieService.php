<?php
// /app/services/OmieService.php

require_once __DIR__ . '/../models/Configuracao.php';
require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/CategoriaFinanceira.php';
require_once __DIR__ . '/../models/OmieProduto.php';
require_once __DIR__ . '/../models/OmieCidade.php';
require_once __DIR__ . '/../utils/OmiePayloadBuilder.php';
require_once __DIR__ . '/../utils/PhoneUtils.php';
require_once __DIR__ . '/OmieApiClient.php';

class OmieService {
    private $configModel;
    private $appKey;
    private $appSecret;
    private $baseApiUrl = 'https://app.omie.com.br/api/v1';
    private $pdo;
    private $processoModel;
    private $clienteModel;
    private $categoriaModel;
    private $omieProdutoModel;

    public function __construct(
        Configuracao $configModel,
        ?PDO $pdo = null,
        ?Processo $processoModel = null,
        ?Cliente $clienteModel = null,
        ?CategoriaFinanceira $categoriaModel = null,
        ?OmieProduto $omieProdutoModel = null
    ) {
        $this->configModel = $configModel;
        $this->appKey = getenv('OMIE_APP_KEY') ?: $this->configModel->getSetting('omie_app_key');
        $this->appSecret = getenv('OMIE_APP_SECRET') ?: $this->configModel->getSetting('omie_app_secret');
        $this->pdo = $pdo;
        $this->processoModel = $processoModel;
        $this->clienteModel = $clienteModel;
        $this->categoriaModel = $categoriaModel;
        $this->omieProdutoModel = $omieProdutoModel;
    }

    /**
     * Prepara e executa uma chamada para a API da Omie.
     *
     * @param string $call O nome do método da API a ser chamado.
     * @param array $params Os parâmetros para o método da API.
     * @return array A resposta da API decodificada.
     * @throws Exception Se a chave ou o segredo da API não estiverem configurados, ou se ocorrer um erro na chamada.
     */
    private function makeRequest(string $endpoint, string $call, array $params): array {
        return OmieApiClient::call($this->appKey ?? '', $this->appSecret ?? '', $endpoint, $call, $params);
    }

    private function requirePdo(): PDO
    {
        if (!$this->pdo instanceof PDO) {
            throw new RuntimeException('Uma conexão PDO válida é necessária para esta operação da Omie.');
        }

        return $this->pdo;
    }

    private function getProcessoModel(): Processo
    {
        if (!$this->processoModel instanceof Processo) {
            $this->processoModel = new Processo($this->requirePdo());
        }

        return $this->processoModel;
    }

    private function getClienteModel(): Cliente
    {
        if (!$this->clienteModel instanceof Cliente) {
            $this->clienteModel = new Cliente($this->requirePdo());
        }

        return $this->clienteModel;
    }

    private function getCategoriaModel(): CategoriaFinanceira
    {
        if (!$this->categoriaModel instanceof CategoriaFinanceira) {
            $this->categoriaModel = new CategoriaFinanceira($this->requirePdo());
        }

        return $this->categoriaModel;
    }

    private function getOmieProdutoModel(): OmieProduto
    {
        if (!$this->omieProdutoModel instanceof OmieProduto) {
            $this->omieProdutoModel = new OmieProduto($this->requirePdo());
        }

        return $this->omieProdutoModel;
    }

    /**
     * Retorna todos os clientes cadastrados na Omie.
     */
    public function listarClientes(array $params = []): array
    {
        return OmieApiClient::fetchAll(
            $this->appKey ?? '',
            $this->appSecret ?? '',
            '/geral/clientes/',
            'ListarClientes',
            ['clientes_cadastro'],
            $params
        );
    }

    /**
     * Retorna todos os produtos/serviços cadastrados na Omie.
     */
    public function listarProdutos(array $params = []): array
    {
        return OmieApiClient::fetchAll(
            $this->appKey ?? '',
            $this->appSecret ?? '',
            '/geral/produtos/',
            'ListarProdutos',
            ['produto_servico_cadastro'],
            $params
        );
    }

    /**
     * Lista as etapas de faturamento cadastradas na Omie.
     */
    public function listarEtapasFaturamento(array $params = []): array
    {
        return OmieApiClient::fetchAll(
            $this->appKey ?? '',
            $this->appSecret ?? '',
            '/servicos/etapafaturamento/',
            'ListarEtapasFaturamento',
            ['etapasCadastro', 'lista'],
            $params
        );
    }

    /**
     * Lista as categorias de serviço cadastradas na Omie.
     */
    public function listarCategorias(array $params = []): array
    {
        return OmieApiClient::fetchAll(
            $this->appKey ?? '',
            $this->appSecret ?? '',
            '/servicos/categorias/',
            'ListarCategorias',
            ['categoriasCadastro', 'lista'],
            $params
        );
    }

    /**
     * Lista as contas correntes cadastradas na Omie.
     */
    public function listarContasCorrentes(array $params = []): array
    {
        return OmieApiClient::fetchAll(
            $this->appKey ?? '',
            $this->appSecret ?? '',
            '/financas/contacorrente/',
            'ListarContasCorrentes',
            ['conta_corrente', 'lista'],
            $params
        );
    }

    /**
     * Lista os cenários fiscais cadastrados na Omie.
     */
    public function listarCenarios(array $params = []): array
    {
        return OmieApiClient::fetchAll(
            $this->appKey ?? '',
            $this->appSecret ?? '',
            '/servicos/cenariofiscal/',
            'ListarCenarios',
            ['cenariosCadastro', 'lista'],
            $params
        );
    }

    /**
     * Lista cadastros de serviços na Omie, incluindo detalhes como cCodServMun.
     */
    public function listarCadastroServico(array $params = []): array
    {
        return $this->makeRequest('/servicos/servico/', 'ListarCadastroServico', $params);
    }

    /**
     * Consulta um serviço específico cadastrado na Omie.
     */
    public function consultarServico(array $params): array
    {
        return $this->makeRequest('/servicos/servico/', 'ConsultarServico', $params);
    }

    public function consultarServicoPorCodigo(string $codigoServico): array
    {
        $codigoNormalizado = trim($codigoServico);
        if ($codigoNormalizado === '') {
            throw new InvalidArgumentException('O código do serviço não pode ser vazio para consulta na Omie.');
        }

        return $this->consultarServico(['codigo_servico' => $codigoNormalizado]);
    }

    public function createCustomer(array $payload): array {
        $omiePayload = OmiePayloadBuilder::buildIncluirClientePayload($payload);
        return $this->incluirCliente($omiePayload);
    }

    public function incluirCliente(array $payload): array
    {
        return $this->makeRequest('/geral/clientes/', 'IncluirCliente', $payload);
    }

    public function pesquisarCidades(string $termo, ?string $uf = null): array
    {
        $payload = OmiePayloadBuilder::buildPesquisarCidadesPayload($termo, $uf);

        $cities = OmieApiClient::fetchAll(
            $this->appKey ?? '',
            $this->appSecret ?? '',
            '/geral/cidades/',
            'PesquisarCidades',
            ['cidades_cadastro', 'cidade_cadastro', 'lista', 'cidades'],
            $payload
        );

        return array_map(static fn (array $item): OmieCidade => OmieCidade::fromArray($item), $cities);
    }

    public function alterarCliente(array $payload): array
    {
        return $this->makeRequest('/geral/clientes/', 'AlterarCliente', $payload);
    }

    public function findCustomerByName(string $name): ?array {
        $params = [
            'pagina' => 1,
            'registros_por_pagina' => 100,
        ];
        try {
            $response = $this->makeRequest('/geral/clientes/', 'ListarClientes', $params);
            if (isset($response['clientes_cadastro'])) {
                foreach ($response['clientes_cadastro'] as $customer) {
                    if (isset($customer['razao_social']) && mb_strtolower($customer['razao_social']) === mb_strtolower($name)) {
                        return $customer;
                    }
                    if (isset($customer['nome_fantasia']) && mb_strtolower($customer['nome_fantasia']) === mb_strtolower($name)) {
                        return $customer;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar cliente na Omie: " . $e->getMessage());
            return null;
        }
        return null;
    }

    public function incluirProduto(array $payload): array
    {
        return $this->makeRequest('/geral/produtos/', 'IncluirProduto', $payload);
    }

    public function alterarProduto(array $payload): array
    {
        return $this->makeRequest('/geral/produtos/', 'AlterarProduto', $payload);
    }

    public function excluirProduto(array $payload): array
    {
        return $this->makeRequest('/geral/produtos/', 'ExcluirProduto', $payload);
    }

    public function consultarProduto(array $payload): array
    {
        return $this->makeRequest('/geral/produtos/', 'ConsultarProduto', $payload);
    }

    public function consultarProdutoPorCodigo(string $codigoProduto): array
    {
        $codigoNormalizado = trim($codigoProduto);
        if ($codigoNormalizado === '') {
            throw new InvalidArgumentException('O código do produto não pode ser vazio para consulta na Omie.');
        }

        return $this->consultarProduto(['codigo_produto' => $codigoNormalizado]);
    }

    public function consultarProdutoPorIntegracao(string $codigoIntegracao): array
    {
        $codigoNormalizado = trim($codigoIntegracao);
        if ($codigoNormalizado === '') {
            throw new InvalidArgumentException('O código de integração do produto não pode ser vazio para consulta na Omie.');
        }

        return $this->consultarProduto(['codigo_produto_integracao' => $codigoNormalizado]);
    }

    public function excluirProdutoPorCodigo(string $codigoProduto): array
    {
        $codigoNormalizado = trim($codigoProduto);
        if ($codigoNormalizado === '') {
            throw new InvalidArgumentException('O código do produto não pode ser vazio para exclusão na Omie.');
        }

        return $this->excluirProduto(['codigo_produto' => $codigoNormalizado]);
    }

    public function excluirProdutoPorIntegracao(string $codigoIntegracao): array
    {
        $codigoNormalizado = trim($codigoIntegracao);
        if ($codigoNormalizado === '') {
            throw new InvalidArgumentException('O código de integração do produto não pode ser vazio para exclusão na Omie.');
        }

        return $this->excluirProduto(['codigo_produto_integracao' => $codigoNormalizado]);
    }

    public function createProduct(array $payload): array {
        // Mantido para compatibilidade retroactiva.
        return $this->incluirProduto($payload);
    }

    public function findProductByName(string $name): ?array {
        $params = [
            'pagina' => 1,
            'registros_por_pagina' => 1,
            'filtrar_por_descricao' => $name
        ];
        try {
            $response = $this->listarProdutos($params);
            return $response[0] ?? null;
        } catch (Exception $e) {
            error_log("Erro ao buscar produto na Omie: " . $e->getMessage());
            return null;
        }
    }

    public function upsertCustomer(array $payload): array {
        // O endpoint UpsertCliente lida com a criação e atualização.
        // O payload deve ser mapeado no controller antes de ser enviado para este método.
        return $this->makeRequest('/geral/clientes/', 'UpsertCliente', $payload);
    }

    public function createSale(array $payload): array {
        // A API da Omie para criar vendas é complexa e espera uma estrutura aninhada.
        // Assumimos que o $payload já vem pré-formatado com as seções necessárias (cabecalho, det, etc.)
        // e com os IDs corretos de cliente e produtos.
        return $this->makeRequest('/vendas/pedido/', 'IncluirPedidoVenda', $payload);
    }

    public function incluirPedido(int $processoId): array
    {
        try {
            $processData = $this->fetchProcessData($processoId);
            $processo = $processData['processo'];
            $documentos = $processData['documentos'];

            $cliente = $this->fetchCliente((int)($processo['cliente_id'] ?? 0));
            $codigoIntegracao = $this->resolvePedidoIntegracaoCodigo($processo, $processoId);

            $itensData = $this->buildOrderItems($processoId, $processo, $documentos);
            $itens = $itensData['items'];

            $valorTotalPedido = $this->normalizeDecimal($processo['valor_total'] ?? 0);
            $cabecalho = [
                'codigo_pedido_integracao' => $codigoIntegracao,
                'codigo_cliente' => (int)$cliente['omie_id'],
                'data_previsao' => $this->formatDateForOmie($processo['data_previsao_entrega'] ?? $processo['traducao_prazo_data'] ?? null),
                'etapa' => $this->resolveEtapaCodigo($processo),
                'quantidade_itens' => count($itens),
            ];

            $frete = ['modalidade' => $this->resolveFreteModalidade()];
            $informacoesAdicionais = $this->buildAdditionalInfo($processo, $cliente, $itensData['categoryCodes']);
            $parcelas = $this->buildInstallments($processo, $valorTotalPedido);

            $payload = [
                'cabecalho' => $cabecalho,
                'det' => array_map(static function (array $item): array {
                    return ['ide' => $item];
                }, $itens),
                'frete' => $frete,
                'informacoes_adicionais' => $informacoesAdicionais,
            ];

            if (!empty($parcelas)) {
                $payload['lista_parcelas'] = array_map(static function (array $parcela): array {
                    return ['parcela' => $parcela];
                }, $parcelas);
            }

            $response = $this->makeRequest('/produtos/pedido/', 'IncluirPedido', $payload);

            if (!empty($response['numero_pedido'])) {
                $this->getProcessoModel()->salvarNumeroOsOmie($processoId, $response['numero_pedido']);
                $this->maybeUpdateProcessStatus($processoId, $processo['status_processo'] ?? null);
            }

            return $response;
        } catch (Throwable $exception) {
            error_log('Erro ao incluir pedido na Omie: ' . $exception->getMessage());
            throw $exception;
        }
    }
    /**
     * Cancela uma Ordem de Serviço na Omie.
     *
     * @param string $osNumber O número da OS gerado pela Omie.
     * @return array A resposta da API.
     */
    public function cancelServiceOrder(string $osNumber): array {
        $payload = ['cNumOS' => $osNumber];
        return $this->makeRequest('/servicos/os/', 'CancelarOS', $payload);
    }
    public function createServiceOrder(array $payload): array {
        // Similar à criação de vendas, a criação de OS espera um payload estruturado.
        // Assumimos que o $payload já vem com a estrutura e IDs corretos.
        return $this->makeRequest('/servicos/os/', 'IncluirOS', $payload);
    }

    private function fetchProcessData(int $processoId): array
    {
        $processData = $this->getProcessoModel()->getById($processoId);
        if (!$processData || empty($processData['processo'])) {
            throw new RuntimeException('Processo não encontrado para envio à Omie.');
        }

        return $processData;
    }

    private function fetchCliente(int $clienteId): array
    {
        if ($clienteId <= 0) {
            throw new RuntimeException('Processo sem cliente vinculado para envio à Omie.');
        }

        $cliente = $this->getClienteModel()->getById($clienteId);
        if (!$cliente) {
            throw new RuntimeException('Cliente do processo não encontrado para envio à Omie.');
        }

        if (empty($cliente['omie_id'])) {
            throw new RuntimeException('Cliente não possui o campo omie_id preenchido. Sincronize o cadastro antes de gerar o pedido.');
        }

        return $cliente;
    }

    private function resolvePedidoIntegracaoCodigo(array $processo, int $processoId): string
    {
        $existingCode = $this->normalizeString($processo['codigo_pedido_integracao'] ?? null);
        if ($existingCode !== null) {
            return $existingCode;
        }

        $generatedCode = $this->generatePedidoIntegracaoCodigo($processoId);
        if (!$this->getProcessoModel()->salvarCodigoPedidoIntegracao($processoId, $generatedCode)) {
            throw new RuntimeException('Falha ao salvar o código de integração do pedido no processo.');
        }

        return $generatedCode;
    }

    private function generatePedidoIntegracaoCodigo(int $processoId): string
    {
        try {
            $random = strtoupper(bin2hex(random_bytes(4)));
        } catch (Exception $exception) {
            $random = strtoupper(substr(str_replace('.', '', uniqid('', true)), -8));
        }

        return sprintf('PRC%06d-%s', $processoId, $random);
    }

    private function buildOrderItems(int $processoId, array $processo, array $documentos): array
    {
        $items = [];
        $categoryCodes = [];

        foreach ($documentos as $index => $documento) {
            $valorUnitario = $this->normalizeDecimal($documento['valor_unitario'] ?? 0);
            $quantidade = $this->normalizeDecimal($documento['quantidade'] ?? 1);
            if ($quantidade <= 0) {
                $quantidade = 1.0;
            }

            $categoria = $this->resolveCategoriaFinanceira($documento);
            $produto = $this->resolveOmieProduto($categoria, $documento);

            if (!empty($categoria['omie_codigo_categoria'])) {
                $categoryCodes[] = $categoria['omie_codigo_categoria'];
            }

            $item = [
                'codigo_item_integracao' => $this->buildItemIntegrationCode($processoId, $documento, $index),
                'descricao' => $this->resolveItemDescription($documento, $categoria, $processo),
                'quantidade' => $this->formatQuantity($quantidade),
                'valor_unitario' => $this->formatCurrency($valorUnitario),
                'valor_total' => $this->formatCurrency($valorUnitario * $quantidade),
                'valor_desconto' => $this->formatCurrency(0.0),
                'tipo_desconto' => 'V',
            ];

            $item += $this->resolveProductCodes($produto);

            if (!empty($produto['ncm'])) {
                $item['ncm'] = $this->normalizeString($produto['ncm']);
            }

            if (!empty($produto['cfop'])) {
                $item['cfop'] = $this->normalizeString($produto['cfop']);
            }

            if (!empty($produto['unidade'])) {
                $item['unidade'] = $this->normalizeString($produto['unidade']);
            }

            $items[] = $item;
        }

        if (empty($items)) {
            throw new RuntimeException('Nenhum item de orçamento válido foi encontrado para compor o pedido da Omie.');
        }

        return [
            'items' => $items,
            'categoryCodes' => array_values(array_unique(array_filter($categoryCodes))),
        ];
    }

    private function resolveCategoriaFinanceira(array $documento): array
    {
        $tipoDocumento = $this->normalizeString($documento['tipo_documento'] ?? null);
        if ($tipoDocumento === null) {
            throw new RuntimeException('Item de orçamento sem categoria financeira definida.');
        }

        $categoria = $this->getCategoriaModel()->findReceitaByNome($tipoDocumento);
        if (!$categoria) {
            throw new RuntimeException(sprintf('Categoria financeira "%s" não está configurada para integração com a Omie.', $tipoDocumento));
        }

        return $categoria;
    }

    private function resolveOmieProduto(array $categoria, array $documento): array
    {
        $categoriaId = isset($categoria['id']) ? (int)$categoria['id'] : 0;
        if ($categoriaId <= 0) {
            throw new RuntimeException('Categoria financeira sem identificador válido para vincular ao produto da Omie.');
        }

        $produto = $this->getOmieProdutoModel()->findByLocalProductId($categoriaId);
        if (!$produto) {
            $descricaoCategoria = $categoria['nome_categoria'] ?? ($documento['tipo_documento'] ?? '');
            throw new RuntimeException(sprintf('Categoria "%s" não possui um produto vinculado na Omie.', $descricaoCategoria));
        }

        return $produto;
    }

    private function resolveItemDescription(array $documento, array $categoria, array $processo): string
    {
        $descricao = [];

        $tipo = $this->normalizeString($documento['tipo_documento'] ?? $categoria['nome_categoria'] ?? null);
        if ($tipo !== null) {
            $descricao[] = $tipo;
        }

        $nomeDocumento = $this->normalizeString($documento['nome_documento'] ?? null);
        if ($nomeDocumento !== null) {
            $descricao[] = $nomeDocumento;
        }

        if (empty($descricao)) {
            $descricao[] = 'Item do processo #' . ($processo['orcamento_numero'] ?? $processo['id'] ?? '');
        }

        return implode(' - ', $descricao);
    }

    private function buildItemIntegrationCode(int $processoId, array $documento, int $index): string
    {
        $documentId = isset($documento['id']) ? (int)$documento['id'] : null;
        $suffix = $documentId && $documentId > 0
            ? str_pad((string)$documentId, 6, '0', STR_PAD_LEFT)
            : str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT);

        return sprintf('PRC%06d-IT%s', $processoId, $suffix);
    }

    private function resolveProductCodes(array $produto): array
    {
        $payload = [];
        $codigoProduto = $this->normalizeString($produto['codigo_produto'] ?? null);
        if ($codigoProduto !== null) {
            $payload['codigo_produto'] = $codigoProduto;
        }

        $codigoIntegracao = $this->normalizeString($produto['codigo_integracao'] ?? null);
        if ($codigoIntegracao !== null) {
            $payload['codigo_produto_integracao'] = $codigoIntegracao;
        }

        if (empty($payload)) {
            throw new RuntimeException('Produto vinculado ao item não possui código cadastrado na Omie.');
        }

        return $payload;
    }

    private function buildAdditionalInfo(array $processo, array $cliente, array $categoryCodes): array
    {
        $info = [
            'codigo_categoria' => $this->resolveOrderCategoryCode($processo, $categoryCodes),
            'codigo_conta_corrente' => $this->resolveOrderContaCorrente($processo),
            'consumidor_final' => $this->resolveFlag($processo['consumidor_final'] ?? null, 'N'),
            'enviar_email' => $this->resolveFlag($processo['enviar_email'] ?? null, 'N'),
        ];

        $cenario = $this->normalizeString($processo['codigo_cenario_fiscal'] ?? null);
        if ($cenario === null) {
            $cenario = $this->normalizeString($this->configModel->getSetting('omie_default_cenario_fiscal') ?? null);
        }

        if ($cenario !== null) {
            $info['codigo_cenario_impostos'] = $cenario;
        }

        return $info;
    }

    private function resolveOrderCategoryCode(array $processo, array $categoryCodes): string
    {
        $candidates = [
            $processo['codigo_categoria'] ?? null,
            $processo['omie_codigo_categoria'] ?? null,
        ];

        foreach ($categoryCodes as $code) {
            $candidates[] = $code;
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeString($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $configuredDefault = $this->normalizeString($this->configModel->getSetting('omie_default_categoria') ?? null);
        if ($configuredDefault !== null) {
            return $configuredDefault;
        }

        $legacyConfigured = $this->normalizeString($this->configModel->getSetting('omie_os_category_code') ?? null);
        if ($legacyConfigured !== null) {
            return $legacyConfigured;
        }

        throw new RuntimeException('Não foi possível determinar o código de categoria da Omie para o pedido.');
    }

    private function resolveOrderContaCorrente(array $processo): string
    {
        $candidates = [
            $processo['codigo_conta_corrente'] ?? null,
            $this->configModel->getSetting('omie_default_conta_corrente') ?? null,
            $this->configModel->getSetting('omie_os_bank_account_code') ?? null,
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeNumericString($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        throw new RuntimeException('Não foi possível determinar a conta corrente da Omie para o pedido.');
    }

    private function resolveFreteModalidade(): string
    {
        $configured = $this->normalizeNumericString($this->configModel->getSetting('omie_pedido_frete_modalidade') ?? null);
        return $configured ?? '9';
    }

    private function resolveFlag($value, string $default = 'N'): string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $normalized = mb_strtoupper(trim((string)$value));
        $normalized = str_replace(['Ã', 'Á', 'Â', 'À'], 'A', $normalized);
        $normalized = str_replace(['Õ', 'Ó', 'Ô', 'Ò'], 'O', $normalized);

        $positives = ['S', 'SIM', 'YES', 'Y', '1', 'TRUE'];
        $negatives = ['N', 'NAO', 'NO', '0', 'FALSE'];

        if (in_array($normalized, $positives, true)) {
            return 'S';
        }

        if (in_array($normalized, $negatives, true)) {
            return 'N';
        }

        return $default;
    }

    private function resolveEtapaCodigo(array $processo): string
    {
        $status = $this->normalizeString($processo['status_processo'] ?? null);
        if ($status !== null) {
            $normalizedStatus = mb_strtolower($status);
            if (in_array($normalizedStatus, ['orçamento', 'orcamento', 'orçamento pendente', 'orcamento pendente'], true)) {
                return '00';
            }
        }

        $etapa = $this->normalizeString($processo['etapa_faturamento_codigo'] ?? null);
        if ($etapa !== null) {
            return str_pad($etapa, 2, '0', STR_PAD_LEFT);
        }

        $configuredDefault = $this->normalizeString($this->configModel->getSetting('omie_default_etapa') ?? null);
        if ($configuredDefault !== null) {
            return str_pad($configuredDefault, 2, '0', STR_PAD_LEFT);
        }

        $legacyConfigured = $this->normalizeString($this->configModel->getSetting('omie_pedido_etapa_confirmada') ?? null);
        if ($legacyConfigured === null) {
            $legacyConfigured = $this->normalizeString($this->configModel->getSetting('omie_pedido_etapa_confirmado') ?? null);
        }
        if ($legacyConfigured !== null) {
            return str_pad($legacyConfigured, 2, '0', STR_PAD_LEFT);
        }

        return '10';
    }

    private function buildInstallments(array $processo, float $valorTotal): array
    {
        if ($valorTotal <= 0.0) {
            return [];
        }

        $parcelas = [];
        $entrada = $this->normalizeDecimal($processo['orcamento_valor_entrada'] ?? 0);
        $totalParcelas = max(1, $this->extractInstallmentCount($processo));
        $datas = $this->extractInstallmentDates($processo);

        $numeroParcela = 1;
        if ($entrada > 0) {
            $primeiraData = $datas[0] ?? $processo['data_pagamento_1'] ?? $processo['data_previsao_entrega'] ?? null;
            $parcelas[] = $this->makeParcel($numeroParcela++, $primeiraData, min($entrada, $valorTotal), $valorTotal);
        }

        $restante = max(0.0, $valorTotal - $entrada);
        $parcelasRestantes = max(0, $totalParcelas - count($parcelas));
        if ($parcelasRestantes === 0 && $restante > 0) {
            $parcelasRestantes = 1;
        }

        if ($parcelasRestantes > 0 && $restante > 0) {
            $valores = $this->distributeAmount($restante, $parcelasRestantes);
            for ($i = 0; $i < $parcelasRestantes; $i++) {
                $indiceData = $i + count($parcelas);
                $dataBase = $datas[$indiceData] ?? $this->addMonthsToDate(
                    $processo['data_pagamento_1'] ?? $processo['data_previsao_entrega'] ?? null,
                    $i + (count($parcelas) > 0 ? 1 : 0)
                );
                $parcelas[] = $this->makeParcel($numeroParcela++, $dataBase, $valores[$i], $valorTotal);
            }
        }

        if (empty($parcelas)) {
            $parcelas[] = $this->makeParcel(1, $processo['data_pagamento_1'] ?? $processo['data_previsao_entrega'] ?? null, $valorTotal, $valorTotal);
        }

        return $parcelas;
    }

    private function extractInstallmentCount(array $processo): int
    {
        $parcelas = $processo['orcamento_parcelas'] ?? null;
        if (is_numeric($parcelas) && (int)$parcelas > 0) {
            return (int)$parcelas;
        }

        $datas = $this->extractInstallmentDates($processo);
        if (!empty($datas)) {
            return count($datas);
        }

        $entrada = $this->normalizeDecimal($processo['orcamento_valor_entrada'] ?? 0);
        if ($entrada > 0) {
            return 2;
        }

        return 1;
    }

    private function extractInstallmentDates(array $processo): array
    {
        $datas = [];
        foreach (['data_pagamento_1', 'data_pagamento_2'] as $campo) {
            if (!empty($processo[$campo])) {
                $datas[] = $processo[$campo];
            }
        }

        return $datas;
    }

    private function makeParcel(int $numero, ?string $dataVencimento, float $valor, float $valorTotal): array
    {
        $valorPositivo = max(0.0, round($valor, 2));
        $percentual = $valorTotal > 0.0 ? round(($valorPositivo / $valorTotal) * 100, 2) : 0.0;

        return [
            'numero_parcela' => $numero,
            'data_vencimento' => $this->formatDateForOmie($dataVencimento),
            'percentual' => $percentual,
            'valor' => $this->formatCurrency($valorPositivo),
        ];
    }

    private function distributeAmount(float $amount, int $parts): array
    {
        if ($parts <= 0) {
            return [];
        }

        $base = floor(($amount / $parts) * 100) / 100;
        $values = array_fill(0, $parts, $base);
        $difference = round($amount - ($base * $parts), 2);

        for ($i = 0; $difference > 0 && $i < $parts; $i++) {
            $values[$i] = round($values[$i] + 0.01, 2);
            $difference = round($difference - 0.01, 2);
        }

        if ($difference < 0) {
            for ($i = 0; $difference < 0 && $i < $parts; $i++) {
                $values[$i] = round(max(0.0, $values[$i] - 0.01), 2);
                $difference = round($difference + 0.01, 2);
            }
        }

        return $values;
    }

    private function addMonthsToDate(?string $date, int $months): string
    {
        $dateTime = $this->createDateTime($date);
        if ($months > 0) {
            $dateTime->modify(sprintf('+%d month', $months));
        }

        return $dateTime->format('Y-m-d');
    }

    private function createDateTime($date): DateTime
    {
        if ($date instanceof DateTime) {
            return clone $date;
        }

        if ($date instanceof DateTimeImmutable) {
            return new DateTime($date->format('Y-m-d H:i:s'));
        }

        $stringDate = $this->normalizeString(is_string($date) ? $date : null);
        $formats = ['Y-m-d', 'd/m/Y', 'Y-m-d H:i:s', 'd/m/Y H:i:s'];

        if ($stringDate !== null) {
            foreach ($formats as $format) {
                $dateTime = DateTime::createFromFormat($format, $stringDate);
                if ($dateTime instanceof DateTime) {
                    return $dateTime;
                }
            }

            try {
                return new DateTime($stringDate);
            } catch (Exception $exception) {
                // Continua para o retorno padrão abaixo.
            }
        }

        return new DateTime();
    }

    private function formatDateForOmie($date): string
    {
        return $this->createDateTime($date)->format('d/m/Y');
    }

    private function normalizeDecimal($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float)$value;
        }

        $normalized = str_replace(['R$', ' '], '', (string)$value);
        $normalized = preg_replace('/[^0-9,.-]/', '', $normalized);
        $hasComma = strpos($normalized, ',') !== false;
        $hasDot = strpos($normalized, '.') !== false;

        if ($hasComma && $hasDot) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif ($hasComma) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float)$normalized : 0.0;
    }

    private function formatCurrency(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals, '.', '');
    }

    private function formatQuantity(float $value): string
    {
        return number_format($value, 4, '.', '');
    }

    private function normalizeString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string)$value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeNumericString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        $digits = preg_replace('/\D+/', '', (string)$value);
        return $digits === '' ? null : $digits;
    }

    private function maybeUpdateProcessStatus(int $processoId, ?string $statusAtual): void
    {
        $normalized = mb_strtolower($statusAtual ?? '');
        $serviceInProgressAliases = ['serviço em andamento', 'servico em andamento', 'em andamento'];

        if (!in_array($normalized, $serviceInProgressAliases, true)) {
            $this->getProcessoModel()->updateStatus($processoId, ['status_processo' => 'Serviço em Andamento']);
        }
    }
}
