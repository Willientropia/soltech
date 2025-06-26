<?php
// models/Contact.php - Model para contatos
class Contact {
    private $conn;
    private $table_name = "contacts";

    public $id;
    public $name;
    public $email;
    public $phone;
    public $city;
    public $consumption;
    public $message;
    public $source;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Criar novo contato
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  (name, email, phone, city, consumption, message, source)
                  VALUES
                  (:name, :email, :phone, :city, :consumption, :message, :source)";

        $stmt = $this->conn->prepare($query);

        // Sanitizar dados
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->city = htmlspecialchars(strip_tags($this->city));
        $this->message = htmlspecialchars(strip_tags($this->message));
        $this->source = htmlspecialchars(strip_tags($this->source));

        // Bind dos parâmetros
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':city', $this->city);
        $stmt->bindParam(':consumption', $this->consumption);
        $stmt->bindParam(':message', $this->message);
        $stmt->bindParam(':source', $this->source);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>