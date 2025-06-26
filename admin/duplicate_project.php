<?php
require_once 'auth_check.php';
$admin = checkAdminAuth();

include_once '../config/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_projects.php?error=invalid_id');
    exit;
}

$original_project_id = $_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // 1. Buscar dados do projeto original
    $query = "SELECT * FROM projects WHERE id = :id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $original_project_id);
    $stmt->execute();
    $original_project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$original_project) {
        throw new Exception('Projeto original não encontrado');
    }
    
    // 2. Criar novo projeto (cópia)
    $new_title = $original_project['title'] . ' (Cópia)';
    $new_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $new_title))) . '-' . time();
    
    $insert_query = "INSERT INTO projects (title, slug, category_id, description, detailed_description, featured, status, created_at)
                     VALUES (:title, :slug, :category_id, :description, :detailed_description, CAST(0 AS BOOLEAN), 'active', CURRENT_TIMESTAMP)";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':title', $new_title);
    $insert_stmt->bindParam(':slug', $new_slug);
    $insert_stmt->bindParam(':category_id', $original_project['category_id']);
    $insert_stmt->bindParam(':description', $original_project['description']);
    $insert_stmt->bindParam(':detailed_description', $original_project['detailed_description']);
    $insert_stmt->execute();
    
    $new_project_id = $db->lastInsertId();
    
    // 3. Copiar especificações
    $specs_query = "SELECT spec_name, spec_value FROM project_specs WHERE project_id = :project_id";
    $specs_stmt = $db->prepare($specs_query);
    $specs_stmt->bindParam(':project_id', $original_project_id);
    $specs_stmt->execute();
    $specs = $specs_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($specs) {
        $insert_spec_query = "INSERT INTO project_specs (project_id, spec_name, spec_value) VALUES (:project_id, :spec_name, :spec_value)";
        $insert_spec_stmt = $db->prepare($insert_spec_query);
        
        foreach ($specs as $spec) {
            $insert_spec_stmt->execute([
                ':project_id' => $new_project_id,
                ':spec_name' => $spec['spec_name'],
                ':spec_value' => $spec['spec_value']
            ]);
        }
    }
    
    // ***** CORREÇÃO APLICADA AQUI *****
    // 4. Copiar mídias (duplicar arquivos físicos)
    $media_query = "SELECT path, media_type, is_primary, order_position FROM project_media WHERE project_id = :project_id ORDER BY order_position ASC";
    $media_stmt = $db->prepare($media_query);
    $media_stmt->bindParam(':project_id', $original_project_id);
    $media_stmt->execute();
    $media_files = $media_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ***** CORREÇÃO APLICADA AQUI *****
    if ($media_files) {
        $insert_media_query = "INSERT INTO project_media (project_id, path, media_type, is_primary, order_position) VALUES (:project_id, :path, :media_type, CAST(:is_primary AS BOOLEAN), :order_position)";
        $insert_media_stmt = $db->prepare($insert_media_query);

        foreach ($media_files as $media) {
            $original_file_path = '../upload/images/projects/' . $media['path'];

            if (file_exists($original_file_path)) {
                // Gerar novo nome de arquivo
                $file_extension = pathinfo($media['path'], PATHINFO_EXTENSION);
                $new_media_name = $new_project_id . '_copy_' . uniqid() . '.' . $file_extension;
                $new_file_path = '../upload/images/projects/' . $new_media_name;

                // Copiar arquivo físico
                if (copy($original_file_path, $new_file_path)) {
                    // Inserir registro no banco
                    $insert_media_stmt->execute([
                        ':project_id'     => $new_project_id,
                        ':path'           => $new_media_name,
                        ':media_type'     => $media['media_type'], // <-- Copia o tipo da mídia
                        ':is_primary'     => $media['is_primary'], // <-- Copia o status de principal
                        ':order_position' => $media['order_position']
                    ]);
                }
            }
        }
    }
    
    $db->commit();
    
    // Redirecionar para edição do novo projeto
    header('Location: edit-project.php?id=' . $new_project_id . '&duplicated=1');
    exit;
    
} catch (Exception $e) {
    $db->rollBack();
    header('Location: manage_projects.php?error=' . urlencode('Erro ao duplicar projeto: ' . $e->getMessage()));
    exit;
}
?>