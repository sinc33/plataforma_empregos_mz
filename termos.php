<?php
session_start();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termos de Uso | Emprego MZ</title>
    <meta name="description" content="Termos de Uso do Emprego MZ - Leia atentamente antes de usar nossa plataforma.">
    
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
            
            /* 🟨 PÁGINA TERMOS - Laranja */
            --cor-termos: #F59E0B;
            --cor-termos-escuro: #d97706;
            --cor-termos-light: #FEF3C7;
            
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
           🟨 HERO TERMOS - Laranja
        ========================================== */
        .page-container {
            margin-top: 70px;
        }

        .page-hero {
            background: var(--cor-cards);
            border-bottom: 6px solid var(--cor-termos);
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
            background: linear-gradient(135deg, var(--cor-termos), var(--cor-termos-escuro));
            box-shadow: 0 8px 32px rgba(245, 158, 11, 0.3);
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
            background: var(--cor-termos-light);
            border-left: 4px solid var(--cor-termos);
            padding: var(--space-4);
            margin-bottom: var(--space-8);
            border-radius: var(--radius);
            font-size: 14px;
            color: var(--cor-texto);
            font-weight: 600;
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

        .content-card h2:first-child,
        .update-info + h2 {
            margin-top: 0;
        }

        .content-card h2::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--cor-termos);
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
            color: var(--cor-termos);
            font-weight: 700;
        }

        .content-card a {
            color: var(--cor-termos);
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px solid transparent;
            transition: var(--transition);
        }

        .content-card a:hover {
            border-bottom-color: var(--cor-termos);
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--cor-borda), transparent);
            margin: var(--space-10) 0;
        }

        .final-note {
            text-align: center;
            color: var(--cor-texto-claro);
            font-size: 14px;
            margin-top: var(--space-8);
            padding: var(--space-4);
            background: var(--cor-acento);
            border-radius: var(--radius);
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
         🟨 PAGE CONTAINER
    ========================================== -->
    <div class="page-container">

        <!-- Hero -->
        <section class="page-hero">
            <div class="hero-container">
                <div class="page-icon">
                    <i data-lucide="file-text"></i>
                </div>
                <h1 class="page-title">Termos de Uso</h1>
                <p class="page-subtitle">
                    Leia atentamente antes de usar nossa plataforma
                </p>
            </div>
        </section>

        <!-- Content -->
        <div class="content-container">
            <div class="content-card">
                <div class="update-info">
                    <strong>Última atualização:</strong> <?php echo date('d/m/Y'); ?>
                </div>
                
                <h2>1. Aceitação dos Termos</h2>
                <p>
                    Ao acessar e usar o <strong>Emprego MZ</strong> ("Plataforma"), você concorda em cumprir 
                    e estar vinculado aos seguintes termos e condições de uso. Se você não concordar com 
                    qualquer parte destes termos, não deverá usar nossa plataforma.
                </p>
                
                <h2>2. Definições</h2>
                <ul>
                    <li><strong>Plataforma:</strong> O site e serviços oferecidos pelo Emprego MZ</li>
                    <li><strong>Usuário:</strong> Qualquer pessoa que acesse ou use a Plataforma</li>
                    <li><strong>Candidato:</strong> Usuário que busca oportunidades de emprego</li>
                    <li><strong>Empresa:</strong> Usuário que publica vagas de emprego</li>
                    <li><strong>Conteúdo:</strong> Informações, textos, imagens, dados e outros materiais</li>
                </ul>
                
                <h2>3. Elegibilidade</h2>
                <p>
                    Para usar nossa Plataforma, você deve:
                </p>
                <ul>
                    <li>Ter pelo menos 18 anos de idade</li>
                    <li>Possuir capacidade legal para celebrar contratos vinculativos</li>
                    <li>Fornecer informações verdadeiras, precisas e completas</li>
                    <li>Manter suas informações de conta atualizadas</li>
                </ul>
                
                <h2>4. Registro de Conta</h2>
                
                <h3>4.1 Criação de Conta</h3>
                <p>
                    Para acessar certas funcionalidades, você deve criar uma conta fornecendo 
                    informações pessoais precisas. Você é responsável por manter a confidencialidade 
                    de suas credenciais de login.
                </p>
                
                <h3>4.2 Responsabilidade pela Conta</h3>
                <p>
                    Você é totalmente responsável por todas as atividades que ocorrem em sua conta. 
                    Notifique-nos imediatamente sobre qualquer uso não autorizado.
                </p>
                
                <h3>4.3 Proibições</h3>
                <p>É proibido:</p>
                <ul>
                    <li>Criar múltiplas contas para o mesmo usuário</li>
                    <li>Usar informações falsas ou enganosas</li>
                    <li>Compartilhar credenciais de acesso com terceiros</li>
                    <li>Usar a conta de outra pessoa sem autorização</li>
                </ul>
                
                <h2>5. Uso da Plataforma</h2>
                
                <h3>5.1 Para Candidatos</h3>
                <p>Como candidato, você pode:</p>
                <ul>
                    <li>Criar e manter um perfil profissional</li>
                    <li>Buscar e visualizar vagas de emprego</li>
                    <li>Candidatar-se a vagas publicadas</li>
                    <li>Carregar CV e documentos relevantes</li>
                    <li>Receber notificações sobre oportunidades</li>
                </ul>
                
                <h3>5.2 Para Empresas</h3>
                <p>Como empresa, você pode:</p>
                <ul>
                    <li>Criar e manter um perfil empresarial</li>
                    <li>Publicar vagas de emprego</li>
                    <li>Receber e gerenciar candidaturas</li>
                    <li>Visualizar perfis de candidatos</li>
                    <li>Entrar em contacto com candidatos</li>
                </ul>
                
                <h3>5.3 Conduta Proibida</h3>
                <p>Você concorda em NÃO:</p>
                <ul>
                    <li>Usar a Plataforma para fins ilegais ou não autorizados</li>
                    <li>Publicar conteúdo falso, enganoso ou discriminatório</li>
                    <li>Assediar, intimidar ou prejudicar outros usuários</li>
                    <li>Coletar dados de outros usuários sem autorização</li>
                    <li>Enviar spam, vírus ou códigos maliciosos</li>
                    <li>Tentar acessar áreas restritas da Plataforma</li>
                    <li>Fazer engenharia reversa ou copiar a Plataforma</li>
                    <li>Usar bots, scrapers ou ferramentas automatizadas</li>
                </ul>
                
                <h2>6. Conteúdo do Usuário</h2>
                
                <h3>6.1 Propriedade do Conteúdo</h3>
                <p>
                    Você mantém todos os direitos sobre o conteúdo que publicar na Plataforma 
                    (perfis, CVs, descrições de vagas, etc.).
                </p>
                
                <h3>6.2 Licença de Uso</h3>
                <p>
                    Ao publicar conteúdo, você concede ao Emprego MZ uma licença mundial, 
                    não exclusiva, livre de royalties para usar, reproduzir, modificar e 
                    exibir esse conteúdo para operar e promover a Plataforma.
                </p>
                
                <h3>6.3 Responsabilidade pelo Conteúdo</h3>
                <p>
                    Você é o único responsável pelo conteúdo que publica. Garantimos que 
                    seu conteúdo:
                </p>
                <ul>
                    <li>É preciso e verdadeiro</li>
                    <li>Não viola direitos de terceiros</li>
                    <li>Não é ilegal, ofensivo ou inapropriado</li>
                    <li>Não contém vírus ou códigos maliciosos</li>
                </ul>
                
                <h2>7. Vagas de Emprego</h2>
                
                <h3>7.1 Publicação de Vagas</h3>
                <p>
                    Empresas são responsáveis pela precisão das informações nas vagas publicadas. 
                    As vagas devem representar oportunidades reais de emprego.
                </p>
                
                <h3>7.2 Moderação</h3>
                <p>
                    Reservamo-nos o direito de revisar, modificar ou remover vagas que violem 
                    estes termos ou sejam consideradas inapropriadas.
                </p>
                
                <h3>7.3 Não Somos uma Agência de Recrutamento</h3>
                <p>
                    O Emprego MZ é uma plataforma de conexão. Não somos uma agência de emprego 
                    e não garantimos contratações ou resultados específicos.
                </p>
                
                <h2>8. Privacidade e Proteção de Dados</h2>
                <p>
                    O uso de dados pessoais é regido por nossa 
                    <a href="privacidade.php">Política de Privacidade</a>. 
                    Ao usar a Plataforma, você concorda com a coleta e uso de informações 
                    conforme descrito nessa política.
                </p>
                
                <h2>9. Propriedade Intelectual</h2>
                <p>
                    Todos os direitos de propriedade intelectual da Plataforma (design, código, 
                    marca, logo, conteúdo) pertencem ao Emprego MZ ou seus licenciadores. Você 
                    não pode copiar, modificar, distribuir ou criar obras derivadas sem autorização.
                </p>
                
                <h2>10. Isenção de Responsabilidade</h2>
                <p>
                    A Plataforma é fornecida "como está" e "conforme disponível". Não garantimos:
                </p>
                <ul>
                    <li>Disponibilidade ininterrupta ou livre de erros</li>
                    <li>Precisão ou confiabilidade das informações</li>
                    <li>Resultados específicos do uso da Plataforma</li>
                    <li>Qualidade, veracidade ou legalidade do conteúdo de terceiros</li>
                </ul>
                
                <h2>11. Limitação de Responsabilidade</h2>
                <p>
                    Na máxima extensão permitida por lei, o Emprego MZ não será responsável por:
                </p>
                <ul>
                    <li>Danos indiretos, incidentais ou consequentes</li>
                    <li>Perda de lucros, dados ou oportunidades</li>
                    <li>Ações ou omissões de outros usuários</li>
                    <li>Vírus ou códigos maliciosos</li>
                    <li>Interrupções ou falhas técnicas</li>
                </ul>
                
                <h2>12. Rescisão</h2>
                
                <h3>12.1 Rescisão pelo Usuário</h3>
                <p>
                    Você pode encerrar sua conta a qualquer momento entrando em contacto conosco 
                    ou através das configurações da conta.
                </p>
                
                <h3>12.2 Rescisão pelo Emprego MZ</h3>
                <p>
                    Podemos suspender ou encerrar sua conta se você violar estes termos ou por 
                    qualquer outra razão, a nosso exclusivo critério, com ou sem aviso prévio.
                </p>
                
                <h2>13. Modificações dos Termos</h2>
                <p>
                    Reservamo-nos o direito de modificar estes termos a qualquer momento. 
                    Notificaremos sobre mudanças significativas através da Plataforma ou por 
                    email. O uso continuado após as modificações constitui aceitação dos novos termos.
                </p>
                
                <h2>14. Lei Aplicável e Jurisdição</h2>
                <p>
                    Estes termos são regidos pelas leis da República de Moçambique. Qualquer 
                    disputa será resolvida nos tribunais competentes de Maputo, Moçambique.
                </p>
                
                <h2>15. Disposições Gerais</h2>
                
                <h3>15.1 Acordo Integral</h3>
                <p>
                    Estes termos constituem o acordo integral entre você e o Emprego MZ 
                    relativamente ao uso da Plataforma.
                </p>
                
                <h3>15.2 Independência das Cláusulas</h3>
                <p>
                    Se qualquer disposição destes termos for considerada inválida, as demais 
                    disposições permanecerão em pleno vigor e efeito.
                </p>
                
                <h3>15.3 Renúncia</h3>
                <p>
                    A não exigência de cumprimento de qualquer disposição não constitui renúncia 
                    a esse direito.
                </p>
                
                <h2>16. Contacto</h2>
                <p>
                    Para questões sobre estes Termos de Uso, entre em contacto:
                </p>
                <ul>
                    <li><strong>Email:</strong> legal@empregomz.co.mz</li>
                    <li><strong>Telefone:</strong> +258 84 300 1234</li>
                    <li><strong>Endereço:</strong> Av. Julius Nyerere, 1234, Maputo, Moçambique</li>
                </ul>
                
                <div class="divider"></div>
                
                <div class="final-note">
                    Ao usar o Emprego MZ, você reconhece que leu, entendeu e concorda em estar 
                    vinculado a estes Termos de Uso.
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
