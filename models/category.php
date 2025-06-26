<?php
// models/Category.php - Model para categorias
class Category {
    private $conn;
    private $table_name = "categories";

    public $id;
    public $name;
    public $slug;
    public $description;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Buscar todas as categorias
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}
?>