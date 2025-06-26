<?php
// update_image_order.php
require_once 'auth_check.php';
$admin = checkAdminAuth();

// Definir header JSON
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Ler dados JSON
$input_raw = file_get_contents('php://input');
if (empty($input_raw)) {
    echo json_encode(['success' => false, 'message' => 'Dados não recebidos']);
    exit;
}

$input = json_decode($input_raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'JSON inválido: ' . json_last_error_msg()]);
    exit;
}

// Validar dados recebidos
if (!isset($input['project_id']) || !isset($input['order'])) {
    echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não fornecidos']);
    exit;
}

if (!is_numeric($input['project_id']) || !is_array($input['order'])) {
    echo json_encode(['success' => false, 'message' => 'Tipos de dados inválidos']);
    exit;
}

$project_id = (int)$input['project_id'];
$order = $input['order'];

// Validar se há itens na ordem
if (empty($order)) {
    echo json_encode(['success' => false, 'message' => 'Lista de ordem vazia']);
    exit;
}

try {
    include_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Falha na conexão com o banco de dados');
    }
    
    $db->beginTransaction();
    
    // 1. Verificar se o projeto existe e pertence ao usuário logado
    $check_project = "SELECT id FROM projects WHERE id = :project_id AND status = 'active'";
    $check_stmt = $db->prepare($check_project);
    $check_stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        throw new Exception('Projeto não encontrado');
    }
    
    // 2. Remover flag primary de todas as imagens do projeto
    $remove_primary_query = "UPDATE project_media SET is_primary = FALSE WHERE project_id = :project_id";
    $remove_primary_stmt = $db->prepare($remove_primary_query);
    $remove_primary_stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
    $remove_primary_stmt->execute();
    
    // 3. Atualizar ordem e definir primeira como principal
    $update_query = "UPDATE project_media SET order_position = :order_position, is_primary = CAST(:is_primary AS BOOLEAN) WHERE id = :image_id AND project_id = :project_id";
    $update_stmt = $db->prepare($update_query);
    
    $updated_count = 0;
    
    foreach ($order as $item) {
        // Validar cada item
        if (!isset($item['image_id']) || !isset($item['order_position'])) {
            continue;
        }
        
        if (!is_numeric($item['image_id']) || !is_numeric($item['order_position'])) {
            continue;
        }
        
        $image_id = (int)$item['image_id'];
        $order_position = (int)$item['order_position'];
        
        // A primeira imagem (order_position = 0) será sempre a principal
        $is_primary = ($order_position === 0) ? 1 : 0;
        
        $update_stmt->execute([
            ':order_position' => $order_position,
            ':is_primary' => $is_primary,
            ':image_id' => $image_id,
            ':project_id' => $project_id
        ]);
        
        $updated_count += $update_stmt->rowCount();
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Ordem atualizada com sucesso',
        'updated_count' => $updated_count,
        'project_id' => $project_id
    ]);
    
} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage(),
        'file' => basename(__FILE__),
        'line' => __LINE__
    ]);
}
?>