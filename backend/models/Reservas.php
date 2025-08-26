<?php
/**
 * Model Reserva - CRUD completo para reservas
 * Sistema de Restaurante Gamificado
 */

require_once __DIR__ . '/../config/database.php';

class Reserva {
    
    /**
     * Criar nova reserva
     */
    public static function criar($dados) {
        try {
            $db = getDB();
            
            // Verificar disponibilidade da mesa
            if (!self::mesaDisponivel($dados['mesa_id'], $dados['data_reserva'], $dados['horario'])) {
                return ['success' => false, 'error' => 'Mesa não disponível neste horário'];
            }
            
            // Verificar capacidade da mesa
            if (!self::verificarCapacidade($dados['mesa_id'], $dados['quantidade_pessoas'])) {
                return ['success' => false, 'error' => 'Quantidade de pessoas excede a capacidade da mesa'];
            }
            
            $sql = "INSERT INTO reservas (usuario_id, mesa_id, data_reserva, horario, quantidade_pessoas, observacoes, status) 
                    VALUES (:usuario_id, :mesa_id, :data_reserva, :horario, :quantidade_pessoas, :observacoes, 'confirmada')";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':usuario_id' => $dados['usuario_id'],
                ':mesa_id' => $dados['mesa_id'],
                ':data_reserva' => $dados['data_reserva'],
                ':horario' => $dados['horario'],
                ':quantidade_pessoas' => $dados['quantidade_pessoas'],
                ':observacoes' => $dados['observacoes'] ?? ''
            ]);
            
            $reserva_id = $db->lastInsertId();
            $reserva = self::buscarPorId($reserva_id);
            
