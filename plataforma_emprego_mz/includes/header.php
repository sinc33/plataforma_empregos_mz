<?php
// Este arquivo precisa da conexão com o banco de dados para buscar o nome do usuário logado
require_once __DIR__ . '/../config/db.php';
$pdo = getPDO();

// Calcular base URL para assets
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['SCRIPT_NAME']);
$base_url = rtrim($base_url, '/');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emprego MZ</title>
    
    <!-- Google Fonts - Ubuntu Moderno -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- CSS Global -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/style.css">
</head>
<body>
<header class="main-header">
    <div class="header-container">
        <a href="<?php echo $base_url; ?>/index.php" class="header-logo">
            <i data-lucide="briefcase" style="width: 32px; height: 32px;"></i>
            Emprego MZ
        </a>
        <nav class="header-nav">
            <a href="<?php echo $base_url; ?>/index.php">
                <i data-lucide="home" style="width: 18px; height: 18px;"></i>
                Início
            </a>
            <a href="<?php echo $base_url; ?>/vagas.php">
                <i data-lucide="briefcase" style="width: 18px; height: 18px;"></i>
                Todas as Vagas
            </a>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['user_type'] === 'empresa'): ?>
                    <a href="<?php echo $base_url; ?>/empresa/dashboard.php">
                        <i data-lucide="bar-chart" style="width: 18px; height: 18px;"></i>
                        Dashboard
                    </a>
                    <a href="<?php echo $base_url; ?>/empresa/publicar_vaga.php">
                        <i data-lucide="plus-circle" style="width: 18px; height: 18px;"></i>
                        Publicar Vaga
                    </a>
                <?php else: ?>
                    <a href="<?php echo $base_url; ?>/candidato/perfil.php">
                        <i data-lucide="user" style="width: 18px; height: 18px;"></i>
                        Meu Perfil
                    </a>
                    <a href="<?php echo $base_url; ?>/candidato/candidaturas.php">
                        <i data-lucide="file-text" style="width: 18px; height: 18px;"></i>
                        Minhas Candidaturas
                    </a>
                <?php endif; ?>
                
                <span class="header-welcome">
                    <i data-lucide="smile" style="width: 18px; height: 18px;"></i>
                    Olá, 
                    <?php 
                    if ($_SESSION['user_type'] === 'empresa') {
                        $stmt_empresa = $pdo->prepare("SELECT nome_empresa FROM empresa WHERE id = ?");
                        $stmt_empresa->execute([$_SESSION['user_id']]);
                        $empresa = $stmt_empresa->fetch();
                        echo htmlspecialchars($empresa['nome_empresa'] ?? 'Empresa');
                    } else {
                        $stmt_candidato = $pdo->prepare("SELECT nome_completo FROM candidato WHERE id = ?");
                        $stmt_candidato->execute([$_SESSION['user_id']]);
                        $candidato = $stmt_candidato->fetch();
                        echo htmlspecialchars($candidato['nome_completo'] ?? 'Candidato');
                    }
                    ?>
                </span>
                <a href="<?php echo $base_url; ?>/auth/logout.php">
                    <i data-lucide="log-out" style="width: 18px; height: 18px;"></i>
                    Sair
                </a>
            <?php else: ?>
                <a href="<?php echo $base_url; ?>/auth/login.php">
                    <i data-lucide="log-in" style="width: 18px; height: 18px;"></i>
                    Login
                </a>
                <a href="<?php echo $base_url; ?>/auth/register.php">
                    <i data-lucide="user-plus" style="width: 18px; height: 18px;"></i>
                    Registar
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>