<?php
error_reporting(0);
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

include_once '../config/database.php';
include_once '../models/Project.php';

$database = new Database();
$db = $database->getConnection();

function getProjectDetails($db, $project_id) {
    $spec_stmt = $db->prepare("SELECT spec_name, spec_value FROM project_specs WHERE project_id = :id");
    $spec_stmt->execute([':id' => $project_id]);
    $specs = $spec_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $img_stmt = $db->prepare("SELECT image_path FROM project_images WHERE project_id = :id ORDER BY CASE WHEN is_primary = true THEN 0 ELSE 1 END, order_position ASC, id ASC");
    $img_stmt->execute([':id' => $project_id]);
    $images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);

    return ['specifications' => $specs, 'images' => $images];
}

$project = new Project($db);

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $project->id = $_GET['id'];
    $row = $project->readOne();

    if ($row) {
        $details = getProjectDetails($db, $row['id']);
        $project_item = [
            "id" => $row['id'], 
            "title" => $row['title'], 
            "slug" => $row['slug'],
            "category" => [
                "id" => $row['category_id'], 
                "name" => $row['category_name'], 
                "slug" => $row['category_slug']
            ],
            "description" => $row['description'],
            "specifications" => $details['specifications'],
            "images" => $details['images'],
            "featured" => (bool)$row['featured']
        ];
        echo json_encode($project_item);
    } else {
        http_response_code(404);
        echo json_encode(["message" => "Projeto não encontrado."]);
    }
} else {
    $category_slug = isset($_GET['category']) ? $_GET['category'] : null;
    $stmt = $project->read($category_slug);
    
    $projects_arr = ["records" => []];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $details = getProjectDetails($db, $row['id']);
        $project_item = [
            "id" => $row['id'], 
            "title" => $row['title'], 
            "slug" => $row['slug'],
            "category" => [
                "id" => $row['category_id'], 
                "name" => $row['category_name'], 
                "slug" => $row['category_slug']
            ],
            "description" => $row['description'],
            "specifications" => $details['specifications'],
            "images" => $details['images'],
            "featured" => (bool)$row['featured']
        ];
        array_push($projects_arr["records"], $project_item);
    }
    echo json_encode($projects_arr);
}
?>