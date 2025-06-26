<?php
require_once 'auth_check.php';
$admin = checkAdminAuth();

// Estatísticas básicas
try {
    include_once '../config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    // Contar projetos
    $stmt = $conn->query("SELECT COUNT(*) as total FROM projects WHERE status = 'active'");
    $total_projects = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Contar contatos
    $stmt = $conn->query("SELECT COUNT(*) as total FROM contacts WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'");
    $total_contacts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Contar categorias
    $stmt = $conn->query("SELECT COUNT(*) as total FROM categories");
    $total_categories = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (Exception $e) {
    // Em caso de erro, define os totais como 0 para evitar que a página quebre
    $total_projects = 0;
    $total_contacts = 0;
    $total_categories = 0;
    // Opcional: registrar o erro em um log para depuração
    // error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SOL TECH Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
        }

        .admin-header {
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #FFA500;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logout-btn {
            background: #FFA500;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .logout-btn:hover {
            background: #FF6B35;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .welcome-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #FFA500;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 1.1rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .action-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-5px);
        }

        .action-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #FFA500;
        }

        .action-btn {
            display: inline-block;
            background: linear-gradient(135deg, #FFA500, #FF6B35);
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,165,0,0.3);
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .container {
                padding: 1rem;
            }

            .stats-grid, .actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-solar-panel"></i> SOL TECH Admin
            </div>
            <div class="user-info">
                <span>Olá, <?php echo htmlspecialchars($admin['full_name']); ?>!</span>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="welcome-section">
            <h1>Dashboard Administrativo</h1>
            <p>Bem-vindo ao painel de controle da SOL TECH. Gerencie projetos, contatos e conteúdo do site.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-solar-panel"></i>
                </div>
                <div class="stat-number"><?php echo $total_projects; ?></div>
                <div class="stat-label">Projetos Ativos</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-number"><?php echo $total_contacts; ?></div>
                <div class="stat-label">Contatos (30 dias)</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-number"><?php echo $total_categories; ?></div>
                <div class="stat-label">Categorias</div>
            </div>
        </div>

        <div class="actions-grid">
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <h3>Adicionar Projeto</h3>
                <p>Cadastre um novo projeto na galeria</p>
                <a href="add_project.php" class="action-btn">Novo Projeto</a>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <h3>Gerenciar Projetos</h3>
                <p>Edite ou remova projetos existentes</p>
                <a href="manage_projects.php" class="action-btn">Gerenciar</a>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Contatos Recebidos</h3>
                <p>Visualize leads e mensagens</p>
                <a href="manage_contacts.php" class="action-btn">Ver Contatos</a>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-globe"></i>
                </div>
                <h3>Ver Site</h3>
                <p>Acesse o site público</p>
                <a href="../index.html" class="action-btn" target="_blank">Abrir Site</a>
            </div>
        </div>
    </div>
</body>
</html>