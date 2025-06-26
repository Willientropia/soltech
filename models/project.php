<?php
// models/Project.php - Model para projetos
class Project {
    private $conn;
    private $table_name = "projects";

    public $id;
    public $title;
    public $slug;
    public $category_id;
    public $description;
    public $detailed_description;
    public $power;
    public $panels;
    public $area;
    public $savings;
    public $location;
    public $year;
    public $status;
    public $featured;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Buscar todos os projetos com filtro opcional por categoria
    public function read($category_slug = null) {
        $query = "SELECT p.*, c.name as category_name, c.slug as category_slug 
                  FROM " . $this->table_name . " p
                  LEFT JOIN categories c ON p.category_id = c.id
                  WHERE p.status = 'active'";
        
        if ($category_slug && $category_slug !== 'all') {
            $query .= " AND c.slug = :category_slug";
        }
        
        $query .= " ORDER BY p.featured DESC, p.created_at DESC";

        $stmt = $this->conn->prepare($query);
        
        if ($category_slug && $category_slug !== 'all') {
            $stmt->bindParam(':category_slug', $category_slug);
        }
        
        $stmt->execute();
        return $stmt;
    }

    // Buscar projeto por ID
    public function readOne() {
        $query = "SELECT p.*, c.name as category_name, c.slug as category_slug 
                  FROM " . $this->table_name . " p
                  LEFT JOIN categories c ON p.category_id = c.id
                  WHERE p.id = :id AND p.status = 'active'
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->title = $row['title'];
            $this->slug = $row['slug'];
            $this->category_id = $row['category_id'];
            $this->description = $row['description'];
            $this->detailed_description = $row['detailed_description'];
            $this->power = $row['power'];
            $this->panels = $row['panels'];
            $this->area = $row['area'];
            $this->savings = $row['savings'];
            $this->location = $row['location'];
            $this->year = $row['year'];
            $this->status = $row['status'];
            $this->featured = $row['featured'];
            
            return $row;
        }
        return false;
    }

    // Buscar projetos em destaque
    public function getFeatured($limit = 4) {
        $query = "SELECT p.*, c.name as category_name, c.slug as category_slug 
                  FROM " . $this->table_name . " p
                  LEFT JOIN categories c ON p.category_id = c.id
                  WHERE p.status = 'active' AND p.featured = true
                  ORDER BY p.created_at DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }
}
?>