<?php
// backend/config/cors.php
// Configuração de CORS para permitir requisições do frontend

// Headers CORS para desenvolvimento local
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 3600');
header('Content-Type: application/json; charset=UTF-8');

// Responder a requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Função para retornar resposta JSON padronizada
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// Função para retornar erro padronizado
function jsonError($message, $status = 400, $details = null) {
    $response = [
        'success' => false,
        'error' => $message
    ];
    
    if ($details !== null) {
        $response['details'] = $details;
    }
    
    jsonResponse($response, $status);
}

// Função para retornar sucesso padronizado
function jsonSuccess($data = [], $message = 'Operação realizada com sucesso') {
    $response = [
        'success' => true,
        'message' => $message,
        'data' => $data
    ];
    
    jsonResponse($response, 200);
}

// Função para validar se é uma requisição POST
function requirePOST() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Método não permitido. Use POST.', 405);
    }
}

// Função para validar se é uma requisição GET
function requireGET() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonError('Método não permitido. Use GET.', 405);
    }
}

// Função para obter dados JSON do corpo da requisição
function getJsonInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonError('JSON inválido no corpo da requisição');
    }
    
    return $data ?? [];
}

// Função para validar campos obrigatórios
function validateRequired($data, $required_fields) {
    $missing = [];
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        jsonError('Campos obrigatórios ausentes: ' . implode(', ', $missing), 400);
    }
}

// Função para sanitizar entrada
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Função para validar email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Função para validar data (formato Y-m-d)
function isValidDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// Função para validar horário (formato H:i)
function isValidTime($time) {
    $t = DateTime::createFromFormat('H:i', $time);
    return $t && $t->format('H:i') === $time;
}

// Função para log de erros
function logError($message, $context = []) {
    $log_message = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($context)) {
        $log_message .= ' - Context: ' . json_encode($context);
    }
    error_log($log_message);
}
?>