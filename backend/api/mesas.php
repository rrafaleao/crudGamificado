<?php
// backend/api/mesas.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../config/database.php';

try {
    // Obtém todos os registros usando a função helper
    $mesas = fetchAll("SELECT * FROM mesas WHERE ativa = TRUE ORDER BY numero");
    
    if (count($mesas) > 0) {
        $response = [
            "records" => $mesas
        ];
        
        http_response_code(200);
        echo json_encode($response);
    } else {
        http_response_code(404);
        echo json_encode(array("message" => "Nenhuma mesa encontrada."));
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "Erro interno do servidor."));
}
?>