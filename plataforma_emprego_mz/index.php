<?php
session_start();
require_once 'config/db.php';

$pdo = getPDO();

// Buscar vagas recentes para exibir
$sql_vagas_recentes = "SELECT v.*, e.nome_empresa, e.logotipo 
                       FROM vaga v 
                       JOIN empresa e ON v.empresa_id = e.id 
                       WHERE v.ativa = TRUE AND v.data_expiracao >= CURDATE()
                       ORDER BY v.data_publicacao DESC 
                       LIMIT 6";

$stmt = $pdo->prepare($sql_vagas_recentes);
$stmt->execute();
$vagas_recentes = $stmt->fetchAll();

// Áreas principais (10 como no Empregos.com.br)
$areas_principais = [
    ['nome' => 'Comercial/Vendas', 'icone' => 'shopping-bag', 'imagem' => 'comercial.jpg'],
    ['nome' => 'Administrativa', 'icone' => 'briefcase', 'imagem' => 'administrativa.jpg'],
    ['nome' => 'Serviços Gerais', 'icone' => 'tool', 'imagem' => 'servicos-gerais.jpg'],
    ['nome' => 'Logística', 'icone' => 'truck', 'imagem' => 'logistica.jpg'],
    ['nome' => 'Informática/TI', 'icone' => 'laptop', 'imagem' => 'informatica.jpg'],
    ['nome' => 'Saúde', 'icone' => 'heart-pulse', 'imagem' => 'saude.jpg'],
    ['nome' => 'Finanças', 'icone' => 'wallet', 'imagem' => 'financas.jpg'],
    ['nome' => 'Industrial', 'icone' => 'factory', 'imagem' => 'industrial.jpg'],
    ['nome' => 'Construção Civil', 'icone' => 'hard-hat', 'imagem' => 'construcao.jpg'],
    ['nome' => 'Gastronomia', 'icone' => 'utensils', 'imagem' => 'gastronomia.jpg']
];

// Províncias de Moçambique
$provincias = [
    ['nome' => 'Maputo Cidade', 'slug' => 'maputo-cidade'],
    ['nome' => 'Maputo Província', 'slug' => 'maputo-provincia'],
    ['nome' => 'Gaza', 'slug' => 'gaza'],
    ['nome' => 'Inhambane', 'slug' => 'inhambane'],
    ['nome' => 'Sofala', 'slug' => 'sofala'],
    ['nome' => 'Manica', 'slug' => 'manica'],
    ['nome' => 'Tete', 'slug' => 'tete'],
    ['nome' => 'Zambézia', 'slug' => 'zambezia'],
    ['nome' => 'Nampula', 'slug' => 'nampula'],
    ['nome' => 'Cabo Delgado', 'slug' => 'cabo-delgado'],
    ['nome' => 'Niassa', 'slug' => 'niassa'],
    ['nome' => 'Remoto', 'slug' => 'remoto']
];

// Cidades principais
$cidades = [
    'Maputo', 'Matola', 'Beira', 'Nampula', 'Chimoio',
    'Quelimane', 'Tete', 'Xai-Xai', 'Inhambane', 'Pemba',
    'Lichinga', 'Maxixe', 'Nacala', 'Mocuba', 'Chokwé'
];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emprego MZ - Sua vaga espera por você</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Modern Enhancements CSS -->
    <link rel="stylesheet" href="assets/css/modern-enhancements.css">
    
    <!-- Vaga Cards Enhanced CSS -->
    <link rel="stylesheet" href="assets/css/vaga-cards-enhanced.css">

    <style>
        /* ========================================
           🎨 RESET & BASE STYLES
        ======================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --cor-primaria: #0088CC;
            --cor-secundaria: #FF8C00;
            --cor-texto: #1A1A1A;
            --cor-texto-claro: #5A5A5A;
            --cor-borda: #E0E0E0;
            --cor-fundo: #F5F5F5;
            --cor-branco: #FFFFFF;
            --cor-hover: #006699;

            --font-principal: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --border-radius: 8px;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: var(--font-principal);
            color: var(--cor-texto);
            background: var(--cor-branco);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* ========================================
           📱 HEADER
        ======================================== */
        .header {
            background: var(--cor-branco);
            border-bottom: 1px solid var(--cor-borda);
            padding: 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: var(--shadow-sm);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--cor-primaria);
            font-size: 24px;
            font-weight: 800;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--cor-primaria);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .nav-link {
            text-decoration: none;
            color: var(--cor-texto);
            font-size: 15px;
            font-weight: 500;
            transition: color 0.2s;
            position: relative;
            cursor: pointer;
        }

        .nav-link:hover {
            color: var(--cor-primaria);
        }

        /* ========================================
           📱 DROPDOWN MENU
        ======================================== */
        .nav-dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            min-width: 240px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            border-radius: var(--border-radius);
            z-index: 1000;
            margin-top: 8px;
            padding: 8px 0;
            border: 1px solid var(--cor-borda);
        }

        /* 🔥 NOVO: Área invisível de 20px acima do dropdown */
