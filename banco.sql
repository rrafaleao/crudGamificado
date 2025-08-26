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

-- Tabela de avaliações
CREATE TABLE avaliacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    reserva_id INT NOT NULL UNIQUE,
    nota INT NOT NULL CHECK (nota >= 1 AND nota <= 5),
    comentario TEXT,
    pontos_ganhos INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE CASCADE
);

-- Tabela de histórico de pontos (para auditoria)
CREATE TABLE historico_pontos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    acao VARCHAR(50) NOT NULL, -- 'reserva', 'avaliacao'
    pontos INT NOT NULL,
    referencia_id INT, -- ID da reserva ou avaliação
    referencia_tipo ENUM('reserva', 'avaliacao') NOT NULL,
    mes_competicao DATE NOT NULL, -- primeiro dia do mês (YYYY-MM-01)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela de vencedores mensais
CREATE TABLE vencedores_mensais (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    nome_usuario VARCHAR(100) NOT NULL,
    mes_competicao DATE NOT NULL, -- YYYY-MM-01
    pontos_total INT NOT NULL,
    premio_resgatado BOOLEAN DEFAULT FALSE,
    data_resgate TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    UNIQUE KEY unique_mes (mes_competicao)
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

-- Trigger para registrar pontos no histórico quando reserva é criada
DELIMITER //
CREATE TRIGGER registrar_pontos_reserva 
AFTER INSERT ON reservas
FOR EACH ROW
BEGIN
    DECLARE mes_atual DATE;
    SET mes_atual = DATE_FORMAT(CURDATE(), '%Y-%m-01');
    
    -- Registrar no histórico
    INSERT INTO historico_pontos (usuario_id, acao, pontos, referencia_id, referencia_tipo, mes_competicao)
    VALUES (NEW.usuario_id, 'Nova Reserva', NEW.pontos_ganhos, NEW.id, 'reserva', mes_atual);
    
    -- Atualizar pontos do usuário
    UPDATE usuarios 
    SET pontos_mes_atual = pontos_mes_atual + NEW.pontos_ganhos 
    WHERE id = NEW.usuario_id;
END//
DELIMITER ;

-- Trigger para registrar pontos no histórico quando avaliação é criada
DELIMITER //
CREATE TRIGGER registrar_pontos_avaliacao 
AFTER INSERT ON avaliacoes
FOR EACH ROW
BEGIN
    DECLARE mes_atual DATE;
    SET mes_atual = DATE_FORMAT(CURDATE(), '%Y-%m-01');
    
    -- Registrar no histórico
    INSERT INTO historico_pontos (usuario_id, acao, pontos, referencia_id, referencia_tipo, mes_competicao)
    VALUES (NEW.usuario_id, 'Avaliação', NEW.pontos_ganhos, NEW.id, 'avaliacao', mes_atual);
    
    -- Atualizar pontos do usuário
    UPDATE usuarios 
    SET pontos_mes_atual = pontos_mes_atual + NEW.pontos_ganhos 
    WHERE id = NEW.usuario_id;
END//
DELIMITER ;

-- View para ranking atual do mês
CREATE VIEW ranking_atual AS
SELECT 
    u.id,
    u.nome,
    u.email,
    u.pontos_mes_atual,
    COUNT(DISTINCT r.id) as total_reservas,
    COUNT(DISTINCT a.id) as total_avaliacoes,
    RANK() OVER (ORDER BY u.pontos_mes_atual DESC) as posicao
FROM usuarios u
LEFT JOIN reservas r ON u.id = r.usuario_id 
    AND r.status IN ('confirmada', 'finalizada')
    AND DATE_FORMAT(r.created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
LEFT JOIN avaliacoes a ON u.id = a.usuario_id
    AND DATE_FORMAT(a.created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
WHERE u.pontos_mes_atual > 0
GROUP BY u.id, u.nome, u.email, u.pontos_mes_atual
ORDER BY u.pontos_mes_atual DESC, u.nome ASC;