<?php
session_start();
require_once 'config/db.php';

$sucesso = '';
$erro = '';
$erros_validacao = [];

// Processar formulário de contacto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_mensagem'])) {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $assunto = trim($_POST['assunto'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');
    
    // ========================================
    // VALIDAÇÕES ROBUSTAS
    // ========================================
    
    // Validar nome
    if (empty($nome)) {
        $erros_validacao[] = "O nome é obrigatório.";
    } elseif (strlen($nome) < 2) {
        $erros_validacao[] = "O nome deve ter pelo menos 2 caracteres.";
    } elseif (strlen($nome) > 100) {
        $erros_validacao[] = "O nome não pode ter mais de 100 caracteres.";
    } elseif (!preg_match("/^[a-zA-ZÀ-ÿ\s'-]+$/u", $nome)) {
        $erros_validacao[] = "O nome contém caracteres inválidos.";
    }
    
    // Validar email
    if (empty($email)) {
        $erros_validacao[] = "O email é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros_validacao[] = "Por favor, insira um email válido.";
    } elseif (strlen($email) > 150) {
        $erros_validacao[] = "O email não pode ter mais de 150 caracteres.";
    }
    
    // Validar assunto
    if (empty($assunto)) {
        $erros_validacao[] = "O assunto é obrigatório.";
    } elseif (strlen($assunto) < 3) {
        $erros_validacao[] = "O assunto deve ter pelo menos 3 caracteres.";
    } elseif (strlen($assunto) > 150) {
        $erros_validacao[] = "O assunto não pode ter mais de 150 caracteres.";
    }
    
    // Validar mensagem
    if (empty($mensagem)) {
        $erros_validacao[] = "A mensagem é obrigatória.";
    } elseif (strlen($mensagem) < 10) {
        $erros_validacao[] = "A mensagem deve ter pelo menos 10 caracteres.";
    } elseif (strlen($mensagem) > 2000) {
        $erros_validacao[] = "A mensagem não pode ter mais de 2000 caracteres.";
    }
    
    // Validação anti-spam simples
    if (!empty($nome) && !empty($mensagem)) {
        // Verificar se tem links suspeitos em excesso
        $link_count = preg_match_all('/https?:\/\//i', $mensagem);
        if ($link_count > 3) {
            $erros_validacao[] = "A mensagem contém muitos links. Por favor, reduza o número de links.";
        }
    }
    
    // Se não há erros, processar
    if (empty($erros_validacao)) {
        try {
        // Aqui você pode implementar o envio de email ou salvar no banco
        // Por enquanto, apenas exibimos mensagem de sucesso
        $sucesso = "Mensagem enviada com sucesso! Entraremos em contacto em breve.";
        
        // Limpar campos
        $nome = $email = $assunto = $mensagem = '';
        } catch (Exception $e) {
            $erros_validacao[] = "Erro ao enviar mensagem. Por favor, tente novamente mais tarde.";
            error_log("Erro ao enviar mensagem de contacto: " . $e->getMessage());
        }
    }
    
    // Consolidar erros para exibição
    if (!empty($erros_validacao)) {
        $erro = implode('<br>', $erros_validacao);
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto | Emprego MZ</title>
    <meta name="description" content="Entre em contacto com o Emprego MZ. Estamos aqui para ajudar!">
    
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
            
            /* 🔷 PÁGINA CONTACTO - Azul */
            --cor-contacto: #06B6D4;
            --cor-contacto-escuro: #0891b2;
            --cor-contacto-light: #CFFAFE;
            
            /* FORMULÁRIO */
            --cor-campo-valido: #10B981;
            --cor-campo-valido-light: #D1FAE5;
            --cor-campo-erro: #EF4444;
            --cor-campo-erro-light: #FEE2E2;
            --cor-obrigatorio: #F59E0B;
            
            /* 🎯 BORDAS E SOMBRAS */
            --cor-borda: #e2e8f0;
            --sombra-conteudo: 0 6px 30px rgba(20, 33, 61, 0.10);
            --sombra-card: 0 4px 20px rgba(20, 33, 61, 0.08);
            --sombra-media: 0 4px 12px rgba(20, 33, 61, 0.12);
            --sombra-formulario: 0 8px 40px rgba(20, 33, 61, 0.12);
            --sombra-input-focus: 0 0 0 4px rgba(6, 182, 212, 0.15);
            
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
           🔷 HERO CONTACTO - Azul
        ========================================== */
        .page-container {
            margin-top: 70px;
        }

        .page-hero {
            background: var(--cor-cards);
            border-bottom: 6px solid var(--cor-contacto);
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
            background: linear-gradient(135deg, var(--cor-contacto), var(--cor-contacto-escuro));
            box-shadow: 0 8px 32px rgba(6, 182, 212, 0.3);
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
           📞 CONTACT GRID
        ========================================== */
        .contact-container {
            max-width: 1200px;
            margin: 0 auto var(--space-16);
            padding: 0 var(--space-6);
        }
        
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-10);
        }

        /* ==========================================
           📋 CONTACT INFO
        ========================================== */
        .contact-info {
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius-2xl);
            padding: var(--space-10);
            box-shadow: var(--sombra-card);
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

        .contact-info h2 {
            font-size: 32px;
            font-weight: 700;
            color: var(--cor-primaria);
            margin-bottom: var(--space-4);
        }
        
        .contact-info > p {
            color: var(--cor-texto-claro);
            margin-bottom: var(--space-8);
            line-height: 1.7;
            font-size: 16px;
        }
        
        .info-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-6);
        }
        
        .info-item {
            display: flex;
            gap: var(--space-4);
        }
        
        .info-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--cor-contacto), var(--cor-contacto-escuro));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        
        .info-content h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--cor-texto);
            margin-bottom: var(--space-1);
        }
        
        .info-content p {
            color: var(--cor-texto-claro);
            font-size: 15px;
            line-height: 1.6;
        }
        
        .info-content a {
            color: var(--cor-contacto);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .info-content a:hover {
            text-decoration: underline;
        }
        
        /* ==========================================
           📧 CONTACT FORM - Azul
        ========================================== */
        .contact-form {
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius-2xl);
            padding: var(--space-10);
            box-shadow: var(--sombra-formulario);
            animation: fadeInUp 0.6s ease-out 0.1s;
            animation-fill-mode: both;
        }

        .contact-form h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--cor-contacto);
            margin-bottom: var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }
        
        .form-group {
            margin-bottom: var(--space-5);
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: var(--space-2);
            color: var(--cor-texto);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: var(--space-1);
        }

        .required-indicator {
            color: var(--cor-obrigatorio);
            font-size: 14px;
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: var(--space-4) var(--space-5);
            background: var(--cor-cards);
            border: 2px solid var(--cor-borda);
            border-radius: var(--radius-lg);
            font-size: 16px;
            font-family: var(--font-family);
            color: var(--cor-texto);
            transition: all 0.3s ease;
            resize: vertical;
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            background: var(--cor-acento);
            border-color: var(--cor-contacto);
            box-shadow: var(--sombra-input-focus);
            transform: scale(1.01);
        }

        .form-input::placeholder,
        .form-textarea::placeholder {
            color: var(--cor-texto-muito-claro);
            font-style: italic;
        }
        
        .form-textarea {
            min-height: 140px;
            line-height: 1.6;
        }

        .btn-submit {
            background: var(--cor-contacto);
            color: white;
            border: none;
            border-radius: var(--radius-lg);
            padding: var(--space-4) var(--space-8);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 16px rgba(6, 182, 212, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            background: var(--cor-contacto-escuro);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(6, 182, 212, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* ==========================================
           🔔 ALERTS
        ========================================== */
        .alert {
            padding: var(--space-4) var(--space-5);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-5);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-weight: 500;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: var(--cor-campo-valido-light);
            color: #065f46;
            border: 1px solid var(--cor-campo-valido);
        }
        
        .alert-error {
            background: var(--cor-campo-erro-light);
            color: #991b1b;
            border: 1px solid var(--cor-campo-erro);
        }

        .alert svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        /* ==========================================
           ❓ FAQ SECTION
        ========================================== */
        .faq-section {
            max-width: 1200px;
            margin: 0 auto var(--space-16);
            padding: 0 var(--space-6);
        }
        
        .faq-section h2 {
            font-size: 36px;
            font-weight: 800;
            text-align: center;
            color: var(--cor-primaria);
            margin-bottom: var(--space-10);
        }
        
        .faq-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-6);
        }
        
        .faq-item {
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            box-shadow: var(--sombra-card);
            transition: var(--transition);
        }

        .faq-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(20, 33, 61, 0.12);
        }
        
        .faq-item h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: var(--space-3);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            color: var(--cor-texto);
        }
        
        .faq-item h3 i {
            color: var(--cor-contacto);
            flex-shrink: 0;
        }
        
        .faq-item p {
            color: var(--cor-texto-claro);
            line-height: 1.7;
            font-size: 15px;
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

            .contact-grid {
                grid-template-columns: 1fr;
                gap: var(--space-8);
            }

            .faq-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 36px;
            }

            .contact-info,
            .contact-form {
                padding: var(--space-8);
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

            .contact-form h2 {
                font-size: 24px;
            }

            .form-input,
            .form-select,
            .form-textarea {
                padding: var(--space-3) var(--space-4);
                font-size: 16px; /* Evitar zoom no iOS */
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
         🔷 PAGE CONTAINER
    ========================================== -->
    <div class="page-container">

    <!-- Hero -->
        <section class="page-hero">
            <div class="hero-container">
                <div class="page-icon">
                    <i data-lucide="mail"></i>
                </div>
                <h1 class="page-title">Entre em Contacto</h1>
                <p class="page-subtitle">
                    Estamos aqui para ajudar. Envie-nos uma mensagem!
                </p>
            </div>
    </section>

        <!-- Contact Grid -->
    <div class="contact-container">
        <div class="contact-grid">
            <!-- Contact Info -->
            <div class="contact-info">
                <h2>Fale Connosco</h2>
                <p>
                    Tem dúvidas, sugestões ou precisa de ajuda? Nossa equipa está pronta 
                    para atendê-lo. Escolha o canal mais conveniente.
                </p>
                
                <div class="info-list">
                    <div class="info-item">
                        <div class="info-icon">
                            <i data-lucide="mail" style="width: 24px; height: 24px;"></i>
                        </div>
                        <div class="info-content">
                            <h3>Email</h3>
                            <p><a href="mailto:contacto@empregomz.co.mz">contacto@empregomz.co.mz</a></p>
                            <p style="font-size: 13px; margin-top: 4px;">Respondemos em até 24h</p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i data-lucide="phone" style="width: 24px; height: 24px;"></i>
                        </div>
                        <div class="info-content">
                            <h3>Telefone</h3>
                            <p><a href="tel:+258843001234">+258 84 300 1234</a></p>
                            <p style="font-size: 13px; margin-top: 4px;">Seg - Sex: 8h às 17h</p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i data-lucide="map-pin" style="width: 24px; height: 24px;"></i>
                        </div>
                        <div class="info-content">
                            <h3>Localização</h3>
                            <p>Av. Julius Nyerere, 1234</p>
                            <p>Maputo, Moçambique</p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i data-lucide="clock" style="width: 24px; height: 24px;"></i>
                        </div>
                        <div class="info-content">
                            <h3>Horário de Atendimento</h3>
                            <p>Segunda a Sexta: 8h - 17h</p>
                            <p>Sábado: 9h - 13h</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Form -->
            <div class="contact-form">
                    <h2>
                        <i data-lucide="send"></i>
                        Envie uma Mensagem
                    </h2>
                
                <?php if ($sucesso): ?>
                    <div class="alert alert-success">
                            <i data-lucide="check-circle"></i>
                        <?php echo $sucesso; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($erro): ?>
                    <div class="alert alert-error">
                            <i data-lucide="alert-circle"></i>
                        <?php echo $erro; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                            <label class="form-label">
                                Nome Completo <span class="required-indicator">*</span>
                            </label>
                        <input type="text" name="nome" class="form-input" 
                                   placeholder="Digite seu nome completo"
                               value="<?php echo htmlspecialchars($nome ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                            <label class="form-label">
                                Email <span class="required-indicator">*</span>
                            </label>
                        <input type="email" name="email" class="form-input" 
                                   placeholder="seuemail@exemplo.com"
                               value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                            <label class="form-label">
                                Assunto <span class="required-indicator">*</span>
                            </label>
                        <select name="assunto" class="form-select" required>
                            <option value="">Selecione um assunto</option>
                            <option value="duvida" <?php echo (($assunto ?? '') === 'duvida') ? 'selected' : ''; ?>>Dúvida Geral</option>
                            <option value="suporte" <?php echo (($assunto ?? '') === 'suporte') ? 'selected' : ''; ?>>Suporte Técnico</option>
                            <option value="empresa" <?php echo (($assunto ?? '') === 'empresa') ? 'selected' : ''; ?>>Sou Empresa</option>
                            <option value="candidato" <?php echo (($assunto ?? '') === 'candidato') ? 'selected' : ''; ?>>Sou Candidato</option>
                            <option value="parceria" <?php echo (($assunto ?? '') === 'parceria') ? 'selected' : ''; ?>>Parceria</option>
                            <option value="outro" <?php echo (($assunto ?? '') === 'outro') ? 'selected' : ''; ?>>Outro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                            <label class="form-label">
                                Mensagem <span class="required-indicator">*</span>
                            </label>
                            <textarea name="mensagem" class="form-textarea" 
                                      placeholder="Escreva sua mensagem aqui..." 
                                      required><?php echo htmlspecialchars($mensagem ?? ''); ?></textarea>
                    </div>
                    
                        <button type="submit" name="enviar_mensagem" class="btn-submit">
                        Enviar Mensagem
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- FAQ Section -->
    <section class="faq-section">
        <h2>Perguntas Frequentes</h2>
        <div class="faq-grid">
            <div class="faq-item">
                <h3>
                    <i data-lucide="help-circle" style="width: 20px; height: 20px;"></i>
                    Como me candidato a uma vaga?
                </h3>
                <p>
                    Basta criar uma conta gratuita, completar seu perfil e clicar em 
                    "Candidatar-se" nas vagas que lhe interessam.
                </p>
            </div>
            
            <div class="faq-item">
                <h3>
                    <i data-lucide="help-circle" style="width: 20px; height: 20px;"></i>
                    É gratuito para candidatos?
                </h3>
                <p>
                    Sim! A plataforma é 100% gratuita para candidatos. Você pode buscar 
                    vagas, candidatar-se e atualizar seu perfil sem custos.
                </p>
            </div>
            
            <div class="faq-item">
                <h3>
                    <i data-lucide="help-circle" style="width: 20px; height: 20px;"></i>
                    Como posso publicar uma vaga?
                </h3>
                <p>
                    Empresas devem criar uma conta, completar o perfil empresarial e 
                    então poderão publicar vagas através do dashboard.
                </p>
            </div>
            
            <div class="faq-item">
                <h3>
                    <i data-lucide="help-circle" style="width: 20px; height: 20px;"></i>
                    Não consigo aceder minha conta
                </h3>
                <p>
                    Use a opção "Esqueci minha senha" na página de login ou entre em 
                    contacto conosco para assistência.
                </p>
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
        
        // ========================================
        // VALIDAÇÃO EM TEMPO REAL
        // ========================================
        
        // Validação de nome em tempo real
        const nomeInput = document.getElementById('nome');
        if (nomeInput) {
            nomeInput.addEventListener('input', function() {
                const nome = this.value.trim();
                const minLength = 2;
                const maxLength = 100;
                
                if (nome.length < minLength) {
                    this.style.borderColor = '#F59E0B'; // warning
                } else if (nome.length > maxLength) {
                    this.style.borderColor = '#EF4444'; // error
                } else {
                    this.style.borderColor = '#10B981'; // success
                }
            });
        }
        
        // Validação de email em tempo real
        const emailInput = document.getElementById('email');
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                const email = this.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email.length === 0) {
                    this.style.borderColor = '#e2e8f0'; // default
                } else if (emailRegex.test(email)) {
                    this.style.borderColor = '#10B981'; // success
                } else {
                    this.style.borderColor = '#EF4444'; // error
                }
            });
        }
        
        // Contador de caracteres para assunto
        const assuntoInput = document.getElementById('assunto');
        if (assuntoInput) {
            assuntoInput.addEventListener('input', function() {
                const length = this.value.length;
                const minLength = 3;
                const maxLength = 150;
                
                if (length < minLength) {
                    this.style.borderColor = '#F59E0B'; // warning
                } else if (length > maxLength) {
                    this.style.borderColor = '#EF4444'; // error
                } else {
                    this.style.borderColor = '#10B981'; // success
                }
            });
        }
        
        // Contador de caracteres para mensagem
        const mensagemInput = document.getElementById('mensagem');
        if (mensagemInput) {
            mensagemInput.addEventListener('input', function() {
                const length = this.value.length;
                const minLength = 10;
                const maxLength = 2000;
                
                if (length < minLength) {
                    this.style.borderColor = '#F59E0B'; // warning
                } else if (length > maxLength) {
                    this.style.borderColor = '#EF4444'; // error
                } else {
                    this.style.borderColor = '#10B981'; // success
                }
            });
        }
        
        // Validação no submit
        const formContacto = document.querySelector('form[name="form_contacto"]');
        if (formContacto) {
            formContacto.addEventListener('submit', function(e) {
                let hasErrors = false;
                const erros = [];
                
                // Validar nome
                const nome = nomeInput?.value.trim() || '';
                if (nome.length < 2) {
                    hasErrors = true;
                    erros.push('O nome deve ter pelo menos 2 caracteres.');
                }
                
                // Validar email
                const email = emailInput?.value.trim() || '';
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    hasErrors = true;
                    erros.push('Por favor, insira um email válido.');
                }
                
                // Validar assunto
                const assunto = assuntoInput?.value.trim() || '';
                if (assunto.length < 3) {
                    hasErrors = true;
                    erros.push('O assunto deve ter pelo menos 3 caracteres.');
                }
                
                // Validar mensagem
                const mensagem = mensagemInput?.value.trim() || '';
                if (mensagem.length < 10) {
                    hasErrors = true;
                    erros.push('A mensagem deve ter pelo menos 10 caracteres.');
                }
                
                if (hasErrors) {
                    e.preventDefault();
                    alert('Por favor, corrija os seguintes erros:\n\n' + erros.join('\n'));
                    return false;
                }
            });
        }
        
        // Scroll suave para o formulário se houver erro
        window.addEventListener('DOMContentLoaded', function() {
            const alertError = document.querySelector('.alert-error');
            const alertSuccess = document.querySelector('.alert-success');
            
            if (alertError || alertSuccess) {
                const form = document.querySelector('.contact-form');
                if (form) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    </script>

</body>
</html>
