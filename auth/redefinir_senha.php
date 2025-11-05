<?php
require_once '../config/session.php';
require_once '../config/db.php';

$sucesso = '';
$erro = '';
$token_valido = false;
$token = $_GET['token'] ?? '';
$reset_data = null;

// Verificar token
if (empty($token)) {
    $erro = "Token de recuperação inválido.";
} else {
    try {
        $stmt = $pdo->prepare("SELECT * FROM password_reset 
                              WHERE token = ? 
                              AND data_expiracao > NOW() 
                              AND usado = FALSE");
        $stmt->execute([$token]);
        $reset_data = $stmt->fetch();
        
        if (!$reset_data) {
            $erro = "Token inválido ou expirado.";
        } else {
            $token_valido = true;
        }
    } catch (Exception $e) {
        $erro = "Erro ao verificar token.";
        error_log("Erro ao verificar token: " . $e->getMessage());
    }
}

// Processar redefinição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    if (empty($nova_senha) || empty($confirmar_senha)) {
        $erro = "Por favor, preencha todos os campos.";
    }
    elseif (strlen($nova_senha) < 8) {
        $erro = "A senha deve ter pelo menos 8 caracteres.";
    }
    elseif (strlen($nova_senha) > 100) {
        $erro = "A senha não pode ter mais de 100 caracteres.";
    }
    elseif (!preg_match('/[A-Z]/', $nova_senha)) {
        $erro = "A senha deve conter pelo menos uma letra maiúscula.";
    } elseif (!preg_match('/[a-z]/', $nova_senha)) {
        $erro = "A senha deve conter pelo menos uma letra minúscula.";
    } elseif (!preg_match('/[0-9]/', $nova_senha)) {
        $erro = "A senha deve conter pelo menos um número.";
    } elseif (!preg_match('/[^A-Za-z0-9]/', $nova_senha)) {
        $erro = "A senha deve conter pelo menos um caractere especial (!@#$%^&*).";
    }
    elseif ($nova_senha !== $confirmar_senha) {
        $erro = "As senhas não coincidem.";
    }
    elseif (in_array(strtolower($nova_senha), ['12345678', 'password', 'senha123', 'password123', 'qwerty123', 'abc12345'])) {
        $erro = "Esta senha é muito comum. Escolha uma senha mais segura.";
    }
    else {
        try {
            $pdo->beginTransaction();
            
            $senha_hash = password_hash($nova_senha, PASSWORD_BCRYPT);
            
            // Atualizar senha
            $stmt = $pdo->prepare("UPDATE utilizador SET senha = ? WHERE email = ? AND tipo = ?");
            $resultado = $stmt->execute([$senha_hash, $reset_data['email'], $reset_data['tipo_usuario']]);
            
            if (!$resultado || $stmt->rowCount() === 0) {
                throw new Exception("Usuário não encontrado.");
            }
            
            // Marcar token como usado
            $stmt = $pdo->prepare("UPDATE password_reset SET usado = TRUE WHERE token = ?");
            $stmt->execute([$token]);
            
            $pdo->commit();
            
            $sucesso = true;
            
            error_log("Senha redefinida: {$reset_data['email']}");
            
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = "Erro ao redefinir senha. Tente novamente.";
            error_log("Erro ao redefinir senha: " . $e->getMessage());
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = "Erro inesperado. Tente novamente.";
            error_log("Erro: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - Emprego MZ</title>
    
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
        
        .reset-container {
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
        .reset-card {
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
        
        .reset-badge {
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
        
        .reset-badge.success {
            background: rgba(16, 185, 129, 0.15);
            color: var(--cor-sucesso);
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .reset-badge.error {
            background: rgba(239, 68, 68, 0.15);
            color: var(--cor-erro);
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .reset-badge svg {
            width: 16px;
            height: 16px;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 28px;
        }
        
        .reset-title {
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        
        .reset-subtitle {
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
            padding: 11px 44px 11px 44px;
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
        
        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--cor-texto-claro);
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .toggle-password:hover {
            color: var(--cor-secundaria);
        }
        
        .toggle-password svg {
            width: 18px;
            height: 18px;
        }
        
        .password-requirements {
            background: rgba(255, 255, 255, 0.95);
            padding: 12px 16px;
            border-radius: var(--radius-lg);
            margin-top: 16px;
            font-size: 13px;
            backdrop-filter: blur(10px);
        }
        
        .requirement {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            color: var(--cor-texto-claro);
            transition: var(--transition);
        }
        
        .requirement:last-child {
            margin-bottom: 0;
        }
        
        .requirement svg {
            width: 16px;
            height: 16px;
        }
        
        .requirement.valid {
            color: var(--cor-sucesso);
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
        
        .btn-submit:hover:not(:disabled) {
            background: var(--cor-secundaria-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(252, 163, 17, 0.4);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .btn-submit:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.6;
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
        
        @media (max-width: 480px) {
            body {
                padding: 20px 16px;
            }
            
            .reset-card {
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
    
    <div class="reset-container">
        
        <?php if ($sucesso): ?>
            <!-- Sucesso -->
            <a href="login.php" class="back-button">
                <div class="back-button-left">
                    <i data-lucide="arrow-left" class="arrow-left"></i>
                    <div class="back-button-text">
                        <span class="back-button-title">Ir para login</span>
                        <span class="back-button-subtitle">Fazer login na conta</span>
                    </div>
                </div>
                <i data-lucide="log-in" class="back-button-icon-end"></i>
            </a>
            
            <div class="reset-card">
                <div style="text-align: center;">
                    <div class="reset-badge success">
                        <i data-lucide="check-circle"></i>
                        <span>Senha redefinida</span>
                    </div>
                </div>
                
                <div class="reset-header">
                    <h1 class="reset-title">Senha alterada!</h1>
                    <p class="reset-subtitle">Sua senha foi redefinida com sucesso</p>
                </div>
                
                <div class="alert-success">
                    <i data-lucide="check-circle"></i>
                    <div>Redirecionando para o login em <strong><span id="countdown">3</span> segundos</strong>...</div>
                </div>
                
                <button onclick="window.location.href='login.php'" class="btn-submit">
                    Ir para Login
                </button>
            </div>
            
            <script>
                let seconds = 3;
                const countdownEl = document.getElementById('countdown');
                const interval = setInterval(() => {
                    seconds--;
                    if (countdownEl) countdownEl.textContent = seconds;
                    if (seconds <= 0) {
                        clearInterval(interval);
                        window.location.href = 'login.php';
                    }
                }, 1000);
            </script>
            
        <?php elseif (!$token_valido): ?>
            <!-- Token Inválido -->
            <a href="esqueci_senha.php" class="back-button">
                <div class="back-button-left">
                    <i data-lucide="arrow-left" class="arrow-left"></i>
                    <div class="back-button-text">
                        <span class="back-button-title">Solicitar novo link</span>
                        <span class="back-button-subtitle">Recuperar senha</span>
                    </div>
                </div>
                <i data-lucide="key" class="back-button-icon-end"></i>
            </a>
            
            <div class="reset-card">
                <div style="text-align: center;">
                    <div class="reset-badge error">
                        <i data-lucide="alert-triangle"></i>
                        <span>Link inválido</span>
                    </div>
                </div>
                
                <div class="reset-header">
                    <h1 class="reset-title">Token expirado</h1>
                    <p class="reset-subtitle">O link de recuperação é inválido ou já expirou</p>
                </div>
                
                <div class="alert-error">
                    <i data-lucide="alert-circle"></i>
                    <div><?php echo htmlspecialchars($erro); ?></div>
                </div>
                
                <button onclick="window.location.href='esqueci_senha.php'" class="btn-submit">
                    Solicitar Novo Link
                </button>
                
                <div class="divider">ou</div>
                
                <div class="login-link">
                    <a href="login.php">Voltar ao login</a>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Formulário de Redefinição -->
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
            
            <div class="reset-card">
                <div style="text-align: center;">
                    <div class="reset-badge">
                        <i data-lucide="lock"></i>
                        <span>Redefinir senha</span>
                    </div>
                </div>
                
                <div class="reset-header">
                    <h1 class="reset-title">Nova senha</h1>
                    <p class="reset-subtitle">Defina uma nova senha segura para sua conta</p>
                </div>
                
                <?php if ($erro): ?>
                    <div class="alert-error">
                        <i data-lucide="alert-circle"></i>
                        <div><?php echo htmlspecialchars($erro); ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="resetForm">
                    
                    <!-- Nova Senha -->
                    <div class="form-group">
                        <label for="nova_senha" class="form-label">Nova senha</label>
                        <div class="input-container">
                            <span class="input-icon">
                                <i data-lucide="lock"></i>
                            </span>
                            <input 
                                type="password" 
                                id="nova_senha" 
                                name="nova_senha" 
                                class="form-input" 
                                placeholder="Digite sua nova senha"
                                required
                                autocomplete="new-password"
                                autofocus
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('nova_senha', this)">
                                <i data-lucide="eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Confirmar Senha -->
                    <div class="form-group">
                        <label for="confirmar_senha" class="form-label">Confirmar senha</label>
                        <div class="input-container">
                            <span class="input-icon">
                                <i data-lucide="lock"></i>
                            </span>
                            <input 
                                type="password" 
                                id="confirmar_senha" 
                                name="confirmar_senha" 
                                class="form-input" 
                                placeholder="Digite novamente"
                                required
                                autocomplete="new-password"
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('confirmar_senha', this)">
                                <i data-lucide="eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Requisitos -->
                    <div class="password-requirements">
                        <div class="requirement" id="req-length">
                            <i data-lucide="circle"></i>
                            <span>Mínimo de 8 caracteres</span>
                        </div>
                        <div class="requirement" id="req-upper">
                            <i data-lucide="circle"></i>
                            <span>Letra maiúscula</span>
                        </div>
                        <div class="requirement" id="req-lower">
                            <i data-lucide="circle"></i>
                            <span>Letra minúscula</span>
                        </div>
                        <div class="requirement" id="req-number">
                            <i data-lucide="circle"></i>
                            <span>Número</span>
                        </div>
                        <div class="requirement" id="req-special">
                            <i data-lucide="circle"></i>
                            <span>Caractere especial</span>
                        </div>
                        <div class="requirement" id="req-match">
                            <i data-lucide="circle"></i>
                            <span>Senhas coincidem</span>
                        </div>
                    </div>
                    
                    <!-- Botão Submit -->
                    <button type="submit" class="btn-submit" id="submitBtn" disabled>
                        Redefinir Senha
                    </button>
                    
                </form>
                
                <!-- Divider -->
                <div class="divider">ou</div>
                
                <!-- Link Login -->
                <div class="login-link">
                    <a href="login.php">Voltar ao login</a>
                </div>
                
            </div>
        <?php endif; ?>
        
    </div>
    
    <script>
        // Inicializar ícones
        lucide.createIcons();
        
        <?php if ($token_valido && !$sucesso): ?>
        // Toggle password visibility
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                input.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            
            lucide.createIcons();
        }
        
        // Validação em tempo real
        const senhaInput = document.getElementById('nova_senha');
        const confirmarInput = document.getElementById('confirmar_senha');
        const submitBtn = document.getElementById('submitBtn');
        
        function validarSenha() {
            const senha = senhaInput.value;
            const confirmar = confirmarInput.value;
            
            // Requisitos
            const hasLength = senha.length >= 8;
            const hasUpper = /[A-Z]/.test(senha);
            const hasLower = /[a-z]/.test(senha);
            const hasNumber = /[0-9]/.test(senha);
            const hasSpecial = /[^A-Za-z0-9]/.test(senha);
            const hasMatch = senha === confirmar && senha.length > 0;
            
            // Atualizar UI
            updateRequirement('req-length', hasLength);
            updateRequirement('req-upper', hasUpper);
            updateRequirement('req-lower', hasLower);
            updateRequirement('req-number', hasNumber);
            updateRequirement('req-special', hasSpecial);
            updateRequirement('req-match', hasMatch);
            
            // Habilitar/Desabilitar botão
            const isValid = hasLength && hasUpper && hasLower && hasNumber && hasSpecial && hasMatch;
            submitBtn.disabled = !isValid;
        }
        
        function updateRequirement(id, isValid) {
            const req = document.getElementById(id);
            const icon = req.querySelector('i');
            
            if (isValid) {
                req.classList.add('valid');
                icon.setAttribute('data-lucide', 'check-circle');
            } else {
                req.classList.remove('valid');
                icon.setAttribute('data-lucide', 'circle');
            }
            
            lucide.createIcons();
        }
        
        senhaInput.addEventListener('input', validarSenha);
        confirmarInput.addEventListener('input', validarSenha);
        <?php endif; ?>
    </script>
    
</body>
</html>
