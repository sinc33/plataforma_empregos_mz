<?php
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/auth_functions.php';

// Se já estiver logado, redirecionar
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'empresa') {
        header("Location: ../empresa/dashboard.php");
    } else {
        header("Location: ../index.php");
    }
    exit;
}

$erro = '';
$tentativas_restantes = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $tipo_usuario_form = $_POST['tipo_usuario'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    
    if (empty($email) || empty($senha) || empty($tipo_usuario_form)) {
        $erro = "Por favor, preencha todos os campos.";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Por favor, informe um email válido.";
    }
    elseif (strlen($email) > 255) {
        $erro = "O email informado é muito longo.";
    }
    elseif (!in_array($tipo_usuario_form, ['candidato', 'empresa'])) {
        $erro = "Tipo de conta inválido.";
    }
    else {
        try {
            $rate_limiting_habilitado = loginAttemptsTableExists($pdo);
            
            if ($rate_limiting_habilitado) {
                $bloqueio_email = verificarBloqueio($pdo, $email, 'email');
                if ($bloqueio_email) {
                    $erro = getMensagemBloqueio($bloqueio_email['bloqueado_ate']);
                }
                
                $bloqueio_ip = verificarBloqueio($pdo, $ip, 'ip');
                if ($bloqueio_ip) {
                    $erro = getMensagemBloqueio($bloqueio_ip['bloqueado_ate']);
                }
            }
            
            if (empty($erro)) {
                $stmt = $pdo->prepare("SELECT id, email, senha, tipo, ativo FROM utilizador WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && $user['ativo'] && password_verify($senha, $user['senha'])) {
                    if ($user['tipo'] !== $tipo_usuario_form) {
                        $erro = "Tipo de conta incorreto para este email.";
                        
                        if ($rate_limiting_habilitado) {
                            $bloqueado = registrarTentativaFalhada($pdo, $email, 'email', 5, 15);
                            registrarTentativaFalhada($pdo, $ip, 'ip', 5, 15);
                            
                            if ($bloqueado) {
                                $erro = "Muitas tentativas falhadas. Sua conta foi bloqueada temporariamente por 15 minutos.";
                            } else {
                                $tentativas_restantes = getTentativasRestantes($pdo, $email, 'email', 5);
                            }
                        }
                    } else {
                        if ($rate_limiting_habilitado) {
                            limparTentativas($pdo, $email, 'email');
                            limparTentativas($pdo, $ip, 'ip');
                        }
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_type'] = $user['tipo'];
                        $_SESSION['user_email'] = $user['email'];
                        
                        if ($user['tipo'] === 'empresa') {
                            $stmt_nome = $pdo->prepare("SELECT nome_empresa FROM empresa WHERE id = ?");
                        } else {
                            $stmt_nome = $pdo->prepare("SELECT nome_completo FROM candidato WHERE id = ?");
                        }
                        $stmt_nome->execute([$user['id']]);
                        $perfil = $stmt_nome->fetch();
                        
                        if ($perfil) {
                            $_SESSION['user_name'] = $user['tipo'] === 'empresa' ? $perfil['nome_empresa'] : $perfil['nome_completo'];
                        } else {
                            $_SESSION['user_name'] = 'Usuário';
                        }
                        
                        // Atualizar último login - IMPORTANTE: deve ser executado antes do redirecionamento
                        try {
                            $stmt_update = $pdo->prepare("UPDATE utilizador SET ultimo_login = NOW() WHERE id = ?");
                            $stmt_update->execute([$user['id']]);
                        } catch (PDOException $e) {
                            // Log do erro mas não interrompe o login
                            error_log("Erro ao atualizar ultimo_login para usuário ID {$user['id']}: " . $e->getMessage());
                        }
                        
                        if ($user['tipo'] === 'empresa') {
                            header("Location: ../empresa/dashboard.php");
                        } else {
                            if (isset($_SESSION['redirect_after_login'])) {
                                $redirect = $_SESSION['redirect_after_login'];
                                unset($_SESSION['redirect_after_login']);
                                header("Location: ../$redirect");
                            } else {
                                header("Location: ../index.php");
                            }
                        }
                        exit;
                    }
                } else {
                    $erro = "Email, senha ou tipo de conta incorretos.";
                    
                    if ($rate_limiting_habilitado) {
                        $bloqueado = registrarTentativaFalhada($pdo, $email, 'email', 5, 15);
                        registrarTentativaFalhada($pdo, $ip, 'ip', 5, 15);
                        
                        if ($bloqueado) {
                            $erro = "Muitas tentativas falhadas. Sua conta foi bloqueada temporariamente por 15 minutos.";
                        } else {
                            $tentativas_restantes = getTentativasRestantes($pdo, $email, 'email', 5);
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $erro = "Erro no sistema. Por favor, tente novamente.";
            error_log("Erro PDO em auth/login.php: " . $e->getMessage());
        } catch (Exception $e) {
            $erro = "Erro inesperado. Por favor, tente novamente.";
            error_log("Erro inesperado em auth/login.php: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Emprego MZ</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --cor-primaria: #14213d;
            --cor-primaria-escura: #0f1a2e;
            --cor-secundaria: #fca311;
            --cor-secundaria-hover: #e3940f;
            --cor-fundo: #f8fafc;
            --cor-cards: #ffffff;
            --cor-texto: #1a202c;
            --cor-texto-claro: #64748b;
            --cor-texto-muito-claro: #94a3b8;
            --cor-erro: #EF4444;
            --cor-aviso: #F59E0B;
            --cor-sucesso: #10B981;
            --cor-borda: #e2e8f0;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --white: #ffffff;
            --radius-sm: 4px;
            --radius: 6px;
            --radius-lg: 8px;
            --radius-xl: 12px;
            --radius-2xl: 16px;
            --shadow-md: 0 4px 12px rgba(20, 33, 61, 0.12);
            --transition: all 0.2s ease;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--cor-fundo);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--cor-texto);
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="%2314213d" opacity="0.03"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/></svg>');
            pointer-events: none;
            z-index: 0;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            color: var(--cor-texto);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 14px 20px;
            margin-bottom: 32px;
            transition: var(--transition);
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        
        .back-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(252, 163, 17, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .back-button:hover::before {
            left: 100%;
        }
        
        .back-button:hover {
            color: var(--cor-primaria);
            border-color: var(--cor-secundaria);
            transform: translateX(-4px);
            box-shadow: 0 4px 12px rgba(252, 163, 17, 0.15);
        }
        
        .back-button-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .back-button svg {
            width: 18px;
            height: 18px;
            transition: transform 0.2s ease;
            flex-shrink: 0;
        }
        
        .back-button:hover .arrow-left {
            transform: translateX(-3px);
        }
        
        .back-button-icon-end {
            color: var(--cor-texto-claro);
            opacity: 0.6;
            transition: var(--transition);
        }
        
        .back-button:hover .back-button-icon-end {
            color: var(--cor-secundaria);
            opacity: 1;
        }
        
        .back-button-text {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
        }
        
        .back-button-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--cor-primaria);
        }
        
        .back-button-subtitle {
            font-size: 12px;
            font-weight: 400;
            color: var(--cor-texto-claro);
        }
        
        .back-button:hover .back-button-title {
            color: var(--cor-secundaria);
        }
        
        
        .login-card {
            background: linear-gradient(135deg, 
                        rgba(20, 33, 61, 0.97) 0%,
                        rgba(30, 44, 71, 0.95) 50%,
                        rgba(20, 33, 61, 0.97) 100%);
            border-radius: var(--radius-2xl);
            box-shadow: 0 20px 60px rgba(20, 33, 61, 0.4), 
                        0 0 0 1px rgba(252, 163, 17, 0.1);
            padding: 32px 36px;
            border: 1px solid rgba(252, 163, 17, 0.2);
            backdrop-filter: blur(10px);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 28px;
        }
        
        .login-title {
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        
        .login-subtitle {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.85);
        }
        
        .alert-error {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-left: 3px solid var(--cor-erro);
            color: #991B1B;
            padding: 14px 16px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-align: left;
            backdrop-filter: blur(10px);
        }
        
        .alert-error svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }
        
        .alert-warning {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-left: 3px solid var(--cor-aviso);
            color: #92400E;
            padding: 12px 14px;
            border-radius: var(--radius-lg);
            font-size: 13px;
            margin-top: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            text-align: left;
            backdrop-filter: blur(10px);
        }
        
        .alert-warning svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }
        
        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: 8px;
        }
        
        .radio-group {
            display: flex;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-lg);
            padding: 4px;
            gap: 6px;
            margin-bottom: 20px;
        }
        
        .radio-option {
            position: relative;
            flex: 1;
        }
        
        .radio-option input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        
        .radio-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .radio-label svg {
            width: 16px;
            height: 16px;
        }
        
        .radio-option input:checked + .radio-label {
            background: var(--cor-secundaria);
            color: var(--white);
            box-shadow: 0 2px 8px rgba(252, 163, 17, 0.3);
        }
        
        .radio-label:hover:not(:has(input:checked)) {
            background: rgba(255, 255, 255, 0.15);
            color: rgba(255, 255, 255, 0.95);
        }
        
        .input-container {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--cor-texto-claro);
        }
        
        .input-icon svg {
            width: 18px;
            height: 18px;
        }
        
        .form-input {
            width: 100%;
            padding: 11px 16px 11px 44px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-lg);
            font-size: 14px;
            color: var(--cor-texto);
            background: rgba(255, 255, 255, 0.95);
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }
        
        .form-input::placeholder {
            color: var(--cor-texto-muito-claro);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--cor-secundaria);
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 0 0 3px rgba(252, 163, 17, 0.15);
        }
        
        .input-with-toggle .form-input {
            padding-right: 44px;
        }
        
        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: 8px;
            cursor: pointer;
            color: var(--cor-texto-claro);
            transition: var(--transition);
            border-radius: var(--radius-sm);
        }
        
        .toggle-password:hover {
            color: var(--cor-primaria);
            background: rgba(20, 33, 61, 0.05);
        }
        
        .toggle-password svg {
            width: 18px;
            height: 18px;
        }
        
        .btn-submit {
            width: 100%;
            padding: 13px 24px;
            background: var(--cor-secundaria);
            color: var(--white);
            border: none;
            border-radius: var(--radius-lg);
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 20px;
            box-shadow: 0 4px 16px rgba(252, 163, 17, 0.3);
        }
        
        .btn-submit:hover {
            background: var(--cor-secundaria-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(252, 163, 17, 0.4);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .forgot-password {
            display: inline-block;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .forgot-password:hover {
            color: var(--cor-secundaria);
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: rgba(255, 255, 255, 0.6);
            font-size: 13px;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.2);
        }
        
        .divider::before {
            margin-right: 14px;
        }
        
        .divider::after {
            margin-left: 14px;
        }
        
        .signup-link {
            text-align: center;
            padding: 14px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-lg);
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 16px;
        }
        
        .signup-link a {
            color: var(--cor-secundaria);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .signup-link a:hover {
            color: var(--cor-secundaria-hover);
            text-decoration: underline;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 28px;
            font-size: 13px;
            color: var(--cor-texto-claro);
        }
        
        .login-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(252, 163, 17, 0.15);
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            color: var(--cor-secundaria);
            margin-bottom: 20px;
            border: 1px solid rgba(252, 163, 17, 0.3);
        }
        
        .login-badge svg {
            width: 16px;
            height: 16px;
        }
        
        @media (max-width: 480px) {
            body {
                padding: 16px;
            }
            
            .login-card {
                padding: 32px 24px;
            }
            
            .back-button {
                width: 100%;
                justify-content: flex-start;
                padding: 12px 16px;
            }
            
            .back-button-subtitle {
                display: none;
            }
            
            .back-button-icon-end {
                display: none;
            }
        }
    </style>
</head>
<body>
    
    <div class="login-container">
        
        <!-- Botão Voltar -->
        <a href="../index.php" class="back-button">
            <div class="back-button-left">
                <i data-lucide="arrow-left" class="arrow-left"></i>
                <div class="back-button-text">
                    <span class="back-button-title">Voltar ao início</span>
                    <span class="back-button-subtitle">Explorar vagas e oportunidades</span>
                </div>
            </div>
            <i data-lucide="home" class="back-button-icon-end"></i>
        </a>
        
        <!-- Card Principal -->
        <div class="login-card">
            
            <!-- Badge -->
            <div style="text-align: center;">
                <div class="login-badge">
                    <i data-lucide="briefcase"></i>
                    <span>Acesse sua conta para encontrar vagas</span>
                </div>
            </div>
            
            <!-- Header -->
            <div class="login-header">
                <h1 class="login-title">Bem-vindo de volta</h1>
                <p class="login-subtitle">Entre com suas credenciais para acessar sua conta</p>
            </div>
            
            <!-- Mensagem de Erro -->
            <?php if ($erro): ?>
                <div class="alert-error" role="alert">
                    <i data-lucide="alert-circle"></i>
                    <span><?php echo htmlspecialchars($erro); ?></span>
                </div>
                
                <?php if ($tentativas_restantes !== null && $tentativas_restantes > 0 && $tentativas_restantes < 5): ?>
                    <div class="alert-warning" role="alert">
                        <i data-lucide="shield-alert"></i>
                        <span>
                            <strong>Atenção:</strong> Você tem <strong><?php echo $tentativas_restantes; ?></strong> 
                            tentativa<?php echo $tentativas_restantes > 1 ? 's' : ''; ?> restante<?php echo $tentativas_restantes > 1 ? 's' : ''; ?>.
                        </span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Formulário -->
            <form method="POST" action="" id="loginForm">
                
                <!-- Tipo de Usuário -->
                <div class="form-group">
                    <label class="form-label">Tipo de conta</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" name="tipo_usuario" value="candidato" id="tipo_candidato" 
                                   <?php echo (!isset($_POST['tipo_usuario']) || $_POST['tipo_usuario'] === 'candidato') ? 'checked' : ''; ?>>
                            <label for="tipo_candidato" class="radio-label">
                                <i data-lucide="user"></i>
                                <span>Candidato</span>
                            </label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="tipo_usuario" value="empresa" id="tipo_empresa"
                                   <?php echo (isset($_POST['tipo_usuario']) && $_POST['tipo_usuario'] === 'empresa') ? 'checked' : ''; ?>>
                            <label for="tipo_empresa" class="radio-label">
                                <i data-lucide="building-2"></i>
                                <span>Empresa</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Email -->
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-container">
                        <span class="input-icon">
                            <i data-lucide="mail"></i>
                        </span>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            placeholder="seu@email.com"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required
                            autocomplete="email"
                        >
                    </div>
                </div>
                
                <!-- Senha -->
                <div class="form-group">
                    <label for="senha" class="form-label">Senha</label>
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
                <button type="submit" class="btn-submit">
                    Entrar
                </button>
                
            </form>
            
            <!-- Link Esqueceu Senha -->
            <div style="text-align: center; margin-top: 16px;">
                <a href="esqueci_senha.php" class="forgot-password">
                    Esqueceu sua senha?
                </a>
            </div>
            
            <!-- Divider -->
            <div class="divider">ou</div>
            
            <!-- Link Criar Conta -->
            <div class="signup-link">
                Não tem uma conta? <a href="register.php">Criar conta</a>
            </div>
            
        </div>
        
        <!-- Footer -->
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> Emprego MZ. Todos os direitos reservados.</p>
        </div>
        
    </div>
    
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
