<?php
session_start();
require_once '../config/db.php';

// Verificar se o usuário está logado e é uma empresa
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'empresa') {
    header("Location: ../auth/login.php");
    exit;
}

 $pdo = getPDO();
 $empresa_id = $_SESSION['user_id'];

// Processar ações (desativar/reativar vaga)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao'])) {
        $vaga_id = (int)$_POST['vaga_id'];
        
        // Verificar se a vaga pertence à empresa
        $stmt_verificar = $pdo->prepare("SELECT id FROM vaga WHERE id = ? AND empresa_id = ?");
        $stmt_verificar->execute([$vaga_id, $empresa_id]);
        
        if ($stmt_verificar->fetch()) {
            try {
                switch ($_POST['acao']) {
                    case 'desativar':
                        $stmt = $pdo->prepare("UPDATE vaga SET ativa = FALSE WHERE id = ?");
                        $stmt->execute([$vaga_id]);
                        $sucesso = "Vaga desativada com sucesso!";
                        break;
                        
                    case 'reativar':
                        $stmt = $pdo->prepare("UPDATE vaga SET ativa = TRUE WHERE id = ?");
                        $stmt->execute([$vaga_id]);
                        $sucesso = "Vaga reativada com sucesso!";
                        break;
                        
                    case 'excluir':
                        // Verificar se há candidaturas antes de excluir
                        $stmt_candidaturas = $pdo->prepare("SELECT COUNT(*) FROM candidatura WHERE vaga_id = ?");
                        $stmt_candidaturas->execute([$vaga_id]);
                        $total_candidaturas = $stmt_candidaturas->fetchColumn();
                        
                        if ($total_candidaturas > 0) {
                            $erro = "Não é possível excluir uma vaga com candidaturas. Desative-a em vez disso.";
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM vaga WHERE id = ?");
                            $stmt->execute([$vaga_id]);
                            $sucesso = "Vaga excluída com sucesso!";
                        }
                        break;
                }
            } catch (PDOException $e) {
                $erro = "Erro ao processar ação: " . $e->getMessage();
            }
        } else {
            $erro = "Vaga não encontrada ou não pertence à sua empresa.";
        }
    }
}

// Buscar estatísticas da empresa
 $sql_estatisticas = "
    SELECT 
        COUNT(*) as total_vagas,
        SUM(CASE WHEN ativa = TRUE AND data_expiracao >= CURDATE() THEN 1 ELSE 0 END) as vagas_ativas,
        SUM(CASE WHEN ativa = FALSE OR data_expiracao < CURDATE() THEN 1 ELSE 0 END) as vagas_inativas,
        (SELECT COUNT(*) FROM candidatura c JOIN vaga v ON c.vaga_id = v.id WHERE v.empresa_id = ?) as total_candidaturas,
        (SELECT COUNT(DISTINCT candidato_id) FROM candidatura c JOIN vaga v ON c.vaga_id = v.id WHERE v.empresa_id = ?) as candidatos_unicos
    FROM vaga 
    WHERE empresa_id = ?
";

 $stmt_estatisticas = $pdo->prepare($sql_estatisticas);
 $stmt_estatisticas->execute([$empresa_id, $empresa_id, $empresa_id]);
 $estatisticas = $stmt_estatisticas->fetch();

// Buscar vagas da empresa com contagem de candidaturas
 $sql_vagas = "
    SELECT 
        v.*,
        COUNT(c.id) as total_candidaturas,
        SUM(CASE WHEN c.estado = 'submetida' THEN 1 ELSE 0 END) as candidaturas_novas,
        SUM(CASE WHEN c.estado = 'em_analise' THEN 1 ELSE 0 END) as em_analise,
        SUM(CASE WHEN c.estado = 'entrevista' THEN 1 ELSE 0 END) as entrevistas,
        SUM(CASE WHEN c.estado = 'rejeitada' THEN 1 ELSE 0 END) as rejeitadas,
        SUM(CASE WHEN c.estado = 'contratado' THEN 1 ELSE 0 END) as contratados
    FROM vaga v
    LEFT JOIN candidatura c ON v.id = c.vaga_id
    WHERE v.empresa_id = ?
    GROUP BY v.id
    ORDER BY v.data_publicacao DESC
";

 $stmt_vagas = $pdo->prepare($sql_vagas);
 $stmt_vagas->execute([$empresa_id]);
 $vagas = $stmt_vagas->fetchAll();

