<?php
require_once 'config/db.php';

$pdo = getPDO();

try {
    // Verificar se a tabela admin tem a coluna ultimo_login
    $stmt = $pdo->query("SHOW COLUMNS FROM admin LIKE 'ultimo_login'");
    $coluna_existe = $stmt->fetch();
    
    if (!$coluna_existe) {
        $pdo->exec("ALTER TABLE admin ADD COLUMN ultimo_login DATETIME NULL AFTER nome");
        echo "✅ Coluna 'ultimo_login' adicionada à tabela admin.<br>";
    }
    
    // Verificar se já existe um admin com este email
    $stmt = $pdo->prepare("SELECT id FROM admin WHERE email = ?");
    $stmt->execute(['admin@gmail.com']);
    $admin_existente = $stmt->fetch();
    
    if ($admin_existente) {
        echo "❌ Já existe um administrador com o email admin@gmail.com<br>";
    } else {
        // Criar hash da senha
        $senhaHash = password_hash('Yassin', PASSWORD_BCRYPT);
        
        // Inserir novo admin
        $stmt = $pdo->prepare("INSERT INTO admin (email, senha, nome) VALUES (?, ?, ?)");
        $stmt->execute(['admin@gmail.com', $senhaHash, 'Administrador Yassin']);
        
        echo "✅ Administrador criado com sucesso!<br>";
        echo "📧 Email: admin@gmail.com<br>";
        echo "🔑 Senha: Yassin<br>";
        echo "👤 Nome: Administrador Yassin<br><br>";
    }
    
    // Listar todos os administradores
    echo "📋 Lista de Administradores:<br>";
    $admins = $pdo->query("SELECT id, email, nome, ultimo_login FROM admin")->fetchAll();
    
    foreach ($admins as $admin) {
        echo "ID: {$admin['id']} | Email: {$admin['email']} | Nome: {$admin['nome']} | Último Login: " . 
             ($admin['ultimo_login'] ? $admin['ultimo_login'] : 'Nunca') . "<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage();
}

echo "<br><br>⚠️ <strong>IMPORTANTE:</strong> Apague este arquivo após usar por questões de segurança!";
?>