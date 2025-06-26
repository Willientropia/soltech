<?php
require_once 'auth_check.php';
$admin = checkAdminAuth();

include_once '../config/database.php';
include_once '../models/Category.php';

$database = new Database();
$db = $database->getConnection();
$category = new Category($db);

$message = '';
$message_type = '';
$project = null;
$project_specs_raw = []; // Armazena dados brutos do BD
$project_specs_form = []; // Armazena dados limpos para o formulário
$project_media = []; // <-- Variável renomeada para mídias
$categories = [];

// Verificar se ID foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_projects.php');
    exit;
}

$project_id = $_GET['id'];

// Processar formulário de edição
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db->beginTransaction();
    try {
        // 1. Atualizar dados básicos do projeto (sem detailed_description)
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['title'])));
        $query = "UPDATE projects SET 
                  title = :title, 
                  slug = :slug, 
                  category_id = :category_id, 
                  description = :description, 
                  featured = :featured,
                  updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";
        $stmt = $db->prepare($query);

        $featured = isset($_POST['featured']) ? 1 : 0;
        $stmt->bindParam(':title', $_POST['title']);
        $stmt->bindParam(':slug', $slug);
        $stmt->bindParam(':category_id', $_POST['category_id']);
        $stmt->bindParam(':description', $_POST['description']);
        $stmt->bindParam(':featured', $featured, PDO::PARAM_INT);
        $stmt->bindParam(':id', $project_id);
        $stmt->execute();

        // 2. Atualizar especificações (Delete + Insert com formatação)
        $delete_specs = "DELETE FROM project_specs WHERE project_id = :project_id";
        $delete_stmt = $db->prepare($delete_specs);
        $delete_stmt->bindParam(':project_id', $project_id);
        $delete_stmt->execute();
        
        $specs = [
            'power' => $_POST['power'], 'panels' => $_POST['panels'],
            'area' => $_POST['area'], 'savings' => $_POST['savings'],
            'location' => $_POST['location'], 'year' => $_POST['year']
        ];
        $spec_query = "INSERT INTO project_specs (project_id, spec_name, spec_value) VALUES (:project_id, :spec_name, :spec_value)";
        $spec_stmt = $db->prepare($spec_query);

        foreach ($specs as $name => $value) {
            if (!empty(trim($value))) {
                 $formatted_value = trim($value);
                // Adiciona a unidade/prefixo se for um campo específico e numérico
                if (is_numeric($formatted_value)) {
                    switch ($name) {
                        case 'power': $formatted_value .= ' kWp'; break;
                        case 'panels': $formatted_value .= ' unidades'; break;
                        case 'area': $formatted_value .= ' m²'; break;
                        case 'savings': $formatted_value = 'R$ ' . number_format((float)$formatted_value, 2, ',', '.') . '/mês'; break;
                    }
                }
                $spec_stmt->execute([
                    ':project_id' => $project_id,
                    ':spec_name' => $name,
                    ':spec_value' => $formatted_value
                ]);
            }
        }

        // 3. Processar novas imagens (lógica original inalterada)
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $upload_dir = '../upload/images/projects/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name)) {
                    $file_name = $_FILES['images']['name'][$key];
                    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    // Lista de extensões permitidas para imagens e vídeos
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'webm'];

                    if (in_array($file_extension, $allowed_extensions)) {
                        $new_file_name = $project_id . '_' . uniqid() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_file_name;

                        if (move_uploaded_file($tmp_name, $upload_path)) {
                            // Define se é imagem ou vídeo
                            $video_types = ['mp4', 'mov', 'webm'];
                            $media_type = in_array($file_extension, $video_types) ? 'video' : 'image';

                            // Para novas mídias, não definir como principal
                            $is_primary = 0;
                            
                            // Encontrar a próxima posição
                            $order_query = "SELECT COALESCE(MAX(order_position), -1) + 1 as next_order FROM project_media WHERE project_id = :project_id";
                            $order_stmt = $db->prepare($order_query);
                            $order_stmt->bindParam(':project_id', $project_id);
                            $order_stmt->execute();
                            $next_order = $order_stmt->fetch(PDO::FETCH_ASSOC)['next_order'];

                            // Query para a tabela correta 'project_media'
                            $media_query = "INSERT INTO project_media (project_id, path, media_type, is_primary, order_position) 
                                        VALUES (:project_id, :path, :media_type, :is_primary, :order_position)";

                            $media_stmt = $db->prepare($media_query);

                            $media_stmt->execute([
                                ':project_id'     => $project_id, 
                                ':path'           => $new_file_name,
                                ':media_type'     => $media_type,
                                ':is_primary'     => $is_primary,
                                ':order_position' => $next_order
                            ]);
                        }
                    }
                }
            }
        }

        $db->commit();
        header('Location: edit-project.php?id=' . $project_id . '&success=1');
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $message = 'Erro: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Buscar dados do projeto para exibição
try {
    $categories_stmt = $category->read();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT p.*, c.name as category_name 
              FROM projects p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.id = :id AND p.status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $project_id);
    $stmt->execute();
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        header('Location: manage_projects.php');
        exit;
    }
    
    // Buscar especificações e limpar para o formulário
    $spec_query = "SELECT spec_name, spec_value FROM project_specs WHERE project_id = :project_id";
    $spec_stmt = $db->prepare($spec_query);
    $spec_stmt->bindParam(':project_id', $project_id);
    $spec_stmt->execute();
    $project_specs_raw = $spec_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Lógica de limpeza melhorada
    foreach ($project_specs_raw as $key => $value) {
        $cleaned_value = preg_replace('/[^\d,\.]/', '', $value); // Remove tudo exceto números, vírgulas e pontos
        $cleaned_value = str_replace('.', '', $cleaned_value); // Remove separador de milhar
        $cleaned_value = str_replace(',', '.', $cleaned_value); // Converte vírgula decimal para ponto

        switch ($key) {
            case 'panels':
            case 'year':
                // Garante que campos de painel e ano sejam sempre inteiros
                $project_specs_form[$key] = (int)$cleaned_value;
                break;
            case 'location':
                // Mantém a localização como texto original
                $project_specs_form[$key] = $value;
                break;
            default:
                // Para os outros campos (power, area, savings) mantém o valor numérico (pode ser decimal)
                $project_specs_form[$key] = (float)$cleaned_value;
                break;
        }
    }
    
    // Buscar mídias da nova tabela
    $media_query = "SELECT id, path, media_type, is_primary, order_position FROM project_media WHERE project_id = :project_id ORDER BY order_position ASC, id ASC";
    $media_stmt = $db->prepare($media_query);
    $media_stmt->bindParam(':project_id', $project_id);
    $media_stmt->execute();
    $project_media = $media_stmt->fetchAll(PDO::FETCH_ASSOC); 
        
} catch (Exception $e) {
    $message = 'Erro ao buscar projeto: ' . $e->getMessage();
    $message_type = 'error';
}

