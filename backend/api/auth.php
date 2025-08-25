<?php
// backend/api/auth.php
// API para login e cadastro de usuários

require_once '../config/cors.php';
require_once '../models/Usuario.php';

// Obter ação da URL
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            login();
            break;
            
        case 'register':
            register();
            break;
            
        case 'verificar-email':
            verificarEmail();
            break;
            
        default:
            jsonError('Ação não encontrada', 404);
    }
    
} catch (Exception $e) {
    logError('Erro na API auth: ' . $e->getMessage());
    jsonError('Erro interno do servidor', 500);
}

function login() {
    requirePOST();
    
    $dados = getJsonInput();
    validateRequired($dados, ['email', 'senha']);
    
    $email = sanitizeInput($dados['email']);
    $senha = $dados['senha'];
    
    // Validar formato do email
    if (!isValidEmail($email)) {
        jsonError('Email inválido');
    }
    
    // Validar credenciais
    $usuario = Usuario::validarLogin($email, $senha);
    
    if (!$usuario) {
        jsonError('Email ou senha incorretos', 401);
    }
    
    // Obter estatísticas do usuário
    $estatisticas = Usuario::obterEstatisticas($usuario['id']);
    $posicao_ranking = Usuario::obterPosicaoRanking($usuario['id']);
    
    $response_data = [
        'usuario' => $usuario,
        'estatisticas' => $estatisticas,
        'posicao_ranking' => $posicao_ranking
    ];
    
    jsonSuccess($response_data, 'Login realizado com sucesso');
}

function register() {
    requirePOST();
    
    $dados = getJsonInput();
    validateRequired($dados, ['nome', 'email', 'senha']);
    
    // Sanitizar dados
    $nome = sanitizeInput($dados['nome']);
    $email = sanitizeInput($dados['email']);
    $senha = $dados['senha'];
    $telefone = isset($dados['telefone']) ? sanitizeInput($dados['telefone']) : null;
    
    // Validações
    if (!isValidEmail($email)) {
        jsonError('Email inválido');
    }
    
    if (strlen($senha) < 6) {
        jsonError('Senha deve ter pelo menos 6 caracteres');
    }
    
    if (strlen($nome) < 2) {
        jsonError('Nome deve ter pelo menos 2 caracteres');
    }
    
    // Verificar se email já existe
    if (Usuario::emailExiste($email)) {
        jsonError('Este email já está cadastrado');
    }
    
    // Criar usuário
    $usuario_id = Usuario::criar([
        'nome' => $nome,
        'email' => $email,
        'senha' => $senha,
        'telefone' => $telefone
    ]);
    
    // Buscar dados do usuário criado
    $usuario = Usuario::buscarPorId($usuario_id);
    
    jsonSuccess([
        'usuario' => $usuario,
        'message' => 'Cadastro realizado com sucesso! Você já pode fazer login.'
    ], 'Usuário cadastrado com sucesso');
}

function verificarEmail() {
    requireGET();
    
    $email = $_GET['email'] ?? '';
    
    if (empty($email)) {
        jsonError('Email é obrigatório');
    }
    
    $email = sanitizeInput($email);
    
    if (!isValidEmail($email)) {
        jsonError('Email inválido');
    }
    
    $existe = Usuario::emailExiste($email);
    
    jsonSuccess([
        'email_existe' => $existe,
        'disponivel' => !$existe
    ]);
}
?>