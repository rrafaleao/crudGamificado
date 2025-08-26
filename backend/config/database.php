<?php
// backend/config/database.php
// Configuração da conexão com o banco de dados

class Database {
    private static $instance = null;
    private $connection;
    private $host = 'localhost';
    private $db_name = 'restaurante_gamificado';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    
    private function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            error_log("Erro na conexão com o banco: " . $e->getMessage());
            throw new Exception("Erro de conexão com o banco de dados");
        }
    }
    
    // Método para obter a instância única (Singleton)
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Método para obter a conexão PDO
    public function getConnection() {
        return $this->connection;
    }
    
    // Método para testar a conexão
    public function testConnection() {
        try {
            $stmt = $this->connection->query("SELECT 1");
            return $stmt !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Método para obter informações do banco
    public function getInfo() {
        try {
            $stmt = $this->connection->query("SELECT DATABASE() as db_name, VERSION() as version");
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
    
    // Previne clonagem da instância
    private function __clone() {}
    
    // Previne deserialização da instância
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Função helper para obter conexão rapidamente
function getDB() {
    return Database::getInstance()->getConnection();
}

// Função para executar queries com tratamento de erro
function executeQuery($sql, $params = []) {
    try {
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Erro na query: " . $e->getMessage());
        throw new Exception("Erro ao executar operação no banco");
    }
}

// Função para buscar um registro
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

// Função para buscar múltiplos registros
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

// Função para inserir e retornar ID
function insertAndGetId($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return getDB()->lastInsertId();
}

// Teste de conexão (apenas para debug - remover em produção)
if (basename($_SERVER['PHP_SELF']) == 'database.php') {
    try {
        $db = Database::getInstance();
        $info = $db->getInfo();
        
        echo "<h2>✅ Conexão com banco estabelecida!</h2>";
        echo "<p><strong>Banco:</strong> " . $info['db_name'] . "</p>";
        echo "<p><strong>Versão MySQL:</strong> " . $info['version'] . "</p>";
        
        // Testar se as tabelas existem
        $tables = fetchAll("SHOW TABLES");
        echo "<p><strong>Tabelas encontradas:</strong> " . count($tables) . "</p>";
        
        foreach ($tables as $table) {
            echo "- " . array_values($table)[0] . "<br>";
        }
        
    } catch (Exception $e) {
        echo "<h2>❌ Erro de conexão</h2>";
        echo "<p>" . $e->getMessage() . "</p>";
    }
}
?>