// Verificar se veio de um redirect
if (isset($_GET['success'])) {
    $message = 'Projeto atualizado com sucesso!';
    $message_type = 'success';
} elseif (isset($_GET['deleted'])) {
    $message = 'Imagem removida com sucesso!';
    $message_type = 'success';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Projeto - SOL TECH Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #FFA500;
            --secondary-color: #FF6B35;
            --dark-color: #1a1a1a;
            --light-color: #f5f5f5;
            --success-color: #4caf50;
            --danger-color: #f44336;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-color);
            color: var(--dark-color);
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--dark-color), #2a2a2a);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .back-btn {
            background: var(--primary-color);
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }

        .back-btn:hover {
            background: var(--secondary-color);
        }

        /* Main Container */
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            margin-bottom: 2rem;
            color: var(--dark-color);
        }

        /* Form */
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        /* Estilos para input com unidade */
        .input-with-unit { position: relative; display: flex; align-items: center; }
        .input-with-unit input { text-align: left; padding-right: 85px; }
        .input-unit { position: absolute; right: 15px; color: #888; font-size: 15px; pointer-events: none; }
        .input-with-prefix input { padding-left: 50px; text-align: left; }
        .input-prefix { position: absolute; left: 15px; color: #888; font-size: 15px; pointer-events: none; }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input {
            width: auto;
        }

        /* File Upload with Drag & Drop */
        .file-upload {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            background: #fafafa;
        }

        .file-upload:hover {
            border-color: var(--primary-color);
            background: rgba(255, 165, 0, 0.05);
        }

        .file-upload.drag-over {
            border-color: var(--primary-color);
            background: rgba(255, 165, 0, 0.1);
            border-style: solid;
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(255, 165, 0, 0.2);
        }

        .file-upload.drag-over::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255, 165, 0, 0.1), rgba(255, 107, 53, 0.1));
            border-radius: 6px;
            animation: pulse 1s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 0.8; }
        }

        .file-upload-icon {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .file-upload.drag-over .file-upload-icon {
            color: var(--primary-color);
            transform: scale(1.2);
        }

        .file-upload input {
            display: none;
        }

        .file-upload-text {
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }

        .file-upload.drag-over .file-upload-text {
            color: var(--primary-color);
            font-weight: 600;
        }

        .file-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }

        .preview-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            background: white;
        }

        .preview-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .preview-item img,
        .preview-item video {
            width: 100%;
            height: 90px;
            object-fit: cover;
            display: block;
        }

        .remove-preview {
            position: absolute;
            top: 5px;
            right: 5px;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .remove-preview:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        /* === DRAG & DROP STYLES === */
        .existing-images { 
            margin-bottom: 2rem; 
        }
        
        .existing-images h4 { 
            margin-bottom: 1rem; 
            color: var(--dark-color); 
        }
        
        .images-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); 
            gap: 1rem; 
            min-height: 100px;
            border: 2px dashed transparent;
            padding: 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .images-grid.drag-over {
            border-color: var(--primary-color);
            background: rgba(255, 165, 0, 0.05);
        }
        
        .image-item { 
            position: relative; 
            border-radius: 8px; 
            overflow: hidden; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            cursor: grab; 
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            background: white;
            transform: scale(1);
        }
        
        .image-item:active { 
            cursor: grabbing; 
        }
        
        .image-item.dragging { 
            opacity: 0.8; 
            transform: rotate(3deg) scale(1.05); 
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
            z-index: 1000;
            transition: none;
        }

        /* Efeito de hover e preview durante o drag */
        .image-item.drag-hover {
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(255, 165, 0, 0.3);
        }

        /* Placeholder para mostrar onde o item será inserido */
        .drag-placeholder {
            border: 2px dashed var(--primary-color);
            background: linear-gradient(45deg, rgba(255, 165, 0, 0.1), rgba(255, 107, 53, 0.1));
            border-radius: 8px;
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-weight: bold;
            animation: pulse 1.5s ease-in-out infinite;
            position: relative;
            overflow: hidden;
        }

        .drag-placeholder::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 165, 0, 0.3), transparent);
            animation: shimmer 2s ease-in-out infinite;
        }

        .drag-placeholder::after {
            content: '📍 Soltar aqui';
            font-size: 14px;
            z-index: 1;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* Animações suaves para reorganização */
        .image-item.moving {
            transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .image-item img, .image-item video { 
            width: 100%; 
            height: 120px; 
            object-fit: cover; 
            pointer-events: none; 
        }
        
        .image-placeholder { 
            width: 100%; 
            height: 120px; 
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 2rem; 
            color: white; 
            pointer-events: none; 
        }
        
        .image-actions { 
            position: absolute; 
            top: 5px; 
            right: 5px; 
            display: flex; 
            gap: 5px; 
        }
        
        .image-btn { 
            background: rgba(0,0,0,0.7); 
            color: white; 
            border: none; 
            border-radius: 3px; 
            padding: 5px 8px; 
            cursor: pointer; 
            font-size: 12px; 
            transition: background 0.3s ease; 
        }
        
        .image-btn:hover { 
            background: rgba(0,0,0,0.9); 
        }
        
        .drag-handle { 
            position: absolute; 
            top: 5px; 
            left: 5px; 
            background: rgba(0,0,0,0.7); 
            color: white; 
            padding: 5px; 
            border-radius: 3px; 
            cursor: grab; 
            font-size: 12px; 
        }
        
        .drag-handle:active { 
            cursor: grabbing; 
        }
        
        .primary-badge { 
            position: absolute; 
            bottom: 5px; 
            left: 5px; 
            background: var(--success-color); 
            color: white; 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-size: 10px; 
        }

        /* Buttons */
        .btn-group { display: flex; gap: 1rem; margin-top: 2rem; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255,165,0,0.3); }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }

        /* Messages */
        .message { padding: 15px; border-radius: 8px; margin-bottom: 2rem; }
        .message.success { background: #d4edda; color: #155724; border-left: 4px solid var(--success-color); }
        .message.error { background: #f8d7da; color: #721c24; border-left: 4px solid var(--danger-color); }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content { flex-direction: column; gap: 1rem; }
            .container { padding: 0 1rem; }
            .page-title { font-size: 2rem; }
            .form-row { grid-template-columns: 1fr; }
            .btn-group { flex-direction: column; }
            .images-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-solar-panel"></i> SOL TECH Admin
            </div>
            <a href="manage_projects.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Voltar aos Projetos
            </a>
        </div>
    </div>

    <div class="container">
        <h1 class="page-title">Editar Projeto</h1>

        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($project): ?>
        <form method="POST" enctype="multipart/form-data" class="form-container">
            <div class="form-group">
                <label for="title">Título do Projeto *</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($project['title']); ?>" required>
            </div>

            <div class="form-group">
                <label for="category_id">Categoria *</label>
                <select id="category_id" name="category_id" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($cat['id'] == $project['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Descrição Resumida *</label>
                <textarea id="description" name="description" placeholder="Descrição que aparece na galeria" required><?php echo htmlspecialchars($project['description']); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="power">Potência</label>
                    <div class="input-with-unit">
                        <input type="number" step="0.01" id="power" name="power" placeholder="Ex: 15.5" value="<?php echo htmlspecialchars($project_specs_form['power'] ?? ''); ?>">
                        <span class="input-unit">kWp</span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="panels">Número de Painéis</label>
                    <div class="input-with-unit">
                        <input type="number" id="panels" name="panels" placeholder="Ex: 38" value="<?php echo htmlspecialchars($project_specs_form['panels'] ?? ''); ?>">
                        <span class="input-unit">unidades</span>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="area">Área</label>
                     <div class="input-with-unit">
                        <input type="number" step="0.01" id="area" name="area" placeholder="Ex: 95" value="<?php echo htmlspecialchars($project_specs_form['area'] ?? ''); ?>">
                        <span class="input-unit">m²</span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="savings">Economia Mensal</label>
                    <div class="input-with-unit input-with-prefix">
                        <span class="input-prefix">R$</span>
                        <input type="number" step="0.01" id="savings" name="savings" placeholder="Ex: 850.00" value="<?php echo htmlspecialchars($project_specs_form['savings'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="location">Localização</label>
                    <input type="text" id="location" name="location" placeholder="Ex: São Luís de Montes Belos - GO" value="<?php echo htmlspecialchars($project_specs_form['location'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="year">Ano</label>
                    <input type="number" id="year" name="year" min="2020" max="2099" value="<?php echo htmlspecialchars($project_specs_form['year'] ?? date('Y')); ?>">
                </div>
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="featured" name="featured" value="1" <?php echo $project['featured'] ? 'checked' : ''; ?>>
                    <label for="featured">Destacar projeto na página inicial</label>
                </div>
            </div>

            <?php if (!empty($project_media)): ?>
            <div class="existing-images">
                <h4>Mídias Atuais <small style="color: #666;">(Arraste para reordenar)</small></h4>
                <div class="images-grid" id="sortableImages">
                    <?php foreach ($project_media as $media): ?>
                        <div class="image-item" data-image-id="<?php echo $media['id']; ?>" data-order="<?php echo $media['order_position']; ?>" draggable="true">

                            <?php if (file_exists('../upload/images/projects/' . $media['path'])): ?>

                            <?php if ($media['media_type'] === 'image'): ?>
                                <img src="../upload/images/projects/<?php echo htmlspecialchars($media['path']); ?>" alt="Imagem do Projeto">
                            <?php elseif ($media['media_type'] === 'video'): ?>
                                <video style="width: 100%; height: 120px; object-fit: cover;">
                                    <source src="../upload/images/projects/<?php echo htmlspecialchars($media['path']); ?>">
                                </video>
                            <?php endif; ?>

                            <?php else: ?>
                                <div class="image-placeholder">❓</div>
                            <?php endif; ?>

                            <?php if ($media['is_primary']): ?>
                                <div class="primary-badge">Principal</div>
                            <?php endif; ?>

                            <div class="drag-handle"><i class="fas fa-grip-vertical"></i></div>

                            <div class="image-actions">
                                <button type="button" class="image-btn" onclick="deleteMedia(<?php echo $media['id']; ?>)" title="Remover mídia">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                    <i class="fas fa-info-circle"></i> Arraste as mídias para alterar a ordem. A primeira será definida como principal.
                </p>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Adicionar Novas Imagens</label>
                <div class="file-upload" id="fileUploadArea">
                    <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="file-upload-text">
                        <p>Clique para adicionar mais imagens</p>
                        <p><strong>ou arraste arquivos aqui</strong></p>
                        <p style="font-size: 0.9rem; color: #666;">Formatos aceitos: JPG, PNG, GIF, WEBP, MP4, MOV (máx. 100MB)</p>
                    </div>
                    <input type="file" id="images" name="images[]" multiple accept="image/*,video/*">
                </div>
                <div id="filePreview" class="file-preview" style="display: none;"></div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
                <a href="manage_projects.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
        <?php endif; ?>
    </div>
    
    <script>
       // === DRAG & DROP IMPLEMENTATION WITH LIVE PREVIEW (STABLE VERSION) ===
        class DragDropManager {
            constructor() {
                this.container = document.getElementById('sortableImages');
                this.draggedElement = null;
                this.placeholder = null;
                this.isDragging = false;
                this.lastValidPosition = null;
                this.throttleTimer = null;
                this.init();
            }

            init() {
                if (!this.container) return;
                this.attachEventListeners();
            }

            attachEventListeners() {
                const items = this.container.querySelectorAll('.image-item');
                
                items.forEach(item => {
                    item.addEventListener('dragstart', this.handleDragStart.bind(this));
                    item.addEventListener('dragend', this.handleDragEnd.bind(this));
                });

                // Usa apenas um listener global otimizado
                document.addEventListener('dragover', this.throttledDragOver.bind(this));
            }

            // Throttle para evitar excesso de atualizações
            throttledDragOver(e) {
                if (!this.isDragging) return;
                
                e.preventDefault();
                
                // Limita a 60fps para suavidade
                if (this.throttleTimer) return;
                
                this.throttleTimer = setTimeout(() => {
                    this.handleGlobalDragOver(e);
                    this.throttleTimer = null;
                }, 16); // ~60fps
            }

            createPlaceholder() {
                if (this.placeholder) return this.placeholder;
                
                const placeholder = document.createElement('div');
                placeholder.className = 'drag-placeholder';
                placeholder.style.width = '150px';
                placeholder.style.height = '120px';
                placeholder.setAttribute('data-placeholder', 'true');
                return placeholder;
            }

            handleDragStart(e) {
                this.draggedElement = e.currentTarget;
                this.isDragging = true;
                
                e.currentTarget.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', ''); // Necessário para alguns browsers
                
                // Cria o placeholder uma única vez
                this.placeholder = this.createPlaceholder();
                
                // Esconde o elemento original após um pequeno delay
                setTimeout(() => {
                    if (this.draggedElement && this.isDragging) {
                        this.draggedElement.style.visibility = 'hidden';
                        // Insere o placeholder na posição original
                        this.container.insertBefore(this.placeholder, this.draggedElement.nextSibling);
                    }
                }, 50);
            }

            handleDragEnd(e) {
                this.isDragging = false;
                
                if (this.throttleTimer) {
                    clearTimeout(this.throttleTimer);
                    this.throttleTimer = null;
                }
                
                e.currentTarget.classList.remove('dragging');
                e.currentTarget.style.visibility = '';
                
                // Se o placeholder estiver no DOM, finaliza o drop
                if (this.placeholder && this.placeholder.parentNode) {
                    const placeholderParent = this.placeholder.parentNode;
                    const nextSibling = this.placeholder.nextSibling;
                    
                    // Remove o placeholder
                    this.placeholder.remove();
                    
                    // Insere o elemento na nova posição
                    if (nextSibling) {
                        placeholderParent.insertBefore(this.draggedElement, nextSibling);
                    } else {
                        placeholderParent.appendChild(this.draggedElement);
                    }
                    
                    // Salva a nova ordem
                    this.saveOrder();
                }
                
                this.cleanupDragStates();
                this.draggedElement = null;
                this.placeholder = null;
                this.lastValidPosition = null;
            }

            handleGlobalDragOver(e) {
                if (!this.isDragging || !this.draggedElement || !this.container) return;
                
                const containerRect = this.container.getBoundingClientRect();
                const mouseX = e.clientX;
                const mouseY = e.clientY;
                
                // Verifica se está dentro do container
                if (mouseX < containerRect.left || mouseX > containerRect.right ||
                    mouseY < containerRect.top || mouseY > containerRect.bottom) {
                    return;
                }
                
                this.updatePlaceholderPosition(mouseX, mouseY);
            }

            updatePlaceholderPosition(mouseX, mouseY) {
                const items = Array.from(this.container.querySelectorAll('.image-item:not(.dragging):not([data-placeholder])'));
                
                if (items.length === 0) {
                    if (!this.placeholder.parentNode) {
                        this.container.appendChild(this.placeholder);
                    }
                    return;
                }
                
                let insertPosition = null;
                let minDistance = Infinity;
                
                // Encontra a melhor posição baseada na distância
                items.forEach((item, index) => {
                    const rect = item.getBoundingClientRect();
                    const itemCenterX = rect.left + rect.width / 2;
                    const itemCenterY = rect.top + rect.height / 2;
                    
                    const distance = Math.sqrt(
                        Math.pow(mouseX - itemCenterX, 2) + 
                        Math.pow(mouseY - itemCenterY, 2)
                    );
                    
                    if (distance < minDistance) {
                        minDistance = distance;
                        
                        // Decide se insere antes ou depois baseado na posição do mouse
                        if (mouseX < itemCenterX) {
                            insertPosition = { element: item, before: true };
                        } else {
                            insertPosition = { element: item, before: false };
                        }
                    }
                });
                
                // Se a posição não mudou, não faz nada
                if (this.lastValidPosition && 
                    this.lastValidPosition.element === insertPosition.element && 
                    this.lastValidPosition.before === insertPosition.before) {
                    return;
                }
                
                this.lastValidPosition = insertPosition;
                
                // Move o placeholder para a nova posição
                if (insertPosition) {
                    if (insertPosition.before) {
                        this.container.insertBefore(this.placeholder, insertPosition.element);
                    } else {
                        const nextSibling = insertPosition.element.nextSibling;
                        if (nextSibling) {
                            this.container.insertBefore(this.placeholder, nextSibling);
                        } else {
                            this.container.appendChild(this.placeholder);
                        }
                    }
                }
                
                // Adiciona animação suave
                this.animateItemsMovement();
            }

            animateItemsMovement() {
                const items = this.container.querySelectorAll('.image-item:not(.dragging)');
                items.forEach(item => {
                    if (!item.classList.contains('moving')) {
                        item.classList.add('moving');
                        setTimeout(() => {
                            item.classList.remove('moving');
                        }, 300);
                    }
                });
            }

            cleanupDragStates() {
                const items = this.container.querySelectorAll('.image-item');
                items.forEach(item => {
                    item.classList.remove('drag-hover', 'moving');
                    item.style.visibility = '';
                });
                
                this.container.classList.remove('drag-over');
                
                if (this.placeholder && this.placeholder.parentNode) {
                    this.placeholder.remove();
                }
            }

            saveOrder() {
                const items = this.container.querySelectorAll('.image-item');
                const newOrder = Array.from(items).map((item, index) => ({
                    image_id: parseInt(item.dataset.imageId),
                    order_position: index
                }));
                
                this.showSaveStatus('Salvando ordem...', 'loading');
                
                fetch('update_image_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        project_id: <?php echo $project_id; ?>,
                        order: newOrder
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.showSaveStatus('Ordem salva!', 'success');
                        this.updatePrimaryBadge();
                    } else {
                        throw new Error(data.message || 'Erro ao salvar a ordem.');
                    }
                })
                .catch(error => {
                    console.error('Erro ao salvar a ordem:', error);
                    this.showSaveStatus('Erro ao salvar ordem', 'error');
                });
            }

            updatePrimaryBadge() {
                this.container.querySelectorAll('.primary-badge').forEach(badge => badge.remove());
                
                const firstItem = this.container.querySelector('.image-item');
                if (firstItem) {
                    const badge = document.createElement('div');
                    badge.className = 'primary-badge';
                    badge.textContent = 'Principal';
                    firstItem.appendChild(badge);
                }
            }

            showSaveStatus(message, type) {
                const existingStatus = document.querySelector('.save-status');
                if (existingStatus) existingStatus.remove();

                const status = document.createElement('div');
                status.className = `save-status ${type}`;
                status.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 12px 24px;
                    border-radius: 8px;
                    color: white;
                    font-weight: 500;
                    z-index: 9999;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                    transition: all 0.3s ease;
                `;
                
                switch(type) {
                    case 'loading':
                        status.style.background = '#007bff';
                        status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + message;
                        break;
                    case 'success':
                        status.style.background = '#28a745';
                        status.innerHTML = '<i class="fas fa-check"></i> ' + message;
                        break;
                    case 'error':
                        status.style.background = '#dc3545';
                        status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + message;
                        break;
                }

                document.body.appendChild(status);

                if (type !== 'loading') {
                    setTimeout(() => {
                        if (status.parentNode) {
                            status.style.opacity = '0';
                            status.style.transform = 'translateY(-10px)';
                            setTimeout(() => status.remove(), 300);
                        }
                    }, 3000);
                }
            }
        }

        // === FUNCIONALIDADE COMPLETA DE UPLOAD DE ARQUIVOS (CORRIGIDA) ===
        class FileUploadManager {
            constructor() {
                this.fileInput = document.getElementById('images');
                this.fileUploadArea = document.getElementById('fileUploadArea');
                this.previewContainer = document.getElementById('filePreview');
                this.selectedFiles = [];
                this.init();
            }

            init() {
                if (!this.fileInput || !this.fileUploadArea || !this.previewContainer) {
                    console.log('Elementos de upload não encontrados');
                    return;
                }
                this.attachEventListeners();
                console.log('FileUploadManager inicializado com sucesso');
            }

            attachEventListeners() {
                // CORREÇÃO: Click to upload - Previne conflito com eventos de drag
                this.fileUploadArea.addEventListener('click', (e) => {
                    // Só abre o seletor se não for durante um drag operation
                    if (!e.target.closest('input[type="file"]') && !this.isDragging) {
                        e.preventDefault();
                        e.stopPropagation();
                        this.fileInput.click();
                    }
                });

                // File input change
                this.fileInput.addEventListener('change', (event) => {
                    this.handleFiles(Array.from(event.target.files));
                });

                // CORREÇÃO: Drag & Drop events - Previne conflitos
                this.fileUploadArea.addEventListener('dragover', this.handleDragOver.bind(this));
                this.fileUploadArea.addEventListener('dragenter', this.handleDragEnter.bind(this));
                this.fileUploadArea.addEventListener('dragleave', this.handleDragLeave.bind(this));
                this.fileUploadArea.addEventListener('drop', this.handleDrop.bind(this));

                // CORREÇÃO: Prevent default apenas para esta área específica
                this.fileUploadArea.addEventListener('dragover', this.preventDefaults);
                this.fileUploadArea.addEventListener('drop', this.preventDefaults);
            }

            preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            handleDragEnter(e) {
                this.preventDefaults(e);
                this.isDragging = true;
                this.fileUploadArea.classList.add('drag-over');
            }

            handleDragOver(e) {
                this.preventDefaults(e);
                this.fileUploadArea.classList.add('drag-over');
            }

            handleDragLeave(e) {
                this.preventDefaults(e);
                // CORREÇÃO: Só remove se realmente saiu da área
                if (!this.fileUploadArea.contains(e.relatedTarget)) {
                    this.isDragging = false;
                    this.fileUploadArea.classList.remove('drag-over');
                }
            }

            handleDrop(e) {
                this.preventDefaults(e);
                this.isDragging = false;
                this.fileUploadArea.classList.remove('drag-over');
                
                const files = Array.from(e.dataTransfer.files);
                if (files.length > 0) {
                    this.handleFiles(files);
                }
            }

            handleFiles(files) {
                console.log('Processando arquivos:', files.length);
                
                // Filtrar apenas arquivos de mídia válidos
                const validFiles = files.filter(file => {
                    const validTypes = [
                        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
                        'video/mp4', 'video/mov', 'video/webm'
                    ];
                    return validTypes.includes(file.type);
                });

                if (validFiles.length !== files.length) {
                    this.showNotification('Alguns arquivos foram ignorados. Apenas imagens e vídeos são aceitos.', 'warning');
                }

                if (validFiles.length === 0) {
                    this.showNotification('Nenhum arquivo válido selecionado.', 'error');
                    return;
                }

                // CORREÇÃO: Adicionar aos arquivos selecionados
                this.selectedFiles = [...this.selectedFiles, ...validFiles];
                
                // Atualizar o input file
                this.updateFileInput();
                
                // Mostrar preview
                this.showPreview();
                
                this.showNotification(`${validFiles.length} arquivo(s) adicionado(s)!`, 'success');
            }

            updateFileInput() {
                // CORREÇÃO: Criar novo FileList
                const dt = new DataTransfer();
                this.selectedFiles.forEach(file => dt.items.add(file));
                this.fileInput.files = dt.files;
            }

            showPreview() {
                if (this.selectedFiles.length === 0) {
                    this.previewContainer.style.display = 'none';
                    return;
                }

                this.previewContainer.style.display = 'grid';
                this.previewContainer.innerHTML = '';
                
                this.selectedFiles.forEach((file, index) => {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'preview-item';
                    
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        let mediaElement;
                        if (file.type.startsWith('image/')) {
                            mediaElement = document.createElement('img');
                            mediaElement.src = e.target.result;
                            mediaElement.alt = file.name;
                        } else if (file.type.startsWith('video/')) {
                            mediaElement = document.createElement('video');
                            mediaElement.src = e.target.result;
                            mediaElement.muted = true;
                            mediaElement.title = file.name;
                        }

                        if (mediaElement) {
                            previewItem.appendChild(mediaElement);
                        }
                    };
                    reader.readAsDataURL(file);

                    // Botão de remover
                    const removeBtn = document.createElement('button');
                    removeBtn.className = 'remove-preview';
                    removeBtn.innerHTML = '×';
                    removeBtn.type = 'button';
                    removeBtn.title = 'Remover arquivo';
                    removeBtn.onclick = () => this.removeFile(index);
                    
                    previewItem.appendChild(removeBtn);
                    this.previewContainer.appendChild(previewItem);
                });
            }

            removeFile(index) {
                this.selectedFiles.splice(index, 1);
                this.updateFileInput();
                this.showPreview();
                this.showNotification('Arquivo removido', 'info');
            }

            showNotification(message, type = 'info') {
                // Remove notificação anterior
                const existing = document.querySelector('.upload-notification');
                if (existing) existing.remove();

                const notification = document.createElement('div');
                notification.className = 'upload-notification';
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 12px 20px;
                    border-radius: 8px;
                    color: white;
                    font-weight: 500;
                    z-index: 9999;
                    max-width: 300px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                    transition: all 0.3s ease;
                `;

                switch(type) {
                    case 'success':
                        notification.style.background = '#28a745';
                        notification.innerHTML = `<i class="fas fa-check"></i> ${message}`;
                        break;
                    case 'warning':
                        notification.style.background = '#ffc107';
                        notification.style.color = '#212529';
                        notification.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
                        break;
                    case 'error':
                        notification.style.background = '#dc3545';
                        notification.innerHTML = `<i class="fas fa-times"></i> ${message}`;
                        break;
                    default:
                        notification.style.background = '#007bff';
                        notification.innerHTML = `<i class="fas fa-info"></i> ${message}`;
                }

                document.body.appendChild(notification);

                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.style.opacity = '0';
                        notification.style.transform = 'translateY(-10px)';
                        setTimeout(() => notification.remove(), 300);
                    }
                }, 3000);
            }
        }

        // === INICIALIZAÇÃO ===
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM carregado, inicializando gerenciadores...');
            
            // Inicializar gerenciadores
            const dragManager = new DragDropManager();
            const uploadManager = new FileUploadManager();
        });

        // Função para deletar mídia
        function deleteMedia(mediaId) {
            if (confirm('Tem certeza que deseja remover esta mídia?')) {
                window.location.href = `delete_media.php?id=${mediaId}&project_id=<?php echo $project_id; ?>`;
            }
        }
    </script>
</body>
</html>