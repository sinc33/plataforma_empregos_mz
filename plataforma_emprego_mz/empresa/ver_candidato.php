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
 $candidato_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verificar se o candidato se candidatou a alguma vaga da empresa
 $sql_verificar = "
    SELECT COUNT(*) as total 
    FROM candidatura c 
    JOIN vaga v ON c.vaga_id = v.id 
    WHERE c.candidato_id = ? AND v.empresa_id = ?
";

 $stmt_verificar = $pdo->prepare($sql_verificar);
 $stmt_verificar->execute([$candidato_id, $empresa_id]);
 $tem_acesso = $stmt_verificar->fetch()['total'] > 0;

if (!$tem_acesso) {
    header("Location: dashboard.php");
    exit;
}

// Buscar dados completos do candidato
 $sql_candidato = "
    SELECT c.*, u.email, u.data_registo, u.ultimo_login 
    FROM candidato c 
    JOIN utilizador u ON c.id = u.id 
    WHERE c.id = ?
";

 $stmt_candidato = $pdo->prepare($sql_candidato);
 $stmt_candidato->execute([$candidato_id]);
 $candidato = $stmt_candidato->fetch();

if (!$candidato) {
    header("Location: dashboard.php");
    exit;
}

// Buscar experiências do candidato
 $sql_experiencias = "SELECT * FROM experiencia WHERE candidato_id = ? ORDER BY data_inicio DESC";
 $stmt_experiencias = $pdo->prepare($sql_experiencias);
 $stmt_experiencias->execute([$candidato_id]);
 $experiencias = $stmt_experiencias->fetchAll();

// Buscar formações do candidato
 $sql_formacoes = "SELECT * FROM formacao WHERE candidato_id = ? ORDER BY data_inicio DESC";
 $stmt_formacoes = $pdo->prepare($sql_formacoes);
 $stmt_formacoes->execute([$candidato_id]);
 $formacoes = $stmt_formacoes->fetchAll();

// Buscar candidaturas deste candidato às vagas da empresa
 $sql_candidaturas = "
    SELECT c.*, v.titulo, v.id as vaga_id, c.data_candidatura, c.estado
    FROM candidatura c
    JOIN vaga v ON c.vaga_id = v.id
    WHERE c.candidato_id = ? AND v.empresa_id = ?
    ORDER BY c.data_candidatura DESC
