<?php
/**
 * API Reservas - CRUD completo para sistema de reservas
 * Sistema de Restaurante Gamificado
 */

require_once '../config/cors.php';
require_once '../models/Reservas.php';

// Ativar exibição de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar método HTTP
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Função para verificar autenticação básica
function verificarAuth() {
    // Aqui você implementaria a verificação do token/sessão
    // Por simplicidade, vamos apenas verificar se foi enviado um user_id
    $input = getJsonInput();
    if (!isset($input['usuario_id']) && !isset($_GET['usuario_id'])) {
        jsonError('Usuário não autenticado', 401);
        exit;
    }
}

switch ($action) {
    
    case 'criar':
        if ($method !== 'POST') {
            jsonError('Método não permitido', 405);
            break;
        }
        
        $dados = getJsonInput();
        
        // Validar campos obrigatórios
        $campos_obrigatorios = ['usuario_id', 'mesa_id', 'data_reserva', 'horario', 'quantidade_pessoas'];
        $validacao = validateRequired($dados, $campos_obrigatorios);
        
        if (!$validacao['valid']) {
            jsonError('Campos obrigatórios: ' . implode(', ', $validacao['missing']), 400);
            break;
        }
        
        // Sanitizar dados
        $dados = sanitizeInput($dados);
        
        // Validações adicionais
        $errors = [];
        
        // Validar data (não pode ser no passado)
        if (strtotime($dados['data_reserva']) < strtotime(date('Y-m-d'))) {
            $errors[] = 'Data da reserva não pode ser no passado';
        }
        
        // Validar horário (formato HH:MM)
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $dados['horario'])) {
            $errors[] = 'Formato de horário inválido (use HH:MM)';
        }
        
        // Validar quantidade de pessoas
        if (!is_numeric($dados['quantidade_pessoas']) || $dados['quantidade_pessoas'] < 1 || $dados['quantidade_pessoas'] > 20) {
            $errors[] = 'Quantidade de pessoas deve ser entre 1 e 20';
        }
        
        // Validar horário de funcionamento (ex: 11:00 às 23:00)
        $hora_abertura = '11:00';
        $hora_fechamento = '23:00';
        if ($dados['horario'] < $hora_abertura || $dados['horario'] > $hora_fechamento) {
            $errors[] = "Horário deve ser entre $hora_abertura e $hora_fechamento";
        }
        
        if (!empty($errors)) {
            jsonError(implode('; ', $errors), 400);
            break;
        }
        
        $resultado = Reserva::criar($dados);
        
        if ($resultado['success']) {
            jsonSuccess($resultado['data'], 'Reserva criada com sucesso! Você ganhou 50 pontos!');
        } else {
            jsonError($resultado['error'], 400);
        }
        break;
        
    case 'buscar':
        if ($method !== 'GET') {
            jsonError('Método não permitido', 405);
            break;
        }
        
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            jsonError('ID da reserva é obrigatório', 400);
            break;
        }
        
        $reserva = Reserva::buscarPorId($id);
        
        if ($reserva) {
            jsonSuccess($reserva, 'Reserva encontrada');
        } else {
            jsonError('Reserva não encontrada', 404);
        }
        break;
        
    case 'minhas':
        if ($method !== 'GET') {
            jsonError('Método não permitido', 405);
            break;
        }
        
        $usuario_id = $_GET['usuario_id'] ?? null;
        $status = $_GET['status'] ?? null;
        
        if (!$usuario_id) {
            jsonError('ID do usuário é obrigatório', 400);
            break;
        }
        
        $reservas = Reserva::buscarPorUsuario($usuario_id, $status);
        
        jsonSuccess($reservas, 'Reservas encontradas');
        break;
        
    case 'proximas':
        if ($method !== 'GET') {
            jsonError('Método não permitido', 405);
            break;
        }
        
        $usuario_id = $_GET['usuario_id'] ?? null;
        
        if (!$usuario_id) {
            jsonError('ID do usuário é obrigatório', 400);
            break;
        }
        
        $reservas = Reserva::buscarProximas($usuario_id);
        
        jsonSuccess($reservas, 'Próximas reservas encontradas');
        break;
        
    case 'atualizar':
        if ($method !== 'PUT') {
            jsonError('Método não permitido', 405);
            break;
        }
        
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            jsonError('ID da reserva é obrigatório', 400);
            break;
        }
        
        $dados = getJsonInput();
        $dados = sanitizeInput($dados);
        
        // Validações condicionais
        $errors = [];
        
        if (isset($dados['data_reserva']) && strtotime($dados['data_reserva']) < strtotime(date('Y-m-d'))) {
            $errors[] = 'Data da reserva não pode ser no passado';
        }
        
        if (isset($dados['horario']) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $dados['horario'])) {
            $errors[] = 'Formato de horário inválido (use HH:MM)';
        }
        
        if (isset($dados['quantidade_pessoas']) && (!is_numeric($dados['quantidade_pessoas']) || $dados['quantidade_pessoas'] < 1 || $dados['quantidade_pessoas'] > 20)) {
            $errors[] = 'Quantidade de pessoas deve ser entre 1 e 20';
        }
        
        if (isset($dados['horario'])) {
            $hora_abertura = '11:00';
            $hora_fechamento = '23:00';
            if ($dados['horario'] < $hora_abertura || $dados['horario'] > $hora_fechamento) {
                $errors[] = "Horário deve ser entre $hora_abertura e $hora_fechamento";
            }
        }
        
        if (!empty($errors)) {
            jsonError(implode('; ', $errors), 400);
            break;
        }
        
        $resultado = Reserva::atualizar($id, $dados);
        
        if ($resultado['success']) {
            jsonSuccess($resultado['data'], 'Reserva atualizada com sucesso');
        } else {
            jsonError($resultado['error'], 400);
        }
        break;
        
    case 'cancelar':
        if ($method !== 'DELETE' && $method !== 'PUT') {
            jsonError('Método não permitido', 405);
            break;
        }
        
        $id = $_GET['id'] ?? null;
        $usuario_id = $_GET['usuario_id'] ?? null;
        
        if (!$id) {
            jsonError('ID da reserva é obrigatório', 400);
            break;
        }
        
        $resultado = Reserva::cancelar($id, $usuario_id);
        
        if ($resultado['success']) {
            jsonSuccess(null, $resultado['message']);
        } else {
            jsonError($resultado['error'], 400);
        }
        break;
        
    case 'finalizar':
        if ($method !== 'PUT') {
            jsonError('Método não permitido', 405);
            break;
        }
        
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            jsonError('ID da reserva é obrigatório', 400);
            break;
        }
        
        $resultado = Reserva::finalizar($id);
        
        if ($resultado['success']) {
            jsonSuccess($resultado['data'], 'Reserva finalizada com sucesso');
        } else {
            jsonError($resultado['error'], 400);
        }
        break;
        
    case 'mesas-disponiveis':
        if ($method !== 'GET') {
            jsonError('Método não permitido', 405);
        }
        $data = $_GET['data'] ?? null;
        $horario = $_GET['horario'] ?? null;
        $quantidade_pessoas = $_GET['quantidade_pessoas'] ?? null;
        if (!$data || !$horario || !$quantidade_pessoas) {
            jsonError('Parâmetros obrigatórios não informados');
        }
        $mesas = Reserva::buscarMesasDisponiveis($data, $horario, $quantidade_pessoas);
        jsonSuccess($mesas);
        break;
        
    case 'estatisticas':
        if ($method !== 'GET') {
            jsonError('Método não permitido', 405);
            break;
        }
        
        $usuario_id = $_GET['usuario_id'] ?? null;
        
        if (!$usuario_id) {
            jsonError('ID do usuário é obrigatório', 400);
            break;
        }
        
        $stats = Reserva::obterEstatisticas($usuario_id);
        
        jsonSuccess($stats, 'Estatísticas obtidas');
        break;
        
    default:
        jsonError('Ação não encontrada. Ações disponíveis: criar, buscar, minhas, proximas, atualizar, cancelar, finalizar, mesas-disponiveis, estatisticas', 404);
        break;
}
?>