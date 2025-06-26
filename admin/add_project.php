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

$categories_stmt = $category->read();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Usar transação para garantir a integridade dos dados
    $db->beginTransaction();
    try {
        // 1. Inserir na tabela `projects` (sem detailed_description)
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['title'])));
        $query = "INSERT INTO projects (title, slug, category_id, description, featured, status)
                  VALUES (:title, :slug, :category_id, :description, CAST(:featured AS BOOLEAN), 'active')";
        $stmt = $db->prepare($query);

        $featured = isset($_POST['featured']) ? 1 : 0;
        $stmt->bindParam(':title', $_POST['title']);
        $stmt->bindParam(':slug', $slug);
        $stmt->bindParam(':category_id', $_POST['category_id']);
        $stmt->bindParam(':description', $_POST['description']);
        $stmt->bindParam(':featured', $featured, PDO::PARAM_INT);
        $stmt->execute();
        $project_id = $db->lastInsertId();

        // 2. Inserir as especificações com unidades formatadas
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

        // 3. Lógica de Upload de imagens (inalterada do seu script original)
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

                            // Define se é a mídia principal
                            $is_primary = ($key == 0) ? 1 : 0;

                            // Query para a tabela correta 'project_media'
                            $media_query = "INSERT INTO project_media (project_id, path, media_type, is_primary, order_position) 
                                        VALUES (:project_id, :path, :media_type, CAST(:is_primary AS BOOLEAN), :order_position)";

                            $media_stmt = $db->prepare($media_query);

                            $media_stmt->execute([
                                ':project_id'     => $project_id, 
                                ':path'           => $new_file_name,
                                ':media_type'     => $media_type,
                                ':is_primary'     => $is_primary,
                                ':order_position' => $key
                            ]);
                        }
                    }
                }
            }
        }

        $db->commit();
        $message = 'Projeto adicionado com sucesso!';
        $message_type = 'success';

    } catch (Exception $e) {
        $db->rollBack();
        $message = 'Erro: ' . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Projeto - SOL TECH Admin</title>
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

        /* Adicione este CSS dentro da sua tag <style> */

        .image-item {
            /* ... seus estilos existentes ... */
            transition: transform 0.2s ease-in-out; /* Adiciona uma transição suave */
        }

        /* Este estilo será aplicado ao item que está sendo arrastado */
        .image-item.dragging {
            opacity: 0.5;
            transform: scale(1.05); /* Levemente maior para dar destaque */
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
        .input-with-unit input { text-align: left; padding-right: 85px; /* Mais espaço para 'unidades' */ }
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

        .file-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 1rem;
        }

        .preview-item {
            position: relative;
            border-radius: 5px;
            overflow: hidden;
        }

        .preview-item img {
            width: 100%;
            height: 80px;
            object-fit: cover;
        }

        .remove-preview {
            position: absolute;
            top: 5px;
            right: 5px;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Buttons */
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,165,0,0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Messages */
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 5px solid;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-color: var(--success-color);
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-color: var(--danger-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content { flex-direction: column; gap: 1rem; }
            .container { padding: 0 1rem; }
            .page-title { font-size: 2rem; }
            .form-row { grid-template-columns: 1fr; }
            .btn-group { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-solar-panel"></i> SOL TECH Admin
            </div>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
            </a>
        </div>
    </div>

    <div class="container">
        <h1 class="page-title">Adicionar Novo Projeto</h1>

        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="form-container">
            <div class="form-group">
                <label for="title">Título do Projeto *</label>
                <input type="text" id="title" name="title" required>
            </div>

            <div class="form-group">
                <label for="category_id">Categoria *</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Selecione uma categoria</option>
                     <?php foreach ($categories as $cat): ?>
                         <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Descrição Resumida *</label>
                <textarea id="description" name="description" placeholder="Descrição que aparece na galeria" required></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="power">Potência</label>
                    <div class="input-with-unit">
                        <input type="number" step="0.01" id="power" name="power" placeholder="Ex: 15.5">
                        <span class="input-unit">kWp</span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="panels">Número de Painéis</label>
                     <div class="input-with-unit">
                        <input type="number" id="panels" name="panels" placeholder="Ex: 38">
                        <span class="input-unit">unidades</span>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="area">Área</label>
                    <div class="input-with-unit">
                        <input type="number" step="0.01" id="area" name="area" placeholder="Ex: 95">
                        <span class="input-unit">m²</span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="savings">Economia Mensal</label>
                    <div class="input-with-unit input-with-prefix">
                        <span class="input-prefix">R$</span>
                        <input type="number" step="0.01" id="savings" name="savings" placeholder="Ex: 850.00">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="location">Localização</label>
                    <input type="text" id="location" name="location" placeholder="Ex: São Luís de Montes Belos - GO">
                </div>
                <div class="form-group">
                    <label for="year">Ano</label>
                    <input type="number" id="year" name="year" min="2020" max="2099" value="<?php echo date('Y'); ?>">
                </div>
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="featured" name="featured" value="1">
                    <label for="featured">Destacar projeto na página inicial</label>
                </div>
            </div>

            <div class="form-group">
                <label>Imagens do Projeto</label>
                <div class="file-upload" onclick="document.getElementById('images').click()">
                    <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <p>Clique para selecionar imagens</p>
                    <p style="font-size: 0.9rem; color: #666;">Formatos aceitos: JPG, PNG, GIF, WEBP, MP4, MOV (máx. 100MB)</p>
                    <input type="file" id="images" name="images[]" multiple accept="image/*,video/*">
                </div>
                <div id="filePreview" class="file-preview"></div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Projeto
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // --- PARTE 1: PRÉ-VISUALIZAÇÃO DE NOVOS ARQUIVOS (código mantido) ---
            const fileInput = document.getElementById('images');
            const previewContainer = document.getElementById('filePreview');

            if (fileInput && previewContainer) {
                fileInput.addEventListener('change', function(event) {
                    previewContainer.innerHTML = '';
                    const files = event.target.files;
                    for (const file of files) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            let mediaElement;
                            if (file.type.startsWith('image/')) {
                                mediaElement = document.createElement('img');
                            } else if (file.type.startsWith('video/')) {
                                mediaElement = document.createElement('video');
                                mediaElement.muted = true;
                                mediaElement.autoplay = true;
                                mediaElement.loop = true;
                            }
                            if (mediaElement) {
                                mediaElement.src = e.target.result;
                                mediaElement.style.cssText = 'width: 120px; height: 90px; object-fit: cover; border-radius: 5px; margin: 5px;';
                                previewContainer.appendChild(mediaElement);
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // --- PARTE 2: NOVA LÓGICA DE DRAG & DROP ---
            const sortableContainer = document.getElementById('sortableImages');
            if (sortableContainer) {
                let draggedItem = null;

                // Evento quando você começa a arrastar um item
                sortableContainer.addEventListener('dragstart', e => {
                    if (e.target.classList.contains('image-item')) {
                        draggedItem = e.target;
                        // Adiciona a classe 'dragging' para dar o efeito visual
                        setTimeout(() => {
                            draggedItem.classList.add('dragging');
                        }, 0);
                    }
                });

                // Evento quando você solta o item
                sortableContainer.addEventListener('dragend', e => {
                    if (draggedItem) {
                        // Remove a classe e salva a nova ordem
                        draggedItem.classList.remove('dragging');
                        draggedItem = null;
                        saveImageOrder();
                    }
                });

                // Evento principal: acontece enquanto você move o item sobre a área
                sortableContainer.addEventListener('dragover', e => {
                    e.preventDefault(); // Essencial para permitir o 'drop'
                    
                    const afterElement = getDragAfterElement(sortableContainer, e.clientX);
                    
                    // Move o item arrastado em tempo real
                    if (draggedItem) {
                        if (afterElement == null) {
                            sortableContainer.appendChild(draggedItem);
                        } else {
                            sortableContainer.insertBefore(draggedItem, afterElement);
                        }
                    }
                });
            }
        });

        /**
         * Função auxiliar que descobre qual elemento está logo a seguir
         * ao cursor do rato, para saber onde inserir o item arrastado.
         * @param {HTMLElement} container O container onde os itens estão.
         * @param {number} x A posição horizontal do rato (clientX).
         */
        function getDragAfterElement(container, x) {
            const draggableElements = [...container.querySelectorAll('.image-item:not(.dragging)')];

            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = x - box.left - box.width / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        // --- PARTE 3: FUNÇÕES DE DELETAR E SALVAR (mantidas) ---
        function deleteMedia(mediaId) {
            if (confirm('Tem certeza que deseja remover esta mídia?')) {
                window.location.href = `delete_media.php?id=${mediaId}&project_id=<?php echo $project_id; ?>`;
            }
        }

        function saveImageOrder() {
            const imageItems = document.querySelectorAll('#sortableImages .image-item');
            const newOrder = Array.from(imageItems).map((item, index) => ({
                image_id: parseInt(item.dataset.imageId),
                order_position: index
            }));
            
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
                    // Atualiza o badge "Principal" no primeiro item da lista
                    document.querySelectorAll('.primary-badge').forEach(b => b.remove());
                    const firstItem = document.querySelector('.image-item');
                    if(firstItem) {
                        const badge = document.createElement('div');
                        badge.className = 'primary-badge';
                        badge.textContent = 'Principal';
                        firstItem.appendChild(badge);
                    }
                } else {
                    throw new Error(data.message || 'Erro ao salvar a ordem.');
                }
            })
            .catch(error => console.error('Erro ao salvar a ordem:', error));
        }
    </script>
</body>
</html>