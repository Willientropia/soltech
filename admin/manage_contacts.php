<?php
require_once 'auth_check.php';
$admin = checkAdminAuth();

include_once '../config/database.php';

// Bloco para processar as ações (antes chamado via API)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $database = new Database();
    $db = $database->getConnection();
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? null;

    if (!$action) {
        echo json_encode(['success' => false, 'message' => 'Ação não especificada.']);
        exit;
    }

    try {
        switch ($action) {
            case 'update_status':
                $id = $data['id'];
                $status = $data['status'];
                $sql = "UPDATE contacts SET status = :status";
                if ($status === 'lixeira') {
                    $sql .= ", deleted_at = NOW()";
                } else {
                    $sql .= ", deleted_at = NULL";
                }
                $sql .= " WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute([':status' => $status, ':id' => $id]);
                echo json_encode(['success' => true]);
                break;

            case 'delete_permanent':
                $id = $data['id'];
                if ($id === 'all') {
                    $stmt = $db->prepare("DELETE FROM contacts WHERE status = 'lixeira'");
                } else {
                    $stmt = $db->prepare("DELETE FROM contacts WHERE id = :id AND status = 'lixeira'");
                    $stmt->bindParam(':id', $id);
                }
                $stmt->execute();
                echo json_encode(['success' => true]);
                break;
            
            default:
                echo json_encode(['success' => false, 'message' => 'Ação desconhecida.']);
                break;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
    }
    exit; // Termina a execução para não renderizar o HTML
}

// Lógica para exibir a página (inalterada)
$message = '';
$message_type = '';
$contacts = [];
$status_map = [
    'novo' => ['label' => 'Novo', 'class' => 'status-novo'],
    'entramos em contato' => ['label' => 'Em Contato', 'class' => 'status-contato'],
    'vendido' => ['label' => 'Vendido', 'class' => 'status-vendido'],
    'perdido' => ['label' => 'Perdido', 'class' => 'status-perdido'],
    'lixeira' => ['label' => 'Lixeira', 'class' => 'status-lixeira'],
];
$stats = array_fill_keys(array_keys($status_map), 0);

