<?php
<?php
require_once '../config/cors.php';
require_once '../models/usuario.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'criar':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método não permitido']);
            exit;
        }
        $dados = json_decode(file_get_contents('php://input'), true);
        $res = Usuario::criar($dados);
        echo json_encode($res);
        break;

    case 'buscar':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID não informado']);
            exit;
        }
        $user = Usuario::buscarPorId($id);
        echo json_encode(['success' => true, 'data' => $user]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Ação não encontrada']);
}
?>