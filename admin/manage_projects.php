<?php
require_once 'auth_check.php';
$admin = checkAdminAuth();

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';
$projects = [];

// Ações (deletar, destacar, etc.)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];
    
    switch ($action) {
        case 'delete':
            try {
                $stmt = $db->prepare("UPDATE projects SET status = 'deleted' WHERE id = :id");
                $stmt->bindParam(':id', $id);
                if ($stmt->execute()) {
                    $message = 'Projeto removido com sucesso!';
                    $message_type = 'success';
                }
            } catch (Exception $e) {
                $message = 'Erro ao remover projeto: ' . $e->getMessage();
                $message_type = 'error';
            }
            break;
            
        case 'toggle_featured':
            try {
                $stmt = $db->prepare("UPDATE projects SET featured = NOT featured WHERE id = :id");
                $stmt->bindParam(':id', $id);
                if ($stmt->execute()) {
                    $message = 'Status de destaque alterado!';
                    $message_type = 'success';
                }
            } catch (Exception $e) {
                $message = 'Erro ao alterar destaque: ' . $e->getMessage();
                $message_type = 'error';
            }
            break;
    }
}

try {
    $query = "SELECT p.*, c.name as category_name 
              FROM projects p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.status = 'active'
              ORDER BY p.featured DESC, p.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $projects_result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($projects_result) {
        foreach ($projects_result as $proj) {
            $spec_query = "SELECT spec_name, spec_value FROM project_specs WHERE project_id = :project_id";
            $spec_stmt = $db->prepare($spec_query);
            $spec_stmt->bindParam(':project_id', $proj['id']);
            $spec_stmt->execute();
            $specs = $spec_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $proj = array_merge($proj, $specs);

            // CORREÇÃO: Buscar a primeira imagem do projeto
            $img_query = "SELECT image_path FROM project_images WHERE project_id = :project_id ORDER BY order_position ASC, id ASC LIMIT 1";
            $img_stmt = $db->prepare($img_query);
            $img_stmt->bindParam(':project_id', $proj['id']);
            $img_stmt->execute();
            $image = $img_stmt->fetch(PDO::FETCH_ASSOC);
            $proj['image_path'] = $image ? $image['image_path'] : null;

            $projects[] = $proj;
        }
    }
} catch (Exception $e) {
    $message = "Erro ao buscar projetos: " . $e->getMessage();
    $message_type = 'error';
    $projects = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Projetos - SOL TECH Admin</title>
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
            --info-color: #007bff;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-color);
            color: var(--dark-color);
        }

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

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            margin-bottom: 2rem;
        }

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .add-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,165,0,0.3);
        }

        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .project-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .project-card:hover {
            transform: translateY(-5px);
        }

        .project-image {
            height: 200px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: rgba(255,255,255,0.7);
            overflow: hidden;
        }

        /* CORREÇÃO: Quando há imagem real */
        .project-image.has-image {
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        /* Placeholder apenas quando NÃO há imagem */
        .project-image:not(.has-image)::before {
            content: '📸';
            font-size: 4rem;
            color: rgba(255,255,255,0.7);
        }

        .project-content {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .project-category {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-bottom: 1rem;
            align-self: flex-start;
        }

        .project-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .project-description {
            color: #666;
            line-height: 1.5;
            margin-bottom: 1rem;
            flex-grow: 1;
        }

        .project-specs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-top: 1px solid #eee;
            padding-top: 1rem;
        }

        .spec-item {
            font-size: 0.9rem;
            color: #666;
        }
        
        .spec-item i {
            margin-right: 8px;
            color: var(--primary-color);
        }

        .project-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: auto;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .btn-edit { background: var(--info-color); color: white; }
        .btn-toggle { background: var(--success-color); color: white; }
        .btn-delete { background: var(--danger-color); color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }

        .featured-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ffd700;
            color: #333;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

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

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #ccc;
        }

        @media (max-width: 768px) {
            .projects-grid { grid-template-columns: 1fr; }
            .actions-bar { flex-direction: column; gap: 1rem; align-items: stretch; }
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
        <div class="page-title">Gerenciar Projetos</div>

        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="actions-bar">
            <div>
                <span>Total: <strong><?php echo count($projects); ?></strong> projetos ativos</span>
            </div>
            <a href="add_project.php" class="add-btn">
                <i class="fas fa-plus"></i> Adicionar Novo Projeto
            </a>
        </div>

        <?php if (empty($projects)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h3>Nenhum projeto encontrado</h3>
                <p>Adicione seu primeiro projeto para começar a alimentar a galeria.</p>
                <a href="add_project.php" class="add-btn" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Adicionar Primeiro Projeto
                </a>
            </div>
        <?php else: ?>
            <div class="projects-grid">
                <?php foreach ($projects as $proj): ?>
                    <div class="project-card">
                        <?php 
                        $imageClass = $proj['image_path'] ? 'has-image' : '';
                        $imageStyle = $proj['image_path'] ? 'background-image: url(../upload/images/projects/' . htmlspecialchars($proj['image_path']) . ');' : '';
                        ?>
                        <div class="project-image <?php echo $imageClass; ?>" style="<?php echo $imageStyle; ?>">
                            <?php if ($proj['featured']): ?>
                                <div class="featured-badge">
                                    <i class="fas fa-star"></i> Destaque
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="project-content">
                            <div class="project-category"><?php echo htmlspecialchars($proj['category_name']); ?></div>
                            <div class="project-title"><?php echo htmlspecialchars($proj['title']); ?></div>
                            <div class="project-description"><?php echo htmlspecialchars(substr($proj['description'], 0, 100)); ?></div>
                            
                            <div class="project-specs">
                                <div class="spec-item"><i class="fas fa-bolt"></i> <?php echo htmlspecialchars($proj['power'] ?? 'N/A'); ?></div>
                                <div class="spec-item"><i class="fas fa-coins"></i> <?php echo htmlspecialchars($proj['savings'] ?? 'N/A'); ?></div>
                                <div class="spec-item"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($proj['location'] ?? 'N/A'); ?></div>
                                <div class="spec-item"><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($proj['year'] ?? 'N/A'); ?></div>
                            </div>

                            <div class="project-actions">
                                <a href="edit-project.php?id=<?php echo $proj['id']; ?>" class="btn btn-edit">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <a href="?action=toggle_featured&id=<?php echo $proj['id']; ?>" class="btn btn-toggle" 
                                   onclick="return confirm('Tem certeza que deseja alterar o status de destaque deste projeto?')">
                                    <i class="fas fa-star"></i> <?php echo $proj['featured'] ? 'Remover Destaque' : 'Destacar'; ?>
                                </a>
                                <a href="?action=delete&id=<?php echo $proj['id']; ?>" class="btn btn-delete" 
                                   onclick="return confirm('Tem certeza que deseja remover este projeto? Esta ação não pode ser desfeita.')">
                                    <i class="fas fa-trash"></i> Remover
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>