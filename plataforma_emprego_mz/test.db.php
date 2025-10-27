<?php
// test_db.php (versão corrigida)
require_once 'config/db.php';

try {
    $pdo = getPDO();
    echo "✅ Conexão bem-sucedida!<br>";
    
    // Testar se as tabelas existem
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📊 Tabelas encontradas: " . count($tables) . "<br>";
    foreach ($tables as $table) {
        echo " - " . $table . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>