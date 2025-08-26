<?php
<?php
require_once '../config/cors.php';
require_once '../models/Pontuacao.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'adicionar':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método não permitido']);
            exit;
        }
        $dados = json_decode(file_get_contents('php://input'), true);
        Pontuacao::adicionar(
            $dados['usuario_id'],
            $dados['pontos'],
            $dados['acao'],
            $dados['referencia_id'],
            $dados['referencia_tipo']
        );
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Ação não encontrada']);
}
?>