try {
    $database = new Database();
    $db = $database->getConnection();

    $db->query("DELETE FROM contacts WHERE status = 'lixeira' AND deleted_at < NOW() - INTERVAL '30 days'");

    $query = "SELECT status, COUNT(*) as count FROM contacts GROUP BY status";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($results as $status => $count) {
        if (isset($stats[$status])) {
            $stats[$status] = $count;
        }
    }

    $current_tab = $_GET['tab'] ?? 'novo';
    $contacts_query = "SELECT *, 
                       CASE 
                         WHEN status = 'lixeira' AND deleted_at IS NOT NULL 
                         THEN 30 - EXTRACT(DAY FROM NOW() - deleted_at)
                         ELSE NULL 
                       END as days_left
                       FROM contacts 
                       WHERE status = :status 
                       ORDER BY created_at DESC";
    $contacts_stmt = $db->prepare($contacts_query);
    $contacts_stmt->bindValue(':status', $current_tab);
    $contacts_stmt->execute();
    $contacts = $contacts_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message = "Erro ao buscar contatos: " . $e->getMessage();
    $message_type = 'error';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Contatos (Leads) - SOL TECH Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #FFA500; --secondary-color: #FF6B35; --dark-color: #1a1a1a;
            --light-color: #f5f5f5; --success-color: #4CAF50; --danger-color: #f44336;
            --info-color: #007bff; --lost-color: #6c757d;
        }
        body { font-family: 'Segoe UI', sans-serif; background: var(--light-color); color: var(--dark-color); margin: 0; }
        .header { background: linear-gradient(135deg, var(--dark-color), #2a2a2a); color: white; padding: 1rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; }
        .container { max-width: 1600px; margin: 2rem auto; padding: 0 2rem; }
        .page-title { font-size: 2.5rem; margin-bottom: 1rem; }
        .tabs { display: flex; flex-wrap: wrap; border-bottom: 1px solid #ccc; margin-bottom: 2rem; }
        .tab-link { padding: 12px 20px; cursor: pointer; border: none; background: none; font-size: 1rem; color: #666; position: relative; text-decoration: none; }
        .tab-link.active { color: var(--primary-color); font-weight: bold; }
        .tab-link.active::after { content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 3px; background: var(--primary-color); border-radius: 3px 3px 0 0; }
        .badge { background: #e0e0e0; color: #333; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; margin-left: 8px; font-weight: normal; }
        .tab-link.active .badge { background: var(--primary-color); color: white; }
        .table-container { background: white; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid #e9e9e9; vertical-align: middle; white-space: nowrap; }
        tr:last-child td { border-bottom: none; }
        .empty-state { padding: 3rem; text-align: center; color: #888; }
        
        .select-wrapper { position: relative; display: inline-block; }
        .status-select {
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
            border-radius: 8px; padding: 10px 35px 10px 15px;
            font-size: 0.9rem; font-weight: 500; cursor: pointer;
            transition: all 0.3s ease; border: 2px solid transparent;
        }
        .select-wrapper::after {
            content: '\f078'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; right: 15px; top: 50%;
            transform: translateY(-50%); pointer-events: none;
            transition: all 0.3s ease;
        }
        .status-novo { color: var(--info-color); background-color: #e6f2ff; }
        .status-contato { color: #b07000; background-color: #fff8e1; }
        .status-vendido { color: var(--success-color); background-color: #e8f5e9; }
        .status-perdido { color: var(--lost-color); background-color: #f1f3f5; }
        
        .actions-cell { display: flex; gap: 10px; align-items: center; }
        .action-btn { padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; color: white; transition: opacity 0.3s; font-size: 1rem; line-height: 1; }
        .btn-delete { background: var(--danger-color); }
        .btn-restore { background: var(--info-color); }
        
        .trash-container { text-align: right; margin-top: 1.5rem; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px); align-items: center; justify-content: center; }
        .modal-content { background-color: #fefefe; padding: 30px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 15px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .modal-content h3 { margin-top: 0; }
        .modal-content input { width: 100%; padding: 12px; margin: 15px 0; border: 1px solid #ccc; border-radius: 8px; font-size: 1rem; text-align: center; }
        
        /* Estilos atualizados para os botões do modal */
        .modal-buttons { display: flex; justify-content: center; gap: 1rem; align-items: center; margin-top: 1rem; }
        .btn-delete-confirm { background: var(--danger-color); color: white; padding: 12px 28px; border-radius: 8px; font-weight: bold; border: none; cursor: pointer; transition: background 0.3s; }
        .btn-delete-confirm:hover { background: #c9302c; }
        .btn-cancel { background: none; border: none; color: #666; text-decoration: underline; padding: 10px; cursor: pointer; font-size: 1rem; }
    </style>
</head>
<body>
    <div class="header">
        </div>

    <div class="container">
        <h1 class="page-title">Gerenciar Contatos (Leads)</h1>

        <div class="tabs">
            <?php foreach ($status_map as $status_key => $status_info):
                if ($status_key === 'lixeira') continue;
            ?>
                <a href="?tab=<?= urlencode($status_key) ?>" class="tab-link <?= $current_tab == $status_key ? 'active' : '' ?>">
                    <?= $status_info['label'] ?> <span class="badge"><?= $stats[$status_key] ?></span>
                </a>
            <?php endforeach; ?>
            <a href="?tab=lixeira" class="tab-link <?= $current_tab == 'lixeira' ? 'active' : '' ?>" style="margin-left: auto;">
                <i class="fas fa-trash"></i> Lixeira <span class="badge"><?= $stats['lixeira'] ?></span>
            </a>
        </div>
        
        <div class="table-container">
            <?php if (empty($contacts)): ?>
                <div class="empty-state"><h3>Nenhum contato nesta categoria.</h3></div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Data</th><th>Nome</th><th>Email / Telefone</th><th>Cidade</th><th>Mensagem</th>
                            <?php if ($current_tab === 'lixeira'): ?>
                                <th>Tempo Restante</th>
                            <?php endif; ?>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($contact['created_at'])) ?></td>
                                <td><?= htmlspecialchars($contact['name']) ?></td>
                                <td><?= htmlspecialchars($contact['email']) ?><br><?= htmlspecialchars($contact['phone']) ?></td>
                                <td><?= htmlspecialchars($contact['city'] ?: 'N/A') ?></td>
                                <td style="white-space: pre-wrap; max-width: 300px;"><?= htmlspecialchars($contact['message'] ?: 'Nenhuma') ?></td>
                                <?php if ($current_tab === 'lixeira'): ?>
                                    <td><i class="fas fa-clock"></i> <?= max(0, round($contact['days_left'])) ?> dias</td>
                                <?php endif; ?>
                                <td class="actions-cell">
                                    <?php if (in_array($contact['status'], ['novo', 'entramos em contato', 'vendido'])): ?>
                                        <div class="select-wrapper">
                                            <select class="status-select <?= $status_map[$contact['status']]['class'] ?>" onchange="updateStatus(this, <?= $contact['id'] ?>)">
                                                <option value="novo" <?= $contact['status'] == 'novo' ? 'selected' : '' ?>>Novo</option>
                                                <option value="entramos em contato" <?= $contact['status'] == 'entramos em contato' ? 'selected' : '' ?>>Em Contato</option>
                                                <option value="vendido" <?= $contact['status'] == 'vendido' ? 'selected' : '' ?>>Vendido</option>
                                                <option value="perdido">Perdido</option>
                                            </select>
                                        </div>
                                    <?php elseif ($contact['status'] === 'perdido'): ?>
                                        <strong class="status-perdido" style="padding: 10px 15px; border-radius: 8px; background-color: #f1f3f5;">Perdido</strong>
                                        <button onclick="updateStatus(this, <?= $contact['id'] ?>, 'lixeira')" class="action-btn btn-delete" title="Mover para Lixeira"><i class="fas fa-trash"></i></button>
                                    <?php elseif ($contact['status'] === 'lixeira'): ?>
                                        <button onclick="updateStatus(this, <?= $contact['id'] ?>, 'novo')" class="action-btn btn-restore" title="Restaurar"><i class="fas fa-undo"></i> Restaurar</button>
                                        <button onclick="showDeleteModal(<?= $contact['id'] ?>)" class="action-btn btn-delete" title="Excluir Permanentemente"><i class="fas fa-fire"></i> Excluir</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if ($current_tab === 'lixeira' && $stats['lixeira'] > 0): ?>
            <div class="trash-container">
                <button onclick="showEmptyTrashModal()" class="action-btn btn-delete"><i class="fas fa-dumpster-fire"></i> Esvaziar Lixeira</button>
            </div>
        <?php endif; ?>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Excluir Permanentemente</h3>
            <p id="modalText">Esta ação não pode ser desfeita. Para confirmar, digite "confirmar" e clique em deletar.</p>
            <input type="text" id="deleteConfirmInput" placeholder="confirmar" autocomplete="off">
            <div class="modal-buttons">
                <button onclick="confirmDelete()" class="btn-delete-confirm">Deletar</button>
                <button onclick="closeModal('deleteModal')" class="btn-cancel">Cancelar</button>
            </div>
        </div>
    </div>
    
    <script>
        let contactIdToDelete = null;

        function updateStatus(element, id, newStatus = null) {
            const status = newStatus || element.value;
            element.disabled = true;

            fetch('manage_contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_status', id: id, status: status })
            })
            .then(response => {
                if (!response.ok) throw new Error(`Erro de Rede: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(`Falha ao atualizar o status: ${data.message || 'Erro desconhecido.'}`);
                    element.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert(`Não foi possível se comunicar com o servidor. Erro: ${error.message}`);
                element.disabled = false;
            });
        }
        
        function confirmDelete() {
            const confirmInput = document.getElementById('deleteConfirmInput');
            if (confirmInput.value.toLowerCase() !== 'confirmar') {
                alert('A palavra de confirmação está incorreta.');
                return;
            }
            
            fetch('manage_contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_permanent', id: contactIdToDelete })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    window.location.href = '?tab=lixeira';
                } else {
                    alert('Erro ao deletar: ' + (data.message || 'Erro desconhecido.'));
                }
            });
        }

        function showDeleteModal(id) {
            contactIdToDelete = id;
            document.getElementById('modalTitle').innerText = 'Excluir Permanentemente';
            document.getElementById('modalText').innerText = 'Esta ação não pode ser desfeita. Para confirmar, digite "confirmar" e clique em deletar.';
            document.getElementById('deleteConfirmInput').value = '';
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function showEmptyTrashModal() {
            contactIdToDelete = 'all';
            document.getElementById('modalTitle').innerText = 'Esvaziar Lixeira';
            document.getElementById('modalText').innerText = 'TODOS os contatos na lixeira serão excluídos permanentemente. Esta ação não pode ser desfeita. Digite "confirmar" para prosseguir.';
            document.getElementById('deleteConfirmInput').value = '';
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
    </script>
</body>
</html>