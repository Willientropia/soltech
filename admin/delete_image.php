<?php
require_once 'auth_check.php';
$admin = checkAdminAuth();

include_once '../config/database.php';

if (!isset($_GET['id']) || !isset($_GET['project_id']) || !is_numeric($_GET['id']) || !is_numeric($_GET['project_id'])) {
    header('Location: manage_projects.php');
    exit;
}

$image_id = $_GET['id'];
$project_id = $_GET['project_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // 1. Buscar informações da imagem a ser deletada
    $query = "SELECT image_path, is_primary FROM project_images WHERE id = :id AND project_id = :project_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $image_id);
    $stmt->bindParam(':project_id', $project_id);
    $stmt->execute();
    
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($image) {
        $was_primary = $image['is_primary'];
        
        // 2. Deletar arquivo físico
        $file_path = '../upload/images/projects/' . $image['image_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // 3. Deletar registro do banco
        $delete_query = "DELETE FROM project_images WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $image_id);
        $delete_stmt->execute();
        
        // 4. Se a imagem deletada era a principal, definir a primeira restante como principal
        if ($was_primary) {
            // Primeiro, remover flag primary de todas as imagens restantes
            $remove_all_primary = "UPDATE project_images SET is_primary = CAST(0 AS BOOLEAN) WHERE project_id = :project_id";
            $remove_stmt = $db->prepare($remove_all_primary);
            $remove_stmt->bindParam(':project_id', $project_id);
            $remove_stmt->execute();
            
            // Definir a primeira imagem (menor order_position) como principal
            $set_new_primary = "UPDATE project_images 
                               SET is_primary = CAST(1 AS BOOLEAN) 
                               WHERE project_id = :project_id 
                               AND id = (
                                   SELECT id FROM project_images 
                                   WHERE project_id = :project_id 
                                   ORDER BY order_position ASC, id ASC 
                                   LIMIT 1
                               )";
            $primary_stmt = $db->prepare($set_new_primary);
            $primary_stmt->bindParam(':project_id', $project_id);
            $primary_stmt->execute();
        }
    }
    
    $db->commit();
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    // Em caso de erro, apenas redireciona
}

// Redirecionar de volta para a página de edição
header('Location: edit-project.php?id=' . $project_id . '&deleted=1');
exit;
?>