<?php
session_start();
require_once 'config/db.php';

$pdo = getPDO();

// Buscar estatísticas para a página
$stmt_total_vagas = $pdo->query("SELECT COUNT(*) FROM vaga WHERE ativa = TRUE");
$total_vagas = $stmt_total_vagas->fetchColumn();

$stmt_total_empresas = $pdo->query("SELECT COUNT(*) FROM empresa");
$total_empresas = $stmt_total_empresas->fetchColumn();

$stmt_total_candidatos = $pdo->query("SELECT COUNT(*) FROM candidato");
$total_candidatos = $stmt_total_candidatos->fetchColumn();

$stmt_total_candidaturas = $pdo->query("SELECT COUNT(*) FROM candidatura");
$total_candidaturas = $stmt_total_candidaturas->fetchColumn();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre Nós | Emprego MZ</title>
    <meta name="description" content="Conheça a história e missão do Emprego MZ, a plataforma líder de empregos em Moçambique.">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        /* ==========================================
           🎨 RESET & VARIABLES
        ========================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* === 🎨 PALETA DEFINITIVA PROFISSIONAL === */
            
            /* 🔵 CORES PRINCIPAIS */
            --cor-primaria: #14213d;              /* Oxford Blue - Headers, Navigation */
            --cor-primaria-escura: #0f1a2e;       /* Oxford Blue Dark - Hover */
            --cor-primaria-clara: #1e2c47;        /* Oxford Blue Light */
            --cor-primaria-alpha: rgba(20, 33, 61, 0.1);
            
            /* 🟠 CORES SECUNDÁRIAS */
            --cor-secundaria: #fca311;            /* Orange Web - CTAs, Destaques */
            --cor-secundaria-hover: #e3940f;      /* Orange Hover */
            --cor-secundaria-clara: #fdb541;      
            --cor-secundaria-alpha: rgba(252, 163, 17, 0.1);
            
            /* 🌫️ HIERARQUIA VISUAL - Soft Gray System */
            --cor-fundo: #f8fafc;                 /* Soft Gray - Fundo geral */
            --cor-cards: #ffffff;                 /* White - Cards, containers */
            --cor-acento: #f1f5ff;                /* Light Blue - Hover states */
            --cor-acento-escuro: #e1ebff;         /* Light Blue Dark */
            
            /* 📝 TEXTO */
            --cor-texto: #1a202c;                 /* Texto principal */
            --cor-texto-claro: #64748b;           /* Texto secundário */
            --cor-texto-muito-claro: #94a3b8;     /* Placeholder */
            
            /* 🟢 PÁGINA SOBRE - Verde */
            --cor-sobre: #10B981;                 /* Verde para página Sobre */
            --cor-sobre-escuro: #059669;          /* Verde escuro */
            --cor-sobre-light: #D1FAE5;           /* Fundo verde claro */
            
            /* 🎯 BORDAS E SOMBRAS */
            --cor-borda: #e2e8f0;                 
            --cor-borda-clara: #f1f5f9;           
            --sombra-conteudo: 0 6px 30px rgba(20, 33, 61, 0.10);
            --sombra-card: 0 4px 20px rgba(20, 33, 61, 0.08);
            --sombra-media: 0 4px 12px rgba(20, 33, 61, 0.12);
            --sombra-forte: 0 8px 24px rgba(20, 33, 61, 0.15);
            
            /* Aliases */
            --primary: var(--cor-primaria);
            --primary-dark: var(--cor-primaria-escura);
            --secondary: var(--cor-secundaria);
            --text: var(--cor-texto);
            --text-light: var(--cor-texto-claro);
            --border: var(--cor-borda);
            --white: var(--cor-cards);
            --success: var(--cor-sobre);
            
            /* Spacing */
            --space-1: 4px;
            --space-2: 8px;
            --space-3: 12px;
            --space-4: 16px;
            --space-5: 20px;
            --space-6: 24px;
            --space-8: 32px;
            --space-10: 40px;
            --space-12: 48px;
            --space-16: 64px;
            
            /* Border Radius */
            --radius: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-2xl: 20px;
            --radius-full: 9999px;
            
            /* Shadows */
            --shadow-sm: var(--sombra-card);
            --shadow-md: var(--sombra-media);
            --shadow-lg: var(--sombra-forte);
            
            /* Transitions */
            --transition: all 0.2s ease;
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            font-family: var(--font-family);
            color: var(--cor-texto);
            background: var(--cor-fundo);
            line-height: 1.7;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ==========================================
           📱 HEADER - EXATO DO INDEX.PHP
        ========================================== */
        .header {
            background: var(--cor-primaria);     /* #14213d - Oxford Blue */
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: var(--shadow-md);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-6);
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 70px;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo-img {
            height: 36px;
            width: auto;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: var(--space-10);  /* 40px - Mais espaçamento entre itens */
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .nav-link:hover {
            color: var(--cor-secundaria);
        }

        .nav-link.active {
            color: var(--cor-secundaria);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--cor-secundaria);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }

        /* Header Buttons */
        .header-actions {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .btn {
            padding: var(--space-3) var(--space-5);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
        }

        .btn-outline:hover {
            border-color: var(--cor-secundaria);
            color: var(--cor-secundaria);
            background: rgba(252, 163, 17, 0.1);
        }

        /* Mobile Menu */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: var(--space-2);
        }

        .mobile-menu-toggle svg {
            width: 24px;
            height: 24px;
        }

        /* Dropdown Navigation */
        .nav-dropdown {
            position: relative;
        }

        .nav-link.has-dropdown {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .nav-link.has-dropdown svg {
            width: 14px;
            height: 14px;
            transition: transform 0.2s;
        }

        .nav-dropdown:hover .nav-link.has-dropdown svg {
            transform: rotate(180deg);
        }

        .dropdown-content {
            position: absolute;
            top: calc(100% + 12px);
            left: 0;
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 24px rgba(20, 33, 61, 0.15);
            min-width: 240px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: var(--transition);
            z-index: 100;
        }

        .nav-dropdown:hover .dropdown-content {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3) var(--space-4);
            color: var(--cor-texto-claro);
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background: var(--cor-acento);
            color: var(--cor-primaria);
        }

        .dropdown-item svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--cor-borda);
            margin: var(--space-2) 0;
        }

        /* ==========================================
           🌟 HERO SOBRE - Verde
        ========================================== */
        .page-container {
            margin-top: 70px; /* Altura do header fixo */
        }

        .page-hero {
            background: var(--cor-cards);
            border-bottom: 6px solid var(--cor-sobre);
            padding: var(--space-16) var(--space-6);
            margin-bottom: var(--space-10);
            box-shadow: var(--sombra-conteudo);
        }

        .hero-container {
            max-width: 1000px;
            margin: 0 auto;
            text-align: center;
        }

        .page-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto var(--space-6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: white;
            background: linear-gradient(135deg, var(--cor-sobre), var(--cor-sobre-escuro));
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.3);
        }

        .page-title {
            color: var(--cor-primaria);
            font-size: 48px;
            font-weight: 800;
            margin-bottom: var(--space-4);
            line-height: 1.2;
        }

        .page-subtitle {
            color: var(--cor-texto-claro);
            font-size: 20px;
            line-height: 1.6;
            max-width: 700px;
            margin: 0 auto;
        }

        /* ==========================================
           📊 STATS CARDS - Verde
        ========================================== */
        .stats-section {
            max-width: 1200px;
            margin: 0 auto var(--space-16);
            padding: 0 var(--space-6);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-6);
        }

        .stat-card {
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
            text-align: center;
            box-shadow: var(--sombra-card);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--sombra-forte);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto var(--space-4);
            background: linear-gradient(135deg, var(--cor-sobre), var(--cor-sobre-escuro));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .stat-value {
            font-size: 40px;
            font-weight: 900;
            color: var(--cor-sobre);
            margin-bottom: var(--space-2);
            line-height: 1;
        }

        .stat-label {
            font-size: 14px;
            color: var(--cor-texto-claro);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ==========================================
           📄 CONTENT SECTIONS
        ========================================== */
        .content-container {
            max-width: 1000px;
            margin: 0 auto var(--space-16);
            padding: 0 var(--space-6);
        }

        .content-section {
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius-2xl);
            box-shadow: var(--sombra-card);
            padding: var(--space-10);
            margin-bottom: var(--space-8);
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-title {
            color: var(--cor-primaria);
            font-size: 32px;
            font-weight: 700;
            margin-bottom: var(--space-5);
            padding-bottom: var(--space-3);
            border-bottom: 3px solid var(--cor-acento-escuro);
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 80px;
            height: 3px;
            background: var(--cor-sobre);
        }

        .content-section p {
            font-size: 17px;
            line-height: 1.8;
            color: var(--cor-texto-claro);
            margin-bottom: var(--space-4);
        }

        .content-section strong {
            color: var(--cor-texto);
            font-weight: 600;
        }

        /* ==========================================
           💎 VALUES GRID - Verde
        ========================================== */
        .values-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-6);
            margin-top: var(--space-8);
        }

        .value-card {
            background: var(--cor-acento);
            border: 1px solid var(--cor-acento-escuro);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
            transition: var(--transition);
        }

        .value-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--sombra-card);
            background: var(--cor-cards);
        }

        .value-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--cor-sobre), var(--cor-sobre-escuro));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-bottom: var(--space-4);
        }

        .value-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--cor-texto);
            margin-bottom: var(--space-3);
        }

        .value-card p {
            font-size: 15px;
            color: var(--cor-texto-claro);
            line-height: 1.7;
        }

        /* ==========================================
           🎯 CTA SECTION - Verde
        ========================================== */
        .cta-section {
            background: linear-gradient(135deg, var(--cor-sobre), var(--cor-sobre-escuro));
            color: white;
            padding: var(--space-16) var(--space-6);
            text-align: center;
            margin-bottom: var(--space-16);
        }

        .cta-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .cta-section h2 {
            font-size: 40px;
            font-weight: 900;
            margin-bottom: var(--space-4);
            line-height: 1.2;
        }

        .cta-section p {
            font-size: 20px;
            opacity: 0.95;
            margin-bottom: var(--space-8);
            line-height: 1.6;
        }

        .cta-buttons {
            display: flex;
            gap: var(--space-4);
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-white {
            background: white;
            color: var(--cor-sobre);
            padding: var(--space-4) var(--space-8);
            border-radius: var(--radius);
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            transition: var(--transition);
        }

        .btn-white:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        .btn-outline-white {
            background: transparent;
            border: 2px solid white;
            color: white;
            padding: var(--space-4) var(--space-8);
            border-radius: var(--radius);
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            transition: var(--transition);
        }

        .btn-outline-white:hover {
            background: white;
            color: var(--cor-sobre);
        }

        /* ==========================================
           🎨 FOOTER - EXATO DO INDEX.PHP
        ========================================== */
        .footer {
            background: var(--cor-primaria);     /* #14213d - Oxford Blue */
            color: rgba(255, 255, 255, 0.8);
            padding: var(--space-16) var(--space-6) var(--space-8);
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: var(--space-10);
            margin-bottom: var(--space-10);
        }

        .footer-column h4 {
            color: white;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: var(--space-4);
        }

        .footer-logo {
            height: 32px;
            margin-bottom: var(--space-4);
        }

        .footer-description {
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: var(--space-4);
        }

        .footer-link {
            display: block;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: var(--space-3);
            transition: var(--transition);
        }

        .footer-link:hover {
            color: var(--cor-secundaria);
        }

        .footer-social {
            display: flex;
            gap: var(--space-4);
        }

        .social-link {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.7);
            transition: var(--transition);
        }

        .social-link:hover {
            background: var(--cor-secundaria);
            color: white;
            transform: translateY(-2px);
        }

        .social-link svg {
            width: 18px;
            height: 18px;
        }

        .footer-bottom {
            padding-top: var(--space-8);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            text-align: center;
        }

        /* ==========================================
           📱 RESPONSIVE
        ========================================== */
        @media (max-width: 1024px) {
            .nav-menu {
                display: none;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .values-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 36px;
            }

            .stats-grid,
            .values-grid {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: var(--space-6);
            }

            .cta-section h2 {
                font-size: 32px;
            }

            .cta-buttons {
                flex-direction: column;
            }

            .footer-bottom {
                flex-direction: column;
                gap: var(--space-2);
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .page-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }

            .page-title {
                font-size: 28px;
            }

            .page-subtitle {
                font-size: 16px;
            }

            .content-section {
                padding: var(--space-6);
            }
        }
    </style>