            return ['success' => true, 'data' => $reserva];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erro ao criar reserva: ' . $e->getMessage()];
        }
    }
    
    /**
     * Buscar reserva por ID
     */
    public static function buscarPorId($id) {
        try {
            $db = getDB();
            
            $sql = "SELECT r.*, u.nome as nome_usuario, u.email, u.telefone,
                           m.numero as numero_mesa, m.capacidade, m.localizacao
                    FROM reservas r
                    JOIN usuarios u ON r.usuario_id = u.id
                    JOIN mesas m ON r.mesa_id = m.id
                    WHERE r.id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Buscar reservas por usuário
     */
    public static function buscarPorUsuario($usuario_id, $status = null) {
        try {
            $db = getDB();
            
            $sql = "SELECT r.*, m.numero as numero_mesa, m.capacidade, m.localizacao,
                           a.id as avaliacao_id, a.nota, a.comentario
                    FROM reservas r
                    JOIN mesas m ON r.mesa_id = m.id
                    LEFT JOIN avaliacoes a ON r.id = a.reserva_id
                    WHERE r.usuario_id = :usuario_id";
            
            $params = [':usuario_id' => $usuario_id];
            
            if ($status) {
                $sql .= " AND r.status = :status";
                $params[':status'] = $status;
            }
            
            $sql .= " ORDER BY r.data_reserva DESC, r.horario DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Atualizar reserva
     */
    public static function atualizar($id, $dados) {
        try {
            $db = getDB();
            
            // Verificar se a reserva existe
            $reserva_atual = self::buscarPorId($id);
            if (!$reserva_atual) {
                return ['success' => false, 'error' => 'Reserva não encontrada'];
            }
            
            // Se mudou mesa, data ou horário, verificar disponibilidade
            if (isset($dados['mesa_id']) || isset($dados['data_reserva']) || isset($dados['horario'])) {
                $mesa_id = $dados['mesa_id'] ?? $reserva_atual['mesa_id'];
                $data = $dados['data_reserva'] ?? $reserva_atual['data_reserva'];
                $horario = $dados['horario'] ?? $reserva_atual['horario'];
                
                if (!self::mesaDisponivel($mesa_id, $data, $horario, $id)) {
                    return ['success' => false, 'error' => 'Mesa não disponível neste horário'];
                }
            }
            
            // Verificar capacidade se mudou mesa ou quantidade
            if (isset($dados['mesa_id']) || isset($dados['quantidade_pessoas'])) {
                $mesa_id = $dados['mesa_id'] ?? $reserva_atual['mesa_id'];
                $quantidade = $dados['quantidade_pessoas'] ?? $reserva_atual['quantidade_pessoas'];
                
                if (!self::verificarCapacidade($mesa_id, $quantidade)) {
                    return ['success' => false, 'error' => 'Quantidade de pessoas excede a capacidade da mesa'];
                }
            }
            
            $campos = [];
            $params = [':id' => $id];
            
            foreach ($dados as $campo => $valor) {
                if (in_array($campo, ['mesa_id', 'data_reserva', 'horario', 'quantidade_pessoas', 'observacoes', 'status'])) {
                    $campos[] = "$campo = :$campo";
                    $params[":$campo"] = $valor;
                }
            }
            
            if (empty($campos)) {
                return ['success' => false, 'error' => 'Nenhum campo válido para atualização'];
            }
            
            $sql = "UPDATE reservas SET " . implode(', ', $campos) . " WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            $reserva = self::buscarPorId($id);
            return ['success' => true, 'data' => $reserva];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erro ao atualizar reserva: ' . $e->getMessage()];
        }
    }
    
    /**
     * Cancelar reserva
     */
    public static function cancelar($id, $usuario_id = null) {
        try {
            $db = getDB();
            
            // Verificar se a reserva existe
            $reserva = self::buscarPorId($id);
            if (!$reserva) {
                return ['success' => false, 'error' => 'Reserva não encontrada'];
            }
            
            // Verificar se o usuário pode cancelar esta reserva
            if ($usuario_id && $reserva['usuario_id'] != $usuario_id) {
                return ['success' => false, 'error' => 'Você não tem permissão para cancelar esta reserva'];
            }
            
            // Verificar se pode cancelar (não pode cancelar reservas finalizadas)
            if ($reserva['status'] == 'finalizada') {
                return ['success' => false, 'error' => 'Não é possível cancelar uma reserva já finalizada'];
            }
            
            if ($reserva['status'] == 'cancelada') {
                return ['success' => false, 'error' => 'Reserva já está cancelada'];
            }
            
            // Remover pontos do usuário se a reserva estava confirmada
            if ($reserva['status'] == 'confirmada') {
                $sql_pontos = "UPDATE usuarios SET pontos_mes_atual = pontos_mes_atual - :pontos WHERE id = :usuario_id";
                $stmt_pontos = $db->prepare($sql_pontos);
                $stmt_pontos->execute([
                    ':pontos' => $reserva['pontos_ganhos'],
                    ':usuario_id' => $reserva['usuario_id']
                ]);
                
                // Registrar no histórico
                $sql_historico = "INSERT INTO historico_pontos (usuario_id, acao, pontos, referencia_id, referencia_tipo, mes_competicao)
                                  VALUES (:usuario_id, 'Cancelamento Reserva', :pontos, :referencia_id, 'reserva', :mes)";
                $stmt_historico = $db->prepare($sql_historico);
                $stmt_historico->execute([
                    ':usuario_id' => $reserva['usuario_id'],
                    ':pontos' => -$reserva['pontos_ganhos'],
                    ':referencia_id' => $id,
                    ':mes' => date('Y-m-01')
                ]);
            }
            
            // Cancelar a reserva
            $sql = "UPDATE reservas SET status = 'cancelada' WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            return ['success' => true, 'message' => 'Reserva cancelada com sucesso'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erro ao cancelar reserva: ' . $e->getMessage()];
        }
    }
    
    /**
     * Finalizar reserva (cliente compareceu)
     */
    public static function finalizar($id) {
        return self::atualizar($id, ['status' => 'finalizada']);
    }
    
    /**
     * Buscar mesas disponíveis
     */
    public static function buscarMesasDisponiveis($data, $horario, $quantidade_pessoas) {
        $db = getDB();
        $sql = "SELECT * FROM mesas 
                WHERE ativa = 1 
                  AND capacidade >= :quantidade_pessoas
                  AND id NOT IN (
                      SELECT mesa_id FROM reservas 
                      WHERE data_reserva = :data 
                        AND horario = :horario
                        AND status IN ('confirmada', 'pendente')
                  )
                ORDER BY capacidade ASC";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':quantidade_pessoas', $quantidade_pessoas, PDO::PARAM_INT);
        $stmt->bindValue(':data', $data);
        $stmt->bindValue(':horario', $horario);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Verificar se mesa está disponível
     */
    private static function mesaDisponivel($mesa_id, $data, $horario, $excluir_reserva_id = null) {
        try {
            $db = getDB();
            
            $sql = "SELECT COUNT(*) FROM reservas 
                    WHERE mesa_id = :mesa_id 
                    AND data_reserva = :data 
                    AND horario = :horario 
                    AND status IN ('confirmada', 'pendente')";
            
            $params = [
                ':mesa_id' => $mesa_id,
                ':data' => $data,
                ':horario' => $horario
            ];
            
            if ($excluir_reserva_id) {
                $sql .= " AND id != :excluir_id";
                $params[':excluir_id'] = $excluir_reserva_id;
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchColumn() == 0;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Verificar capacidade da mesa
     */
    private static function verificarCapacidade($mesa_id, $quantidade_pessoas) {
        try {
            $db = getDB();
            
            $sql = "SELECT capacidade FROM mesas WHERE id = :mesa_id AND ativa = 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([':mesa_id' => $mesa_id]);
            
            $mesa = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $mesa && $mesa['capacidade'] >= $quantidade_pessoas;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Obter estatísticas de reservas do usuário
     */
    public static function obterEstatisticas($usuario_id) {
        try {
            $db = getDB();
            
            $sql = "SELECT 
                        COUNT(*) as total_reservas,
                        COUNT(CASE WHEN status = 'confirmada' THEN 1 END) as confirmadas,
                        COUNT(CASE WHEN status = 'finalizada' THEN 1 END) as finalizadas,
                        COUNT(CASE WHEN status = 'cancelada' THEN 1 END) as canceladas,
                        COUNT(CASE WHEN DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN 1 END) as mes_atual
                    FROM reservas 
                    WHERE usuario_id = :usuario_id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':usuario_id' => $usuario_id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return [
                'total_reservas' => 0,
                'confirmadas' => 0,
                'finalizadas' => 0,
                'canceladas' => 0,
                'mes_atual' => 0
            ];
        }
    }
    
    /**
     * Buscar reservas próximas (hoje e próximos 3 dias)
     */
    public static function buscarProximas($usuario_id) {
        try {
            $db = getDB();
            
            $sql = "SELECT r.*, m.numero as numero_mesa, m.localizacao
                    FROM reservas r
                    JOIN mesas m ON r.mesa_id = m.id
                    WHERE r.usuario_id = :usuario_id
                    AND r.data_reserva >= CURDATE()
                    AND r.data_reserva <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                    AND r.status IN ('confirmada', 'pendente')
                    ORDER BY r.data_reserva ASC, r.horario ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':usuario_id' => $usuario_id]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return [];
        }
    }
}