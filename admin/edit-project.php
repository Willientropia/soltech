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
$project_images = [];
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
                  featured = CAST(:featured AS BOOLEAN),
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
            
            $max_order_query = "SELECT COALESCE(MAX(order_position), -1) + 1 as next_order FROM project_images WHERE project_id = :project_id";
            $max_order_stmt = $db->prepare($max_order_query);
            $max_order_stmt->bindParam(':project_id', $project_id);
            $max_order_stmt->execute();
            $next_order = $max_order_stmt->fetch(PDO::FETCH_ASSOC)['next_order'];
            
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name)) {
                    $file_name = $_FILES['images']['name'][$key];
                    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (in_array($file_extension, $allowed_extensions)) {
                        $new_file_name = $project_id . '_' . uniqid() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_file_name;
                        if (move_uploaded_file($tmp_name, $upload_path)) {
                            $img_query = "INSERT INTO project_images (project_id, image_path, is_primary, order_position) 
                                          VALUES (:project_id, :image_path, CAST(:is_primary AS BOOLEAN), :order_position)";
                            $img_stmt = $db->prepare($img_query);
                            if (count($project_images) == 0 && $key == 0) {
                                $is_primary = 1;
                            } else {
                                $is_primary = 0;
}
                            $current_order = $next_order + $key;
                            
                            $img_stmt->execute([
                                ':project_id' => $project_id, 
                                ':image_path' => $new_file_name,
                                ':is_primary' => $is_primary,
                                ':order_position' => $current_order
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
    
    // ***** LÓGICA DE LIMPEZA MELHORADA *****
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
    
    // Buscar imagens
    $img_query = "SELECT id, image_path, is_primary, order_position FROM project_images WHERE project_id = :project_id ORDER BY order_position ASC, id ASC";
    $img_stmt = $db->prepare($img_query);
    $img_stmt->bindParam(':project_id', $project_id);
    $img_stmt->execute();
    $project_images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
    
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

        /* NOVO: Estilos para input com unidade */
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

        /* File Upload */
        .file-upload {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: border-color 0.3s ease;
            cursor: pointer;
        }

        .file-upload:hover {
            border-color: var(--primary-color);
        }

        .file-upload-icon {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 1rem;
        }

        .file-upload input {
            display: none;
        }

        /* Existing Images (CSS original mantido) */
        .existing-images { margin-bottom: 2rem; }
        .existing-images h4 { margin-bottom: 1rem; color: var(--dark-color); }
        .images-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; }
        .image-item { position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); cursor: grab; transition: all 0.3s ease; }
        .image-item:active { cursor: grabbing; }
        .image-item.dragging { opacity: 0.5; transform: rotate(5deg); box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
        .image-item.drag-over { border: 2px dashed var(--primary-color); background: rgba(255, 165, 0, 0.1); }
        .image-item img { width: 100%; height: 120px; object-fit: cover; pointer-events: none; }
        .image-placeholder { width: 100%; height: 120px; background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; pointer-events: none; }
        .image-actions { position: absolute; top: 5px; right: 5px; display: flex; gap: 5px; }
        .image-btn { background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 3px; padding: 5px 8px; cursor: pointer; font-size: 12px; transition: background 0.3s ease; }
        .image-btn:hover { background: rgba(0,0,0,0.9); }
        .drag-handle { position: absolute; top: 5px; left: 5px; background: rgba(0,0,0,0.7); color: white; padding: 5px; border-radius: 3px; cursor: grab; font-size: 12px; }
        .drag-handle:active { cursor: grabbing; }
        .primary-badge { position: absolute; bottom: 5px; left: 5px; background: var(--success-color); color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; }

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
                    <option value="">Selecione uma categoria</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['id']); ?>" 
                                <?php echo ($cat['id'] == $project['category_id']) ? 'selected' : ''; ?>>
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

            <?php if (!empty($project_images)): ?>
            <div class="existing-images">
                <h4>Imagens Atuais <small style="color: #666;">(Arraste para reordenar)</small></h4>
                <div class="images-grid sortable-images" id="sortableImages">
                    <?php foreach ($project_images as $image): ?>
                        <div class="image-item" data-image-id="<?php echo $image['id']; ?>" data-order="<?php echo $image['order_position']; ?>">
                            <?php if (file_exists('../upload/images/projects/' . $image['image_path'])): ?>
                                <img src="../upload/images/projects/<?php echo htmlspecialchars($image['image_path']); ?>" alt="Imagem do projeto">
                            <?php else: ?>
                                <div class="image-placeholder">📸</div>
                            <?php endif; ?>
                            
                            <?php if ($image['is_primary']): ?>
                                <div class="primary-badge">Principal</div>
                            <?php endif; ?>
                            
                            <div class="drag-handle">
                                <i class="fas fa-grip-vertical"></i>
                            </div>
                            
                            <div class="image-actions">
                                <button type="button" class="image-btn" onclick="deleteImage(<?php echo $image['id']; ?>)" title="Remover imagem">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                    <i class="fas fa-info-circle"></i> Arraste as imagens para alterar a ordem. A primeira imagem será automaticamente definida como principal.
                </p>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Adicionar Novas Imagens</label>
                <div class="file-upload" onclick="document.getElementById('images').click()">
                    <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <p>Clique para adicionar mais imagens</p>
                    <p style="font-size: 0.9rem; color: #666;">Formatos aceitos: JPG, PNG, GIF, WEBP (máx. 5MB cada)</p>
                    <input type="file" id="images" name="images[]" multiple accept="image/*">
                </div>
                <div id="filePreview" class="file-preview"></div>
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
        // Todo o JavaScript original está aqui, completo e inalterado.
        
        // Preview de novas imagens
        document.getElementById('images').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('filePreview');
            previewContainer.innerHTML = '';
            const files = Array.from(e.target.files);

            files.forEach((file, index) => {
                if (!file.type.startsWith('image/')) return;

                const reader = new FileReader();
                reader.onload = function(event) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.setAttribute('data-file-index', index);
                    div.style.cssText = 'display: inline-block; margin: 10px; position: relative;';
                    div.innerHTML = `
                        <img src="${event.target.result}" alt="${file.name}" style="width: 100px; height: 80px; object-fit: cover; border-radius: 5px;">
                        <button type="button" onclick="removePreviewItem(this, '${file.name}')" style="position: absolute; top: -5px; right: -5px; background: red; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center;">×</button>
                    `;
                    previewContainer.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        });

        function removePreviewItem(button, fileName) {
            const input = document.getElementById('images');
            const dataTransfer = new DataTransfer();
            const files = Array.from(input.files);

            files.forEach(file => {
                if (file.name !== fileName) {
                    dataTransfer.items.add(file);
                }
            });

            input.files = dataTransfer.files;
            button.parentElement.remove();
        }

        function deleteImage(imageId) {
            if (confirm('Tem certeza que deseja remover esta imagem?')) {
                window.location.href = `delete_image.php?id=${imageId}&project_id=<?php echo $project_id; ?>`;
            }
        }

        // Sistema de Drag & Drop para reordenação
        document.addEventListener('DOMContentLoaded', function() {
            const sortableContainer = document.getElementById('sortableImages');
            if (!sortableContainer) return;

            let draggedElement = null;
            let draggedIndex = null;

            const imageItems = sortableContainer.querySelectorAll('.image-item');
            imageItems.forEach((item, index) => {
                item.draggable = true;
                
                item.addEventListener('dragstart', function(e) {
                    draggedElement = this;
                    draggedIndex = index;
                    this.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });

                item.addEventListener('dragend', function(e) {
                    this.classList.remove('dragging');
                    draggedElement = null;
                    draggedIndex = null;
                    
                    imageItems.forEach(item => item.classList.remove('drag-over'));
                });

                item.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    
                    if (this !== draggedElement) {
                        this.classList.add('drag-over');
                    }
                });

                item.addEventListener('dragleave', function(e) {
                    this.classList.remove('drag-over');
                });

                item.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('drag-over');
                    
                    if (this !== draggedElement) {
                        const targetIndex = Array.from(sortableContainer.children).indexOf(this);
                        
                        if (draggedIndex < targetIndex) {
                            this.parentNode.insertBefore(draggedElement, this.nextSibling);
                        } else {
                            this.parentNode.insertBefore(draggedElement, this);
                        }
                        
                        saveImageOrder();
                    }
                });
            });
        });

        function saveImageOrder() {
            const imageItems = document.querySelectorAll('#sortableImages .image-item');
            const newOrder = [];
            
            imageItems.forEach((item, index) => {
                const imageId = item.dataset.imageId;
                if (imageId) {
                    newOrder.push({
                        image_id: parseInt(imageId),
                        order_position: index
                    });
                }
            });

            if (newOrder.length === 0) return;

            const loadingMsg = document.createElement('div');
            loadingMsg.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando ordem...';
            loadingMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #007bff; color: white; padding: 10px 20px; border-radius: 5px; z-index: 9999;';
            document.body.appendChild(loadingMsg);

            fetch('update_image_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    project_id: <?php echo $project_id; ?>,
                    order: newOrder
                })
            })
            .then(response => response.json())
            .then(data => {
                loadingMsg.remove();
                if (data.success) {
                    const successMsg = document.createElement('div');
                    successMsg.innerHTML = '<i class="fas fa-check"></i> Ordem atualizada!';
                    successMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #4caf50; color: white; padding: 10px 20px; border-radius: 5px; z-index: 9999;';
                    document.body.appendChild(successMsg);
                    updatePrimaryBadges();
                    setTimeout(() => successMsg.remove(), 3000);
                } else {
                    throw new Error(data.message || 'Erro ao salvar a ordem.');
                }
            })
            .catch(error => {
                if (loadingMsg.parentNode) loadingMsg.remove();
                const errorMsg = document.createElement('div');
                errorMsg.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Erro: ${error.message}`;
                errorMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #f44336; color: white; padding: 10px 20px; border-radius: 5px; z-index: 9999;';
                document.body.appendChild(errorMsg);
                setTimeout(() => errorMsg.remove(), 5000);
            });
        }

        function updatePrimaryBadges() {
            const imageItems = document.querySelectorAll('#sortableImages .image-item');
            
            imageItems.forEach((item, index) => {
                let existingBadge = item.querySelector('.primary-badge');
                if (existingBadge) existingBadge.remove();

                if (index === 0) {
                    const badge = document.createElement('div');
                    badge.className = 'primary-badge';
                    badge.textContent = 'Principal';
                    item.appendChild(badge);
                }
            });
        }
    </script>
</body>
</html>