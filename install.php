<?php
// install.php - Script de verificação e instalação
echo "<!DOCTYPE html>
<html>
<head>
    <title>SOL TECH - Verificação do Sistema</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>";

echo "<h1>🔧 SOL TECH - Verificação do Sistema</h1>";

// Verificar extensões PHP necessárias
echo "<div class='section'>";
echo "<h2>📦 Extensões PHP:</h2>";
$extensions = ['pdo', 'pdo_pgsql', 'json'];
$all_extensions_ok = true;

foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<span class='success'>✅ $ext: OK</span><br>";
    } else {
        echo "<span class='error'>❌ $ext: NÃO ENCONTRADA</span><br>";
        $all_extensions_ok = false;
    }
}

if (!$all_extensions_ok) {
    echo "<p class='error'><strong>AÇÃO NECESSÁRIA:</strong> Entre em contato com a Locaweb para ativar as extensões faltantes.</p>";
}
echo "</div>";

// Verificar permissões de escrita
echo "<div class='section'>";
echo "<h2>📁 Permissões de Diretórios:</h2>";
$dirs = ['upload', 'upload/images', 'upload/images/projects'];
$all_dirs_ok = true;

foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "<span class='info'>📁 $dir: CRIADO</span><br>";
        } else {
            echo "<span class='error'>❌ $dir: ERRO AO CRIAR</span><br>";
            $all_dirs_ok = false;
            continue;
        }
    }
    
    if (is_writable($dir)) {
        echo "<span class='success'>✅ $dir: ESCRITA OK</span><br>";
    } else {
        echo "<span class='error'>❌ $dir: SEM PERMISSÃO DE ESCRITA</span><br>";
        $all_dirs_ok = false;
    }
}

if (!$all_dirs_ok) {
    echo "<p class='error'><strong>AÇÃO NECESSÁRIA:</strong> Configure as permissões via painel da Locaweb ou FTP (chmod 755).</p>";
}
echo "</div>";

// Testar conexão com banco
echo "<div class='section'>";
echo "<h2>🗄️ Conexão com Banco de Dados:</h2>";
try {
    include_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    if ($db) {
        echo "<span class='success'>✅ Conexão PostgreSQL: OK</span><br>";
        
        // Testar se as tabelas existem
        $tables = ['categories', 'projects', 'contacts'];
        foreach ($tables as $table) {
            try {
                $stmt = $db->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                echo "<span class='success'>✅ Tabela '$table': OK ($count registros)</span><br>";
            } catch (Exception $e) {
                echo "<span class='error'>❌ Tabela '$table': NÃO ENCONTRADA</span><br>";
                echo "<p class='error'>Execute o script database.sql no seu banco PostgreSQL!</p>";
            }
        }
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Erro na conexão: " . $e->getMessage() . "</span><br>";
    echo "<p class='error'><strong>AÇÃO NECESSÁRIA:</strong> Verifique as credenciais em config/database.php</p>";
}
echo "</div>";

// Verificar estrutura de arquivos
echo "<div class='section'>";
echo "<h2>📋 Estrutura de Arquivos:</h2>";
$files = [
    'config/database.php',
    'models/Project.php',
    'models/Category.php',
    'models/Contact.php',
    'api/projects.php',
    'api/categories.php',
    'api/contact.php',
    '.htaccess'
];

$all_files_ok = true;
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<span class='success'>✅ $file: OK</span><br>";
    } else {
        echo "<span class='error'>❌ $file: NÃO ENCONTRADO</span><br>";
        $all_files_ok = false;
    }
}

if (!$all_files_ok) {
    echo "<p class='error'><strong>AÇÃO NECESSÁRIA:</strong> Faça upload de todos os arquivos via FTP.</p>";
}
echo "</div>";

// Testar endpoints da API
echo "<div class='section'>";
echo "<h2>🔗 Teste de Endpoints:</h2>";
$base_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

echo "<p><a href='{$base_url}/api/categories' target='_blank'>Testar API Categorias</a></p>";
echo "<p><a href='{$base_url}/api/projects' target='_blank'>Testar API Projetos</a></p>";
echo "</div>";

// Próximos passos
echo "<div class='section'>";
echo "<h2>🎯 Próximos Passos:</h2>";
echo "<ol>";
echo "<li>Configure as credenciais do banco em <strong>config/database.php</strong></li>";
echo "<li>Execute o script <strong>database.sql</strong> no seu PostgreSQL</li>";
echo "<li>Faça upload de todos os arquivos via FTP</li>";
echo "<li>Configure a URL da API em <strong>galeria.html</strong></li>";
echo "<li>Teste a galeria: <a href='galeria.html' target='_blank'>galeria.html</a></li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
?>