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
    
    // Buscar informações da imagem
    $query = "SELECT image_path FROM project_images WHERE id = :id AND project_id = :project_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $image_id);
    $stmt->bindParam(':project_id', $project_id);
    $stmt->execute();
    
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($image) {
        // Deletar arquivo físico
        $file_path = '../upload/images/projects/' . $image['image_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Deletar registro do banco
        $delete_query = "DELETE FROM project_images WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $image_id);
        $delete_stmt->execute();
    }
    
} catch (Exception $e) {
    // Em caso de erro, apenas redireciona
}

// Redirecionar de volta para a página de edição
header('Location: edit-project.php?id=' . $project_id . '&deleted=1');
exit;
?>