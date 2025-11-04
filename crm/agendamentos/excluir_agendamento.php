<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

$redirectTo = isset($_POST['redirect_to']) && $_POST['redirect_to'] !== '' ? $_POST['redirect_to'] : null;

function respondWithError(string $message, ?string $redirectTo): void
{
    if ($redirectTo) {
        $_SESSION['error_message'] = $message;
        header('Location: ' . $redirectTo);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
    }

    exit();
}

function respondWithSuccess(string $message, ?string $redirectTo): void
{
    if ($redirectTo) {
        $_SESSION['success_message'] = $message;
        header('Location: ' . $redirectTo);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $message]);
    }

    exit();
}

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
if ($currentUserId <= 0) {
    respondWithError('Usuário não autenticado.', $redirectTo);
}

$agendamentoId = filter_input(INPUT_POST, 'agendamento_id', FILTER_VALIDATE_INT);
if (!$agendamentoId) {
    respondWithError('Agendamento inválido.', $redirectTo);
}

try {
    $stmt = $pdo->prepare('SELECT id, usuario_id, prospeccao_id, titulo, data_inicio FROM agendamentos WHERE id = :id');
    $stmt->bindValue(':id', $agendamentoId, PDO::PARAM_INT);
    $stmt->execute();
    $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agendamento) {
        respondWithError('Agendamento não encontrado.', $redirectTo);
    }

    $perfisGerenciais = ['admin', 'gerencia', 'supervisor'];
    $usuarioPodeGerenciar = in_array($_SESSION['user_perfil'] ?? '', $perfisGerenciais, true);

    if ((int) $agendamento['usuario_id'] !== $currentUserId && !$usuarioPodeGerenciar) {
        respondWithError('Você não tem permissão para excluir este agendamento.', $redirectTo);
    }

    $pdo->beginTransaction();

    $stmtDelete = $pdo->prepare('DELETE FROM agendamentos WHERE id = :id');
    $stmtDelete->bindValue(':id', $agendamentoId, PDO::PARAM_INT);
    $stmtDelete->execute();

    if (!empty($agendamento['prospeccao_id'])) {
        $dataInicio = new DateTime($agendamento['data_inicio']);
        $descricaoInteracao = sprintf(
            'Agendamento cancelado: %s em %s.',
            $agendamento['titulo'],
            $dataInicio->format('d/m/Y H:i')
        );

        $stmtInteracao = $pdo->prepare(
            'INSERT INTO interacoes (prospeccao_id, usuario_id, observacao, tipo) VALUES (:prospeccao_id, :usuario_id, :observacao, :tipo)'
        );
        $stmtInteracao->execute([
            ':prospeccao_id' => (int) $agendamento['prospeccao_id'],
            ':usuario_id' => $currentUserId,
            ':observacao' => $descricaoInteracao,
            ':tipo' => 'reuniao'
        ]);
    }

    $pdo->commit();

    respondWithSuccess('Agendamento excluído com sucesso.', $redirectTo);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro em excluir_agendamento.php: ' . $exception->getMessage());
    respondWithError('Não foi possível excluir o agendamento.', $redirectTo);
}
