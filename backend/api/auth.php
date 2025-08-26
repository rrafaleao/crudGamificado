<?php
// backend/api/auth.php
// Sistema de autenticação - Login, Cadastro, Validação de token

require_once '../config/database.php';

// Headers CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Tratar requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Classe para gerenciar autenticação
class AuthManager {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    // Registrar novo usuário
    public function register($dados) {
        try {
            // Validação dos dados
            $this->validarDadosRegistro($dados);
            
            // Verificar se email já existe
            if ($this->emailJaExiste($dados['email'])) {
                throw new Exception("Este email já está em uso");
            }
            
            // Hash da senha
            $senhaHash = password_hash($dados['senha'], PASSWORD_DEFAULT);
            
            // Inserir usuário no banco
            $sql = "INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $dados['nome'],
                $dados['email'],
                $senhaHash
            ]);
            
            $userId = $this->db->lastInsertId();
            
            // Buscar dados completos do usuário
            $usuario = $this->buscarUsuarioPorId($userId);
            
            // Gerar token JWT
            $token = $this->gerarToken($usuario);
            
            return [
                'success' => true,
                'message' => 'Usuário registrado com sucesso',
                'data' => [
                    'user' => $this->formatarUsuario($usuario),
                    'token' => $token
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Fazer login
    public function login($dados) {
        try {
            // Validação básica
            if (empty($dados['email']) || empty($dados['senha'])) {
                throw new Exception("Email e senha são obrigatórios");
            }
            
            // Buscar usuário por email
            $usuario = $this->buscarUsuarioPorEmail($dados['email']);
            
            // Verificar senha
            if (!password_verify($dados['senha'], $usuario['senha'])) {
                throw new Exception("Email ou senha incorretos");
            }
            
            // Gerar token JWT
            $token = $this->gerarToken($usuario);
            
            return [
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'data' => [
                    'user' => $this->formatarUsuario($usuario),
                    'token' => $token
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Validar token
    public function validate($token) {
        try {
            $payload = $this->validarToken($token);
            $usuario = $this->buscarUsuarioPorId($payload['user_id']);
            
            if (!$usuario) {
                throw new Exception("Token inválido");
            }
            
            return [
                'success' => true,
                'data' => [
                    'user' => $this->formatarUsuario($usuario)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Logout (por enquanto apenas retorna sucesso)
    public function logout() {
        return [
            'success' => true,
            'message' => 'Logout realizado com sucesso'
        ];
    }
    
    // Métodos privados de validação e utilidade
    private function validarDadosRegistro($dados) {
        $erros = [];
        
        if (empty($dados['nome']) || strlen($dados['nome']) < 2) {
            $erros[] = "Nome deve ter pelo menos 2 caracteres";
        }
        
        if (empty($dados['email']) || !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = "Email válido é obrigatório";
        }
        
        if (empty($dados['senha']) || strlen($dados['senha']) < 6) {
            $erros[] = "Senha deve ter pelo menos 6 caracteres";
        }
        
        if (!empty($erros)) {
            throw new Exception(implode(", ", $erros));
        }
    }
    
    private function emailJaExiste($email) {
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
    
    private function buscarUsuarioPorEmail($email) {
        $sql = "SELECT * FROM usuarios WHERE email = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    private function buscarUsuarioPorId($id) {
        $sql = "SELECT * FROM usuarios WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    private function formatarUsuario($usuario) {
        return [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'pontos_mes_atual' => (int)$usuario['pontos_mes_atual'],
            'pontos_total' => (int)$usuario['pontos_total']
        ];
    }
    
    // Gerar token JWT simples (para produção, use uma lib como Firebase JWT)
    private function gerarToken($usuario) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $usuario['id'],
            'email' => $usuario['email'],
            'exp' => time() + (24 * 60 * 60) // 24 horas
        ]);
        
        $headerEncoded = base64url_encode($header);
        $payloadEncoded = base64url_encode($payload);
        
        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, 'sua_chave_secreta_aqui', true);
        $signatureEncoded = base64url_encode($signature);
        
        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }
    
    // Validar token JWT
    private function validarToken($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            throw new Exception("Token inválido");
        }
        
        list($header, $payload, $signature) = $parts;
        
        // Verificar assinatura
        $expectedSignature = base64url_encode(hash_hmac('sha256', $header . "." . $payload, 'sua_chave_secreta_aqui', true));
        
        if (!hash_equals($expectedSignature, $signature)) {
            throw new Exception("Token inválido");
        }
        
        // Decodificar payload
        $payloadData = json_decode(base64url_decode($payload), true);
        
        // Verificar expiração
        if ($payloadData['exp'] < time()) {
            throw new Exception("Token expirado");
        }
        
        return $payloadData;
    }
}

// Função helper para base64url
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

// Função helper para obter dados JSON do corpo da requisição
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

// Processar a requisição
try {
    $authManager = new AuthManager();
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($action) {
        case 'register':
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            $dados = getJsonInput();
            $resultado = $authManager->register($dados);
            break;
            
        case 'login':
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            $dados = getJsonInput();
            $resultado = $authManager->login($dados);
            break;
            
        case 'validate':
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            // Obter token do header Authorization
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            
            if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                throw new Exception('Token não fornecido');
            }
            
            $token = $matches[1];
            $resultado = $authManager->validate($token);
            break;
            
        case 'logout':
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            $resultado = $authManager->logout();
            break;
            
        default:
            throw new Exception('Ação não encontrada');
    }
    
    // Definir código de resposta HTTP
    http_response_code($resultado['success'] ? 200 : 400);
    
    // Retornar resposta JSON
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>