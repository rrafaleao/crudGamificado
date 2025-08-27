-- Banco de Dados: Restaurante Gamificado
-- Sistema de pontos mensal com jantar grátis para o vencedor

DROP DATABASE IF EXISTS restaurante_gamificado;
CREATE DATABASE restaurante_gamificado CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE restaurante_gamificado;

-- Tabela de usuários
-- Criação da tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    pontos_mes_atual INT DEFAULT 0,
    pontos_total INT DEFAULT 0,
    INDEX idx_email (email),
    INDEX idx_pontos_mes (pontos_mes_atual)
);
-- Tabela de mesas
CREATE TABLE mesas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero INT NOT NULL UNIQUE,
    capacidade INT NOT NULL,
    localizacao VARCHAR(50), -- 'janela', 'centro', 'varanda'
    ativa BOOLEAN DEFAULT TRUE
);

-- Tabela de reservas
CREATE TABLE reservas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    mesa_id INT NOT NULL,
    data_reserva DATE NOT NULL,
    horario TIME NOT NULL,
    quantidade_pessoas INT NOT NULL,
    status ENUM('pendente', 'confirmada', 'finalizada', 'cancelada') DEFAULT 'confirmada',
    observacoes TEXT,
    pontos_ganhos INT DEFAULT 50,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (mesa_id) REFERENCES mesas(id),
    
    -- Evitar reservas duplicadas no mesmo horário
    UNIQUE KEY unique_mesa_datetime (mesa_id, data_reserva, horario)
);

-- Inserir mesas do restaurante
INSERT INTO mesas (numero, capacidade, localizacao) VALUES
(1, 2, 'janela'),
(2, 2, 'janela'),
(3, 4, 'janela'),
(4, 2, 'centro'),
(5, 4, 'centro'),
(6, 4, 'centro'),
(7, 6, 'centro'),
(8, 2, 'varanda'),
(9, 4, 'varanda'),
(10, 8, 'varanda');