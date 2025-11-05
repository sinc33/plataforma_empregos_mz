<?php
require_once '../config/session.php';
require_once '../config/db.php';

$sucesso = '';
$erro = '';

// Se já está logado, redirecionar
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'candidato') {
        header("Location: ../index.php");
    } else {
        header("Location: ../empresa/dashboard.php");
    }
    exit;
}

// Processar solicitação de recuperação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $tipo_usuario = $_POST['tipo_usuario'] ?? 'candidato';
    
    if (!in_array($tipo_usuario, ['candidato', 'empresa'])) {
        $erro = "Tipo de conta inválido.";
    }
    elseif (empty($email)) {
        $erro = "Por favor, informe seu email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Por favor, informe um email válido.";
    } elseif (strlen($email) > 255) {
        $erro = "O email informado é muito longo.";
    } else {
        try {
            // Gerar token único e seguro
            $token = bin2hex(random_bytes(32));
            
            // Deletar tokens antigos deste email
            $stmt = $pdo->prepare("DELETE FROM password_reset WHERE email = ? AND tipo_usuario = ?");
            $stmt->execute([$email, $tipo_usuario]);
            
            // Inserir novo token com expiração de 1 hora
            $stmt = $pdo->prepare("INSERT INTO password_reset (email, token, tipo_usuario, data_criacao, data_expiracao) 
                                  VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))");
            $stmt->execute([$email, $token, $tipo_usuario]);
            
            // Gerar link de recuperação
            $link_recuperacao = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/redefinir_senha.php?token=" . $token;
            
            // Armazenar na sessão
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_link'] = $link_recuperacao;
            $_SESSION['reset_tipo'] = $tipo_usuario;
            
            // Redirecionar para página de confirmação
            header("Location: esqueci_senha.php?enviado=1");
            exit;
            
        } catch (PDOException $e) {
            $erro = "Erro ao processar solicitação. Por favor, tente novamente.";
            error_log("Erro ao gerar token de recuperação: " . $e->getMessage());
        } catch (Exception $e) {
            $erro = "Erro inesperado. Por favor, tente novamente.";
            error_log("Erro inesperado na recuperação de senha: " . $e->getMessage());
        }
    }
}

