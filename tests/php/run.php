<?php

declare(strict_types=1);

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
require_once __DIR__ . '/../../app/models/Cliente.php';
require_once __DIR__ . '/../../app/utils/OmiePayloadBuilder.php';
require_once __DIR__ . '/../../app/services/DigisacService.php';

$tests = [];

function runTest(string $name, callable $callback, array &$tests): void
{
    try {
        $callback();
        $tests[] = ['name' => $name, 'status' => 'passed'];
    } catch (Throwable $exception) {
        $tests[] = [
            'name' => $name,
            'status' => 'failed',
            'message' => $exception->getMessage(),
        ];
    }
}

function assertEquals($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $formatted = $message !== '' ? $message : sprintf('Failed asserting that %s matches expected %s.', var_export($actual, true), var_export($expected, true));
        throw new RuntimeException($formatted);
    }
}

runTest('normalizeDDD strips non-digits and validates length', function (): void {
    assertEquals('21', normalizeDDD('(21)'));
}, $tests);

runTest('normalizePhone removes DDD and non-digit characters', function (): void {
    assertEquals('34567890', normalizePhone('(21) 3456-7890'));
}, $tests);

runTest('normalizePhone trims country code when present', function (): void {
    assertEquals('912345678', normalizePhone('+55 (11) 91234-5678'));
}, $tests);

runTest('normalizePhone accepts short phone numbers', function (): void {
    assertEquals('1234', normalizePhone('1234'));
}, $tests);

runTest('normalizeDDI removes non-digits and validates length', function (): void {
    assertEquals('351', normalizeDDI('+351'));
}, $tests);

runTest('extractPhoneParts returns digits without country code', function (): void {
    $parts = extractPhoneParts('+55 (21) 3456-7890');
    assertEquals('21', $parts['ddd']);
    assertEquals('34567890', $parts['phone']);
}, $tests);

runTest('extractPhoneParts handles short local numbers', function (): void {
    $parts = extractPhoneParts('(11) 1234');
    assertEquals('11', $parts['ddd']);
    assertEquals('1234', $parts['phone']);
}, $tests);

runTest('formatInternationalPhone builds human readable string', function (): void {
    assertEquals('+55 (11) 91234-5678', formatInternationalPhone('55', '11', '912345678'));
}, $tests);

runTest('updateIntegrationIdentifiers skips integration column when absent', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE clientes (id INTEGER PRIMARY KEY AUTOINCREMENT, nome_cliente TEXT, omie_id INTEGER)');
    $pdo->exec("INSERT INTO clientes (nome_cliente, omie_id) VALUES ('Cliente Teste', NULL)");

    $clienteModel = new Cliente($pdo);
    $result = $clienteModel->updateIntegrationIdentifiers(1, 'CLI-000001', 123456);
    assertEquals(true, $result, 'O método deve retornar true mesmo sem a coluna de integração.');

    $row = $pdo->query('SELECT omie_id FROM clientes WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    assertEquals(123456, (int) $row['omie_id'], 'O campo omie_id deve ser atualizado.');
}, $tests);

runTest('buildAlterarClientePayload privilegia codigo_cliente_omie', function (): void {
    $payload = OmiePayloadBuilder::buildAlterarClientePayload([
        'id' => 10,
        'nome_cliente' => 'Empresa Teste',
        'telefone' => '(11) 91234-5678',
        'cidade' => 'São Paulo',
        'estado' => 'sp',
        'codigo_cliente_integracao' => 'CLI-000010',
        'omie_id' => 998877,
    ]);

    assertEquals(998877, $payload['codigo_cliente_omie']);
    if (isset($payload['codigo_cliente_integracao'])) {
        throw new RuntimeException('codigo_cliente_integracao não deve estar presente quando codigo_cliente_omie está definido.');
    }
    assertEquals('11', $payload['telefone1_ddd']);
    assertEquals('912345678', $payload['telefone1_numero']);
}, $tests);

runTest('buildAlterarClientePayload utiliza telefone pré-normalizado', function (): void {
    $payload = OmiePayloadBuilder::buildAlterarClientePayload([
        'nome_cliente' => 'Empresa Rio',
        'telefone_ddd' => '21',
        'telefone_numero' => '34567890',
        'cidade' => 'Rio de Janeiro',
        'estado' => 'RJ',
        'codigo_cliente_integracao' => 'CLI-000020',
    ]);

    assertEquals('21', $payload['telefone1_ddd']);
    assertEquals('34567890', $payload['telefone1_numero']);
}, $tests);

