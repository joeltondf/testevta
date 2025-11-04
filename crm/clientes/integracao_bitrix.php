<?php
// Arquivo: crm/clientes/integracao_bitrix.php (VERSÃO CORRIGIDA E INTEGRADA)

// 1. LÓGICA PHP PRIMEIRO
// =================================================================
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

// 2. Verificação de permissão CORRIGIDA
if (!isset($_SESSION['user_perfil']) || !in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'supervisor'])) {
    header("Location: " . APP_URL . "/crm/clientes/lista.php?error=access_denied");
    exit();
}

function find_contact_info($details_array, $priority_type = 'WORK') {
    if (empty($details_array) || !is_array($details_array)) return null;
    foreach ($details_array as $detail) {
        if (isset($detail['VALUE_TYPE']) && $detail['VALUE_TYPE'] === $priority_type) {
            return $detail['VALUE'] ?? null;
        }
    }
    return $details_array[0]['VALUE'] ?? null;
}

$feedback_message = '';
$feedback_type = 'info';
$contacts_from_bitrix = [];
$action = $_POST['action'] ?? 'show_initial_page';

// Ação 1: BUSCAR CONTATOS DA API
if ($action === 'fetch_bitrix') {
    $webhook_url = 'https://b24-nobgfj.bitrix24.com.br/rest/12/g0bqz57q4diohl3r/'; // Mantenha sua URL
    $all_contacts = [];
    $start = 0;
    $max_pages_to_fetch = 20;

    for ($i = 0; $i < $max_pages_to_fetch; $i++) {
        $select_fields = ['ID', 'NAME', 'LAST_NAME', 'COMPANY_TITLE', 'PHONE', 'EMAIL'];
        $query_params = http_build_query([
            'order'  => ['ID' => 'DESC'],
            'select' => $select_fields,
            'start'  => $start
        ]);
        $api_url = $webhook_url . 'crm.contact.list' . '?' . $query_params;

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);

        if (isset($result['result']) && !empty($result['result'])) {
            $all_contacts = array_merge($all_contacts, $result['result']);
        }
        if (isset($result['next'])) {
            $start = $result['next'];
        } else {
            break; 
        }
    }
    
    if (!empty($all_contacts)) {
        $_SESSION['bitrix_import_data'] = $all_contacts;
        $contacts_from_bitrix = $all_contacts;
    } else {
        $feedback_message = "Nenhum lead encontrado no Bitrix24 ou erro na API.";
        $feedback_type = 'info';
    }
}

// Ação 2: IMPORTAR OS CONTATOS SELECIONADOS
if ($action === 'import_selected') {
    $selected_contacts_ids = $_POST['contact_ids'] ?? [];

    if (empty($selected_contacts_ids)) {
        $feedback_message = "Nenhum lead foi selecionado para importação.";
        $feedback_type = 'error';
        $contacts_from_bitrix = $_SESSION['bitrix_import_data'] ?? [];
    } else {
        $all_contacts_from_session = $_SESSION['bitrix_import_data'] ?? [];
        $contacts_to_import = array_filter($all_contacts_from_session, function($contact) use ($selected_contacts_ids) {
            return in_array($contact['ID'], $selected_contacts_ids);
        });

        $pdo->beginTransaction();
        $count_adicionados = 0;
        $count_ignorados = 0;

        foreach ($contacts_to_import as $contact) {
            $nome_responsavel = trim(($contact['NAME'] ?? '') . ' ' . ($contact['LAST_NAME'] ?? ''));
            $nome_cliente = !empty($contact['COMPANY_TITLE']) ? $contact['COMPANY_TITLE'] : $nome_responsavel;
            $email = find_contact_info($contact['EMAIL'] ?? [], 'WORK');
            $telefone = find_contact_info($contact['PHONE'] ?? [], 'WORK');
            
            if (empty(trim($nome_cliente))) continue;

            try {
                if (!empty($email)) {
                    $stmt_check = $pdo->prepare("SELECT id FROM clientes WHERE email = ?");
                    $stmt_check->execute([$email]);
                    if ($stmt_check->fetch()) {
                        $count_ignorados++;
                        continue;
                    }
                }
                
                // 3. Consulta INSERT CORRIGIDA
                $sql = "INSERT INTO clientes (nome_cliente, nome_responsavel, email, telefone, canal_origem, categoria, is_prospect) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$nome_cliente, $nome_responsavel, $email, $telefone, 'Bitrix24', 'Entrada', 1]);
                $count_adicionados++;

            } catch (PDOException $e) {
                $pdo->rollBack();
                die("Erro ao inserir dados: " . $e->getMessage());
            }
        }
        
        $pdo->commit();
        $feedback_message = "Importação concluída! <strong>{$count_adicionados}</strong> novos leads importados. <strong>{$count_ignorados}</strong> leads ignorados (já existiam).";
        $feedback_type = 'success';
        
        unset($_SESSION['bitrix_import_data']);
    }
}

// 4. HTML DEPOIS
// =================================================================
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
    <div class="md:flex md:items-center md:justify-between border-b border-gray-200 pb-4 mb-6">
        <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
            Importar Leads do Bitrix24
        </h1>
    </div>

    <?php if (!empty($feedback_message)): ?>
        <?php $colors = ['success' => 'bg-green-100 border-green-400 text-green-700', 'error' => 'bg-red-100 border-red-400 text-red-700', 'info' => 'bg-blue-100 border-blue-400 text-blue-700']; ?>
        <div class="mb-6 p-4 rounded-lg border <?php echo $colors[$feedback_type]; ?>" role="alert"><?php echo $feedback_message; ?></div>
    <?php endif; ?>

    <?php if (empty($contacts_from_bitrix)): ?>
        <div class="text-center">
            <p class="text-gray-600 mb-4">Clique no botão para buscar os leads do Bitrix24 e visualizá-los antes de importar.</p>
            <form action="<?php echo APP_URL; ?>/crm/clientes/integracao_bitrix.php" method="POST">
                <input type="hidden" name="action" value="fetch_bitrix">
                <button type="submit" class="bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 text-lg shadow-lg">
                    Buscar Leads do Bitrix24
                </button>
            </form>
        </div>
    <?php else: ?>
        <form action="<?php echo APP_URL; ?>/crm/clientes/integracao_bitrix.php" method="POST">
            </form>
    <?php endif; ?>
</div>

<script>
function toggleSelectAll(source) {
    const checkboxes = document.querySelectorAll('.contact-checkbox');
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}
</script>

<?php 
require_once __DIR__ . '/../../app/views/layouts/footer.php'; 
?>