.dropdown-content::after {
    content: '';
    position: absolute;
    top: -20px;  /* Preenche o gap */
    left: 0;
    right: 0;
    height: 20px;
    background: transparent;
}


        .dropdown-content::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 20px;
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-bottom: 8px solid white;
        }

        .nav-dropdown:hover .dropdown-content,
.dropdown-content:hover {  /* ← ADICIONAR esta linha */
    display: block;
}


        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--cor-texto);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
             cursor: pointer;
        }

        .dropdown-item:hover {
            background: var(--cor-fundo);
            color: var(--cor-primaria);
        }

        .dropdown-item i {
            width: 18px;
            height: 18px;
            color: var(--cor-primaria);
            flex-shrink: 0;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--cor-borda);
            margin: 8px 0;
        }

        .nav-link.has-dropdown::after {
            content: '';
            display: inline-block;
            width: 0;
            height: 0;
            margin-left: 6px;
            vertical-align: middle;
            border-top: 4px solid currentColor;
            border-right: 4px solid transparent;
            border-left: 4px solid transparent;
            transition: transform 0.2s;
        }

        .nav-dropdown::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    height: 16px;
    background: transparent;
    pointer-events: none;
}

        .nav-dropdown:hover .nav-link.has-dropdown::after {
            transform: rotate(180deg);
        }

        .header-buttons {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 24px;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--cor-primaria);
            color: white;
        }

        .btn-primary:hover {
            background: var(--cor-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--cor-secundaria);
            color: white;
        }

        .btn-secondary:hover {
            background: #E67E00;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* ========================================
           🌅 HERO SECTION
        ======================================== */
        .hero {
            background: linear-gradient(135deg, #FFE8CC 0%, #FFD8A8 100%);
            padding: 120px 24px 80px;
            margin-top: 73px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 60%;
            height: 100%;
            background-image: url('assets/images/hero-bg.png');
            background-size: cover;
            background-position: center;
            opacity: 0.15;
            pointer-events: none;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .hero-title {
            font-size: 48px;
            font-weight: 800;
            color: var(--cor-texto);
            margin-bottom: 16px;
            line-height: 1.2;
            max-width: 700px;
        }

        .hero-subtitle {
            font-size: 20px;
            color: var(--cor-texto-claro);
            margin-bottom: 40px;
            max-width: 600px;
        }

        /* ========================================
           🔍 SEARCH BOX
        ======================================== */
        .search-box {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            padding: 12px;
            display: flex;
            gap: 12px;
            max-width: 900px;
            margin-bottom: 24px;
        }

        .search-input {
            flex: 1;
            padding: 16px 20px;
            border: 1px solid var(--cor-borda);
            border-radius: var(--border-radius);
            font-size: 15px;
            font-family: var(--font-principal);
            outline: none;
            transition: border 0.3s;
        }

        .search-input:focus {
            border-color: var(--cor-primaria);
        }

        .search-input::placeholder {
            color: #999;
        }

        .btn-search {
            padding: 16px 48px;
            background: var(--cor-secundaria);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-search:hover {
            background: #E67E00;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-create-profile {
            background: var(--cor-primaria);
            color: white;
            padding: 14px 32px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-create-profile:hover {
            background: var(--cor-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* ========================================
           📊 SECTION SOLUÇÕES
        ======================================== */
        .solucoes-section {
            padding: 80px 24px;
            background: var(--cor-fundo);
        }

        .solucoes-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            align-items: center;
        }

        .solucoes-text h2 {
            font-size: 32px;
            font-weight: 700;
            color: var(--cor-texto);
            margin-bottom: 24px;
            line-height: 1.3;
        }

        .solucoes-text h2 strong {
            color: var(--cor-texto-claro);
            font-weight: 500;
        }

        .solucoes-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .solucao-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 40px 24px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }

        .solucao-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-md);
        }

        .solucao-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 24px;
        }

        .solucao-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .solucao-card h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--cor-texto);
        }

        .solucao-card p {
            font-size: 14px;
            color: var(--cor-texto-claro);
        }

        /* ========================================
           📱 SECTION WHATSAPP
        ======================================== */
        .whatsapp-section {
            padding: 80px 24px;
            background: var(--cor-branco);
        }

        .whatsapp-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .whatsapp-text h2 {
            font-size: 40px;
            font-weight: 800;
            color: var(--cor-texto);
            margin-bottom: 24px;
        }

        .whatsapp-text p {
            font-size: 18px;
            color: var(--cor-texto-claro);
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .whatsapp-text strong {
            color: var(--cor-texto);
        }

        .whatsapp-mockup {
            text-align: center;
        }

        .whatsapp-chat {
            background: #E5DDD5;
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow-md);
            max-width: 400px;
            margin: 0 auto;
        }

        .chat-message {
            background: white;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 14px;
            color: var(--cor-texto);
            position: relative;
        }

        .chat-message.sent {
            background: #D9FDD3;
            margin-left: 40px;
        }

        .chat-message.received {
            background: white;
            margin-right: 40px;
        }

        /* ========================================
           🏷️ VAGAS E CONTEÚDOS SECTION
        ======================================== */
        .vagas-content-section {
            padding: 80px 24px;
            background: var(--cor-fundo);
        }

        .vagas-content-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--cor-texto);
            margin-bottom: 24px;
        }

        .section-tabs {
            display: flex;
            gap: 32px;
            border-bottom: 2px solid var(--cor-borda);
        }

        .tab {
            padding: 12px 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--cor-texto-claro);
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            bottom: -2px;
        }

        .tab.active {
            color: var(--cor-texto);
            border-bottom-color: var(--cor-secundaria);
        }

        .locations-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 24px 48px;
            margin-top: 32px;
        }

        .location-link {
            color: var(--cor-primaria);
            text-decoration: none;
            font-size: 15px;
            transition: all 0.2s;
        }

        .location-link:hover {
            color: var(--cor-hover);
            text-decoration: underline;
        }

        .ver-todas-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--cor-secundaria);
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            margin-top: 32px;
            transition: gap 0.3s;
        }

        .ver-todas-link:hover {
            gap: 12px;
        }

        /* ========================================
           🎯 CATEGORIAS SECTION
        ======================================== */
        .categorias-section {
            padding: 80px 24px 100px;
            background: var(--cor-branco);
        }

        .categorias-container {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .categorias-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--cor-texto);
            margin-bottom: 16px;
        }

        .categorias-underline {
            width: 80px;
            height: 4px;
            background: var(--cor-secundaria);
            margin: 0 auto 48px;
            border-radius: 2px;
        }

        .categorias-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 24px;
            margin-bottom: 48px;
        }

        .categoria-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            position: relative;
        }

        .categoria-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-md);
        }

        .categoria-image {
            width: 100%;
            height: 180px;
            position: relative;
            overflow: hidden;
        }

        .categoria-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .categoria-card:hover .categoria-image img {
            transform: scale(1.1);
        }

        .categoria-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .categoria-icon {
            width: 48px;
            height: 48px;
            color: white;
        }

        .categoria-nome {
            color: white;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            padding: 0 12px;
        }

        .btn-descobrir {
            background: var(--cor-secundaria);
            color: white;
            padding: 16px 48px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-descobrir:hover {
            background: #E67E00;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* ========================================
           📱 FOOTER
        ======================================== */
        .footer {
            background: var(--cor-fundo);
            border-top: 1px solid var(--cor-borda);
            padding: 60px 24px 32px;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 48px;
            margin-bottom: 48px;
        }

        .footer-column h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--cor-texto);
            margin-bottom: 20px;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: var(--cor-texto-claro);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--cor-primaria);
        }

        .footer-logo-section {
            text-align: right;
        }

        .footer-logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--cor-primaria);
            font-size: 24px;
            font-weight: 800;
            text-decoration: none;
            margin-bottom: 16px;
        }

        .footer-copyright {
            font-size: 13px;
            color: var(--cor-texto-claro);
        }

        .footer-social {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            justify-content: flex-end;
        }

        .social-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--cor-primaria);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s;
        }

        .social-icon:hover {
            background: var(--cor-hover);
            transform: translateY(-3px);
        }

        .footer-bottom {
            border-top: 1px solid var(--cor-borda);
            padding-top: 24px;
            text-align: center;
            font-size: 13px;
            color: var(--cor-texto-claro);
        }

        /* ========================================
           📱 RESPONSIVE
        ======================================== */
        @media (max-width: 1024px) {
            .categorias-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .locations-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 36px;
            }

            .hero-subtitle {
                font-size: 18px;
            }

            .search-box {
                flex-direction: column;
            }

            .btn-search {
                width: 100%;
            }

            .solucoes-container,
            .whatsapp-container {
                grid-template-columns: 1fr;
            }

            .categorias-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .locations-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .nav-menu {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .categorias-grid {
                grid-template-columns: 1fr;
            }

            .locations-grid {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: 1fr;
            }

            .footer-logo-section {
                text-align: left;
            }

            .footer-social {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>

    <!-- ========================================
         📱 HEADER COM DROPDOWN
    ======================================== -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i data-lucide="briefcase" style="width: 24px; height: 24px;"></i>
                </div>
                emprego<strong>s</strong>
            </a>

            <nav class="nav-menu">
                <!-- DROPDOWN PARA CANDIDATOS -->
                <div class="nav-dropdown">
                    <span class="nav-link has-dropdown">Para candidatos</span>
                    <div class="dropdown-content">
                        <a href="vagas.php" class="dropdown-item">
                            <i data-lucide="search"></i>
                            Todas as vagas
                        </a>
                        
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'candidato'): ?>
                            <a href="candidato/perfil.php" class="dropdown-item">
                                <i data-lucide="user"></i>
                                Meu perfil
                            </a>
                            <a href="candidato/candidaturas.php" class="dropdown-item">
                                <i data-lucide="file-text"></i>
                                Minhas candidaturas
                            </a>
                        <?php else: ?>
                            <div class="dropdown-divider"></div>
                            <a href="auth/register.php?tipo=candidato" class="dropdown-item">
                                <i data-lucide="user-plus"></i>
                                Criar conta
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <a href="empresa/dashboard.php" class="nav-link">Para empresas</a>
            </nav>

            <div class="header-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['user_type'] === 'empresa'): ?>
                        <a href="empresa/dashboard.php" class="btn btn-primary">Dashboard</a>
                    <?php else: ?>
                        <a href="candidato/perfil.php" class="btn btn-primary">Meu Perfil</a>
                    <?php endif; ?>
                    <a href="auth/logout.php" class="btn btn-secondary">Sair</a>
                <?php else: ?>
                    <a href="auth/register.php" class="btn btn-primary">Cadastre-se</a>
                    <a href="auth/login.php" class="btn btn-secondary">Entrar</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- ========================================
         🌅 HERO SECTION
    ======================================== -->
    <section class="hero">
        <div class="hero-container">
            <h1 class="hero-title">
                Sua vaga espera por você.<br>
                Simples, rápido e do seu jeito!
            </h1>
            <p class="hero-subtitle">
                Milhares de empregos disponíveis em todo Moçambique.
            </p>

            <form action="vagas.php" method="GET" class="search-box">
                <input 
                    type="text" 
                    name="q" 
                    class="search-input" 
                    placeholder="Digite um cargo ou palavra-chave"
                    required
                >
                <input 
                    type="text" 
                    name="local" 
                    class="search-input" 
                    placeholder="Endereço, CEP, bairro ou cidade"
                >
                <button type="submit" class="btn-search">
                    Buscar
                </button>
            </form>

            <?php if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] === 'candidato'): ?>
                <a href="auth/register.php" class="btn-create-profile">
                    Criar meu perfil grátis
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- ========================================
         📊 SOLUÇÕES SECTION
    ======================================== -->
    <section class="solucoes-section">
        <div class="solucoes-container">
            <div class="solucoes-text">
                <h2>
                    Soluções completas para quem busca um <strong>novo emprego</strong> ou para 
                    <strong>empresas que estão contratando.</strong>
                </h2>
            </div>

            <div class="solucoes-cards">
                <a href="auth/register.php?tipo=candidato" class="solucao-card">
                    <div class="solucao-icon">
                        <img src="assets/images/candidato-icon.svg" alt="Candidatos">
                    </div>
                    <h3>Candidatos</h3>
                    <p>Criar meu perfil</p>
                </a>

                <a href="auth/register.php?tipo=empresa" class="solucao-card">
                    <div class="solucao-icon">
                        <img src="assets/images/empresa-icon.svg" alt="Empresas">
                    </div>
                    <h3>Empresas</h3>
                    <p>Anunciar vagas</p>
                </a>
            </div>
        </div>
    </section>

    <!-- ========================================
         📱 WHATSAPP SECTION
    ======================================== -->
    <section class="whatsapp-section">
        <div class="whatsapp-container">
            <div class="whatsapp-text">
                <h2>Contato via Whatsapp!</h2>
                <p>
                    Os recrutadores podem falar com você <strong>diretamente via WhatsApp</strong>. 
                    Mais praticidade e uma chance real de agilizar sua contratação.
                </p>
                <a href="auth/register.php" class="btn btn-secondary">
                    Cadastre-se agora e encontre a vaga ideal
                </a>
            </div>

            <div class="whatsapp-mockup">
                <div class="whatsapp-chat">
                    <div class="chat-message received">
                        Olá, João! Tudo bem?<br>
                        Gostamos muito do seu currículo.<br>
                        Poderíamos marcar uma entrevista?
                    </div>
                    <div class="chat-message sent">
                        Oi! Tudo bem e com você?
                    </div>
                    <div class="chat-message sent">
                        Claro! Podemos sim :)
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================
         🏷️ VAGAS E CONTEÚDOS
    ======================================== -->
    <section class="vagas-content-section">
        <div class="vagas-content-container">
            <div class="section-header">
                <h2 class="section-title">Vagas e conteúdos que estão em alta</h2>

                <div class="section-tabs">
                    <button class="tab active" data-tab="provincias">Vagas por Província</button>
                    <button class="tab" data-tab="cidades">Vagas por Cidade</button>
                    <button class="tab" data-tab="areas">Vagas por Área</button>
                    <button class="tab" data-tab="cargos">Cargos mais procurados</button>
                </div>
            </div>

            <div class="tab-content active" data-content="provincias">
                <div class="locations-grid">
                    <?php foreach (array_slice($provincias, 0, 15) as $provincia): ?>
                        <a href="vagas.php?provincia=<?php echo urlencode($provincia['slug']); ?>" class="location-link">
                            <?php echo htmlspecialchars($provincia['nome']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <a href="vagas.php" class="ver-todas-link">
                    Ver todas as vagas →
                </a>
            </div>

            <div class="tab-content" data-content="cidades" style="display: none;">
                <div class="locations-grid">
                    <?php foreach (array_slice($cidades, 0, 15) as $cidade): ?>
                        <a href="vagas.php?cidade=<?php echo urlencode($cidade); ?>" class="location-link">
                            <?php echo htmlspecialchars($cidade); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-content" data-content="areas" style="display: none;">
                <div class="locations-grid">
                    <?php foreach ($areas_principais as $area): ?>
                        <a href="vagas.php?area=<?php echo urlencode($area['nome']); ?>" class="location-link">
                            <?php echo htmlspecialchars($area['nome']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-content" data-content="cargos" style="display: none;">
                <div class="locations-grid">
                    <a href="vagas.php?cargo=gerente" class="location-link">Gerente</a>
                    <a href="vagas.php?cargo=assistente" class="location-link">Assistente</a>
                    <a href="vagas.php?cargo=vendedor" class="location-link">Vendedor</a>
                    <a href="vagas.php?cargo=analista" class="location-link">Analista</a>
                    <a href="vagas.php?cargo=coordenador" class="location-link">Coordenador</a>
                    <a href="vagas.php?cargo=auxiliar" class="location-link">Auxiliar</a>
                    <a href="vagas.php?cargo=tecnico" class="location-link">Técnico</a>
                    <a href="vagas.php?cargo=supervisor" class="location-link">Supervisor</a>
                    <a href="vagas.php?cargo=operador" class="location-link">Operador</a>
                    <a href="vagas.php?cargo=contador" class="location-link">Contador</a>
                    <a href="vagas.php?cargo=recepcionista" class="location-link">Recepcionista</a>
                    <a href="vagas.php?cargo=motorista" class="location-link">Motorista</a>
                    <a href="vagas.php?cargo=engenheiro" class="location-link">Engenheiro</a>
                    <a href="vagas.php?cargo=consultor" class="location-link">Consultor</a>
                    <a href="vagas.php?cargo=desenvolvedor" class="location-link">Desenvolvedor</a>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================
         🎯 CATEGORIAS
    ======================================== -->
    <section class="categorias-section">
        <div class="categorias-container">
            <h2 class="categorias-title">Encontre vagas na sua área</h2>
            <div class="categorias-underline"></div>

            <div class="categorias-grid">
                <?php foreach ($areas_principais as $area): ?>
                    <a href="vagas.php?area=<?php echo urlencode($area['nome']); ?>" class="categoria-card">
                        <div class="categoria-image">
                            <img src="assets/images/<?php echo $area['imagem']; ?>" alt="<?php echo htmlspecialchars($area['nome']); ?>">
                            <div class="categoria-overlay">
                                <i data-lucide="<?php echo $area['icone']; ?>" class="categoria-icon"></i>
                                <div class="categoria-nome"><?php echo htmlspecialchars($area['nome']); ?></div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <a href="vagas.php" class="btn-descobrir">
                Descubra vagas na sua área
            </a>
        </div>
    </section>

    <!-- ========================================
         📱 FOOTER
    ======================================== -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3>Institucional</h3>
                    <ul class="footer-links">
                        <li><a href="#">Sobre o EmpregosMZ</a></li>
                        <li><a href="#">Políticas de privacidade</a></li>
                        <li><a href="#">Contrato de Serviços</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h3>Candidatos</h3>
                    <ul class="footer-links">
                        <li><a href="vagas.php">Busca de Vagas</a></li>
                        <li><a href="#">Busca de empresas</a></li>
                        <li><a href="auth/register.php">Cadastrar currículo</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h3>Para empresas</h3>
                    <ul class="footer-links">
                        <li><a href="empresa/criar_vaga.php">Anúncio de vagas</a></li>
                        <li><a href="#">Busca de currículos</a></li>
                    </ul>
                </div>

                <div class="footer-column footer-logo-section">
                    <a href="index.php" class="footer-logo">
                        <div class="logo-icon">
                            <i data-lucide="briefcase" style="width: 20px; height: 20px;"></i>
                        </div>
                        emprego<strong>s</strong>
                    </a>
                    <div class="footer-copyright">
                        Copyright © 2024-2025<br>
                        EmpregosMZ.com.mz.<br>
                        Todos os direitos reservados.
                    </div>
                    <div class="footer-social">
                        <a href="#" class="social-icon">
                            <i data-lucide="instagram" style="width: 20px; height: 20px;"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i data-lucide="linkedin" style="width: 20px; height: 20px;"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i data-lucide="facebook" style="width: 20px; height: 20px;"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i data-lucide="twitter" style="width: 20px; height: 20px;"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i data-lucide="youtube" style="width: 20px; height: 20px;"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>EmpregosMZ LTDA | NUIT: XX.XXX.XXX/XXXX-XX | Avenida Julius Nyerere, nº XXX, Maputo, Moçambique</p>
            </div>
        </div>
    </footer>

    <!-- ========================================
         ✨ SCRIPTS
    ======================================== -->
    
    <!-- Modern Features JavaScript -->
    <script src="assets/js/modern-features.js"></script>
    
    <script>
        // Inicializar ícones Lucide
        lucide.createIcons();

        // Funcionalidade das tabs
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;

                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(content => {
                    content.style.display = 'none';
                    content.classList.remove('active');
                });

                tab.classList.add('active');
                const targetContent = document.querySelector(`[data-content="${targetTab}"]`);
                if (targetContent) {
                    targetContent.style.display = 'block';
                    targetContent.classList.add('active');
                }
            });
        });

        // Mostrar notificação de boas-vindas
        setTimeout(() => {
            <?php if (isset($_SESSION['user_id'])): ?>
                showToast('Bem-vindo de volta, <?php echo htmlspecialchars($_SESSION['nome'] ?? 'Usuário'); ?>! 👋', 'success');
            <?php endif; ?>
        }, 500);
    </script>
</body>
</html>