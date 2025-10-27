<?php
// listar_estrutura.php - APENAS PARA DESENVOLVIMENTO - REMOVER EM PRODUÇÃO
session_start();

// Segurança básica - permitir apenas localhost
$allowed_ips = ['127.0.0.1', '::1'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    die('Acesso não permitido');
}

function listarDiretorio($dir, $nivel = 0, $ignorar = ['.', '..', '.git', 'node_modules', 'vendor']) {
    $estrutura = [];
    $itens = scandir($dir);
    
    foreach ($itens as $item) {
        if (in_array($item, $ignorar)) continue;
        
        $caminho = $dir . DIRECTORY_SEPARATOR . $item;
        $identacao = str_repeat('│   ', $nivel);
        
        if (is_dir($caminho)) {
            $estrutura[] = $identacao . '├── 📁 ' . $item . '/';
            $estrutura = array_merge($estrutura, listarDiretorio($caminho, $nivel + 1, $ignorar));
        } else {
            $extensao = pathinfo($item, PATHINFO_EXTENSION);
            $icones = [
                'php' => '🐘',
                'html' => '🌐',
                'css' => '🎨',
                'js' => '📜',
                'sql' => '🗃️',
                'md' => '📝',
                'json' => '📋'
            ];
            $icone = $icones[$extensao] ?? '📄';
            $estrutura[] = $identacao . '├── ' . $icone . ' ' . $item;
        }
    }
    
    return $estrutura;
}

$diretorio_raiz = __DIR__;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Estrutura do Projeto - Emprego MZ</title>
    <style>
        body {
            font-family: 'Consolas', 'Monaco', monospace;
            background: #1a1a1a;
            color: #e0e0e0;
            padding: 20px;
            line-height: 1.4;
        }
        .estrutura-container {
            background: #2d2d2d;
            border-radius: 8px;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .estrutura-item {
            margin: 2px 0;
        }
        .diretorio {
            color: #4fc3f7;
            font-weight: bold;
        }
        .arquivo {
            color: #e0e0e0;
        }
        .php { color: #8892bf; }
        .html { color: #e34c26; }
        .css { color: #2965f1; }
        .js { color: #f7df1e; }
        .sql { color: #f29111; }
        .header {
            text-align: center;
            margin-bottom: 20px;
            color: #bb86fc;
        }
    </style>
</head>
<body>
    <div class="estrutura-container">
        <div class="header">
            <h2>📁 Estrutura do Projeto: plataforma_emprego_mz</h2>
            <p>Caminho: <?php echo htmlspecialchars($diretorio_raiz); ?></p>
        </div>
        
        <pre class="estrutura">
📁 C:\xampp\htdocs\plataforma_emprego_mz/
<?php
$estrutura = listarDiretorio($diretorio_raiz);
foreach ($estrutura as $linha) {
    echo $linha . "\n";
}
?>
        </pre>
    </div>
</body>
</html>