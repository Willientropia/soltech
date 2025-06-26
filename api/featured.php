<?php
// api/featured.php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

include_once '../config/database.php';
include_once '../models/Project.php';

$database = new Database();
$db = $database->getConnection();
$project = new Project($db);

// Função auxiliar para buscar detalhes - CORRIGIDA para priorizar imagem principal
function getProjectDetails($db, $project_id) {
    // Buscar a imagem principal ou a primeira disponível
    $img_stmt = $db->prepare("
        SELECT image_path 
        FROM project_images 
        WHERE project_id = :id 
        ORDER BY 
            CASE WHEN is_primary = true THEN 0 ELSE 1 END,
            order_position ASC, 
            id ASC
        LIMIT 1
    ");
    $img_stmt->execute([':id' => $project_id]);
    $image = $img_stmt->fetch(PDO::FETCH_ASSOC);
    return ['image' => $image ? $image['image_path'] : null];
}

// Usar a função getFeatured do modelo
$stmt = $project->getFeatured(4); // Pega até 4 projetos em destaque

$projects_arr = ["records" => []];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $details = getProjectDetails($db, $row['id']);
    $project_item = [
        "id" => $row['id'],
        "title" => $row['title'],
        "category_name" => $row['category_name'],
        "image" => $details['image']
    ];
    array_push($projects_arr["records"], $project_item);
}

echo json_encode($projects_arr);
?>