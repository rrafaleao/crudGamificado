<?php
require_once 'Database.php';

class Reservas
{
    // Cria uma nova reserva
    public static function criar($dados)
    {
        $db = Database::getInstance();
        $sql = "INSERT INTO reservas (usuario_id, mesa_id, data_reserva, horario, quantidade_pessoas, observacoes)
                VALUES (:usuario_id, :mesa_id, :data_reserva, :horario, :quantidade_pessoas, :observacoes)";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':usuario_id', $dados['usuario_id']);
        $stmt->bindValue(':mesa_id', $dados['mesa_id']);
        $stmt->bindValue(':data_reserva', $dados['data_reserva']);
        $stmt->bindValue(':horario', $dados['horario']);
        $stmt->bindValue(':quantidade_pessoas', $dados['quantidade_pessoas']);
        $stmt->bindValue(':observacoes', $dados['observacoes'] ?? null);

        if ($stmt->execute()) {
            return $db->lastInsertId();
        }
        return false;
    }

    // Lista todas as reservas (opcional: filtro por usuário)
    public static function listar($usuario_id = null)
    {
        $db = Database::getInstance();
        if ($usuario_id) {
            $sql = "SELECT * FROM reservas WHERE usuario_id = :usuario_id ORDER BY data_reserva DESC, horario DESC";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':usuario_id', $usuario_id);
        } else {
            $sql = "SELECT * FROM reservas ORDER BY data_reserva DESC, horario DESC";
            $stmt = $db->prepare($sql);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Busca uma reserva pelo ID
    public static function buscarPorId($id)
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM reservas WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Remove uma reserva
    public static function remover($id)
    {
        $db = Database::getInstance();
        $sql = "DELETE FROM reservas WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $id);
        return $stmt-
    }