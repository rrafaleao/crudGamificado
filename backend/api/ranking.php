<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';

// Para requisições OPTIONS (pré-voo)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$database = Database::getInstance();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'GET') {
    try {
        // Buscar ranking dos usuários com mais pontos no mês atual
        $query = "SELECT id, nome, pontos_mes_atual, pontos_total 
                  FROM usuarios 
                  WHERE pontos_mes_atual > 0 
                  ORDER BY pontos_mes_atual DESC, nome ASC 
                  LIMIT 20";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            $ranking_arr = array();
            $ranking_arr["records"] = array();
            
            $posicao = 1;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ranking_item = array(
                    "posicao" => $posicao,
                    "id" => $row['id'],
                    "nome" => $row['nome'],
                    "pontos_mes_atual" => $row['pontos_mes_atual'],
                    "pontos_total" => $row['pontos_total']
                );
                array_push($ranking_arr["records"], $ranking_item);
                $posicao++;
            }
            
            http_response_code(200);
            echo json_encode($ranking_arr);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "Nenhum usuário com pontos encontrado."));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("message" => "Erro interno do servidor."));
    }
} else {
    http_response_code(405);
    echo json_encode(array("message" => "Método não permitido."));
}
?>