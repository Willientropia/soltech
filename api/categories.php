<?php
// api/categories.php - Endpoint para categorias
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include_once '../config/database.php';
include_once '../models/Category.php';

$database = new Database();
$db = $database->getConnection();

$category = new Category($db);
$stmt = $category->read();
$num = $stmt->rowCount();

if ($num > 0) {
    $categories_arr = array();
    $categories_arr["records"] = array();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        
        $category_item = array(
            "id" => $id,
            "name" => $name,
            "slug" => $slug,
            "description" => $description
        );

        array_push($categories_arr["records"], $category_item);
    }

    echo json_encode($categories_arr);
} else {
    echo json_encode(array("records" => array()));
}
?>