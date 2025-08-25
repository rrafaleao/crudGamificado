<?php
// backend/models/Usuario.php
require_once __DIR__ . '/../config/database.php';

class Usuario {
    
    // Criar novo usuário
    public static function criar($dados) {
        $sql = "INSERT INTO usuarios (nome, email, senha, telefone) VALUES (?, ?, ?, ?)";
        
        $senha_hash = password_hash($dados['senha'], PASSWORD_DEFAULT);
        $params = [
            $dados['nome'],
            $dados['email'],
            $senha_hash,
            $dados['telefone'] ?? null
        ];
        
        return insertAndGetId($sql, $params);
    }
    
    // Buscar usuário por email (para login)
    public static function buscarPorEmail($email) {
        $sql = "SELECT * FROM usuarios WHERE email = ?";
        return fetchOne($sql, [$email]);
    }
    
    // Buscar usuário por ID
    public static function buscarPorId($id) {
        $sql = "SELECT id, nome, email, telefone, pontos_mes_atual, created_at FROM usuarios WHERE id = ?";
        return fetchOne($sql, [$id]);
    }
    
    // Verificar se email já existe
    public static function emailExiste($email) {
        $sql = "SELECT COUNT(*) as total FROM usuarios WHERE email = ?";
        $result = fetchOne($sql, [$email]);
        return $result['total'] > 0;
    }
    
    // Atualizar perfil do usuário
    public static function atualizarPerfil($id, $dados) {
        $sql = "UPDATE usuarios SET nome = ?, telefone = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $params = [
            $dados['nome'],
            $dados['telefone'] ?? null,
            $id
        ];
        