";

 $stmt_candidaturas = $pdo->prepare($sql_candidaturas);
 $stmt_candidaturas->execute([$candidato_id, $empresa_id]);
 $candidaturas = $stmt_candidaturas->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil do Candidato - <?php echo htmlspecialchars($candidato['nome_completo']); ?> - Emprego MZ</title>
    
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
           👤 PERFIL HEADER
        ============================================ */
        .profile-header {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-xl);
            box-shadow: var(--shadow-strong);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            margin-bottom: var(--space-xl);
            display: flex;
            align-items: center;
            gap: var(--space-lg);
        }

        /* Padrão Capulana muito sutil no card */
        .profile-header::before {
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

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--branco-puro);
            box-shadow: var(--shadow-medium);
            flex-shrink: 0;
        }

        .profile-avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--gradient-terra);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--azul-indico);
            box-shadow: var(--shadow-medium);
            flex-shrink: 0;
        }

        .profile-info {
            flex: 1;
            min-width: 0;
        }

        .profile-name {
            font-family: var(--font-heading);
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--carvao);
            margin-bottom: var(--space-sm);
        }

        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-md);
            margin-bottom: var(--space-md);
            color: var(--cinza-baobab);
        }

        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .btn-cv {
            background: var(--verde-esperanca);
            color: var(--branco-puro);
            padding: var(--space-sm) var(--space-md);
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-soft);
        }

        .btn-cv:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        /* ============================================
           📋 SECTIONS
        ============================================ */
        .section {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-xl);
            box-shadow: var(--shadow-medium);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            margin-bottom: var(--space-xl);
        }

        /* Padrão Capulana muito sutil na seção */
        .section::before {
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

        .section:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--dourado-sol);
        }

        .section-title {
            font-family: var(--font-heading);
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--carvao);
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        /* ============================================
           📊 INFO GRID
        ============================================ */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
        }

        .info-item {
            margin-bottom: var(--space-md);
        }

        .info-label {
            font-weight: 600;
            color: var(--carvao);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            margin-bottom: var(--space-xs);
        }

        .info-value {
            color: var(--cinza-baobab);
            font-size: 1rem;
        }

        /* ============================================
           🏷️ COMPETÊNCIAS
        ============================================ */
        .competencias-container {
            margin-top: var(--space-md);
        }

        .competencias-tags {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-sm);
        }

        .competencia-tag {
            background: var(--areia-quente);
            color: var(--carvao);
            padding: var(--space-xs) var(--space-md);
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .competencia-tag:hover {
            background: var(--gradient-terra);
            color: var(--carvao);
            transform: translateY(-2px);
        }

        /* ============================================
           💼 EXPERIÊNCIAS E FORMAÇÃO
        ============================================ */
        .item-list {
            margin-top: var(--space-lg);
        }

        .item-card {
            background: var(--areia-quente);
            border-radius: 16px;
            padding: var(--space-lg);
            margin-bottom: var(--space-md);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .item-title {
            font-family: var(--font-heading);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--carvao);
            margin: 0 0 var(--space-sm) 0;
        }

        .item-subtitle {
            color: var(--azul-indico);
            font-weight: 600;
            margin: 0 0 var(--space-sm) 0;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .item-meta {
            color: var(--cinza-baobab);
            font-size: 0.9rem;
            margin: var(--space-xs) 0;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .item-description {
            color: var(--carvao);
            line-height: 1.6;
            margin: var(--space-md) 0 0 0;
        }

        /* ============================================
           📋 CANDIDATURAS
        ============================================ */
        .candidatura-card {
            background: var(--branco-puro);
            border-radius: 16px;
            padding: var(--space-lg);
            margin-bottom: var(--space-md);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--areia-quente);
        }

        .candidatura-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }

        .candidatura-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-md);
        }

        .candidatura-title {
            font-family: var(--font-heading);
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--carvao);
            margin: 0 0 var(--space-xs) 0;
        }

        .candidatura-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s;
        }

        .candidatura-title a:hover {
            color: var(--verde-esperanca);
        }

        .candidatura-meta {
            color: var(--cinza-baobab);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .candidatura-content {
            background: var(--areia-quente);
            border-radius: 12px;
            padding: var(--space-md);
            margin: var(--space-md) 0 0 0;
        }

        .candidatura-label {
            font-weight: 600;
            color: var(--carvao);
            margin-bottom: var(--space-xs);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .candidatura-text {
            color: var(--carvao);
            line-height: 1.5;
        }

        .estado-badge {
            padding: var(--space-xs) var(--space-sm);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .status-submetida {
            background: rgba(30, 58, 95, 0.1);
            color: var(--azul-indico);
        }

        .status-em_analise {
            background: rgba(255, 176, 59, 0.1);
            color: #cc8a2e;
        }

        .status-entrevista {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .status-rejeitada {
            background: rgba(255, 107, 107, 0.1);
            color: var(--coral-vivo);
        }

        .status-contratado {
            background: rgba(43, 122, 75, 0.1);
            color: var(--verde-esperanca);
        }

        /* ============================================
           🎭 EMPTY STATE
        ============================================ */
        .empty-state {
            text-align: center;
            padding: var(--space-xl);
            background: var(--areia-quente);
            border-radius: 16px;
            color: var(--cinza-baobab);
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
        }

        /* ============================================
           🔘 BUTTONS - Sistema de Botões
        ============================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-xs);
            padding: var(--space-md) var(--space-lg);
            border-radius: 50px;
            font-size: 1.05rem;
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
            margin-top: var(--space-lg);
            justify-content: center;
            flex-wrap: wrap;
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

        /* ============================================
           📱 RESPONSIVE DESIGN
        ============================================ */
        @media (max-width: 768px) {
            .hero {
                padding: var(--space-lg) var(--space-md);
            }

            .profile-header {
                flex-direction: column;
                gap: var(--space-md);
                text-align: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .candidatura-header {
                flex-direction: column;
                gap: var(--space-sm);
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
            <h1>Perfil do Candidato</h1>
            <p>Visualize informações detalhadas sobre o candidato e seu histórico de candidaturas</p>
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

    <!-- 👤 PERFIL CONTENT -->
    <div class="main-content">
        <div class="container">
            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <a href="../index.php">
                    <i data-lucide="home" style="width: 16px; height: 16px;"></i>
                    Início
                </a>
                <span>/</span>
                <a href="dashboard.php">
                    <i data-lucide="bar-chart" style="width: 16px; height: 16px;"></i>
                    Dashboard
                </a>
                <span>/</span>
                <a href="candidaturas.php">
                    <i data-lucide="users" style="width: 16px; height: 16px;"></i>
                    Candidaturas
                </a>
                <span>/</span>
                <span>Perfil do Candidato</span>
            </nav>

            <!-- Cabeçalho do Perfil -->
            <div class="profile-header">
                <?php if ($candidato['foto_perfil']): ?>
                    <img src="../uploads/fotos/<?php echo htmlspecialchars($candidato['foto_perfil']); ?>" 
                         alt="Foto de perfil" class="profile-avatar">
                <?php else: ?>
                    <div class="profile-avatar-placeholder">
                        <i data-lucide="user" style="width: 60px; height: 60px;"></i>
                    </div>
                <?php endif; ?>
                
                <div class="profile-info">
                    <h2 class="profile-name"><?php echo htmlspecialchars($candidato['nome_completo']); ?></h2>
                    <div class="profile-meta">
                        <div class="profile-meta-item">
                            <i data-lucide="mail" style="width: 16px; height: 16px;"></i>
                            <?php echo htmlspecialchars($candidato['email']); ?>
                        </div>
                        <div class="profile-meta-item">
                            <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i>
                            <?php echo htmlspecialchars($candidato['localizacao'] ?: 'Localização não definida'); ?>
                        </div>
                        <div class="profile-meta-item">
                            <i data-lucide="phone" style="width: 16px; height: 16px;"></i>
                            <?php echo htmlspecialchars($candidato['telefone'] ?: 'Telefone não definido'); ?>
                        </div>
                    </div>
                    
                    <div class="profile-meta">
                        <div class="profile-meta-item">
                            <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                            Membro desde: <?php echo date('d/m/Y', strtotime($candidato['data_registo'])); ?>
                        </div>
                        <div class="profile-meta-item">
                            <i data-lucide="log-in" style="width: 16px; height: 16px;"></i>
                            Último login: 
                            <?php echo $candidato['ultimo_login'] ? date('d/m/Y H:i', strtotime($candidato['ultimo_login'])) : 'Nunca'; ?>
                        </div>
                    </div>
                    
                    <?php if ($candidato['cv_pdf']): ?>
                        <a href="../uploads/cv/<?php echo htmlspecialchars($candidato['cv_pdf']); ?>" 
                           target="_blank" class="btn-cv">
                            <i data-lucide="file-text" style="width: 16px; height: 16px;"></i>
                            Ver Currículo (CV)
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Informações de Contacto -->
            <div class="section">
                <h2 class="section-title">
                    <i data-lucide="user" style="width: 24px; height: 24px;"></i>
                    Informações Pessoais
                </h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">
                            <i data-lucide="user" style="width: 16px; height: 16px;"></i>
                            Nome Completo
                        </span>
                        <span class="info-value"><?php echo htmlspecialchars($candidato['nome_completo']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">
                            <i data-lucide="mail" style="width: 16px; height: 16px;"></i>
                            Email
                        </span>
                        <span class="info-value"><?php echo htmlspecialchars($candidato['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">
                            <i data-lucide="phone" style="width: 16px; height: 16px;"></i>
                            Telefone
                        </span>
                        <span class="info-value"><?php echo htmlspecialchars($candidato['telefone'] ?: 'Não definido'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">
                            <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i>
                            Localização
                        </span>
                        <span class="info-value"><?php echo htmlspecialchars($candidato['localizacao'] ?: 'Não definida'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">
                            <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                            Data de Registo
                        </span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($candidato['data_registo'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">
                            <i data-lucide="log-in" style="width: 16px; height: 16px;"></i>
                            Último Login
                        </span>
                        <span class="info-value">
                            <?php echo $candidato['ultimo_login'] ? date('d/m/Y H:i', strtotime($candidato['ultimo_login'])) : 'Nunca'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Competências -->
            <?php if ($candidato['competencias']): ?>
                <div class="section">
                    <h2 class="section-title">
                        <i data-lucide="award" style="width: 24px; height: 24px;"></i>
                        Competências
                    </h2>
                    <div class="competencias-container">
                        <div class="competencias-tags">
                            <?php 
                                $competencias_array = explode(',', $candidato['competencias']);
                                foreach ($competencias_array as $competencia):
                                    $competencia_trim = trim($competencia);
                                    if (!empty($competencia_trim)):
                            ?>
                                <span class="competencia-tag"><?php echo htmlspecialchars($competencia_trim); ?></span>
                            <?php 
                                    endif;
                                endforeach; 
                            ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Experiências Profissionais -->
            <div class="section">
                <h2 class="section-title">
                    <i data-lucide="briefcase" style="width: 24px; height: 24px;"></i>
                    Experiência Profissional
                </h2>
                <div class="item-list">
                    <?php if (empty($experiencias)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="briefcase" style="width: 64px; height: 64px;"></i>
                            </div>
                            <h3>Nenhuma experiência profissional cadastrada</h3>
                            <p>O candidato ainda não adicionou experiências profissionais ao perfil.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($experiencias as $exp): ?>
                            <div class="item-card">
                                <h3 class="item-title"><?php echo htmlspecialchars($exp['cargo']); ?></h3>
                                <div class="item-subtitle">
                                    <i data-lucide="building" style="width: 16px; height: 16px;"></i>
                                    <?php echo htmlspecialchars($exp['empresa']); ?>
                                </div>
                                <div class="item-meta">
                                    <div class="item-meta-item">
                                        <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                                        <?php echo date('m/Y', strtotime($exp['data_inicio'])); ?> - 
                                        <?php echo $exp['data_fim'] ? date('m/Y', strtotime($exp['data_fim'])) : 'Atual'; ?>
                                    </div>
                                    <div class="item-meta-item">
                                        <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                                        <?php 
                                            $inicio = new DateTime($exp['data_inicio']);
                                            $fim = $exp['data_fim'] ? new DateTime($exp['data_fim']) : new DateTime();
                                            $interval = $inicio->diff($fim);
                                            echo $interval->y . ' anos ' . $interval->m . ' meses';
                                        ?>
                                    </div>
                                </div>
                                <?php if ($exp['descricao']): ?>
                                    <div class="item-description">
                                        <?php echo nl2br(htmlspecialchars($exp['descricao'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formação Académica -->
            <div class="section">
                <h2 class="section-title">
                    <i data-lucide="graduation-cap" style="width: 24px; height: 24px;"></i>
                    Formação Académica
                </h2>
                <div class="item-list">
                    <?php if (empty($formacoes)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="book-open" style="width: 64px; height: 64px;"></i>
                            </div>
                            <h3>Nenhuma formação académica cadastrada</h3>
                            <p>O candidato ainda não adicionou formações académicas ao perfil.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($formacoes as $form): ?>
                            <div class="item-card">
                                <h3 class="item-title"><?php echo htmlspecialchars($form['curso']); ?></h3>
                                <div class="item-subtitle">
                                    <i data-lucide="school" style="width: 16px; height: 16px;"></i>
                                    <?php echo htmlspecialchars($form['instituicao']); ?>
                                </div>
                                <div class="item-meta">
                                    <?php if ($form['grau']): ?>
                                        <div class="item-meta-item">
                                            <i data-lucide="award" style="width: 16px; height: 16px;"></i>
                                            <?php echo htmlspecialchars($form['grau']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="item-meta-item">
                                        <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                                        <?php echo date('m/Y', strtotime($form['data_inicio'])); ?> - 
                                        <?php echo $form['data_fim'] ? date('m/Y', strtotime($form['data_fim'])) : 'Atual'; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Candidaturas à Empresa -->
            <div class="section">
                <h2 class="section-title">
                    <i data-lucide="users" style="width: 24px; height: 24px;"></i>
                    Candidaturas à Sua Empresa
                </h2>
                <div class="item-list">
                    <?php if (empty($candidaturas)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="inbox" style="width: 64px; height: 64px;"></i>
                            </div>
                            <h3>Nenhuma candidatura encontrada</h3>
                            <p>Este candidato não se candidatou a nenhuma vaga da sua empresa.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($candidaturas as $cand): ?>
                            <div class="candidatura-card">
                                <div class="candidatura-header">
                                    <div class="candidatura-title-section">
                                        <h3 class="candidatura-title">
                                            <a href="../vaga_detalhe.php?id=<?php echo $cand['vaga_id']; ?>" 
                                               style="text-decoration: none; color: inherit;">
                                                <?php echo htmlspecialchars($cand['titulo']); ?>
                                            </a>
                                        </h3>
                                        <div class="candidatura-meta">
                                            <div class="candidatura-meta-item">
                                                <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                                                Candidatou-se em: <?php echo date('d/m/Y H:i', strtotime($cand['data_candidatura'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <?php 
                                            $status_classes = [
                                                'submetida' => 'status-submetida',
                                                'em_analise' => 'status-em_analise',
                                                'entrevista' => 'status-entrevista',
                                                'rejeitada' => 'status-rejeitada',
                                                'contratado' => 'status-contratado'
                                            ];
                                            $status_labels = [
                                                'submetida' => 'Submetida',
                                                'em_analise' => 'Em Análise',
                                                'entrevista' => 'Entrevista',
                                                'rejeitada' => 'Rejeitada',
                                                'contratado' => 'Contratado'
                                            ];
                                            ?>
                                            <span class="estado-badge <?php echo $status_classes[$cand['estado']]; ?>">
                                                <?php echo $status_labels[$cand['estado']]; ?>
                                            </span>
                                    </div>
                                </div>
                                
                                <?php if ($cand['carta_apresentacao']): ?>
                                    <div class="candidatura-content">
                                        <div class="candidatura-label">
                                            <i data-lucide="message-square" style="width: 16px; height: 16px;"></i>
                                            Carta de Apresentação
                                        </div>
                                        <div class="candidatura-text">
                                            <?php echo nl2br(htmlspecialchars($cand['carta_apresentacao'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="btn-group">
                <a href="candidaturas.php" class="btn btn-secondary">
                    <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
                    Voltar às Candidaturas
                </a>
                <a href="dashboard.php" class="btn btn-primary">
                    <i data-lucide="bar-chart" style="width: 18px; height: 18px;"></i>
                    Ir para Dashboard
                </a>
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
            const animateElements = document.querySelectorAll('.section, .item-card, .candidatura-card');
            
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
            
            // Animação de entrada para o header do perfil
            const profileHeader = document.querySelector('.profile-header');
            profileHeader.style.opacity = '0';
            profileHeader.style.transform = 'translateY(30px)';
            profileHeader.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
            
            setTimeout(() => {
                profileHeader.style.opacity = '1';
                profileHeader.style.transform = 'translateY(0)';
            }, 300);
            
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