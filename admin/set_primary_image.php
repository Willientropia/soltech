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

$image_id = $input['image_id']; // O nome da variável pode ser mantido por consistência da API
$project_id = $input['project_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // 1. Remover flag primary de todas as mídias do projeto
    // ***** CORREÇÃO APLICADA AQUI *****
    $update_all = "UPDATE project_media SET is_primary = FALSE WHERE project_id = :project_id";
    $stmt_all = $db->prepare($update_all);
    $stmt_all->bindParam(':project_id', $project_id);
    $stmt_all->execute();
    
    // 2. Definir a mídia selecionada como principal
    // ***** CORREÇÃO APLICADA AQUI *****
    $update_primary = "UPDATE project_media SET is_primary = TRUE WHERE id = :image_id AND project_id = :project_id";
    $stmt_primary = $db->prepare($update_primary);
    $stmt_primary->bindParam(':image_id', $image_id);
    $stmt_primary->bindParam(':project_id', $project_id);
    $stmt_primary->execute();
    
    if ($stmt_primary->rowCount() > 0) {
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Mídia principal definida']);
    } else {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Mídia não encontrada']);
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>