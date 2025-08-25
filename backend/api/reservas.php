<?php
require_once '../config/cors.php';
require_once '../models/Reservas.php';

// Obter ação da URL
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'criar':
        criarReserva();
        break;
    // Outras ações...
    default:
        jsonError('Ação não encontrada', 404);
}

function criarReserva() {
    requirePOST();
    $dados = getJsonInput();
    validateRequired($dados, ['usuario_id', 'mesa_id', 'data_reserva', 'horario', 'quantidade_pessoas']);

    // Chame o método do model para criar a reserva
    $reservaId = Reservas::criar($dados);
    if ($reservaId) {
        jsonSuccess(['reserva_id' => $reservaId], 'Reserva criada com sucesso');
    } else {
        jsonError('Erro ao criar reserva');
    }
}
?>