// Buscar informações da empresa
 $stmt_empresa = $pdo->prepare("SELECT nome_empresa, logotipo FROM empresa WHERE id = ?");
 $stmt_empresa->execute([$empresa_id]);
 $empresa = $stmt_empresa->fetch();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Empresa - Emprego MZ</title>
    
    <!-- Google Fonts - Ubuntu Moderno -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        /* ============================================
           🎨 MARRABENTA UI - Design System
           Ubuntu Moderno + Afro-Futurista Profissional
        ============================================ */
        
        :root {
            /* Cores Principais - Identidade Moçambicana */
            --verde-esperanca: #2B7A4B;
            --dourado-sol: #FFB03B;
            --azul-indico: #1E3A5F;
            --coral-vivo: #FF6B6B;
            --areia-quente: #F4E4C1;
            
            /* Neutros Suaves */
            --carvao: #2C3E50;
            --cinza-baobab: #95A5A6;
            --branco-marfim: #FAFAF8;
            --branco-puro: #FFFFFF;
            
            /* Gradientes Signature */
            --gradient-por-do-sol: linear-gradient(135deg, #FFB03B 0%, #FF6B6B 50%, #764ba2 100%);
            --gradient-oceano: linear-gradient(135deg, #1E3A5F 0%, #2B7A4B 100%);
            --gradient-terra: linear-gradient(135deg, #F4E4C1 0%, #FFB03B 100%);
            
            /* Sombras Coloridas (não cinzas!) */
            --shadow-soft: 0 2px 8px rgba(43, 122, 75, 0.08);
            --shadow-medium: 0 4px 16px rgba(43, 122, 75, 0.12);
            --shadow-strong: 0 8px 24px rgba(43, 122, 75, 0.16);
            --shadow-hover: 0 12px 32px rgba(255, 176, 59, 0.2);
            
            /* Espaçamentos Harmônicos */
            --space-xs: 0.5rem;
            --space-sm: 1rem;
            --space-md: 1.5rem;
            --space-lg: 2.5rem;
            --space-xl: 4rem;
            
            /* Tipografia Ubuntu Moderno */
            --font-body: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-heading: 'Poppins', 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-body);
            background: var(--branco-marfim);
            color: var(--carvao);
            line-height: 1.6;
        }

        /* ============================================
           🌅 HERO SECTION - Pôr do Sol de Maputo
        ============================================ */
        .hero {
            background: var(--gradient-oceano);
            color: var(--branco-puro);
            padding: var(--space-xl) var(--space-md);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        /* Padrão Capulana Sutil no Background */
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                repeating-linear-gradient(45deg, transparent, transparent 35px, rgba(255,255,255,0.03) 35px, rgba(255,255,255,0.03) 70px),
                repeating-linear-gradient(-45deg, transparent, transparent 35px, rgba(255,255,255,0.03) 35px, rgba(255,255,255,0.03) 70px);
            opacity: 0.5;
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h1 {
            font-family: var(--font-heading);
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 700;
            margin-bottom: var(--space-md);
        }

        .hero p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        /* ============================================
           🌊 OCEAN DIVIDER - Elemento Assinatura
        ============================================ */
        .ocean-divider {
            position: relative;
            width: 100%;
            overflow: hidden;
            line-height: 0;
        }

        .ocean-divider svg {
            position: relative;
            display: block;
            width: calc(100% + 1.3px);
            height: 60px;
        }

        /* ============================================
           📦 CONTAINER SYSTEM
        ============================================ */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-md);
        }

        .main-content {
            padding: var(--space-xl) 0;
        }

        /* ============================================
           📊 DASHBOARD HEADER
        ============================================ */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            flex-wrap: wrap;
            gap: var(--space-md);
        }

        .dashboard-title {
            font-family: var(--font-heading);
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--carvao);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .dashboard-subtitle {
            color: var(--cinza-baobab);
            font-size: 1rem;
            margin-top: var(--space-xs);
        }

        /* ============================================
           📈 STATS GRID
        ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        .stat-card {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-lg);
            box-shadow: var(--shadow-medium);
            text-align: center;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Padrão Capulana muito sutil no card */
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: 
                repeating-linear-gradient(45deg, transparent, transparent 20px, rgba(43, 122, 75, 0.02) 20px, rgba(43, 122, 75, 0.02) 40px);
            opacity: 0.3;
            pointer-events: none;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--dourado-sol);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--gradient-terra);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-md);
            box-shadow: var(--shadow-soft);
        }

        .stat-number {
            font-family: var(--font-heading);
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--gradient-por-do-sol);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
            margin-bottom: var(--space-xs);
        }

        .stat-label {
            color: var(--cinza-baobab);
            font-size: 1rem;
            font-weight: 500;
        }

        /* ============================================
           🎴 XIMA CARDS - Cards de Vagas
        ============================================ */
        .vagas-section {
            margin-bottom: var(--space-xl);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            flex-wrap: wrap;
            gap: var(--space-md);
        }

        .section-title {
            font-family: var(--font-heading);
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--carvao);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .vaga-card {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-lg);
            box-shadow: var(--shadow-medium);
            margin-bottom: var(--space-lg);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Padrão Capulana muito sutil no card */
        .vaga-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: 
                repeating-linear-gradient(45deg, transparent, transparent 20px, rgba(43, 122, 75, 0.02) 20px, rgba(43, 122, 75, 0.02) 40px);
            opacity: 0.3;
            pointer-events: none;
        }

        .vaga-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--dourado-sol);
        }

        .vaga-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-md);
        }

        .vaga-title-section {
            flex: 1;
            min-width: 0;
        }

        .vaga-title {
            font-family: var(--font-heading);
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--carvao);
            margin-bottom: var(--space-xs);
            line-height: 1.3;
        }

        .vaga-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s;
        }

        .vaga-title a:hover {
            color: var(--verde-esperanca);
        }

        .vaga-meta {
            color: var(--cinza-baobab);
            font-size: 0.9rem;
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-md);
            margin-bottom: var(--space-xs);
        }

        .vaga-meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .vaga-status {
            padding: var(--space-xs) var(--space-sm);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .status-ativa {
            background: rgba(43, 122, 75, 0.1);
            color: var(--verde-esperanca);
        }

        .status-inativa {
            background: rgba(255, 107, 107, 0.1);
            color: var(--coral-vivo);
        }

        .status-expirada {
            background: rgba(255, 176, 59, 0.1);
            color: #cc8a2e;
        }

        /* ============================================
           👥 CANDIDATURAS STATS
        ============================================ */
        .candidaturas-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: var(--space-sm);
            margin: var(--space-md) 0;
            padding: var(--space-md);
            background: var(--areia-quente);
            border-radius: 16px;
        }

        .candidatura-stat {
            text-align: center;
            padding: var(--space-sm);
            border-radius: 12px;
            background: var(--branco-puro);
            box-shadow: var(--shadow-soft);
        }

        .candidatura-number {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            font-weight: 700;
            display: block;
            margin-bottom: var(--space-xs);
        }

        .candidatura-label {
            font-size: 0.8rem;
            color: var(--cinza-baobab);
        }

        .no-candidaturas {
            padding: var(--space-md);
            background: var(--areia-quente);
            border-radius: 16px;
            text-align: center;
            color: var(--cinza-baobab);
        }

        /* ============================================
           🔘 BUTTONS - Sistema de Botões
        ============================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-xs);
            padding: var(--space-sm) var(--space-md);
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary {
            background: var(--gradient-oceano);
            color: var(--branco-puro);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-success {
            background: var(--verde-esperanca);
            color: var(--branco-puro);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-warning {
            background: var(--dourado-sol);
            color: var(--carvao);
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-danger {
            background: var(--coral-vivo);
            color: var(--branco-puro);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-secondary {
            background: var(--cinza-baobab);
            color: var(--branco-puro);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-group {
            display: flex;
            gap: var(--space-sm);
            flex-wrap: wrap;
        }

        /* ============================================
           🎭 ALERTAS
        ============================================ */
        .alert {
            padding: var(--space-md);
            border-radius: 12px;
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: flex-start;
            gap: var(--space-sm);
        }

        .alert-success {
            background: rgba(43, 122, 75, 0.1);
            color: var(--verde-esperanca);
            border-left: 4px solid var(--verde-esperanca);
        }

        .alert-danger {
            background: rgba(255, 107, 107, 0.1);
            color: var(--coral-vivo);
            border-left: 4px solid var(--coral-vivo);
        }

        /* ============================================
           🎭 EMPTY STATE
        ============================================ */
        .empty-state {
            text-align: center;
            padding: var(--space-xl);
            background: var(--branco-puro);
            border-radius: 20px;
            box-shadow: var(--shadow-medium);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: var(--space-md);
            color: var(--cinza-baobab);
        }

        .empty-state h3 {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            color: var(--carvao);
            margin-bottom: var(--space-sm);
        }

        .empty-state p {
            color: var(--cinza-baobab);
            font-size: 1.1rem;
            margin-bottom: var(--space-lg);
        }

        /* ============================================
           📱 NAVIGATION
        ============================================ */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            margin-bottom: var(--space-md);
            font-size: 0.9rem;
            color: var(--cinza-baobab);
        }

        .breadcrumb a {
            color: var(--azul-indico);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            transition: color 0.3s;
        }

        .breadcrumb a:hover {
            color: var(--verde-esperanca);
        }

        .vaga-actions {
            margin-top: var(--space-md);
            padding-top: var(--space-md);
            border-top: 1px solid var(--areia-quente);
        }

        /* ============================================
           📱 RESPONSIVE DESIGN
        ============================================ */
        @media (max-width: 768px) {
            .hero {
                padding: var(--space-lg) var(--space-md);
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-md);
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .vaga-header {
                flex-direction: column;
                gap: var(--space-sm);
            }

            .candidaturas-stats {
                grid-template-columns: repeat(3, 1fr);
            }

            .btn-group {
                flex-direction: column;
            }
        }

        /* ============================================
           ✨ MICRO-ANIMATIONS
        ============================================ */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .floating {
            animation: float 3s ease-in-out infinite;
        }

        /* Smooth scroll */
        html {
            scroll-behavior: smooth;
        }

        /* Selection colors */
        ::selection {
            background: var(--dourado-sol);
            color: var(--carvao);
        }

        /* Foco melhorado para acessibilidade */
        .btn:focus, .breadcrumb a:focus {
            outline: 3px solid var(--dourado-sol);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <!-- 🌅 HERO SECTION -->
    <div class="hero">
        <div class="hero-content">
            <h1>Dashboard da Empresa</h1>
            <p>Gerencie suas vagas e acompanhe as candidaturas em tempo real</p>
        </div>
    </div>

    <!-- 🌊 OCEAN DIVIDER -->
    <div class="ocean-divider">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                  style="fill: url(#gradient-oceano); opacity: 0.8;"></path>
            <defs>
                <linearGradient id="gradient-oceano" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" style="stop-color:#1E3A5F;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#2B7A4B;stop-opacity:1" />
                </linearGradient>
            </defs>
        </svg>
    </div>

    <!-- 📊 DASHBOARD CONTENT -->
    <div class="main-content">
        <div class="container">
            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <a href="../index.php">
                    <i data-lucide="home" style="width: 16px; height: 16px;"></i>
                    Início
                </a>
                <span>/</span>
                <span>Dashboard Empresa</span>
            </nav>

            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div>
                    <h2 class="dashboard-title">
                        <i data-lucide="bar-chart" style="width: 24px; height: 24px;"></i>
                        <?php echo htmlspecialchars($empresa['nome_empresa']); ?>
                    </h2>
                    <p class="dashboard-subtitle">Gerencie suas vagas e acompanhe as candidaturas</p>
                </div>
                <a href="criar_vaga.php" class="btn btn-success">
                    <i data-lucide="plus-circle" style="width: 18px; height: 18px;"></i>
                    Criar Nova Vaga
                </a>
            </div>

            <!-- Mensagens de Sucesso/Erro -->
            <?php if (isset($sucesso)): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" style="width: 20px; height: 20px; margin-top: 2px;"></i>
                    <div><?php echo htmlspecialchars($sucesso); ?></div>
                </div>
            <?php endif; ?>

            <?php if (isset($erro)): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" style="width: 20px; height: 20px; margin-top: 2px;"></i>
                    <div><?php echo htmlspecialchars($erro); ?></div>
                </div>
            <?php endif; ?>

            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="briefcase" style="width: 30px; height: 30px; color: var(--azul-indico);"></i>
                    </div>
                    <div class="stat-number"><?php echo $estatisticas['total_vagas']; ?></div>
                    <div class="stat-label">Total de Vagas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="check-circle" style="width: 30px; height: 30px; color: var(--verde-esperanca);"></i>
                    </div>
                    <div class="stat-number"><?php echo $estatisticas['vagas_ativas']; ?></div>
                    <div class="stat-label">Vagas Ativas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="users" style="width: 30px; height: 30px; color: var(--dourado-sol);"></i>
                    </div>
                    <div class="stat-number"><?php echo $estatisticas['total_candidaturas']; ?></div>
                    <div class="stat-label">Total de Candidaturas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="user-check" style="width: 30px; height: 30px; color: var(--coral-vivo);"></i>
                    </div>
                    <div class="stat-number"><?php echo $estatisticas['candidatos_unicos']; ?></div>
                    <div class="stat-label">Candidatos Únicos</div>
                </div>
            </div>

            <!-- Lista de Vagas -->
            <div class="vagas-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i data-lucide="list" style="width: 24px; height: 24px;"></i>
                        Minhas Vagas
                    </h2>
                </div>
                
                <?php if (empty($vagas)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i data-lucide="inbox" style="width: 64px; height: 64px;"></i>
                        </div>
                        <h3>Nenhuma vaga criada ainda</h3>
                        <p>Comece criando sua primeira vaga de emprego para encontrar talentos.</p>
                        <a href="criar_vaga.php" class="btn btn-success">
                            <i data-lucide="plus-circle" style="width: 18px; height: 18px;"></i>
                            Criar Primeira Vaga
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($vagas as $vaga): 
                        $esta_ativa = $vaga['ativa'] && strtotime($vaga['data_expiracao']) >= time();
                        $esta_expirada = !$vaga['ativa'] || strtotime($vaga['data_expiracao']) < time();
                    ?>
                        <div class="vaga-card">
                            <div class="vaga-header">
                                <div class="vaga-title-section">
                                    <h3 class="vaga-title">
                                        <a href="../vaga_detalhe.php?id=<?php echo $vaga['id']; ?>">
                                            <?php echo htmlspecialchars($vaga['titulo']); ?>
                                        </a>
                                    </h3>
                                    <div class="vaga-meta">
                                        <div class="vaga-meta-item">
                                            <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i>
                                            <?php echo htmlspecialchars($vaga['localizacao']); ?>
                                        </div>
                                        <div class="vaga-meta-item">
                                            <i data-lucide="tag" style="width: 16px; height: 16px;"></i>
                                            <?php echo htmlspecialchars($vaga['area']); ?>
                                        </div>
                                    </div>
                                    <div class="vaga-meta">
                                        <div class="vaga-meta-item">
                                            <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                                            Publicada: <?php echo date('d/m/Y', strtotime($vaga['data_publicacao'])); ?>
                                        </div>
                                        <div class="vaga-meta-item">
                                            <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                                            Expira: <?php echo date('d/m/Y', strtotime($vaga['data_expiracao'])); ?>
                                        </div>
                                    </div>
                                    <?php if ($vaga['salario_estimado']): ?>
                                        <div class="vaga-meta-item">
                                            <i data-lucide="dollar-sign" style="width: 16px; height: 16px;"></i>
                                            <?php echo number_format($vaga['salario_estimado'], 0, ',', ' '); ?> MT
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($esta_ativa): ?>
                                        <span class="vaga-status status-ativa">
                                            <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                                            Ativa
                                        </span>
                                    <?php elseif ($esta_expirada): ?>
                                        <span class="vaga-status status-expirada">
                                            <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                            Expirada
                                        </span>
                                    <?php else: ?>
                                        <span class="vaga-status status-inativa">
                                            <i data-lucide="pause-circle" style="width: 14px; height: 14px;"></i>
                                            Inativa
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Estatísticas de Candidaturas -->
                            <?php if ($vaga['total_candidaturas'] > 0): ?>
                                <div class="candidaturas-stats">
                                    <div class="candidatura-stat">
                                        <span class="candidatura-number"><?php echo $vaga['total_candidaturas']; ?></span>
                                        <span class="candidatura-label">Total</span>
                                    </div>
                                    <div class="candidatura-stat">
                                        <span class="candidatura-number" style="color: var(--azul-indico);"><?php echo $vaga['candidaturas_novas']; ?></span>
                                        <span class="candidatura-label">Novas</span>
                                    </div>
                                    <div class="candidatura-stat">
                                        <span class="candidatura-number" style="color: var(--dourado-sol);"><?php echo $vaga['em_analise']; ?></span>
                                        <span class="candidatura-label">Em Análise</span>
                                    </div>
                                    <div class="candidatura-stat">
                                        <span class="candidatura-number" style="color: #9b59b6;"><?php echo $vaga['entrevistas']; ?></span>
                                        <span class="candidatura-label">Entrevista</span>
                                    </div>
                                    <div class="candidatura-stat">
                                        <span class="candidatura-number" style="color: var(--coral-vivo);"><?php echo $vaga['rejeitadas']; ?></span>
                                        <span class="candidatura-label">Rejeitadas</span>
                                    </div>
                                    <div class="candidatura-stat">
                                        <span class="candidatura-number" style="color: var(--verde-esperanca);"><?php echo $vaga['contratados']; ?></span>
                                        <span class="candidatura-label">Contratados</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="no-candidaturas">
                                    <i data-lucide="inbox" style="width: 20px; height: 20px; margin-bottom: var(--space-xs);"></i>
                                    Nenhuma candidatura recebida ainda
                                </div>
                            <?php endif; ?>

                            <!-- Ações -->
                            <div class="vaga-actions">
                                <div class="btn-group">
                                    <?php if ($vaga['total_candidaturas'] > 0): ?>
                                        <a href="candidaturas.php?vaga_id=<?php echo $vaga['id']; ?>" class="btn btn-primary">
                                            <i data-lucide="users" style="width: 16px; height: 16px;"></i>
                                            Ver Candidaturas (<?php echo $vaga['total_candidaturas']; ?>)
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="editar_vaga.php?id=<?php echo $vaga['id']; ?>" class="btn btn-secondary">
                                        <i data-lucide="edit" style="width: 16px; height: 16px;"></i>
                                        Editar
                                    </a>
                                    
                                    <?php if ($esta_ativa): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="vaga_id" value="<?php echo $vaga['id']; ?>">
                                            <input type="hidden" name="acao" value="desativar">
                                            <button type="submit" class="btn btn-warning" 
                                                    onclick="return confirm('Tem certeza que deseja desativar esta vaga?')">
                                                <i data-lucide="pause-circle" style="width: 16px; height: 16px;"></i>
                                                Desativar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="vaga_id" value="<?php echo $vaga['id']; ?>">
                                            <input type="hidden" name="acao" value="reativar">
                                            <button type="submit" class="btn btn-success" 
                                                    onclick="return confirm('Tem certeza que deseja reativar esta vaga?')">
                                                <i data-lucide="play-circle" style="width: 16px; height: 16px;"></i>
                                                Reativar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($vaga['total_candidaturas'] == 0): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="vaga_id" value="<?php echo $vaga['id']; ?>">
                                            <input type="hidden" name="acao" value="excluir">
                                            <button type="submit" class="btn btn-danger" 
                                                    onclick="return confirm('Tem certeza que deseja excluir permanentemente esta vaga?')">
                                                <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                                                Excluir
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 🌊 OCEAN DIVIDER -->
    <div class="ocean-divider">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none" transform="rotate(180)">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                  style="fill: url(#gradient-oceano); opacity: 0.8;"></path>
        </svg>
    </div>

    <!-- ✨ MICRO-INTERACTION SCRIPT -->
    <script>
        // Inicializar ícones Lucide
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            
            // Animação de entrada dos cards
            const animateElements = document.querySelectorAll('.stat-card, .vaga-card');
            
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 100);
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            animateElements.forEach(element => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(element);
            });

            // Animação de contagem para estatísticas
            const animateCounter = (element) => {
                const target = parseInt(element.textContent);
                const duration = 2000;
                const step = target / (duration / 16);
                let current = 0;

                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) {
                        element.textContent = target;
                        clearInterval(timer);
                    } else {
                        element.textContent = Math.floor(current);
                    }
                }, 16);
            };

            const statNumbers = document.querySelectorAll('.stat-number');
            const statsObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        statsObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            statNumbers.forEach(stat => statsObserver.observe(stat));
            
            // Adicionar efeito de onda ao clicar nos botões
            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;

                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.style.position = 'absolute';
                    ripple.style.borderRadius = '50%';
                    ripple.style.background = 'rgba(255, 255, 255, 0.5)';
                    ripple.style.transform = 'scale(0)';
                    ripple.style.animation = 'ripple 0.6s ease-out';
                    ripple.style.pointerEvents = 'none';

                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);

                    setTimeout(() => ripple.remove(), 600);
                });
            });
            
            // Animação CSS para ripple
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>