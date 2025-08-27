<?php
// backend/models/Reserva.php
class Reserva {
    private $conn;
    private $table_name = "reservas";

    public $id;
    public $usuario_id;
    public $mesa_id;
    public $data_reserva;
    public $horario;
    public $quantidade_pessoas;
    public $status;
    public $observacoes;
    public $pontos_ganhos;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                 SET usuario_id=:usuario_id, mesa_id=:mesa_id, data_reserva=:data_reserva, 
                     horario=:horario, quantidade_pessoas=:quantidade_pessoas, 
                     status=:status, observacoes=:observacoes, pontos_ganhos=:pontos_ganhos";

        $stmt = $this->conn->prepare($query);

        $this->usuario_id = htmlspecialchars(strip_tags($this->usuario_id));
        $this->mesa_id = htmlspecialchars(strip_tags($this->mesa_id));
        $this->data_reserva = htmlspecialchars(strip_tags($this->data_reserva));
        $this->horario = htmlspecialchars(strip_tags($this->horario));
        $this->quantidade_pessoas = htmlspecialchars(strip_tags($this->quantidade_pessoas));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->observacoes = htmlspecialchars(strip_tags($this->observacoes));
        $this->pontos_ganhos = htmlspecialchars(strip_tags($this->pontos_ganhos));

        $stmt->bindParam(":usuario_id", $this->usuario_id);
        $stmt->bindParam(":mesa_id", $this->mesa_id);
        $stmt->bindParam(":data_reserva", $this->data_reserva);
        $stmt->bindParam(":horario", $this->horario);
        $stmt->bindParam(":quantidade_pessoas", $this->quantidade_pessoas);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":observacoes", $this->observacoes);
        $stmt->bindParam(":pontos_ganhos", $this->pontos_ganhos);

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    public function read() {
        $query = "SELECT r.*, u.nome as usuario_nome, m.numero as mesa_numero 
                 FROM " . $this->table_name . " r
                 INNER JOIN usuarios u ON r.usuario_id = u.id
                 INNER JOIN mesas m ON r.mesa_id = m.id
                 ORDER BY r.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function readOne() {
        $query = "SELECT r.*, u.nome as usuario_nome, m.numero as mesa_numero 
                 FROM " . $this->table_name . " r
                 INNER JOIN usuarios u ON r.usuario_id = u.id
                 INNER JOIN mesas m ON r.mesa_id = m.id
                 WHERE r.id = ? LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->usuario_id = $row['usuario_id'];
            $this->mesa_id = $row['mesa_id'];
            $this->data_reserva = $row['data_reserva'];
            $this->horario = $row['horario'];
            $this->quantidade_pessoas = $row['quantidade_pessoas'];
            $this->status = $row['status'];
            $this->observacoes = $row['observacoes'];
            $this->pontos_ganhos = $row['pontos_ganhos'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->usuario_nome = $row['usuario_nome'];
            $this->mesa_numero = $row['mesa_numero'];
        }
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                 SET usuario_id=:usuario_id, mesa_id=:mesa_id, data_reserva=:data_reserva, 
                     horario=:horario, quantidade_pessoas=:quantidade_pessoas, 
                     status=:status, observacoes=:observacoes, pontos_ganhos=:pontos_ganhos
                 WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        $this->usuario_id = htmlspecialchars(strip_tags($this->usuario_id));
        $this->mesa_id = htmlspecialchars(strip_tags($this->mesa_id));
        $this->data_reserva = htmlspecialchars(strip_tags($this->data_reserva));
        $this->horario = htmlspecialchars(strip_tags($this->horario));
        $this->quantidade_pessoas = htmlspecialchars(strip_tags($this->quantidade_pessoas));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->observacoes = htmlspecialchars(strip_tags($this->observacoes));
        $this->pontos_ganhos = htmlspecialchars(strip_tags($this->pontos_ganhos));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(":usuario_id", $this->usuario_id);
        $stmt->bindParam(":mesa_id", $this->mesa_id);
        $stmt->bindParam(":data_reserva", $this->data_reserva);
        $stmt->bindParam(":horario", $this->horario);
        $stmt->bindParam(":quantidade_pessoas", $this->quantidade_pessoas);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":observacoes", $this->observacoes);
        $stmt->bindParam(":pontos_ganhos", $this->pontos_ganhos);
        $stmt->bindParam(":id", $this->id);

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";

        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id);

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }
}
?>