// Verificar se foi enviado
$enviado = isset($_GET['enviado']) && $_GET['enviado'] === '1';
if ($enviado && isset($_SESSION['reset_email'])) {
    $email_enviado = $_SESSION['reset_email'];
    $link_temp = $_SESSION['reset_link'] ?? '';
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_link']);
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Emprego MZ</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
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
            --cor-sucesso: #10B981;
            --cor-borda: #e2e8f0;
            --gray-50: #f8fafc;
            --white: #ffffff;
            --radius-sm: 4px;
            --radius: 6px;
            --radius-lg: 8px;
            --radius-xl: 12px;
            --radius-2xl: 16px;
            --shadow-sm: 0 1px 3px rgba(20, 33, 61, 0.06);
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
            padding: 40px 20px;
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
        
        .forgot-container {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 1;
        }
        
        /* Botão Voltar */
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
        
        /* Card Principal */
        .forgot-card {
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
        
        .forgot-badge {
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
        
        .forgot-badge svg {
            width: 16px;
            height: 16px;
        }
        
        .forgot-header {
            text-align: center;
            margin-bottom: 28px;
        }
        
        .forgot-title {
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        
        .forgot-subtitle {
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
        
        .alert-success {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-left: 3px solid var(--cor-sucesso);
            color: #065f46;
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
        
        .alert-success svg {
            width: 18px;
            height: 18px;
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
        
        .login-link {
            text-align: center;
            padding: 14px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-lg);
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 16px;
        }
        
        .login-link a {
            color: var(--cor-secundaria);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .login-link a:hover {
            color: var(--cor-secundaria-hover);
            text-decoration: underline;
        }
        
        /* Link Box para desenvolvimento */
        .dev-link-box {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(252, 163, 17, 0.3);
            border-left: 3px solid var(--cor-secundaria);
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            margin-top: 16px;
            backdrop-filter: blur(10px);
        }
        
        .dev-link-box h4 {
            color: var(--cor-primaria);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dev-link-box a {
            display: block;
            color: var(--cor-secundaria);
            font-size: 13px;
            word-break: break-all;
            text-decoration: none;
            padding: 8px 12px;
            background: rgba(252, 163, 17, 0.1);
            border-radius: var(--radius);
            margin-top: 8px;
            transition: var(--transition);
        }
        
        .dev-link-box a:hover {
            background: rgba(252, 163, 17, 0.2);
        }
        
        @media (max-width: 480px) {
            body {
                padding: 20px 16px;
            }
            
            .forgot-card {
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

    <div class="forgot-container">
        
        <!-- Botão Voltar -->
        <a href="login.php" class="back-button">
            <div class="back-button-left">
                <i data-lucide="arrow-left" class="arrow-left"></i>
                <div class="back-button-text">
                    <span class="back-button-title">Voltar ao login</span>
                    <span class="back-button-subtitle">Fazer login na conta</span>
                </div>
            </div>
            <i data-lucide="log-in" class="back-button-icon-end"></i>
        </a>
        
        <!-- Card Principal -->
        <div class="forgot-card">
            
            <?php if (!$enviado): ?>
                <!-- Formulário de Recuperação -->
                <div style="text-align: center;">
                    <div class="forgot-badge">
                        <i data-lucide="key"></i>
                        <span>Recuperação de senha</span>
                    </div>
                </div>
                
                <div class="forgot-header">
                    <h1 class="forgot-title">Esqueceu sua senha?</h1>
                    <p class="forgot-subtitle">Digite seu email para receber o link de recuperação</p>
                </div>
                
                <?php if ($erro): ?>
                    <div class="alert-error" role="alert">
                        <i data-lucide="alert-circle"></i>
                        <span><?php echo htmlspecialchars($erro); ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    
                    <!-- Tipo de Usuário -->
                    <div class="form-group">
                        <label class="form-label">Tipo de conta</label>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" name="tipo_usuario" value="candidato" id="tipo_candidato" checked>
                                <label for="tipo_candidato" class="radio-label">
                                    <i data-lucide="user"></i>
                                    <span>Candidato</span>
                                </label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="tipo_usuario" value="empresa" id="tipo_empresa">
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
                                required
                                autocomplete="email"
                                autofocus
                            >
                        </div>
                    </div>
                    
                    <!-- Botão Submit -->
                    <button type="submit" class="btn-submit">
                        Enviar Link de Recuperação
                    </button>
                    
                </form>
                
                <!-- Divider -->
                <div class="divider">ou</div>
                
                <!-- Link Login -->
                <div class="login-link">
                    Lembrou a senha? <a href="login.php">Fazer login</a>
            </div>
            
            <?php else: ?>
                <!-- Mensagem de Sucesso -->
                <div style="text-align: center;">
                    <div class="forgot-badge" style="background: rgba(16, 185, 129, 0.15); color: var(--cor-sucesso); border-color: rgba(16, 185, 129, 0.3);">
                        <i data-lucide="check-circle"></i>
                        <span>Link de recuperação</span>
                    </div>
            </div>
            
                <div class="forgot-header">
                    <h1 class="forgot-title">Link de recuperação</h1>
                    <p class="forgot-subtitle">Use o link abaixo para redefinir sua senha</p>
                </div>
                
                <?php if (!empty($link_temp)): ?>
                    <div class="dev-link-box">
                        <h4>
                            <i data-lucide="key" style="width: 16px; height: 16px;"></i>
                            Link de Recuperação
                        </h4>
                        <p style="color: var(--cor-texto-claro); font-size: 12px; margin-bottom: 8px;">
                            Clique no link abaixo para redefinir sua senha. O link expira em 1 hora.
                        </p>
                        <a href="<?php echo htmlspecialchars($link_temp); ?>">
                            <?php echo htmlspecialchars($link_temp); ?>
                        </a>
                </div>
            <?php endif; ?>
            
                <!-- Divider -->
                <div class="divider">ou</div>
                
                <!-- Link Login -->
                <div class="login-link">
                    <a href="login.php">Voltar ao login</a>
                </div>
                
            <?php endif; ?>
            
        </div>
        
    </div>
    
    <script>
        // Inicializar ícones Lucide
        lucide.createIcons();
    </script>
</body>
</html>
