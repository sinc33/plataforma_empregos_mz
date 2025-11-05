<?php
session_start();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidade | Emprego MZ</title>
    <meta name="description" content="Política de Privacidade do Emprego MZ - Como protegemos e tratamos seus dados pessoais.">
    
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
            --cor-primaria: #14213d;
            --cor-primaria-escura: #0f1a2e;
            --cor-primaria-clara: #1e2c47;
            
            /* 🟠 CORES SECUNDÁRIAS */
            --cor-secundaria: #fca311;
            --cor-secundaria-hover: #e3940f;
            
            /* 🌫️ HIERARQUIA VISUAL */
            --cor-fundo: #f8fafc;
            --cor-cards: #ffffff;
            --cor-acento: #f1f5ff;
            --cor-acento-escuro: #e1ebff;
            
            /* 📝 TEXTO */
            --cor-texto: #1a202c;
            --cor-texto-claro: #64748b;
            --cor-texto-muito-claro: #94a3b8;
            
            /* 🟣 PÁGINA PRIVACIDADE - Roxo */
            --cor-privacidade: #8B5CF6;
            --cor-privacidade-escuro: #7c3aed;
            --cor-privacidade-light: #EDE9FE;
            
            /* 🎯 BORDAS E SOMBRAS */
            --cor-borda: #e2e8f0;
            --sombra-conteudo: 0 6px 30px rgba(20, 33, 61, 0.10);
            --sombra-card: 0 4px 20px rgba(20, 33, 61, 0.08);
            --sombra-media: 0 4px 12px rgba(20, 33, 61, 0.12);
            
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
        }

        /* ==========================================
           📱 HEADER - EXATO DO INDEX.PHP
        ========================================== */
        .header {
            background: var(--cor-primaria);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: var(--sombra-media);
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
            background: var(--cor-primaria);
            color: white;
        }

        .btn-primary:hover {
            background: var(--cor-primaria-escura);
            transform: translateY(-1px);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
        }

        .btn-outline:hover {
            border-color: var(--cor-secundaria);
            color: var(--cor-secundaria);
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
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
           🟣 HERO PRIVACIDADE - Roxo
        ========================================== */
        .page-container {
            margin-top: 70px;
        }

        .page-hero {
            background: var(--cor-cards);
            border-bottom: 6px solid var(--cor-privacidade);
            padding: var(--space-16) var(--space-6);
            margin-bottom: var(--space-10);
            box-shadow: var(--sombra-conteudo);
        }

        .hero-container {
            max-width: 900px;
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
            background: linear-gradient(135deg, var(--cor-privacidade), var(--cor-privacidade-escuro));
            box-shadow: 0 8px 32px rgba(139, 92, 246, 0.3);
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
           📄 CONTENT
        ========================================== */
        .content-container {
            max-width: 900px;
            margin: 0 auto var(--space-16);
            padding: 0 var(--space-6);
        }

        .content-card {
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius-2xl);
            box-shadow: var(--sombra-card);
            padding: var(--space-12);
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

        .update-info {
            background: var(--cor-privacidade-light);
            border-left: 4px solid var(--cor-privacidade);
            padding: var(--space-4);
            margin-bottom: var(--space-8);
            border-radius: var(--radius);
            font-size: 14px;
            color: var(--cor-texto);
            font-weight: 600;
        }

        .highlight-box {
            background: var(--cor-privacidade-light);
            border: 1px solid var(--cor-privacidade);
            border-left: 4px solid var(--cor-privacidade);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            margin-bottom: var(--space-8);
        }

        .highlight-box h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--cor-privacidade);
            margin-bottom: var(--space-2);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .highlight-box p {
            font-size: 15px;
            color: var(--cor-texto);
            line-height: 1.7;
            margin: 0;
        }

        .content-card h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--cor-primaria);
            margin-top: var(--space-10);
            margin-bottom: var(--space-4);
            padding-bottom: var(--space-3);
            border-bottom: 3px solid var(--cor-acento-escuro);
            position: relative;
        }

        .content-card h2::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--cor-privacidade);
        }

        .content-card h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--cor-primaria-clara);
            margin-top: var(--space-8);
            margin-bottom: var(--space-3);
        }

        .content-card p {
            font-size: 16px;
            line-height: 1.8;
            color: var(--cor-texto-claro);
            margin-bottom: var(--space-4);
        }

        .content-card strong {
            color: var(--cor-texto);
            font-weight: 600;
        }

        .content-card ul,
        .content-card ol {
            margin-left: var(--space-6);
            margin-bottom: var(--space-4);
        }

        .content-card li {
            font-size: 16px;
            line-height: 1.8;
            color: var(--cor-texto-claro);
            margin-bottom: var(--space-2);
        }

        .content-card li::marker {
            color: var(--cor-privacidade);
            font-weight: 700;
        }

        .content-card a {
            color: var(--cor-privacidade);
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px solid transparent;
            transition: var(--transition);
        }

        .content-card a:hover {
            border-bottom-color: var(--cor-privacidade);
        }

        /* Tabela */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: var(--space-6) 0;
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .data-table th,
        .data-table td {
            padding: var(--space-4);
            text-align: left;
            border-bottom: 1px solid var(--cor-borda);
        }

        .data-table th {
            background: var(--cor-privacidade-light);
            font-weight: 700;
            color: var(--cor-primaria);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            color: var(--cor-texto-claro);
            font-size: 15px;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover {
            background: var(--cor-acento);
        }

        /* Contact box */
        .contact-box {
            background: var(--cor-acento);
            border: 1px solid var(--cor-acento-escuro);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            margin-top: var(--space-6);
        }

        .contact-box p {
            margin-bottom: var(--space-3);
        }

        .contact-box p:last-child {
            margin-bottom: 0;
        }

        /* Commitment box */
        .commitment-box {
            background: var(--cor-privacidade-light);
            border-left: 4px solid var(--cor-privacidade);
            padding: var(--space-5);
            border-radius: var(--radius);
            margin-top: var(--space-10);
        }

        .commitment-box h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--cor-privacidade);
            margin-bottom: var(--space-2);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .commitment-box p {
            margin: 0;
            color: var(--cor-texto);
            line-height: 1.7;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--cor-borda), transparent);
            margin: var(--space-10) 0;
        }

        /* ==========================================
           🎨 FOOTER - EXATO DO INDEX.PHP
        ========================================== */
        .footer {
            background: var(--cor-primaria);
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
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 36px;
            }

            .content-card {
                padding: var(--space-8) var(--space-5);
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: var(--space-6);
            }

            .footer-bottom {
                flex-direction: column;
                gap: var(--space-2);
                text-align: center;
            }

            .data-table {
                font-size: 13px;
            }

            .data-table th,
            .data-table td {
                padding: var(--space-3);
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
         🟣 PAGE CONTAINER
    ========================================== -->
    <div class="page-container">

        <!-- Hero -->
        <section class="page-hero">
            <div class="hero-container">
                <div class="page-icon">
                    <i data-lucide="shield-check"></i>
                </div>
                <h1 class="page-title">Política de Privacidade</h1>
                <p class="page-subtitle">
                    Como protegemos e tratamos seus dados pessoais
                </p>
            </div>
        </section>

        <!-- Content -->
        <div class="content-container">
            <div class="content-card">
                <div class="update-info">
                    <strong>Última atualização:</strong> <?php echo date('d/m/Y'); ?>
                </div>
                
                <div class="highlight-box">
                    <h3>📌 Compromisso com sua Privacidade</h3>
                    <p>
                        O Emprego MZ está comprometido em proteger sua privacidade e dados pessoais. 
                        Esta política explica claramente como coletamos, usamos, compartilhamos e 
                        protegemos suas informações.
                    </p>
                </div>
                
                <h2>1. Informações que Coletamos</h2>
                
                <h3>1.1 Informações Fornecidas por Você</h3>
                <p>Coletamos informações que você nos fornece diretamente ao:</p>
                <ul>
                    <li><strong>Criar uma conta:</strong> Nome, email, senha, tipo de conta (candidato/empresa)</li>
                    <li><strong>Completar o perfil de candidato:</strong> Nome completo, foto, telefone, localização, CV, competências, experiência profissional, formação académica</li>
                    <li><strong>Completar o perfil de empresa:</strong> Nome da empresa, NUIT, logotipo, website, descrição, localização</li>
                    <li><strong>Publicar vagas:</strong> Título, descrição, requisitos, localização, salário, tipo de contrato</li>
                    <li><strong>Candidatar-se a vagas:</strong> Carta de apresentação, informações adicionais</li>
                    <li><strong>Contactar-nos:</strong> Nome, email, assunto, mensagem</li>
                </ul>
                
                <h3>1.2 Informações Coletadas Automaticamente</h3>
                <p>Quando você usa nossa Plataforma, coletamos automaticamente:</p>
                <ul>
                    <li><strong>Dados de uso:</strong> Páginas visitadas, vagas visualizadas, tempo de navegação</li>
                    <li><strong>Dados técnicos:</strong> Endereço IP, tipo de navegador, sistema operacional, dispositivo</li>
                    <li><strong>Cookies e tecnologias similares:</strong> Para melhorar a experiência do usuário</li>
                </ul>
                
                <h2>2. Como Usamos suas Informações</h2>
                
                <p>Usamos suas informações para:</p>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Finalidade</th>
                            <th>Base Legal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Fornecer e operar a Plataforma</strong></td>
                            <td>Execução de contrato</td>
                        </tr>
                        <tr>
                            <td><strong>Criar e gerenciar sua conta</strong></td>
                            <td>Execução de contrato</td>
                        </tr>
                        <tr>
                            <td><strong>Conectar candidatos com empresas</strong></td>
                            <td>Execução de contrato</td>
                        </tr>
                        <tr>
                            <td><strong>Processar candidaturas</strong></td>
                            <td>Execução de contrato</td>
                        </tr>
                        <tr>
                            <td><strong>Enviar notificações relevantes</strong></td>
                            <td>Consentimento / Interesse legítimo</td>
                        </tr>
                        <tr>
                            <td><strong>Melhorar nossos serviços</strong></td>
                            <td>Interesse legítimo</td>
                        </tr>
                        <tr>
                            <td><strong>Prevenir fraudes e abusos</strong></td>
                            <td>Interesse legítimo / Obrigação legal</td>
                        </tr>
                        <tr>
                            <td><strong>Cumprir obrigações legais</strong></td>
                            <td>Obrigação legal</td>
                        </tr>
                    </tbody>
                </table>
                
                <h2>3. Compartilhamento de Informações</h2>
                
                <h3>3.1 Com Outros Usuários</h3>
                <ul>
                    <li><strong>Candidatos:</strong> Seus dados de perfil, CV e carta de apresentação são compartilhados com empresas às quais você se candidata</li>
                    <li><strong>Empresas:</strong> Informações da empresa e vagas são visíveis para todos os usuários</li>
                </ul>
                
                <h3>3.2 Com Terceiros</h3>
                <p>Podemos compartilhar informações com:</p>
                <ul>
                    <li><strong>Prestadores de serviços:</strong> Hospedagem, email, análise de dados (sob acordos de confidencialidade)</li>
                    <li><strong>Autoridades legais:</strong> Quando exigido por lei ou para proteger direitos</li>
                    <li><strong>Sucessores empresariais:</strong> Em caso de fusão, aquisição ou venda de ativos</li>
                </ul>
                
                <h3>3.3 Não Vendemos seus Dados</h3>
                <p>
                    <strong>Nunca vendemos, alugamos ou comercializamos seus dados pessoais.</strong>
                </p>
                
                <h2>4. Segurança dos Dados</h2>
                
                <p>Implementamos medidas de segurança para proteger suas informações:</p>
                <ul>
                    <li>🔒 <strong>Criptografia SSL/TLS</strong> para transmissão de dados</li>
                    <li>🔐 <strong>Hash de senhas</strong> com bcrypt</li>
                    <li>🛡️ <strong>Firewalls e proteção contra ataques</strong></li>
                    <li>👥 <strong>Acesso restrito</strong> aos dados por nossa equipa</li>
                    <li>📊 <strong>Monitoramento contínuo</strong> de atividades suspeitas</li>
                    <li>🔄 <strong>Backups regulares</strong> para recuperação de dados</li>
                </ul>
                
                <p style="font-size: 14px; color: var(--cor-texto-claro); margin-top: var(--space-4);">
                    ⚠️ <em>Nenhum método de transmissão pela internet é 100% seguro. Embora implementemos 
                    medidas rigorosas, não podemos garantir segurança absoluta.</em>
                </p>
                
                <h2>5. Seus Direitos</h2>
                
                <p>Você tem os seguintes direitos sobre seus dados pessoais:</p>
                
                <h3>5.1 Acesso</h3>
                <p>Direito de saber quais dados pessoais temos sobre você e solicitar uma cópia.</p>
                
                <h3>5.2 Correção</h3>
                <p>Direito de corrigir dados imprecisos ou incompletos através do seu perfil.</p>
                
                <h3>5.3 Exclusão</h3>
                <p>Direito de solicitar a exclusão de seus dados pessoais (direito ao esquecimento).</p>
                
                <h3>5.4 Portabilidade</h3>
                <p>Direito de receber seus dados em formato estruturado e legível por máquina.</p>
                
                <h3>5.5 Oposição</h3>
                <p>Direito de se opor ao processamento de seus dados para certos fins.</p>
                
                <h3>5.6 Restrição</h3>
                <p>Direito de solicitar a limitação do processamento de seus dados.</p>
                
                <h3>5.7 Retirada de Consentimento</h3>
                <p>Direito de retirar consentimento para processamento baseado em consentimento.</p>
                
                <p style="margin-top: var(--space-5);">
                    <strong>Para exercer esses direitos, contacte-nos em:</strong> 
                    <a href="mailto:privacidade@empregomz.co.mz">privacidade@empregomz.co.mz</a>
                </p>
                
                <h2>6. Retenção de Dados</h2>
                
                <p>Mantemos seus dados pessoais apenas pelo tempo necessário para:</p>
                <ul>
                    <li>Fornecer nossos serviços</li>
                    <li>Cumprir obrigações legais</li>
                    <li>Resolver disputas</li>
                    <li>Fazer cumprir nossos acordos</li>
                </ul>
                
                <p><strong>Períodos de retenção:</strong></p>
                <ul>
                    <li><strong>Contas ativas:</strong> Enquanto a conta estiver ativa</li>
                    <li><strong>Contas inativas:</strong> Até 2 anos após última atividade</li>
                    <li><strong>Dados de candidaturas:</strong> 5 anos (requisito legal trabalhista)</li>
                    <li><strong>Logs e dados técnicos:</strong> 12 meses</li>
                </ul>
                
                <h2>7. Cookies e Tecnologias de Rastreamento</h2>
                
                <h3>7.1 O que são Cookies</h3>
                <p>
                    Cookies são pequenos arquivos de texto armazenados no seu dispositivo quando 
                    você visita nossa Plataforma.
                </p>
                
                <h3>7.2 Como Usamos Cookies</h3>
                <ul>
                    <li><strong>Essenciais:</strong> Necessários para o funcionamento da Plataforma (login, segurança)</li>
                    <li><strong>Funcionais:</strong> Lembram suas preferências e configurações</li>
                    <li><strong>Analíticos:</strong> Ajudam-nos a entender como você usa a Plataforma</li>
                    <li><strong>Performance:</strong> Melhoram velocidade e desempenho</li>
                </ul>
                
                <h3>7.3 Controle de Cookies</h3>
                <p>
                    Você pode controlar cookies através das configurações do seu navegador. 
                    Note que desativar cookies pode afetar a funcionalidade da Plataforma.
                </p>
                
                <h2>8. Privacidade de Menores</h2>
                
                <p>
                    Nossa Plataforma não é destinada a menores de 18 anos. Não coletamos 
                    intencionalmente informações de menores. Se descobrirmos que coletamos 
                    dados de um menor, excluiremos essas informações imediatamente.
                </p>
                
                <h2>9. Transferência Internacional de Dados</h2>
                
                <p>
                    Seus dados são armazenados em servidores localizados em Moçambique. 
                    Se houver transferência internacional, garantiremos proteções adequadas 
                    conforme exigido pela lei.
                </p>
                
                <h2>10. Alterações nesta Política</h2>
                
                <p>
                    Podemos atualizar esta Política de Privacidade periodicamente. Notificaremos 
                    sobre mudanças significativas através da Plataforma ou por email. A data da 
                    última atualização está sempre indicada no topo desta página.
                </p>
                
                <h2>11. Base Legal para Processamento (LGPD/GDPR)</h2>
                
                <p>Processamos seus dados pessoais com base em:</p>
                <ul>
                    <li><strong>Consentimento:</strong> Quando você nos dá permissão explícita</li>
                    <li><strong>Contrato:</strong> Para fornecer serviços que você solicitou</li>
                    <li><strong>Interesse legítimo:</strong> Para melhorar serviços e prevenir fraudes</li>
                    <li><strong>Obrigação legal:</strong> Para cumprir leis aplicáveis</li>
                </ul>
                
                <h2>12. Contacto e Encarregado de Proteção de Dados</h2>
                
                <p>Para questões sobre privacidade ou para exercer seus direitos:</p>
                
                <div class="contact-box">
                    <p><strong>📧 Email:</strong> privacidade@empregomz.co.mz</p>
                    <p><strong>📞 Telefone:</strong> +258 84 300 1234</p>
                    <p><strong>📍 Endereço:</strong> Av. Julius Nyerere, 1234, Maputo, Moçambique</p>
                    <p><strong>⏰ Horário:</strong> Segunda a Sexta, 8h - 17h</p>
                </div>
                
                <h2>13. Reclamações</h2>
                
                <p>
                    Se acredita que seus direitos de privacidade foram violados, você pode 
                    apresentar uma reclamação à autoridade de proteção de dados competente 
                    em Moçambique.
                </p>
                
                <div class="divider"></div>
                
                <div class="commitment-box">
                    <h3>✅ Nosso Compromisso</h3>
                    <p>
                        Levamos sua privacidade a sério. Estamos comprometidos em processar seus 
                        dados de forma transparente, segura e em conformidade com todas as leis 
                        aplicáveis de proteção de dados.
                    </p>
                </div>
            </div>
        </div>

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