</head>
<body>

    <!-- ==========================================
         📱 HEADER - EXATO DO INDEX.PHP
    ========================================== -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <img src="assets/images/empregos-logo.svg" alt="Emprego MZ" class="logo-img">
            </a>

            <nav class="nav-menu">
                <a href="index.php" class="nav-link">Início</a>
                <a href="vagas.php" class="nav-link">Vagas</a>
                
                <?php 
                // ==========================================
                // LÓGICA DE NAVEGAÇÃO PARA CANDIDATOS
                // ==========================================
                // Mostrar "Para Candidatos" APENAS se logado como candidato
                if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'candidato'): 
                ?>
                <div class="nav-dropdown">
                    <a href="#" class="nav-link has-dropdown">
                        Para Candidatos
                        <i data-lucide="chevron-down"></i>
                    </a>
                    <div class="dropdown-content">
                        <a href="candidato/perfil.php" class="dropdown-item">
                            <i data-lucide="user"></i>
                            <span>Meu Perfil</span>
                        </a>
                        <a href="candidato/vagas_guardadas.php" class="dropdown-item">
                            <i data-lucide="bookmark"></i>
                            <span>Vagas Guardadas</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="candidato/candidaturas.php" class="dropdown-item">
                            <i data-lucide="briefcase"></i>
                            <span>Minhas Candidaturas</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php 
                // ==========================================
                // LÓGICA DE NAVEGAÇÃO PARA EMPRESAS
                // ==========================================
                // Mostrar "Para Empresas" APENAS se logado como empresa
                if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'empresa'): 
                ?>
                <div class="nav-dropdown">
                    <a href="#" class="nav-link has-dropdown">
                        Para Empresas
                        <i data-lucide="chevron-down"></i>
                    </a>
                    <div class="dropdown-content">
                        <a href="empresa/dashboard.php" class="dropdown-item">
                            <i data-lucide="layout-dashboard"></i>
                            <span>Painel de Controle</span>
                        </a>
                        <a href="empresa/criar_vaga.php" class="dropdown-item">
                            <i data-lucide="plus-circle"></i>
                            <span>Publicar Vaga</span>
                        </a>
                        <a href="empresa/candidaturas.php" class="dropdown-item">
                            <i data-lucide="users"></i>
                            <span>Candidatos</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </nav>

            <div class="header-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['user_type'] === 'empresa'): ?>
                        <!-- Ações para Empresa -->
                        <a href="empresa/dashboard.php" class="btn btn-primary">
                            <i data-lucide="layout-dashboard"></i>
                            Dashboard
                        </a>
                        <a href="auth/logout.php" class="btn btn-outline">
                            <i data-lucide="log-out"></i>
                            Sair
                        </a>
                    <?php elseif ($_SESSION['user_type'] === 'candidato'): ?>
                        <!-- Ações para Candidato - Ordem priorizada -->
                        <a href="vagas.php" class="btn btn-primary">
                            <i data-lucide="search"></i>
                            Buscar Vagas
                        </a>
                        <a href="candidato/candidaturas.php" class="btn btn-outline">
                            <i data-lucide="briefcase"></i>
                            Candidaturas
                        </a>
                        <a href="candidato/perfil.php" class="btn btn-outline">
                            <i data-lucide="user"></i>
                            Perfil
                        </a>
                        <a href="auth/logout.php" class="btn btn-outline">
                            <i data-lucide="log-out"></i>
                            Sair
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Ações para não logado -->
                    <a href="auth/login.php" class="btn btn-outline">Entrar</a>
                    <a href="auth/register.php" class="btn btn-primary">Criar Conta</a>
                <?php endif; ?>
                
                <button class="mobile-menu-toggle" aria-label="Menu">
                    <i data-lucide="menu"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- ==========================================
         🌟 PAGE CONTAINER
    ========================================== -->
    <div class="page-container">

        <!-- Hero -->
        <section class="page-hero">
            <div class="hero-container">
                <div class="page-icon">
                    <i data-lucide="heart"></i>
                </div>
                <h1 class="page-title">Sobre o Emprego MZ</h1>
                <p class="page-subtitle">
                    A plataforma líder de empregos em Moçambique, conectando talentos com 
                    oportunidades desde 2024
                </p>
            </div>
        </section>

        <!-- Stats -->
        <section class="stats-section">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="briefcase" style="width: 28px; height: 28px;"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_vagas); ?>+</div>
                    <div class="stat-label">Vagas Ativas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="building-2" style="width: 28px; height: 28px;"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_empresas); ?>+</div>
                    <div class="stat-label">Empresas Parceiras</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="users" style="width: 28px; height: 28px;"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_candidatos); ?>+</div>
                    <div class="stat-label">Candidatos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="send" style="width: 28px; height: 28px;"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_candidaturas); ?>+</div>
                    <div class="stat-label">Candidaturas</div>
                </div>
            </div>
        </section>

        <!-- Content -->
        <div class="content-container">
            <!-- Nossa História -->
            <div class="content-section">
                <h2 class="section-title">🚀 Nossa História</h2>
                <p>
                    O <strong>Emprego MZ</strong> nasceu da visão de transformar o mercado de trabalho moçambicano, 
                    criando uma ponte eficiente entre empresas que procuram talentos e profissionais que buscam 
                    oportunidades de crescimento.
                </p>
                <p>
                    Fundada em 2024, nossa plataforma foi desenvolvida por uma equipa de profissionais moçambicanos 
                    apaixonados por tecnologia e pelo desenvolvimento do país. Entendemos os desafios únicos do 
                    mercado local e criamos soluções específicas para superá-los.
                </p>
                <p>
                    Hoje, somos a plataforma de empregos mais moderna de Moçambique, utilizando tecnologia de 
                    ponta para oferecer a melhor experiência tanto para empresas quanto para candidatos.
                </p>
            </div>

            <!-- Nossa Missão -->
            <div class="content-section">
                <h2 class="section-title">🎯 Nossa Missão</h2>
                <p>
                    Democratizar o acesso ao mercado de trabalho em Moçambique, oferecendo uma plataforma 
                    gratuita, eficiente e acessível que conecta os melhores talentos às melhores oportunidades, 
                    contribuindo para o desenvolvimento económico e social do país.
                </p>
            </div>

            <!-- Nossa Visão -->
            <div class="content-section">
                <h2 class="section-title">👁️ Nossa Visão</h2>
                <p>
                    Ser a plataforma de empregos número 1 em Moçambique até 2030, reconhecida pela excelência 
                    no serviço, inovação tecnológica e impacto positivo na vida de milhares de moçambicanos.
                </p>
            </div>

            <!-- Nossos Valores -->
            <div class="content-section">
                <h2 class="section-title">💎 Nossos Valores</h2>
                <div class="values-grid">
                    <div class="value-card">
                        <div class="value-icon">
                            <i data-lucide="shield-check" style="width: 24px; height: 24px;"></i>
                        </div>
                        <h3>Transparência</h3>
                        <p>
                            Operamos com total clareza em todos os processos, garantindo confiança 
                            e credibilidade para empresas e candidatos.
                        </p>
                    </div>
                    
                    <div class="value-card">
                        <div class="value-icon">
                            <i data-lucide="zap" style="width: 24px; height: 24px;"></i>
                        </div>
                        <h3>Inovação</h3>
                        <p>
                            Utilizamos tecnologia de ponta para criar soluções modernas e eficientes 
                            que facilitam o processo de recrutamento.
                        </p>
                    </div>
                    
                    <div class="value-card">
                        <div class="value-icon">
                            <i data-lucide="heart" style="width: 24px; height: 24px;"></i>
                        </div>
                        <h3>Compromisso Social</h3>
                        <p>
                            Acreditamos no poder do emprego para transformar vidas e comunidades, 
                            contribuindo para o desenvolvimento de Moçambique.
                        </p>
                    </div>
                    
                    <div class="value-card">
                        <div class="value-icon">
                            <i data-lucide="users-round" style="width: 24px; height: 24px;"></i>
                        </div>
                        <h3>Inclusão</h3>
                        <p>
                            Promovemos oportunidades para todos, independentemente de origem, género 
                            ou localização geográfica.
                        </p>
                    </div>
                    
                    <div class="value-card">
                        <div class="value-icon">
                            <i data-lucide="award" style="width: 24px; height: 24px;"></i>
                        </div>
                        <h3>Excelência</h3>
                        <p>
                            Buscamos a excelência em tudo o que fazemos, desde a tecnologia até 
                            o atendimento ao cliente.
                        </p>
                    </div>
                    
                    <div class="value-card">
                        <div class="value-icon">
                            <i data-lucide="handshake" style="width: 24px; height: 24px;"></i>
                        </div>
                        <h3>Parceria</h3>
                        <p>
                            Construímos relacionamentos duradouros baseados em confiança mútua 
                            e benefícios compartilhados.
                        </p>
                    </div>
                </div>
            </div>

            <!-- O que nos diferencia -->
            <div class="content-section">
                <h2 class="section-title">⭐ O que nos Diferencia</h2>
                <p>
                    <strong>🎯 Foco em Moçambique:</strong> Conhecemos profundamente o mercado local, 
                    suas particularidades e desafios específicos.
                </p>
                <p>
                    <strong>💻 Tecnologia Moderna:</strong> Plataforma desenvolvida com as mais recentes 
                    tecnologias, garantindo segurança, velocidade e facilidade de uso.
                </p>
                <p>
                    <strong>🆓 100% Gratuito para Candidatos:</strong> Acreditamos que buscar emprego 
                    não deve ter custo. Candidatos podem usar todas as funcionalidades gratuitamente.
                </p>
                <p>
                    <strong>📱 Acessível em Qualquer Dispositivo:</strong> Nossa plataforma funciona 
                    perfeitamente em computadores, tablets e smartphones.
                </p>
                <p>
                    <strong>🚀 Processo Simplificado:</strong> Candidatar-se a vagas é rápido e fácil. 
                    Em poucos cliques, seu perfil chega às empresas.
                </p>
                <p>
                    <strong>🤝 Suporte Dedicado:</strong> Nossa equipa está sempre disponível para 
                    ajudar candidatos e empresas.
                </p>
            </div>
        </div>

        <!-- CTA -->
        <section class="cta-section">
            <div class="cta-container">
                <h2>Junte-se a nós!</h2>
                <p>Faça parte da maior plataforma de empregos de Moçambique</p>
                <div class="cta-buttons">
                    <a href="auth/register.php" class="btn-white">
                        <i data-lucide="user-plus"></i>
                        Criar Conta Grátis
                    </a>
                    <a href="vagas.php" class="btn-outline-white">
                        <i data-lucide="search"></i>
                        Explorar Vagas
                    </a>
                </div>
            </div>
        </section>

    </div>

    <!-- ==========================================
         🎨 FOOTER - EXATO DO INDEX.PHP
    ========================================== -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-column">
                    <img src="assets/images/empregos-logo.svg" alt="Emprego MZ" class="footer-logo">
                    <p class="footer-description">
                        A maior plataforma de empregos de Moçambique. Conectamos talentos com oportunidades em todas as províncias.
                    </p>
                    <div class="footer-social">
                        <a href="#" class="social-link" aria-label="Facebook">
                            <i data-lucide="facebook"></i>
                        </a>
                        <a href="#" class="social-link" aria-label="Twitter">
                            <i data-lucide="twitter"></i>
                        </a>
                        <a href="#" class="social-link" aria-label="LinkedIn">
                            <i data-lucide="linkedin"></i>
                        </a>
                        <a href="#" class="social-link" aria-label="Instagram">
                            <i data-lucide="instagram"></i>
                        </a>
                    </div>
                </div>

                <?php 
                // Mostrar "Para Candidatos" apenas se não for empresa
                if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'empresa'): 
                ?>
                <div class="footer-column">
                    <h4>Para Candidatos</h4>
                    <a href="vagas.php" class="footer-link">Buscar Vagas</a>
                    <a href="candidato/perfil.php" class="footer-link">Meu Perfil</a>
                    <a href="candidato/candidaturas.php" class="footer-link">Candidaturas</a>
                </div>
                <?php endif; ?>

                <?php 
                // Mostrar "Para Empresas" apenas se não for candidato
                if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'candidato'): 
                ?>
                <div class="footer-column">
                    <h4>Para Empresas</h4>
                    <a href="empresa/dashboard.php" class="footer-link">Dashboard</a>
                    <a href="empresa/criar_vaga.php" class="footer-link">Publicar Vaga</a>
                    <a href="empresa/candidaturas.php" class="footer-link">Candidatos</a>
                    <a href="auth/register.php" class="footer-link">Criar Conta</a>
                </div>
                <?php endif; ?>

                <div class="footer-column">
                    <h4>Empresa</h4>
                    <a href="sobre.php" class="footer-link">Sobre Nós</a>
                    <a href="contacto.php" class="footer-link">Contacto</a>
                    <a href="termos.php" class="footer-link">Termos de Uso</a>
                    <a href="privacidade.php" class="footer-link">Privacidade</a>
                </div>
            </div>

            <div class="footer-bottom">
                <div>&copy; <?php echo date('Y'); ?> Emprego MZ. Todos os direitos reservados.</div>
            </div>
        </div>
    </footer>

    <script>
        // Inicializar ícones Lucide
        lucide.createIcons();
    </script>

</body>
</html>
