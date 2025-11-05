<?php
/**
 * Configuração de Conexão ao Banco de Dados
 * Plataforma Emprego MZ
 */

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'plataforma_emprego_mz');
define('DB_USER', 'root');
define('DB_PASS', 'admin');  // Senha padrão do XAMPP é vazia
define('DB_CHARSET', 'utf8mb4');

// Opções do PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Criar conexão PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Definir timezone do MySQL para Moçambique (CAT - Central Africa Time)
    $pdo->exec("SET time_zone = '+02:00'");
    
} catch (PDOException $e) {
    // Log do erro (em produção, não mostre detalhes ao usuário)
    error_log("Erro de conexão ao banco de dados: " . $e->getMessage());
    
    // Exibir mensagem amigável
    die("
    <!DOCTYPE html>
    <html lang='pt-MZ'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Erro de Conexão</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                background: #F5F5F5;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
            }
            .error-container {
                background: white;
                border-radius: 12px;
                padding: 40px;
                max-width: 500px;
                box-shadow: 0 4px 16px rgba(0,0,0,0.1);
                text-align: center;
            }
            .error-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            h1 {
                font-size: 24px;
                color: #E74C3C;
                margin-bottom: 16px;
            }
            p {
                color: #5A5A5A;
                line-height: 1.6;
                margin-bottom: 12px;
            }
            .error-details {
                background: #FFF3E0;
                border: 1px solid #FFB74D;
                border-radius: 8px;
                padding: 16px;
                margin-top: 20px;
                text-align: left;
            }
            .error-details strong {
                color: #E65100;
            }
            code {
                background: #FFEBEE;
                padding: 2px 6px;
                border-radius: 4px;
                font-family: monospace;
                color: #C62828;
            }
            .checklist {
                text-align: left;
                margin-top: 20px;
                padding: 20px;
                background: #E8F5E9;
                border-radius: 8px;
            }
            .checklist h3 {
                color: #2E7D32;
                margin-bottom: 12px;
                font-size: 16px;
            }
            .checklist ul {
                list-style: none;
                padding-left: 0;
            }
            .checklist li {
                padding: 6px 0;
                color: #424242;
            }
            .checklist li:before {
                content: '✓ ';
                color: #2E7D32;
                font-weight: bold;
                margin-right: 8px;
            }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <div class='error-icon'>⚠️</div>
            <h1>Erro de Conexão ao Banco de Dados</h1>
            <p>Não foi possível conectar ao banco de dados. Por favor, verifique as configurações.</p>
            
            <div class='error-details'>
                <strong>Detalhes técnicos:</strong><br>
                <code>" . htmlspecialchars($e->getMessage()) . "</code>
            </div>
            
            <div class='checklist'>
                <h3>📋 Checklist de Verificação:</h3>
                <ul>
                    <li>O XAMPP está rodando?</li>
                    <li>O MySQL está iniciado no XAMPP?</li>
                    <li>O banco de dados <code>plataforma_emprego_mz</code> existe?</li>
                    <li>As credenciais em <code>/config/db.php</code> estão corretas?</li>
                    <li>O usuário <code>root</code> tem permissão de acesso?</li>
                </ul>
            </div>
        </div>
    </body>
    </html>
    ");
}

// Função auxiliar para obter a conexão PDO
function getPDO() {
    global $pdo;
    return $pdo;
}

// Função auxiliar para fechar conexão (opcional, PDO fecha automaticamente)
function fecharConexao() {
    global $pdo;
    $pdo = null;
}
