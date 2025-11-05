<?php
require_once '../config/session.php';  // Configuração de sessão não-persistente
require_once '../config/db.php';
require_once '../config/auth_functions.php';
require_once '../config/admin_functions.php';

// Se já estiver logado como admin, redirecionar para o dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: ../admin/index.php");
    exit;
}

$erro = '';
$tentativas_restantes = null;

// Processar o formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    
    // ========================================
    // VALIDAÇÃO 1: CAMPOS VAZIOS
    // ========================================
    if (empty($email) || empty($senha)) {
        $erro = "Por favor, preencha todos os campos.";
    }
    // ========================================
    // VALIDAÇÃO 2: FORMATO DE EMAIL
    // ========================================
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Por favor, informe um email válido.";
    }
    // ========================================
    // VALIDAÇÃO 3: TAMANHO DO EMAIL
    // ========================================
    elseif (strlen($email) > 255) {
        $erro = "O email informado é muito longo.";
    }
    // ========================================
    // VALIDAÇÃO 4: RATE LIMITING (BLOQUEIO MAIS RESTRITIVO)
    // ========================================
    else {
        try {
            // Verificar se tabela de rate limiting existe
            $rate_limiting_habilitado = loginAttemptsTableExists($pdo);
            
            if ($rate_limiting_habilitado) {
                // Verificar bloqueio por email (ADMIN)
                $bloqueio_email = verificarBloqueio($pdo, $email, 'admin_email');
                if ($bloqueio_email) {
                    $tempo = formatarTempoBloqueio($bloqueio_email['bloqueado_ate']);
                    $erro = "Acesso bloqueado. Tente novamente em $tempo.";
                    
                    // Log de tentativa de acesso bloqueado
                    error_log("SEGURANÇA: Tentativa de login admin bloqueada - Email: $email, IP: $ip");
                }
                
                // Verificar bloqueio por IP (ADMIN)
                $bloqueio_ip = verificarBloqueio($pdo, $ip, 'admin_ip');
                if ($bloqueio_ip) {
                    $tempo = formatarTempoBloqueio($bloqueio_ip['bloqueado_ate']);
                    $erro = "Acesso bloqueado. Tente novamente em $tempo.";
                    
                    // Log de tentativa de acesso bloqueado
                    error_log("SEGURANÇA: Tentativa de login admin bloqueada - IP: $ip");
                }
            }
            
            // Se não está bloqueado, processar login
            if (empty($erro)) {
                // Buscar admin pelo email
                $stmt = $pdo->prepare("SELECT id, email, senha, nome FROM admin WHERE email = ?");
                $stmt->execute([$email]);
                $admin = $stmt->fetch();
                
                // ========================================
                // VALIDAÇÃO 5: CREDENCIAIS
                // ========================================
                if ($admin && password_verify($senha, $admin['senha'])) {
                    // ========================================
                    // LOGIN BEM-SUCEDIDO
                    // ========================================
                    
                    // Limpar tentativas falhadas
                    if ($rate_limiting_habilitado) {
                        limparTentativas($pdo, $email, 'admin_email');
                        limparTentativas($pdo, $ip, 'admin_ip');
                    }
                    
                    // Registrar login bem-sucedido em admin_logs
                    registrarLoginAdmin($pdo, $admin['id'], $admin['email']);
                    
                    // Criar sessão
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_nome'] = $admin['nome'];
                    
                    // Atualizar último login
                    $pdo->prepare("UPDATE admin SET ultimo_login = NOW() WHERE id = ?")
                        ->execute([$admin['id']]);
                    
                    // Log de sucesso
                    error_log("Admin Login: {$admin['email']} (IP: $ip) - SUCESSO");
                    
                    header("Location: ../admin/index.php");
                    exit;
                    
                } else {
                    // ========================================
                    // CREDENCIAIS INVÁLIDAS
                    // ========================================
                    $erro = "Email ou senha incorretos.";
                    
                    // Registrar tentativa falhada (ADMIN - MAIS RESTRITIVO)
                    if ($rate_limiting_habilitado) {
                        // ADMIN: 3 tentativas, bloqueio de 30 minutos
                        $bloqueado_email = registrarTentativaFalhada($pdo, $email, 'admin_email', 3, 30);
                        $bloqueado_ip = registrarTentativaFalhada($pdo, $ip, 'admin_ip', 3, 30);
                        
                        if ($bloqueado_email || $bloqueado_ip) {
                            $erro = "Muitas tentativas falhadas. Acesso bloqueado por 30 minutos.";
                            
                            // Log crítico de segurança
                            error_log("ALERTA SEGURANÇA: Admin bloqueado após 3 tentativas - Email: $email, IP: $ip");
                        } else {
                            $tentativas_restantes = getTentativasRestantes($pdo, $email, 'admin_email', 3);
                        }
                    }
                    
                    // Log de tentativa falhada
                    error_log("Admin Login FALHA: Email: $email, IP: $ip");
                }
            }
        } catch (PDOException $e) {
            $erro = "Erro no sistema. Por favor, tente novamente.";
            error_log("Erro PDO em auth/admin_login.php: " . $e->getMessage());
        } catch (Exception $e) {
            $erro = "Erro inesperado. Por favor, tente novamente.";
            error_log("Erro inesperado em auth/admin_login.php: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo | Emprego MZ</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        /* ==========================================
           🎨 CSS VARIABLES - CONSISTENT WITH PLATFORM
        ========================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            /* === 🎯 PALETA DEFINITIVA PARA ADMIN LOGIN === */
            
            /* 🔵 CORES PRINCIPAIS ADMIN */
            --cor-primaria-admin: #0c1220;        /* Dark Navy - Mais escuro e autoritativo */
            --cor-primaria-admin-escura: #070b12; /* Ultra dark para hover */
            --cor-primaria-admin-clara: #1e293b;  /* Elementos secundários */
            
            /* 🟠 CORES SECUNDÁRIAS ADMIN */
            --cor-secundaria-admin: #dc6803;      /* Darker Orange - Mais profissional */
            --cor-secundaria-admin-hover: #c2590c; /* Hover em botões críticos */
            
            /* 🌫️ HIERARQUIA VISUAL ADMIN */
            --cor-fundo-admin: #f1f5f9;           /* Lighter Gray - Fundo clean */
            --cor-cards-admin: #ffffff;           /* Pure White - Cards e painéis */
            --cor-acento-admin: #e2e8f0;          /* Lighter Blue-Gray - Elementos suaves */
            --cor-acento-admin-escuro: #cbd5e1;   /* Bordas e separadores */
            
            /* 📝 TEXTO ADMIN */
            --cor-texto-admin: #0f172a;           /* Texto principal super escuro */
            --cor-texto-admin-claro: #475569;     /* Texto secundário */
            --cor-texto-admin-muito-claro: #94a3b8; /* Metadados */
            --cor-texto-admin-inverso: #ffffff;   /* Texto em fundos escuros */
            
            /* 🎯 ESTADOS ADMIN */
            --cor-admin-critico: #dc2626;         /* Vermelho para ações críticas */
            --cor-admin-critico-light: #fef2f2;   /* Fundo crítico */
            --cor-admin-sucesso: #059669;         /* Verde para sucessos */
            --cor-admin-sucesso-light: #ecfdf5;   /* Fundo sucesso */
            
            /* 🔒 BORDAS E SOMBRAS ADMIN */
            --cor-borda-admin: #cbd5e1;           /* Bordas padrão */
            --cor-borda-admin-ativa: #0c1220;     /* Bordas em foco */
            --sombra-admin-card: 0 4px 24px rgba(12, 18, 32, 0.08);
            --sombra-admin-modal: 0 12px 48px rgba(12, 18, 32, 0.15);
            --sombra-admin-button: 0 2px 8px rgba(220, 104, 3, 0.3);
            --sombra-admin-input-focus: 0 0 0 3px rgba(12, 18, 32, 0.1);
            
            /* 🔄 Aliases compatibilidade */
            --primary: var(--cor-primaria);
            --primary-dark: var(--cor-primaria-escura);
            --secondary: var(--cor-secundaria);
            --text: var(--cor-texto);
            --text-light: var(--cor-texto-claro);
            --border: var(--cor-borda);
            --background: var(--cor-fundo);
            --white: var(--cor-cards);
            --success: var(--cor-sucesso);
            --error: var(--cor-erro);
            
            /* 🔐 Admin Accent - Oxford Blue */
            --admin-accent: var(--cor-primaria);
            --admin-accent-light: rgba(20, 33, 61, 0.1);
            
            /* Typography */
            --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            
            /* Spacing */
            --space-1: 4px;
            --space-2: 8px;
            --space-3: 12px;
            --space-4: 16px;
            --space-5: 20px;
            --space-6: 24px;
            --space-8: 32px;
            
            /* Borders */
            --radius: 8px;
            --radius-lg: 12px;
            
            /* Shadows - Usando variáveis específicas */
            --shadow: var(--sombra-card);
            --shadow-lg: var(--sombra-card);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--cor-primaria-admin), var(--cor-primaria-admin-clara)); /* Dark Navy gradient */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--cor-texto-admin);           /* #0f172a - Super escuro */
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* ==========================================
           🔐 LOGO HEADER - CONSISTENT WITH LOGIN.PHP
        ========================================== */
        .logo-container {
            text-align: center;
            margin-bottom: var(--space-8);
        }
        
        .logo-link {
            display: inline-block;
            transition: var(--transition);
        }
        
        .logo-link:hover {
            opacity: 0.8;
        }
        
        .logo-img {
            height: 40px;
            width: auto;
        }
        
        /* Admin Badge - Mais Autoritativo */
        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-5);
            background: rgba(220, 104, 3, 0.1);
            border: 2px solid var(--cor-secundaria-admin);
            border-radius: var(--radius-lg);
            font-size: 12px;
            font-weight: 700;
            color: var(--cor-secundaria-admin);
            margin-top: var(--space-4);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .admin-badge svg {
            width: 16px;
            height: 16px;
        }
        
        /* ==========================================
           🎴 LOGIN CONTAINER - CONSISTENT WITH LOGIN.PHP
        ========================================== */
        .login-container {
            width: 100%;
            max-width: 420px;
        }
        
        .login-card {
            background: var(--cor-cards-admin);      /* #ffffff - White */
            border-radius: 24px;
            box-shadow: var(--sombra-admin-modal);   /* Sombra modal admin */
            padding: 48px;
            border: 1px solid var(--cor-borda-admin);/* #cbd5e1 */
            border-top: 6px solid var(--cor-secundaria-admin); /* Darker Orange - Admin badge */
            text-align: center;
            animation: fadeInUp 0.6s ease-out;
            position: relative;
            overflow: hidden;
        }
        
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--cor-secundaria-admin), var(--cor-secundaria-admin-hover));
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
        
        /* Header - CONSISTENT WITH LOGIN.PHP */
        .login-header {
            margin-bottom: 32px;
        }
        
        /* ==========================================
           🔔 ALERTS - ERROR E WARNING
        ========================================== */
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-left: 4px solid var(--cor-erro-admin);
            color: #991B1B;
            padding: 14px 16px;
            border-radius: var(--radius-lg);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: var(--space-4);
            text-align: left;
            display: flex;
            align-items: flex-start;
            gap: var(--space-3);
        }
        
        .alert-error svg {
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-left: 4px solid var(--cor-aviso-admin);
            color: #92400E;
            padding: 14px 16px;
            border-radius: var(--radius-lg);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: var(--space-4);
            text-align: left;
            display: flex;
            align-items: flex-start;
            gap: var(--space-3);
        }
        
        .alert-warning svg {
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .login-title {
            font-size: 28px;
            font-weight: 800;
            color: var(--cor-primaria-admin);        /* #0c1220 - Dark Navy */
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .login-subtitle {
            font-size: 16px;
            color: var(--cor-texto-admin-claro);     /* #475569 */
            font-weight: 500;
        }
        
        /* ==========================================
           🔒 SECURITY INFO - SIMPLIFIED
        ========================================== */
        .security-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
            padding: 12px;
            background: var(--cor-admin-sucesso-light); /* Verde claro */
            border: 1px solid var(--cor-admin-sucesso);
            border-radius: 8px;
            font-size: 12px;
            color: var(--cor-admin-sucesso);
            font-weight: 600;
        }
        
        .security-info svg {
            width: 14px;
            height: 14px;
            color: var(--cor-admin-sucesso);
        }
        
        /* ==========================================
           ⚠️ ALERTS - CONSISTENT WITH LOGIN.PHP
        ========================================== */
        .alert-error {
            background: var(--cor-admin-critico-light); /* #fef2f2 */
            border: 1px solid rgba(220, 38, 38, 0.3);
            border-left: 4px solid var(--cor-admin-critico); /* #dc2626 */
            color: #991B1B;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: var(--space-3);
        }
        
        .alert-error svg {
            flex-shrink: 0;
            margin-top: 2px;
            width: 16px;
            height: 16px;
        }
        
        /* ==========================================
           📝 FORM ELEMENTS - CONSISTENT WITH LOGIN.PHP
        ========================================== */
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: var(--cor-texto-admin);           /* #0f172a - Super escuro */
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-label-required::after {
            content: ' *';
            color: var(--cor-admin-critico);         /* #dc2626 */
        }
        
        /* Input Container */
        .input-container {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--cor-texto-admin-claro);     /* #475569 */
            pointer-events: none;
        }
        
        .input-icon svg {
            width: 18px;
            height: 18px;
        }
        
        /* Form Input */
        .form-input {
            width: 100%;
            padding: 16px 20px;
            padding-left: 44px;
            border: 2px solid var(--cor-borda-admin); /* #cbd5e1 */
            border-radius: 12px;
            font-size: 16px;
            font-family: inherit;
            color: var(--cor-texto-admin);           /* #0f172a - Super escuro */
            background: var(--cor-cards-admin);      /* #ffffff */
            transition: all 0.3s ease;
            outline: none;
        }
        
        .form-input::placeholder {
            color: var(--cor-texto-admin-muito-claro); /* #94a3b8 */
            font-style: italic;
        }
        
        .form-input:hover {
            border-color: var(--cor-primaria-admin-clara);
        }
        
        .form-input:focus {
            background: var(--cor-acento-admin);     /* #e2e8f0 - Lighter Gray */
            border-color: var(--cor-primaria-admin); /* #0c1220 - Dark Navy */
            box-shadow: var(--sombra-admin-input-focus); /* 3px Dark Navy */
            transform: scale(1.01);
        }
        
        @keyframes inputFocus {
            from { transform: scale(1); }
            50% { transform: scale(1.02); }
            to { transform: scale(1); }
        }
        
        /* Input com Toggle de Senha */
        .input-with-toggle {
            position: relative;
        }
        
        .input-with-toggle .form-input {
            padding-right: 44px;
        }
        
        .toggle-password {
            position: absolute;
            right: var(--space-4);
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: var(--space-2);
            cursor: pointer;
            color: var(--cor-texto-muito-claro);
            transition: var(--transition);
            border-radius: var(--radius-sm);
        }
        
        .toggle-password:hover {
            color: var(--cor-primaria-admin);
            background: var(--cor-acento-admin);
        }
        
        .toggle-password svg {
            width: 18px;
            height: 18px;
        }
        
        /* ==========================================
           🔘 BUTTONS - CONSISTENT WITH LOGIN.PHP
        ========================================== */
        /* Botão Submit - Darker Orange (Admin) */
        .btn-submit {
            width: 100%;
            padding: 18px 24px;
            background: var(--cor-secundaria-admin); /* #dc6803 - Darker Orange */
            color: var(--cor-texto-admin-inverso);
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 16px;
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--sombra-admin-button); /* Sombra Orange */
        }
        
        /* Efeito de brilho no hover */
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s;
        }
        
        .btn-submit:hover {
            background: var(--cor-secundaria-admin-hover); /* #c2590c */
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(220, 104, 3, 0.4);
        }
        
        .btn-submit:hover::before {
            left: 100%;
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .btn-submit:disabled {
            background: #94a3b8;
            color: var(--white);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-submit.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .btn-submit.loading .btn-text {
            opacity: 0;
        }
        
        .btn-submit.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Divider - ADMIN */
        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            color: var(--cor-texto-admin-claro);     /* #475569 */
            font-size: 13px;
            font-weight: 600;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--cor-borda-admin);      /* #cbd5e1 */
        }
        
        .divider::before {
            margin-right: 16px;
        }
        
        .divider::after {
            margin-left: 16px;
        }
        
        /* Signup Link Style */
        .signup-link,
        .back-link {
            text-align: center;
            padding: 16px;
            background: var(--cor-acento-admin);     /* #e2e8f0 - Lighter Gray */
            border: 1px solid var(--cor-borda-admin);
            border-radius: 12px;
            font-size: 14px;
            color: var(--cor-texto-admin-claro);     /* #475569 */
            margin-top: 16px;
        }
        
        .signup-link a,
        .back-link a {
            color: var(--cor-primaria-admin);        /* #0c1220 - Dark Navy */
            font-weight: 700;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .signup-link a:hover,
        .back-link a:hover {
            color: var(--cor-secundaria-admin);      /* #dc6803 - Darker Orange */
            text-decoration: underline;
        }
        
        /* ==========================================
           🦶 FOOTER - ADMIN
        ========================================== */
        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: var(--cor-texto-admin-claro);     /* #475569 */
        }
        
        .login-footer a {
            color: var(--cor-primaria-admin);        /* #0c1220 - Dark Navy */
            text-decoration: none;
            font-weight: 700;
            transition: var(--transition);
        }
        
        .login-footer a:hover {
            color: var(--cor-secundaria-admin);      /* #dc6803 - Darker Orange */
            text-decoration: underline;
        }
        
        /* ==========================================
           📱 RESPONSIVE - CONSISTENT WITH LOGIN.PHP
        ========================================== */
        @media (max-width: 480px) {
            body {
                padding: var(--space-4);
            }
            
            .login-card {
                padding: 24px;
                border-radius: 12px;
            }
            
            .login-title {
                font-size: 24px;
            }
            
            .form-input {
                padding: 12px 14px;
                padding-left: 44px;
                font-size: 16px; /* Evitar zoom no iOS */
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .login-card {
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            }
        }
    </style>
</head>
<body>

    <div class="login-container">
        
        <!-- Logo -->
        <div class="logo-container">
            <a href="../index.php" class="logo-link">
                <img src="../assets/images/empregos-logo.svg" alt="Emprego MZ" class="logo-img">
            </a>
        </div>
        
        <!-- Card Principal -->
        <div class="login-card">
            
            <!-- Header -->
            <div class="login-header">
                <h1 class="login-title">Login Administrativo</h1>
                <p class="login-subtitle">Acesso restrito ao painel de controle</p>
            </div>
            
            <!-- Badge Admin -->
            <div class="admin-badge">
                <i data-lucide="shield-check"></i>
                ÁREA ADMINISTRATIVA
            </div>
            
            <!-- Mensagem de Erro -->
            <?php if ($erro): ?>
                <div class="alert-error" role="alert">
                    <i data-lucide="alert-circle" style="width: 16px; height: 16px;"></i>
                    <span><?php echo htmlspecialchars($erro); ?></span>
                </div>
                
                <!-- Aviso de Tentativas Restantes (ADMIN - MAIS CRÍTICO) -->
                <?php if ($tentativas_restantes !== null && $tentativas_restantes > 0 && $tentativas_restantes < 3): ?>
                    <div class="alert-warning" role="alert" style="margin-top: 12px;">
                        <i data-lucide="shield-alert" style="width: 16px; height: 16px;"></i>
                        <span>
                            <strong>🔴 ALERTA CRÍTICO:</strong> Você tem apenas <strong><?php echo $tentativas_restantes; ?></strong> 
                            tentativa<?php echo $tentativas_restantes > 1 ? 's' : ''; ?> restante<?php echo $tentativas_restantes > 1 ? 's' : ''; ?> 
                            antes do bloqueio por 30 minutos.
                        </span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Formulário -->
            <form method="POST" action="" id="loginForm">
                
                <!-- Email -->
                <div class="form-group">
                    <label for="email" class="form-label form-label-required">Email Administrativo</label>
                    <div class="input-container">
                        <span class="input-icon">
                            <i data-lucide="mail"></i>
                        </span>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            placeholder="admin@plataforma.co.mz"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required
                            autocomplete="email"
                        >
                    </div>
                </div>
                
                <!-- Senha -->
                <div class="form-group">
                    <label for="senha" class="form-label form-label-required">Senha</label>
                    <div class="input-container input-with-toggle">
                        <span class="input-icon">
                            <i data-lucide="lock"></i>
                        </span>
                        <input 
                            type="password" 
                            id="senha" 
                            name="senha" 
                            class="form-input" 
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Mostrar senha">
                            <i data-lucide="eye" id="toggle-icon"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Botão Submit -->
                <button type="submit" class="btn-submit" id="submitBtn">
                    <span class="btn-text">Entrar no Painel</span>
                </button>
                
            </form>
            
            <!-- Divider -->
            <div class="divider">ou</div>
            
            <!-- Link Voltar -->
            <div class="back-link">
                Não é administrador? <a href="../index.php">Voltar ao site</a>
            </div>
            
            <!-- Security Info -->
            <div class="security-info">
                <i data-lucide="shield-alert"></i>
                <span>Esta área é monitorizada e registada para segurança</span>
            </div>
            
        </div>
        
        <!-- Footer -->
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> Emprego MZ. Todos os direitos reservados.</p>
        </div>
        
    </div>

    <!-- ==========================================
         ✨ SCRIPTS - CONSISTENT WITH LOGIN.PHP
    ========================================== -->
    <script>
        // Inicializar ícones Lucide
        lucide.createIcons();

        // Toggle mostrar/esconder senha
        function togglePassword() {
            const passwordInput = document.getElementById('senha');
            const toggleIcon = document.getElementById('toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.setAttribute('data-lucide', 'eye-off');
            } else {
                passwordInput.type = 'password';
                toggleIcon.setAttribute('data-lucide', 'eye');
            }
            
            lucide.createIcons();
        }

        // Loading state no botão de submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.classList.add('loading');
        });

        // Auto-foco no primeiro campo vazio
        window.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            const senhaInput = document.getElementById('senha');
            
            if (!emailInput.value) {
                emailInput.focus();
            } else if (!senhaInput.value) {
                senhaInput.focus();
            }
        });
    </script>

</body>
</html>