runTest('Cliente::create persiste DDI quando coluna existe', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $inspectorReflection = new ReflectionClass(DatabaseSchemaInspector::class);
    $cacheProperty = $inspectorReflection->getProperty('columnCache');
    $cacheProperty->setAccessible(true);
    $cacheProperty->setValue(null, []);

    $pdo->exec('CREATE TABLE clientes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome_cliente TEXT NOT NULL,
        nome_responsavel TEXT NULL,
        cpf_cnpj TEXT NULL,
        email TEXT NULL,
        telefone TEXT NULL,
        telefone_ddi TEXT NULL,
        telefone_ddd TEXT NULL,
        telefone_numero TEXT NULL,
        endereco TEXT NULL,
        numero TEXT NULL,
        bairro TEXT NULL,
        cidade TEXT NULL,
        estado TEXT NULL,
        cep TEXT NULL,
        tipo_pessoa TEXT NULL,
        tipo_assessoria TEXT NULL,
        prazo_acordado_dias INTEGER NULL,
        user_id INTEGER NULL,
        is_prospect INTEGER NOT NULL DEFAULT 0
    )');

    $clienteModel = new Cliente($pdo);

    $data = [
        'nome_cliente' => 'Cliente DDI',
        'nome_responsavel' => 'Responsável',
        'cpf_cnpj' => '12345678000100',
        'email' => 'ddi@example.com',
        'telefone' => '5511987654321',
        'telefone_ddi' => '55',
        'telefone_ddd' => '11',
        'telefone_numero' => '987654321',
        'endereco' => 'Rua Teste',
        'numero' => '123',
        'bairro' => 'Centro',
        'cidade' => 'São Paulo',
        'estado' => 'SP',
        'cep' => '01001000',
        'tipo_pessoa' => 'Jurídica',
        'tipo_assessoria' => 'Mensalista',
        'prazo_acordado_dias' => 10,
    ];

    $newId = $clienteModel->create($data);
    $row = $pdo->query('SELECT telefone_ddi, telefone_ddd, telefone_numero FROM clientes WHERE id = ' . (int) $newId)->fetch(PDO::FETCH_ASSOC);

    assertEquals('55', $row['telefone_ddi']);
    assertEquals('11', $row['telefone_ddd']);
    assertEquals('987654321', $row['telefone_numero']);
}, $tests);

class TestDigisacService extends DigisacService
{
    public array $requests = [];

    protected function performMessageRequest(array $payload): array
    {
        $this->requests[] = $payload;

        return ['success' => true, 'message_id' => 'msg_test'];
    }
}

runTest('DigisacService::formatPhoneNumber normaliza número nacional', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE configuracoes (chave TEXT PRIMARY KEY, valor TEXT NULL)');
    $pdo->exec('CREATE TABLE digisac_messages_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        phone_number TEXT,
        message_type TEXT,
        message_text TEXT,
        prospeccao_id INTEGER NULL,
        digisac_message_id TEXT NULL,
        status TEXT,
        created_at TEXT,
        delivered_at TEXT NULL,
        read_at TEXT NULL
    )');
    $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?, ?)')->execute(['digisac_enabled', '1']);
    $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?, ?)')->execute(['digisac_api_token', 'token']);

    $service = new DigisacService($pdo);
    $formatted = $service->formatPhoneNumber('(11) 98765-4321');
    assertEquals('+5511987654321', $formatted);
}, $tests);

runTest('DigisacService::sendMessage registra log de envio bem sucedido', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE configuracoes (chave TEXT PRIMARY KEY, valor TEXT NULL)');
    $pdo->exec('CREATE TABLE digisac_messages_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        phone_number TEXT,
        message_type TEXT,
        message_text TEXT,
        prospeccao_id INTEGER NULL,
        digisac_message_id TEXT NULL,
        status TEXT,
        created_at TEXT,
        delivered_at TEXT NULL,
        read_at TEXT NULL
    )');

    $insert = $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?, ?)');
    $insert->execute(['digisac_enabled', '1']);
    $insert->execute(['digisac_api_token', 'token']);

    $service = new TestDigisacService($pdo);
    $result = $service->sendMessage('11 91234-5678', 'Teste rápido', null, [
        'user_id' => 5,
        'message_type' => 'unit_test',
        'prospeccao_id' => 9,
    ]);

    assertEquals(true, $result['success']);
    assertEquals('msg_test', $result['message_id']);
    assertEquals(1, count($service->requests));
    assertEquals('+5511912345678', $service->requests[0]['number']);

    $logRow = $pdo->query('SELECT phone_number, message_type, status, digisac_message_id FROM digisac_messages_log')->fetch(PDO::FETCH_ASSOC);
    assertEquals('+5511912345678', $logRow['phone_number']);
    assertEquals('unit_test', $logRow['message_type']);
    assertEquals('sent', $logRow['status']);
    assertEquals('msg_test', $logRow['digisac_message_id']);
}, $tests);

runTest('DigisacService::sendMessage falha quando integração está desativada', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE configuracoes (chave TEXT PRIMARY KEY, valor TEXT NULL)');
    $pdo->exec('CREATE TABLE digisac_messages_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        phone_number TEXT,
        message_type TEXT,
        message_text TEXT,
        prospeccao_id INTEGER NULL,
        digisac_message_id TEXT NULL,
        status TEXT,
        created_at TEXT,
        delivered_at TEXT NULL,
        read_at TEXT NULL
    )');

    $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?, ?)')->execute(['digisac_enabled', '0']);

    $service = new TestDigisacService($pdo);
    $result = $service->sendMessage('11 99999-0000', 'Mensagem', null);

    assertEquals(false, $result['success']);
}, $tests);

foreach ($tests as $test) {
    $prefix = $test['status'] === 'passed' ? '[PASS]' : '[FAIL]';
    echo $prefix . ' ' . $test['name'] . PHP_EOL;
    if ($test['status'] === 'failed' && isset($test['message'])) {
        echo '  ' . $test['message'] . PHP_EOL;
    }
}

$failures = array_filter($tests, static fn (array $test) => $test['status'] !== 'passed');
if (!empty($failures)) {
    exit(1);
}

