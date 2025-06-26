<?php
require_once 'auth_check.php';
$admin = checkAdminAuth();

include_once '../config/database.php';

if (!isset($_GET['id']) || !isset($_GET['project_id']) || !is_numeric($_GET['id']) || !is_numeric($_GET['project_id'])) {
    header('Location: manage_projects.php');
    exit;
}

$media_id = $_GET['id']; // Variável renomeada
$project_id = $_GET['project_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // 1. Buscar informações da mídia a ser deletada
    // ***** CORREÇÃO APLICADA AQUI *****
    $query = "SELECT path, is_primary FROM project_media WHERE id = :id AND project_id = :project_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $media_id);
    $stmt->bindParam(':project_id', $project_id);
    $stmt->execute();
    
    $media = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($media) {
        $was_primary = $media['is_primary'];
        
        // 2. Deletar arquivo físico
        $file_path = '../upload/images/projects/' . $media['path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // 3. Deletar registro do banco
        // ***** CORREÇÃO APLICADA AQUI *****
        $delete_query = "DELETE FROM project_media WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $media_id);
        $delete_stmt->execute();
        
        // 4. Se a mídia deletada era a principal, definir a primeira restante como principal
        if ($was_primary) {
            // Primeiro, remover flag primary de todas as mídias restantes
            // ***** CORREÇÃO APLICADA AQUI *****
            $remove_all_primary = "UPDATE project_media SET is_primary = FALSE WHERE project_id = :project_id";
            $remove_stmt = $db->prepare($remove_all_primary);
            $remove_stmt->bindParam(':project_id', $project_id);
            $remove_stmt->execute();
            
            // Definir a primeira mídia (menor order_position) como principal
            // ***** CORREÇÃO APLICADA AQUI *****
            $set_new_primary = "UPDATE project_media 
                               SET is_primary = TRUE 
                               WHERE id = (
                                   SELECT id FROM project_media 
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