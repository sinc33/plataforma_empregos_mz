<?php
require_once '../config/session.php';
require_once '../config/db.php';

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
$erros_validacao = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    $tipo = $_POST['tipo_usuario'] ?? '';
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $nome_empresa = trim($_POST['nome_empresa'] ?? '');
    
    // Validar tipo de usuário
    if (empty($tipo) || !in_array($tipo, ['candidato', 'empresa'])) {
        $erros_validacao[] = "Selecione o tipo de conta.";
    }
    
    // Validar email
    if (empty($email)) {
        $erros_validacao[] = "O email é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros_validacao[] = "Por favor, insira um email válido.";
    } elseif (strlen($email) > 150) {
        $erros_validacao[] = "O email não pode ter mais de 150 caracteres.";
    }
    
    // Validar senha
    if (empty($senha)) {
        $erros_validacao[] = "A senha é obrigatória.";
    } elseif (strlen($senha) < 8) {
        $erros_validacao[] = "A senha deve ter no mínimo 8 caracteres.";
    } elseif (strlen($senha) > 255) {
        $erros_validacao[] = "A senha não pode ter mais de 255 caracteres.";
    }
    
    // Validar confirmação de senha
    if (empty($confirmar_senha)) {
        $erros_validacao[] = "A confirmação de senha é obrigatória.";
    } elseif ($senha !== $confirmar_senha) {
        $erros_validacao[] = "As senhas não coincidem.";
    }
    
    // Validar nome específico do tipo de usuário
    if ($tipo === 'candidato') {
        if (empty($nome_completo)) {
            $erros_validacao[] = "O nome completo é obrigatório.";
        } elseif (strlen($nome_completo) < 3) {
            $erros_validacao[] = "O nome completo deve ter pelo menos 3 caracteres.";
        } elseif (strlen($nome_completo) > 150) {
            $erros_validacao[] = "O nome completo não pode ter mais de 150 caracteres.";
        }
    } elseif ($tipo === 'empresa') {
        if (empty($nome_empresa)) {
            $erros_validacao[] = "O nome da empresa é obrigatório.";
        } elseif (strlen($nome_empresa) < 2) {
            $erros_validacao[] = "O nome da empresa deve ter pelo menos 2 caracteres.";
        } elseif (strlen($nome_empresa) > 200) {
            $erros_validacao[] = "O nome da empresa não pode ter mais de 200 caracteres.";
        }
    }
    
    // Se não há erros de validação, prosseguir com registro
    if (empty($erros_validacao)) {
        try {
            $pdo->beginTransaction();
            
            // Verificar se email já existe
            $stmt = $pdo->prepare("SELECT id FROM utilizador WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $erros_validacao[] = "Este email já está registado. Por favor, use outro email ou faça login.";
                $pdo->rollBack();
            } else {
                // Hash da senha
                $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
                
                // Criar utilizador
                $stmt = $pdo->prepare("INSERT INTO utilizador (email, senha, tipo) VALUES (?, ?, ?)");
                $stmt->execute([$email, $senhaHash, $tipo]);
                $userId = $pdo->lastInsertId();
                
                // Criar perfil específico
                if ($tipo === 'empresa') {
                    $stmt = $pdo->prepare("INSERT INTO empresa (id, nome_empresa) VALUES (?, ?)");
                    $stmt->execute([$userId, $nome_empresa]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO candidato (id, nome_completo) VALUES (?, ?)");
                    $stmt->execute([$userId, $nome_completo]);
                }
                
                $pdo->commit();
                
                // Auto-login após registro
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_type'] = $tipo;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $tipo === 'empresa' ? $nome_empresa : $nome_completo;
                
                // Redirecionar conforme o tipo
                if ($tipo === 'empresa') {
                    header("Location: ../empresa/dashboard.php");
                } else {
                    header("Location: ../index.php");
                }
                exit;
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erros_validacao[] = "Erro no registo. Tente novamente mais tarde.";
            error_log("Erro no registro: " . $e->getMessage());
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
    <title>Criar Conta - Emprego MZ</title>
    
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
        
        .register-container {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
        }
        
        /* Botão Voltar - Igual ao login.php */
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
        
        /* Card Principal - Igual ao login.php */
        .register-card {
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
        
        .register-badge {
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
        
        .register-badge svg {
            width: 16px;
            height: 16px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 28px;
        }
        
        .register-title {
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        
        .register-subtitle {
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
        
        .register-footer {
            text-align: center;
            margin-top: 28px;
            font-size: 13px;
            color: var(--cor-texto-claro);
        }
        
        /* Campo condicional */
        .conditional-field {
            display: none;
            animation: slideDown 0.3s ease-out;
        }
        
        .conditional-field.show {
            display: block;
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
        
        @media (max-width: 480px) {
            body {
                padding: 20px 16px;
            }
            
            .register-card {
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
    
    <div class="register-container">
        
        <!-- Botão Voltar -->
        <a href="../index.php" class="back-button">
            <div class="back-button-left">
                <i data-lucide="arrow-left" class="arrow-left"></i>
                <div class="back-button-text">
                    <span class="back-button-title">Voltar ao início</span>
                    <span class="back-button-subtitle">Explorar vagas disponíveis</span>
        </div>
            </div>
            <i data-lucide="home" class="back-button-icon-end"></i>
        </a>
        
        <!-- Card Principal -->
        <div class="register-card">
            
            <!-- Badge -->
            <div style="text-align: center;">
                <div class="register-badge">
                    <i data-lucide="user-plus"></i>
                    <span>Crie sua conta grátis</span>
                </div>
            </div>
            
            <!-- Header -->
            <div class="register-header">
                <h1 class="register-title">Criar Conta</h1>
                <p class="register-subtitle">Preencha os dados abaixo para começar</p>
            </div>
            
            <!-- Mensagem de Erro -->
            <?php if ($erro): ?>
                <div class="alert-error" role="alert">
                    <i data-lucide="alert-circle"></i>
                    <div><?php echo $erro; ?></div>
                </div>
            <?php endif; ?>
            
            <!-- Formulário -->
            <form method="POST" action="" id="registerForm">
                
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
                
                <!-- Nome Completo (Candidato) -->
                <div class="form-group conditional-field <?php echo (!isset($_POST['tipo_usuario']) || $_POST['tipo_usuario'] === 'candidato') ? 'show' : ''; ?>" id="campo-candidato">
                    <label for="nome_completo" class="form-label">Nome Completo</label>
                    <div class="input-container">
                        <span class="input-icon">
                            <i data-lucide="user"></i>
                        </span>
                        <input 
                            type="text" 
                            id="nome_completo" 
                            name="nome_completo" 
                            class="form-input" 
                            placeholder="João Silva"
                            value="<?php echo htmlspecialchars($_POST['nome_completo'] ?? ''); ?>"
                            <?php echo (!isset($_POST['tipo_usuario']) || $_POST['tipo_usuario'] === 'candidato') ? 'required' : ''; ?>
                        >
                    </div>
                </div>
                
                <!-- Nome Empresa (Empresa) -->
                <div class="form-group conditional-field <?php echo (isset($_POST['tipo_usuario']) && $_POST['tipo_usuario'] === 'empresa') ? 'show' : ''; ?>" id="campo-empresa">
                    <label for="nome_empresa" class="form-label">Nome da Empresa</label>
                    <div class="input-container">
                        <span class="input-icon">
                            <i data-lucide="building-2"></i>
                        </span>
                        <input 
                            type="text" 
                            id="nome_empresa" 
                            name="nome_empresa" 
                            class="form-input" 
                            placeholder="Minha Empresa Lda"
                            value="<?php echo htmlspecialchars($_POST['nome_empresa'] ?? ''); ?>"
                            <?php echo (isset($_POST['tipo_usuario']) && $_POST['tipo_usuario'] === 'empresa') ? 'required' : ''; ?>
                        >
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
                            placeholder="Mínimo 8 caracteres"
                            required
                            autocomplete="new-password"
                            minlength="8"
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword('senha', 'toggle-icon-1')" aria-label="Mostrar senha">
                            <i data-lucide="eye" id="toggle-icon-1"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Confirmar Senha -->
                <div class="form-group">
                    <label for="confirmar_senha" class="form-label">Confirmar Senha</label>
                    <div class="input-container input-with-toggle">
                        <span class="input-icon">
                            <i data-lucide="lock"></i>
                        </span>
                        <input 
                            type="password" 
                            id="confirmar_senha" 
                            name="confirmar_senha" 
                            class="form-input" 
                            placeholder="Digite a senha novamente"
                            required
                            autocomplete="new-password"
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword('confirmar_senha', 'toggle-icon-2')" aria-label="Mostrar senha">
                            <i data-lucide="eye" id="toggle-icon-2"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Botão Submit -->
                <button type="submit" class="btn-submit">
                    Criar Conta
                </button>
                
            </form>
            
            <!-- Divider -->
            <div class="divider">ou</div>
            
            <!-- Link Login -->
            <div class="login-link">
                Já tem uma conta? <a href="login.php">Entrar</a>
            </div>
            
        </div>
        
        <!-- Footer -->
        <div class="register-footer">
            <p>&copy; <?php echo date('Y'); ?> Emprego MZ. Todos os direitos reservados.</p>
        </div>
        
    </div>
    
    <script>
        // Inicializar ícones Lucide
        lucide.createIcons();

        // Toggle mostrar/esconder senha
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.setAttribute('data-lucide', 'eye-off');
            } else {
                passwordInput.type = 'password';
                toggleIcon.setAttribute('data-lucide', 'eye');
            }
            
            lucide.createIcons();
        }

        // Mostrar/esconder campos condicionais conforme tipo de usuário
        function updateConditionalFields() {
            const tipoCandidato = document.getElementById('tipo_candidato').checked;
            const campoCandidato = document.getElementById('campo-candidato');
            const campoEmpresa = document.getElementById('campo-empresa');
            const inputCandidato = document.getElementById('nome_completo');
            const inputEmpresa = document.getElementById('nome_empresa');
            
            if (tipoCandidato) {
                campoCandidato.classList.add('show');
                campoEmpresa.classList.remove('show');
                inputCandidato.required = true;
                inputEmpresa.required = false;
            } else {
                campoCandidato.classList.remove('show');
                campoEmpresa.classList.add('show');
                inputCandidato.required = false;
                inputEmpresa.required = true;
            }
        }

        // Adicionar event listeners aos radio buttons
        document.getElementById('tipo_candidato').addEventListener('change', updateConditionalFields);
        document.getElementById('tipo_empresa').addEventListener('change', updateConditionalFields);

        // Inicializar campos ao carregar
        document.addEventListener('DOMContentLoaded', function() {
            // Garantir que os campos corretos estão visíveis
            updateConditionalFields();
            
            // Auto-foco no primeiro campo visível
            const tipoSelecionado = document.querySelector('input[name="tipo_usuario"]:checked').value;
            if (tipoSelecionado === 'candidato') {
                const nomeInput = document.getElementById('nome_completo');
                if (nomeInput && !nomeInput.value) {
                    nomeInput.focus();
                }
            } else {
                const nomeEmpresaInput = document.getElementById('nome_empresa');
                if (nomeEmpresaInput && !nomeEmpresaInput.value) {
                    nomeEmpresaInput.focus();
                }
            }
        });
        
        // Também executar imediatamente se o DOM já estiver carregado
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', updateConditionalFields);
        } else {
            updateConditionalFields();
        }
    </script>
</body>
</html>
