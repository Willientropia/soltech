<?php
require_once 'auth_check.php';
$admin = checkAdminAuth();

header('Content-Type: application/json');

include_once '../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['image_id']) || !isset($input['project_id']) || 
    !is_numeric($input['image_id']) || !is_numeric($input['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$image_id = $input['image_id'];
$project_id = $input['project_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // 1. Remover flag primary de todas as imagens do projeto
    $update_all = "UPDATE project_images SET is_primary = CAST(0 AS BOOLEAN) WHERE project_id = :project_id";
    $stmt_all = $db->prepare($update_all);
    $stmt_all->bindParam(':project_id', $project_id);
    $stmt_all->execute();
    
    // 2. Definir a imagem selecionada como principal
    $update_primary = "UPDATE project_images SET is_primary = CAST(1 AS BOOLEAN) WHERE id = :image_id AND project_id = :project_id";
    $stmt_primary = $db->prepare($update_primary);
    $stmt_primary->bindParam(':image_id', $image_id);
    $stmt_primary->bindParam(':project_id', $project_id);
    $stmt_primary->execute();
    
    if ($stmt_primary->rowCount() > 0) {
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Imagem principal definida']);
    } else {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Imagem não encontrada']);
    }
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}<?php
require_once 'auth_check.php';
$admin = checkAdminAuth();

header('Content-Type: application/json');

include_once '../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['image_id']) || !isset($input['project_id']) || 
    !is_numeric($input['image_id']) || !is_numeric($input['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$image_id = $input['image_id'];
$project_id = $input['project_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // 1. Remover flag primary de todas as imagens do projeto
    $update_all = "UPDATE project_images SET is_primary = CAST(0 AS BOOLEAN) WHERE project_id = :project_id";
    $stmt_all = $db->prepare($update_all);
    $stmt_all->bindParam(':project_id', $project_id);
    $stmt_all->execute();
    
    // 2. Definir a imagem selecionada como principal
    $update_primary = "UPDATE project_images SET is_primary = CAST(1 AS BOOLEAN) WHERE id = :image_id AND project_id = :project_id";
    $stmt_primary = $db->prepare($update_primary);
    $stmt_primary->bindParam(':image_id', $image_id);
    $stmt_primary->bindParam(':project_id', $project_id);
    $stmt_primary->execute();
    
    if ($stmt_primary->rowCount() > 0) {
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Imagem principal definida']);
    } else {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Imagem não encontrada']);
    }
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}