<?php
// /migracao.php
// ATENÇÃO: Coloque este ficheiro na pasta raiz do seu projeto.
// Após a utilização, APAGUE este ficheiro do servidor por motivos de segurança.

set_time_limit(0);
ini_set('memory_limit', '512M');

echo "<pre>";

// --- 1. CONFIGURAÇÕES ---
require_once __DIR__ . '/config.php';

define('WP_DB_HOST', 'localhost');
define('WP_DB_NAME', 'u371107598_0v1Nw');
define('WP_DB_USER', 'u371107598_rAy2o');
define('WP_DB_PASS', '@Amora051307');

$statusMap = [
    'status-aberto'   => 'Serviço em Andamento',
    'status-fechado'  => 'Concluído',
    'arquivado'       => 'Cancelado',
];
$categoryMap = [
    '19' => 'Tradução',
    '20' => 'CRC',
    '21' => 'Apostilamento',
    '72' => 'Postagem',
];
$prazoTipoMap = ['colocar-data' => 'data', 'colocar-dia' => 'dias'];


// --- 2. FUNÇÕES AUXILIARES ---
function connectToDb($host, $name, $user, $pass) { try { $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8", $user, $pass); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); echo "Conexão com a base de dados '{$name}' bem-sucedida.\n"; return $pdo; } catch (PDOException $e) { die("Erro na conexão com a base de dados '{$name}': " . $e->getMessage()); } }
function cleanCurrency($value) { return empty($value) ? null : (float) trim(str_replace(',', '.', str_replace('.', '', str_replace('R$', '', $value)))); }
function getPostMeta($pdo, $postId) { $stmt = $pdo->prepare("SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ?"); $stmt->execute([$postId]); $metaArray = []; foreach ($stmt->fetchAll() as $meta) { $metaArray[$meta['meta_key']] = $meta['meta_value']; } return $metaArray; }
function findVendedorIdByName($pdo, $nome) { if (empty($nome)) return null; $stmt = $pdo->prepare("SELECT v.id FROM vendedores v JOIN users u ON v.user_id = u.id WHERE u.nome_completo = ?"); $stmt->execute([$nome]); return $stmt->fetchColumn() ?: null; }
function findTradutorIdByOldTermId($pdoNovo, $pdoAntigo, $termId) { if (empty($termId)) return null; $stmtAntigo = $pdoAntigo->prepare("SELECT name FROM wp_terms WHERE term_id = ?"); $stmtAntigo->execute([$termId]); $nomeTradutor = $stmtAntigo->fetchColumn(); if (!$nomeTradutor) return null; $stmtNovo = $pdoNovo->prepare("SELECT id FROM tradutores WHERE nome_tradutor = ?"); $stmtNovo->execute([$nomeTradutor]); return $stmtNovo->fetchColumn() ?: null; }
function findParentPostId($pdoAntigo, $childPostId) { $relationTable = 'wp_jet_rel_default'; try { $stmt = $pdoAntigo->prepare("SELECT parent_object_id FROM {$relationTable} WHERE child_object_id = ?"); $stmt->execute([$childPostId]); return $stmt->fetchColumn() ?: null; } catch (PDOException $e) { echo "AVISO: Tabela '{$relationTable}' não encontrada. Erro: " . $e->getMessage() . "\n"; return null; } }
function checkUserExists($pdo, $userId) { $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?"); $stmt->execute([$userId]); return $stmt->fetchColumn() ? true : false; }


// --- 3. PRÉ-MIGRAÇÃO: POPULAR DADOS-CHAVE ---
function migrateTranslators($pdoNovo, $pdoAntigo) { echo "\n--- Iniciando migração de Tradutores ---\n"; $stmtTax = $pdoAntigo->prepare("SELECT t.term_id, t.name FROM wp_terms t INNER JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id WHERE tt.taxonomy = 'tradutores'"); $stmtTax->execute(); $tradutoresAntigos = $stmtTax->fetchAll(); if (empty($tradutoresAntigos)) { echo "Nenhum tradutor encontrado na taxonomia 'tradutores'.\n"; return; } $stmtInsert = $pdoNovo->prepare("INSERT INTO tradutores (nome_tradutor, ativo) VALUES (?, 1)"); $stmtCheck = $pdoNovo->prepare("SELECT id FROM tradutores WHERE nome_tradutor = ?"); $count = 0; foreach ($tradutoresAntigos as $tradutor) { $stmtCheck->execute([$tradutor['name']]); if ($stmtCheck->fetch()) continue; $stmtInsert->execute([$tradutor['name']]); $count++; } echo "{$count} novos tradutores migrados com sucesso.\n"; }


// --- 4. EXECUÇÃO DA MIGRAÇÃO PRINCIPAL ---
try {
    $pdoNovo = $pdo;
    $pdoAntigo = connectToDb(WP_DB_HOST, WP_DB_NAME, WP_DB_USER, WP_DB_PASS);
    $pdoNovo->beginTransaction();

    migrateTranslators($pdoNovo, $pdoAntigo);

    $postTypesToFetch = ['assessoria', 'servico-familia'];
    $placeholders = implode(',', array_fill(0, count($postTypesToFetch), '?'));
    $sqlFetch = "SELECT * FROM wp_posts WHERE post_type IN ($placeholders) AND post_status NOT IN ('auto-draft', 'trash')";
    
    echo "\n--- Buscando 'assessoria' e 'servico-familia' do WordPress... ---\n";
    $stmtPosts = $pdoAntigo->prepare($sqlFetch);
    $stmtPosts->execute($postTypesToFetch);
    $allPosts = $stmtPosts->fetchAll();
    echo count($allPosts) . " registros encontrados.\n";

    echo "\n--- Processando 'assessoria' como Clientes... ---\n";
    $assessoriaToClienteMap = []; $clientesInseridos = 0;
    foreach ($allPosts as $post) { if ($post['post_type'] === 'assessoria') { $meta = getPostMeta($pdoAntigo, $post['ID']); $nomeCliente = $post['post_title']; $stmtCheck = $pdoNovo->prepare("SELECT id FROM clientes WHERE nome_cliente = ?"); $stmtCheck->execute([$nomeCliente]); $clienteId = $stmtCheck->fetchColumn(); if (!$clienteId) { $stmtInsert = $pdoNovo->prepare("INSERT INTO clientes (nome_cliente, nome_responsavel, email, telefone, data_cadastro, is_prospect) VALUES (?, ?, ?, ?, ?, ?)"); $stmtInsert->execute([$nomeCliente, $meta['responsavel'] ?? null, $meta['e-mail'] ?? null, $meta['telefone'] ?? null, $post['post_date'], 0]); $clienteId = $pdoNovo->lastInsertId(); $clientesInseridos++; } $assessoriaToClienteMap[$post['ID']] = $clienteId; } }
    echo "{$clientesInseridos} novos clientes criados.\n";

    echo "\n--- Processando 'servico-familia' como Processos... ---\n";
    $processosInseridos = 0; $documentosInseridos = 0;

    foreach ($allPosts as $post) {
        if ($post['post_type'] === 'servico-familia') {
            $oldPostId = $post['ID'];
            echo "--------------------------------------------------\n";
            echo "Processando Servico-Familia ID: {$oldPostId}\n";
            
            $meta = getPostMeta($pdoAntigo, $oldPostId);
            $parentAssessoriaId = findParentPostId($pdoAntigo, $oldPostId);
            $clienteId = $assessoriaToClienteMap[$parentAssessoriaId] ?? null;

            if (!$clienteId) { echo "AVISO: Nenhum cliente (assessoria) relacionado encontrado para o processo {$oldPostId}. O processo não será migrado.\n"; continue; }
            echo ">>> Cliente relacionado encontrado: ID {$clienteId} (via Assessoria ID {$parentAssessoriaId})\n";

            $colaboradorId = $meta['colaborador-id'] ?? 1;
            if (!checkUserExists($pdoNovo, $colaboradorId)) { echo "AVISO: Colaborador ID {$colaboradorId} do sistema antigo não existe no novo. Usando ID 1 como fallback.\n"; $colaboradorId = 1; }

            $categoriasServico = [];
            $unserializedCats = @unserialize($meta['jet_tax__tipos-de-servico'] ?? '');
            if (is_array($unserializedCats)) {
                foreach ($unserializedCats as $catId) {
                    $categoriasServico[] = $categoryMap[$catId] ?? 'Outros';
                }
            }

            // ==================================================================
            // AJUSTES APLICADOS AQUI
            // ==================================================================
            $dadosProcesso = [
                'cliente_id' => $clienteId,
                'colaborador_id' => $colaboradorId,
                'vendedor_id' => findVendedorIdByName($pdoNovo, $meta['orc-vendedor'] ?? null),
                'tradutor_id' => findTradutorIdByOldTermId($pdoNovo, $pdoAntigo, $meta['jet_tax__tradutores'] ?? null),
                'titulo' => ucwords(str_replace('-', ' ', $post['post_name'] ?? '')),
                'status_processo' => $statusMap[$meta['status-id'] ?? ''] ?? 'Orçamento',
                'categorias_servico' => implode(',', $categoriasServico),
                'observacoes' => $meta['orc-observacao'] ?? null,
                'data_criacao' => $post['post_date'],
                'data_atualizacao' => $post['post_modified'],
                
                // --- CAMPOS MAPEADOS ANTERIORMENTE ---
                'orcamento_origem' => $meta['crc-origem-orcamento'] ?? null,
                'modalidade_assinatura' => $meta['orc-idioma-traducao'] ?? null,
                'data_inicio_traducao' => !empty($meta['data-de-envio']) ? $meta['data-de-envio'] : null,
                'traducao_prazo_tipo' => $prazoTipoMap[$meta['tipo-de-prazo'] ?? ''] ?? 'dias',
                'traducao_prazo_dias' => $meta['soma-data-cliente'] ?? null,
                'traducao_prazo_data' => !empty($meta['contador']) ? $meta['contador'] : null,
                'data_previsao_entrega' => !empty($meta['soma-data-cliente_data']) ? $meta['soma-data-cliente_data'] : null,

                // --- NOVOS CAMPOS MAPEADOS ---
                'data_envio_assinatura' => !empty($meta['envio-da-traducao']) ? $meta['envio-da-traducao'] : null,
                'data_envio_cartorio' => !empty($meta['data-de-envio-para-cartorio']) ? $meta['data-de-envio-para-cartorio'] : null,

                // --- CAMPOS ANTIGOS MANTIDOS ---
                'os_numero_conta_azul' => $meta['ordem-de-servico'] ?? null,
                'orcamento_forma_pagamento' => $meta['orc-forma-de-pagamento'] ?? null,
                'orcamento_parcelas' => $meta['orc-parcelas'] ?? null,
                'orcamento_valor_entrada' => cleanCurrency($meta['orc-valor-entrada'] ?? null),
                'orcamento_valor_restante' => cleanCurrency($meta['orc-valor-restante'] ?? null),
                'orcamento_comprovante_url' => $meta['orc-comprovante-pagamento'] ?? null,
                'data_devolucao_assinatura' => !empty($meta['data-de-devolucao_trad']) ? $meta['data-de-devolucao_trad'] : null,
                'valor_total' => cleanCurrency($meta['orc-valor-total'] ?? null),
            ];
            
            // Query de inserção ATUALIZADA com os novos campos
            $sqlProcesso = "INSERT INTO processos (
                                cliente_id, colaborador_id, vendedor_id, tradutor_id, titulo, status_processo, 
                                categorias_servico, observacoes, data_criacao, data_atualizacao, orcamento_origem, 
                                modalidade_assinatura, data_inicio_traducao, traducao_prazo_tipo, traducao_prazo_dias, 
                                traducao_prazo_data, data_previsao_entrega, os_numero_conta_azul, orcamento_forma_pagamento, 
                                orcamento_parcelas, orcamento_valor_entrada, orcamento_valor_restante, 
                                orcamento_comprovante_url, data_devolucao_assinatura, valor_total,
                                data_envio_assinatura, data_envio_cartorio
                            ) VALUES (
                                :cliente_id, :colaborador_id, :vendedor_id, :tradutor_id, :titulo, :status_processo,
                                :categorias_servico, :observacoes, :data_criacao, :data_atualizacao, :orcamento_origem,
                                :modalidade_assinatura, :data_inicio_traducao, :traducao_prazo_tipo, :traducao_prazo_dias,
                                :traducao_prazo_data, :data_previsao_entrega, :os_numero_conta_azul, :orcamento_forma_pagamento,
                                :orcamento_parcelas, :orcamento_valor_entrada, :orcamento_valor_restante,
                                :orcamento_comprovante_url, :data_devolucao_assinatura, :valor_total,
                                :data_envio_assinatura, :data_envio_cartorio
                            )";

            $stmtNovoProcesso = $pdoNovo->prepare($sqlProcesso);
            $stmtNovoProcesso->execute($dadosProcesso);
            $novoProcessoId = $pdoNovo->lastInsertId();
            $processosInseridos++;
            echo ">>> Processo inserido no novo sistema com ID: {$novoProcessoId}\n";

            if (!empty($meta['orc-documento-traducao'])) {
                $documentosTraducao = @unserialize($meta['orc-documento-traducao']);
                if (is_array($documentosTraducao)) {
                    $stmtNovoDocumento = $pdoNovo->prepare("INSERT INTO documentos (processo_id, categoria, tipo_documento, nome_documento, valor_unitario) VALUES (?, ?, ?, ?, ?)");
                    foreach ($documentosTraducao as $doc) {
                        if (!empty($doc['orc-tipo-doc-traducao'])) {
                            $valorUnitario = cleanCurrency($doc['orc-repeatvalor-trad'] ?? null);
                            $stmtNovoDocumento->execute([$novoProcessoId, 'Tradução', $doc['orc-tipo-doc-traducao'], $doc['orc-repeat-nome-trad'], $valorUnitario]);
                            $documentosInseridos++;
                        }
                    }
                }
            }
        }
    }

    $pdoNovo->commit();
    echo "\n\n--- MIGRAÇÃO CONCLUÍDA COM SUCESSO! ---\n";
    echo "Total de Clientes criados: {$clientesInseridos}\n";
    echo "Total de Processos migrados: {$processosInseridos}\n";
    echo "Total de Documentos migrados: {$documentosInseridos}\n";

} catch (Exception $e) {
    if ($pdoNovo->inTransaction()) {
        $pdoNovo->rollBack();
    }
    die("\n--- ERRO NA MIGRAÇÃO! --- \nOperação cancelada.\n\nDetalhes do erro: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}

echo "</pre>";
?>