<?php
// generate_hash.php
// Arquivo temporário para gerar um hash de senha seguro

$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h1>Gerador de Hash de Senha</h1>";
echo "<p>Use este hash gerado para o usuário 'admin' no seu arquivo <strong>database.sql</strong>.</p>";
echo "<hr>";
echo "<p><strong>Senha:</strong> " . htmlspecialchars($password) . "</p>";
echo "<p><strong>Hash Gerado:</strong></p>";
echo "<textarea readonly style='width: 100%; height: 60px; font-family: monospace; font-size: 16px;'>" . htmlspecialchars($hash) . "</textarea>";

?>