<?php
// config/database.php - Configuração do banco de dados
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        // Usar variáveis de ambiente para facilitar uso com Docker
        $this->host = getenv('DB_HOST') ?: 'soltech.mysql.dbaas.com.br';
        $this->db_name = getenv('DB_NAME') ?: 'soltech';
        $this->username = getenv('DB_USER') ?: 'soltech';
        $this->password = getenv('DB_PASS') ?: 'Wapsol10!';
        $this->port = getenv('DB_PORT') ?: '5432';
    }

    public function getConnection() {
        $this->conn = null;
        try {
            // A porta 3306 é padrão do MySQL, geralmente não precisa ser informada
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Erro de conexão: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>