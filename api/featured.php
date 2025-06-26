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
// ***** CORREÇÃO APLICADA AQUI *****
function getProjectDetails($db, $project_id) {
    // Busca a mídia principal (imagem ou vídeo)
    $media_stmt = $db->prepare("
        SELECT path, media_type 
        FROM project_media 
        WHERE project_id = :id 
        ORDER BY 
            is_primary DESC,
            order_position ASC, 
            id ASC
        LIMIT 1
    ");
    $media_stmt->execute([':id' => $project_id]);
    $media = $media_stmt->fetch(PDO::FETCH_ASSOC);
    // Retorna um array com os detalhes da mídia para ser usado no JSON
    return ['media' => $media ?: null];
}

// Usar a função getFeatured do modelo
$stmt = $project->getFeatured(4); // Pega até 4 projetos em destaque

$projects_arr = ["records" => []];
// ***** CORREÇÃO APLICADA AQUI *****
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $details = getProjectDetails($db, $row['id']);
    $project_item = [
        "id" => $row['id'],
        "title" => $row['title'],
        "category_name" => $row['category_name'],
        "media" => $details['media'] // <-- Usa a nova chave 'media'
    ];
    array_push($projects_arr["records"], $project_item);
}

echo json_encode($projects_arr);
?>