        executeQuery($sql, $params);
        return self::buscarPorId($id);
    }
    
    // Alterar senha
    public static function alterarSenha($id, $nova_senha) {
        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios SET senha = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        executeQuery($sql, [$senha_hash, $id]);
    }
    
    // Validar login
    public static function validarLogin($email, $senha) {
        $usuario = self::buscarPorEmail($email);
        
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            // Remove a senha dos dados retornados
            unset($usuario['senha']);
            return $usuario;
        }
        
        return false;
    }
    
    // Adicionar pontos ao usuário
    public static function adicionarPontos($usuario_id, $pontos) {
        $sql = "UPDATE usuarios SET pontos_mes_atual = pontos_mes_atual + ? WHERE id = ?";
        executeQuery($sql, [$pontos, $usuario_id]);
    }
    
    // Obter ranking atual
    public static function obterRanking($limit = 10) {
        $sql = "SELECT * FROM ranking_atual LIMIT ?";
        return fetchAll($sql, [$limit]);
    }
    
    // Obter posição do usuário no ranking
    public static function obterPosicaoRanking($usuario_id) {
        $sql = "SELECT posicao FROM ranking_atual WHERE id = ?";
        $result = fetchOne($sql, [$usuario_id]);
        return $result ? $result['posicao'] : null;
    }
    
    // Obter histórico de pontos do usuário
    public static function obterHistoricoPontos($usuario_id, $mes = null) {
        if ($mes) {
            $sql = "SELECT hp.*, 
                           CASE hp.referencia_tipo
                               WHEN 'reserva' THEN CONCAT('Mesa ', m.numero, ' - ', DATE_FORMAT(r.data_reserva, '%d/%m/%Y'), ' às ', TIME_FORMAT(r.horario, '%H:%i'))
                               WHEN 'avaliacao' THEN CONCAT('Avaliação - Nota ', a.nota, '/5')
                           END as descricao
                    FROM historico_pontos hp
                    LEFT JOIN reservas r ON hp.referencia_tipo = 'reserva' AND hp.referencia_id = r.id
                    LEFT JOIN mesas m ON r.mesa_id = m.id
                    LEFT JOIN avaliacoes a ON hp.referencia_tipo = 'avaliacao' AND hp.referencia_id = a.id
                    WHERE hp.usuario_id = ? AND hp.mes_competicao = ?
                    ORDER BY hp.created_at DESC";
            return fetchAll($sql, [$usuario_id, $mes]);
        } else {
            $sql = "SELECT hp.*, 
                           CASE hp.referencia_tipo
                               WHEN 'reserva' THEN CONCAT('Mesa ', m.numero, ' - ', DATE_FORMAT(r.data_reserva, '%d/%m/%Y'), ' às ', TIME_FORMAT(r.horario, '%H:%i'))
                               WHEN 'avaliacao' THEN CONCAT('Avaliação - Nota ', a.nota, '/5')
                           END as descricao
                    FROM historico_pontos hp
                    LEFT JOIN reservas r ON hp.referencia_tipo = 'reserva' AND hp.referencia_id = r.id
                    LEFT JOIN mesas m ON r.mesa_id = m.id
                    LEFT JOIN avaliacoes a ON hp.referencia_tipo = 'avaliacao' AND hp.referencia_id = a.id
                    WHERE hp.usuario_id = ?
                    ORDER BY hp.created_at DESC
                    LIMIT 50";
            return fetchAll($sql, [$usuario_id]);
        }
    }
    
    // Obter vencedores mensais
    public static function obterVencedoresMensais($limit = 12) {
        $sql = "SELECT vm.*, 
                       DATE_FORMAT(vm.mes_competicao, '%M/%Y') as mes_formatado,
                       DATE_FORMAT(vm.mes_competicao, '%m/%Y') as mes_numerico
                FROM vencedores_mensais vm
                ORDER BY vm.mes_competicao DESC
                LIMIT ?";
        return fetchAll($sql, [$limit]);
    }
    
    // Obter estatísticas do usuário
    public static function obterEstatisticas($usuario_id) {
        // Total de reservas
        $sql_reservas = "SELECT COUNT(*) as total FROM reservas WHERE usuario_id = ? AND status IN ('confirmada', 'finalizada')";
        $total_reservas = fetchOne($sql_reservas, [$usuario_id])['total'];
        
        // Total de avaliações
        $sql_avaliacoes = "SELECT COUNT(*) as total FROM avaliacoes WHERE usuario_id = ?";
        $total_avaliacoes = fetchOne($sql_avaliacoes, [$usuario_id])['total'];
        
        // Reservas pendentes de avaliação
        $sql_pendentes = "SELECT COUNT(*) as total 
                         FROM reservas r 
                         LEFT JOIN avaliacoes a ON r.id = a.reserva_id
                         WHERE r.usuario_id = ? AND r.status = 'finalizada' AND a.id IS NULL";
        $pendentes_avaliacao = fetchOne($sql_pendentes, [$usuario_id])['total'];
        
        // Pontos totais histórico
        $sql_pontos_total = "SELECT COALESCE(SUM(pontos), 0) as total FROM historico_pontos WHERE usuario_id = ?";
        $pontos_total_historico = fetchOne($sql_pontos_total, [$usuario_id])['total'];
        
        return [
            'total_reservas' => (int)$total_reservas,
            'total_avaliacoes' => (int)$total_avaliacoes,
            'pendentes_avaliacao' => (int)$pendentes_avaliacao,
            'pontos_total_historico' => (int)$pontos_total_historico
        ];
    }
    
    // Resetar pontos mensais (para ser chamado mensalmente)
    public static function resetarPontosMensais() {
        try {
            getDB()->beginTransaction();
            
            // Encontrar vencedor atual
            $vencedor = fetchOne("SELECT * FROM ranking_atual ORDER BY pontos_mes_atual DESC, nome ASC LIMIT 1");
            
            if ($vencedor && $vencedor['pontos_mes_atual'] > 0) {
                $mes_anterior = date('Y-m-01', strtotime('-1 month'));
                
                // Registrar vencedor
                $sql_vencedor = "INSERT INTO vencedores_mensais (usuario_id, nome_usuario, mes_competicao, pontos_total) 
                                VALUES (?, ?, ?, ?)";
                executeQuery($sql_vencedor, [
                    $vencedor['id'],
                    $vencedor['nome'],
                    $mes_anterior,
                    $vencedor['pontos_mes_atual']
                ]);
            }
            
            // Resetar todos os pontos
            executeQuery("UPDATE usuarios SET pontos_mes_atual = 0");
            
            getDB()->commit();
            return $vencedor;
            
        } catch (Exception $e) {
            getDB()->rollBack();
            throw $e;
        }
    }
}
?>