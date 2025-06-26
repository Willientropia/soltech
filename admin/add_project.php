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
        // 1. Inserir na tabela `projects`
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['title'])));
        $query = "INSERT INTO projects (title, slug, category_id, description, detailed_description, featured, status)
                  VALUES (:title, :slug, :category_id, :description, :detailed_description, :featured, 'active')";
        $stmt = $db->prepare($query);

        $featured = isset($_POST['featured']) ? 1 : 0;
        $stmt->bindParam(':title', $_POST['title']);
        $stmt->bindParam(':slug', $slug);
        $stmt->bindParam(':category_id', $_POST['category_id']);
        $stmt->bindParam(':description', $_POST['description']);
        $stmt->bindParam(':detailed_description', $_POST['detailed_description']);
        $stmt->bindParam(':featured', $featured, PDO::PARAM_BOOL);
        $stmt->execute();
        $project_id = $db->lastInsertId();

        // 2. Inserir as especificações na tabela `project_specs`
        $specs = [
            'power' => $_POST['power'], 'panels' => $_POST['panels'],
            'area' => $_POST['area'], 'savings' => $_POST['savings'],
            'location' => $_POST['location'], 'year' => $_POST['year']
        ];
        $spec_query = "INSERT INTO project_specs (project_id, spec_name, spec_value) VALUES (:project_id, :spec_name, :spec_value)";
        $spec_stmt = $db->prepare($spec_query);

        foreach ($specs as $name => $value) {
            if (!empty(trim($value))) {
                $spec_stmt->execute([
                    ':project_id' => $project_id,
                    ':spec_name' => $name,
                    ':spec_value' => trim($value)
                ]);
            }
        }

        // 3. Lógica de Upload de imagens
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $upload_dir = '../upload/images/projects/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
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
                                          VALUES (:project_id, :image_path, :is_primary, :order_position)";
                            $img_stmt = $db->prepare($img_query);
                            $is_primary = ($key == 0) ? true : false;
                            $img_stmt->execute([
                                ':project_id' => $project_id, 
                                ':image_path' => $new_file_name,
                                ':is_primary' => $is_primary,
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
            gap: 1rem;
        }

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

        .file-upload-btn {
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 1rem;
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
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success-color);
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .container {
                padding: 0 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }
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

            <div class="form-group">
                <label for="detailed_description">Descrição Detalhada</label>
                <textarea id="detailed_description" name="detailed_description" placeholder="Descrição completa que aparece no modal" rows="4"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="power">Potência</label>
                    <input type="text" id="power" name="power" placeholder="ex: 500 kWp">
                </div>
                <div class="form-group">
                    <label for="panels">Número de Painéis</label>
                    <input type="text" id="panels" name="panels" placeholder="ex: 1.250 unidades">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="area">Área</label>
                    <input type="text" id="area" name="area" placeholder="ex: 3.200 m²">
                </div>
                <div class="form-group">
                    <label for="savings">Economia Mensal</label>
                    <input type="text" id="savings" name="savings" placeholder="ex: R$ 45.000/mês">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="location">Localização</label>
                    <input type="text" id="location" name="location" placeholder="ex: São Luís de Montes Belos - GO">
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
                    <p style="font-size: 0.9rem; color: #666;">Formatos aceitos: JPG, PNG, GIF, WEBP (máx. 5MB cada)</p>
                    <input type="file" id="images" name="images[]" multiple accept="image/*">
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
                    div.innerHTML = `
                        <img src="${event.target.result}" alt="${file.name}">
                        <button type="button" class="remove-preview" onclick="removePreviewItem(this, '${file.name}')">×</button>
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
    </script>
</body>
</html>