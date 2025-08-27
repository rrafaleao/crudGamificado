<?php
// backend/api/reservas.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
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

switch ($method) {
    case 'GET':
        // Obter reservas por usuário ou por ID de reserva
        if (isset($_GET['usuario_id'])) {
            $usuario_id = $_GET['usuario_id'];
            
            $query = "SELECT r.*, m.numero as mesa_numero 
                      FROM reservas r 
                      INNER JOIN mesas m ON r.mesa_id = m.id 
                      WHERE r.usuario_id = :usuario_id 
                      ORDER BY r.data_reserva DESC, r.horario DESC";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':usuario_id', $usuario_id);
            $stmt->execute();
            
            $num = $stmt->rowCount();
            
            if ($num > 0) {
                $reservas_arr = array();
                $reservas_arr["records"] = array();
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $reserva_item = array(
                        "id" => $row['id'],
                        "usuario_id" => $row['usuario_id'],
                        "mesa_id" => $row['mesa_id'],
                        "mesa_numero" => $row['mesa_numero'],
                        "data_reserva" => $row['data_reserva'],
                        "horario" => $row['horario'],
                        "quantidade_pessoas" => $row['quantidade_pessoas'],
                        "observacoes" => $row['observacoes'],
                        "status" => $row['status'],
                        "pontos_ganhos" => $row['pontos_ganhos']
                    );
                    array_push($reservas_arr["records"], $reserva_item);
                }
                
                http_response_code(200);
                echo json_encode($reservas_arr);
            } else {
                http_response_code(404);
                echo json_encode(array("message" => "Nenhuma reserva encontrada."));
            }
        } else if (isset($_GET['reserva_id'])) {
            // Buscar reserva por ID
            $reserva_id = $_GET['reserva_id'];
            
            $query = "SELECT r.*, m.numero as mesa_numero 
                      FROM reservas r 
                      INNER JOIN mesas m ON r.mesa_id = m.id 
                      WHERE r.id = :reserva_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':reserva_id', $reserva_id);
            $stmt->execute();
            
            $num = $stmt->rowCount();
            
            if ($num > 0) {
                $reservas_arr = array();
                $reservas_arr["records"] = array();
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $reserva_item = array(
                        "id" => $row['id'],
                        "usuario_id" => $row['usuario_id'],
                        "mesa_id" => $row['mesa_id'],
                        "mesa_numero" => $row['mesa_numero'],
                        "data_reserva" => $row['data_reserva'],
                        "horario" => $row['horario'],
                        "quantidade_pessoas" => $row['quantidade_pessoas'],
                        "observacoes" => $row['observacoes'],
                        "status" => $row['status'],
                        "pontos_ganhos" => $row['pontos_ganhos']
                    );
                    array_push($reservas_arr["records"], $reserva_item);
                }
                
                http_response_code(200);
                echo json_encode($reservas_arr);
            } else {
                http_response_code(404);
                echo json_encode(array("message" => "Reserva não encontrada."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Necessário fornecer usuario_id ou reserva_id."));
        }
        break;
        
    case 'POST':
        // Criar nova reserva
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->usuario_id) && !empty($data->mesa_id) && 
            !empty($data->data_reserva) && !empty($data->horario) && 
            !empty($data->quantidade_pessoas)) {
            
            // Verificar disponibilidade da mesa
            $query_check = "SELECT id FROM reservas 
                           WHERE mesa_id = :mesa_id 
                           AND data_reserva = :data_reserva 
                           AND horario = :horario 
                           AND status != 'cancelada'";
            
            $stmt_check = $db->prepare($query_check);
            $stmt_check->bindParam(':mesa_id', $data->mesa_id);
            $stmt_check->bindParam(':data_reserva', $data->data_reserva);
            $stmt_check->bindParam(':horario', $data->horario);
            $stmt_check->execute();
            
            if ($stmt_check->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(array("message" => "Mesa já reservada para este horário."));
                break;
            }
            
            // Inserir reserva
            $query = "INSERT INTO reservas 
                     (usuario_id, mesa_id, data_reserva, horario, quantidade_pessoas, observacoes, status, pontos_ganhos) 
                     VALUES 
                     (:usuario_id, :mesa_id, :data_reserva, :horario, :quantidade_pessoas, :observacoes, 'confirmada', 10)";
            
            $stmt = $db->prepare($query);
            
            $stmt->bindParam(':usuario_id', $data->usuario_id);
            $stmt->bindParam(':mesa_id', $data->mesa_id);
            $stmt->bindParam(':data_reserva', $data->data_reserva);
            $stmt->bindParam(':horario', $data->horario);
            $stmt->bindParam(':quantidade_pessoas', $data->quantidade_pessoas);
            $stmt->bindParam(':observacoes', $data->observacoes);
            
            if ($stmt->execute()) {
                // Atualizar pontos do usuário
                $updatePontos = "UPDATE usuarios 
                                SET pontos_mes_atual = pontos_mes_atual + 10, 
                                    pontos_total = pontos_total + 10 
                                WHERE id = :usuario_id";
                $stmtUpdate = $db->prepare($updatePontos);
                $stmtUpdate->bindParam(':usuario_id', $data->usuario_id);
                $stmtUpdate->execute();

                http_response_code(201);
                echo json_encode(array("message" => "Reserva criada com sucesso."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Não foi possível criar a reserva."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Dados incompletos."));
        }
        break;
        
    case 'PUT':
        // Atualizar reserva
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->id)) {
            // Verificar se é um cancelamento
            if (!empty($data->status) && $data->status === 'cancelada') {
                $query = "UPDATE reservas SET status = 'cancelada' WHERE id = :id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $data->id);
                
                if ($stmt->execute()) {
                    http_response_code(200);
                    echo json_encode(array("message" => "Reserva cancelada com sucesso."));
                } else {
                    http_response_code(503);
                    echo json_encode(array("message" => "Não foi possível cancelar a reserva."));
                }
            } else {
                // Atualização normal da reserva
                // Verificar disponibilidade da mesa (exceto para a própria reserva)
                if (!empty($data->mesa_id) && !empty($data->data_reserva) && !empty($data->horario)) {
                    $query_check = "SELECT id FROM reservas 
                                   WHERE mesa_id = :mesa_id 
                                   AND data_reserva = :data_reserva 
                                   AND horario = :horario 
                                   AND status != 'cancelada'
                                   AND id != :id";
                    
                    $stmt_check = $db->prepare($query_check);
                    $stmt_check->bindParam(':mesa_id', $data->mesa_id);
                    $stmt_check->bindParam(':data_reserva', $data->data_reserva);
                    $stmt_check->bindParam(':horario', $data->horario);
                    $stmt_check->bindParam(':id', $data->id);
                    $stmt_check->execute();
                    
                    if ($stmt_check->rowCount() > 0) {
                        http_response_code(409);
                        echo json_encode(array("message" => "Mesa já reservada para este horário."));
                        break;
                    }
                }
                
                // Construir query dinamicamente com base nos campos fornecidos
                $updates = [];
                $params = [':id' => $data->id];
                
                if (!empty($data->mesa_id)) {
                    $updates[] = "mesa_id = :mesa_id";
                    $params[':mesa_id'] = $data->mesa_id;
                }
                
                if (!empty($data->data_reserva)) {
                    $updates[] = "data_reserva = :data_reserva";
                    $params[':data_reserva'] = $data->data_reserva;
                }
                
                if (!empty($data->horario)) {
                    $updates[] = "horario = :horario";
                    $params[':horario'] = $data->horario;
                }
                
                if (!empty($data->quantidade_pessoas)) {
                    $updates[] = "quantidade_pessoas = :quantidade_pessoas";
                    $params[':quantidade_pessoas'] = $data->quantidade_pessoas;
                }
                
                if (isset($data->observacoes)) {
                    $updates[] = "observacoes = :observacoes";
                    $params[':observacoes'] = $data->observacoes;
                }
                
                if (count($updates) === 0) {
                    http_response_code(400);
                    echo json_encode(array("message" => "Nenhum dado fornecido para atualização."));
                    break;
                }
                
                $query = "UPDATE reservas SET " . implode(', ', $updates) . " WHERE id = :id";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute($params)) {
                    http_response_code(200);
                    echo json_encode(array("message" => "Reserva atualizada com sucesso."));
                } else {
                    http_response_code(503);
                    echo json_encode(array("message" => "Não foi possível atualizar a reserva."));
                }
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "ID da reserva não fornecido."));
        }
        break;
    case 'DELETE':
        // Excluir reserva
        $reserva_id = isset($_GET['id']) ? $_GET['id'] : null;
        
        if (!$reserva_id) {
            http_response_code(400);
            echo json_encode(array("message" => "ID da reserva não fornecido."));
            break;
        }
        
        try {
            // Primeiro verificar se a reserva existe e pertence ao usuário
            $query_check = "SELECT usuario_id FROM reservas WHERE id = :id";
            $stmt_check = $db->prepare($query_check);
            $stmt_check->bindParam(':id', $reserva_id);
            $stmt_check->execute();
            
            if ($stmt_check->rowCount() == 0) {
                http_response_code(404);
                echo json_encode(array("message" => "Reserva não encontrada."));
                break;
            }
            
            // Excluir a reserva
            $query = "DELETE FROM reservas WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $reserva_id);
            
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(array("message" => "Reserva excluída com sucesso."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Não foi possível excluir a reserva."));
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(array("message" => "Erro ao excluir reserva: " . $e->getMessage()));
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Método não permitido."));
        break;
}
?>
