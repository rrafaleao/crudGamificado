<?php
<?php
require_once '../config/cors.php';
// Supondo que você tenha um model Avaliacao.php
// require_once '../models/Avaliacao.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    // Exemplo de endpoint para criar avaliação
    case 'criar':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método não permitido']);
            exit;
        }
        $dados = json_decode(file_get_contents('php://input'), true);
        // $res = Avaliacao::criar($dados);
        // echo json_encode($res);
        echo json_encode(['success' => true, 'mensagem' => 'Avaliação criada (exemplo)']);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Ação não encontrada']);
}
?>