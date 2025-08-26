<?php
<?php
require_once __DIR__ . '/../config/database.php';

class Pontuacao {
    // Adiciona pontos ao usuário
    public static function adicionar($usuario_id, $pontos, $acao, $referencia_id, $referencia_tipo) {
        $mes_competicao = date('Y-m-01');
        // Atualiza pontos do usuário
        $sql = "UPDATE usuarios SET pontos_mes_atual = pontos_mes_atual + ? WHERE id = ?";
        executeQuery($sql, [$pontos, $usuario_id]);
        // Registra no histórico
        $sql2 = "INSERT INTO historico_pontos (usuario_id, acao, pontos, referencia_id, referencia_tipo, mes_competicao)
                 VALUES (?, ?, ?, ?, ?, ?)";
        executeQuery($sql2, [$usuario_id, $acao, $pontos, $referencia_id, $referencia_tipo, $mes_competicao]);
    }
}
?>