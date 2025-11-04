<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
if ($currentUserId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit();
}

$appointmentId = filter_input(INPUT_POST, 'agendamento_id', FILTER_VALIDATE_INT);
$title = trim($_POST['titulo'] ?? '');
$startRaw = $_POST['data_inicio'] ?? '';
$endRaw = $_POST['data_fim'] ?? '';
$status = trim($_POST['status'] ?? 'Confirmado');

if (!$appointmentId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Agendamento inválido.']);
    exit();
}

if ($title === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Informe o título do agendamento.']);
    exit();
}

$startAt = DateTime::createFromFormat('Y-m-d\TH:i', $startRaw) ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $startRaw);
$endAt = DateTime::createFromFormat('Y-m-d\TH:i', $endRaw) ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $endRaw);

if (!$startAt || !$endAt || $endAt <= $startAt) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Datas inválidas.']);
    exit();
}

try {
    $stmt = $pdo->prepare('SELECT id, usuario_id, prospeccao_id FROM agendamentos WHERE id = :id');
    $stmt->bindValue(':id', $appointmentId, PDO::PARAM_INT);
    $stmt->execute();
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado.']);
        exit();
    }

    $allowedProfiles = ['admin', 'gerencia', 'supervisor'];
    $canEdit = (int) $appointment['usuario_id'] === $currentUserId || in_array($_SESSION['user_perfil'] ?? '', $allowedProfiles, true);

    if (!$canEdit) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para editar este agendamento.']);
        exit();
    }

    $stmtUpdate = $pdo->prepare(
        'UPDATE agendamentos
         SET titulo = :titulo,
             data_inicio = :data_inicio,
             data_fim = :data_fim,
             status = :status
         WHERE id = :id'
    );
    $stmtUpdate->execute([
        ':titulo' => $title,
        ':data_inicio' => $startAt->format('Y-m-d H:i:s'),
        ':data_fim' => $endAt->format('Y-m-d H:i:s'),
        ':status' => $status,
        ':id' => $appointmentId
    ]);

    if (!empty($appointment['prospeccao_id'])) {
        $stmtInteraction = $pdo->prepare(
            'INSERT INTO interacoes (prospeccao_id, usuario_id, observacao, tipo)
             VALUES (:prospeccao_id, :usuario_id, :observacao, :tipo)'
        );
        $description = sprintf(
            'Agendamento atualizado: %s em %s.',
            $title,
            $startAt->format('d/m/Y H:i')
        );
        $stmtInteraction->execute([
            ':prospeccao_id' => (int) $appointment['prospeccao_id'],
            ':usuario_id' => $currentUserId,
            ':observacao' => $description,
            ':tipo' => 'reuniao'
        ]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $exception) {
    http_response_code(500);
    error_log('Erro em editar_agendamento.php: ' . $exception->getMessage());
    echo json_encode(['success' => false, 'message' => 'Não foi possível atualizar o agendamento.']);
}
