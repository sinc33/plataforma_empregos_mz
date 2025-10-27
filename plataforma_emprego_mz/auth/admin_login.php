<?php
session_start();
require_once '../config/db.php';

// Se já estiver logado como admin, redirecionar para o dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: ../admin/index.php");
    exit;
}

 $erro = '';

// Processar o formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    
    if (!empty($email) && !empty($senha)) {
        $pdo = getPDO();
        
        try {
            // Buscar admin pelo email
            $stmt = $pdo->prepare("SELECT id, email, senha, nome FROM admin WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($senha, $admin['senha'])) {
                // Login bem-sucedido
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_nome'] = $admin['nome'];
                
                // Registrar login (opcional - você pode criar uma tabela de logs se quiser)
                $pdo->prepare("UPDATE admin SET ultimo_login = NOW() WHERE id = ?")
                    ->execute([$admin['id']]);
                
                header("Location: ../admin/index.php");
                exit;
                
            } else {
                $erro = "Credenciais inválidas.";
            }
            
        } catch (PDOException $e) {
            $erro = "Erro no sistema. Tente novamente.";
        }
    } else {
        $erro = "Por favor, preencha todos os campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo - Emprego MZ</title>
    
    <!-- Google Fonts - Ubuntu Moderno -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        /* ============================================
           🎨 MARRABENTA UI - Design System
           Ubuntu Moderno + Afro-Futurista Profissional
        ============================================ */
        
        :root {
            /* Cores Principais - Identidade Moçambicana */
            --verde-esperanca: #2B7A4B;
            --dourado-sol: #FFB03B;
            --azul-indico: #1E3A5F;
            --coral-vivo: #FF6B6B;
            --areia-quente: #F4E4C1;
            
            /* Neutros Suaves */
            --carvao: #2C3E50;
            --cinza-baobab: #95A5A6;
            --branco-marfim: #FAFAF8;
            --branco-puro: #FFFFFF;
            
            /* Gradientes Signature */
            --gradient-por-do-sol: linear-gradient(135deg, #FFB03B 0%, #FF6B6B 50%, #764ba2 100%);
            --gradient-oceano: linear-gradient(135deg, #1E3A5F 0%, #2B7A4B 100%);
            --gradient-terra: linear-gradient(135deg, #F4E4C1 0%, #FFB03B 100%);
            
            /* Sombras Coloridas (não cinzas!) */
            --shadow-soft: 0 2px 8px rgba(43, 122, 75, 0.08);
            --shadow-medium: 0 4px 16px rgba(43, 122, 75, 0.12);
            --shadow-strong: 0 8px 24px rgba(43, 122, 75, 0.16);
            --shadow-hover: 0 12px 32px rgba(255, 176, 59, 0.2);
            
            /* Espaçamentos Harmônicos */
            --space-xs: 0.5rem;
            --space-sm: 1rem;
            --space-md: 1.5rem;
            --space-lg: 2.5rem;
            --space-xl: 4rem;
            
            /* Tipografia Ubuntu Moderno */
            --font-body: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-heading: 'Poppins', 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-body);
            background: var(--branco-marfim);
            color: var(--carvao);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ============================================
           🌅 HERO SECTION
        ============================================ */
        .hero {
            background: var(--gradient-oceano);
            color: var(--branco-puro);
            padding: var(--space-xl) var(--space-md);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                repeating-linear-gradient(45deg, transparent, transparent 35px, rgba(255,255,255,0.03) 35px, rgba(255,255,255,0.03) 70px),
                repeating-linear-gradient(-45deg, transparent, transparent 35px, rgba(255,255,255,0.03) 35px, rgba(255,255,255,0.03) 70px);
            opacity: 0.5;
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 600px;
            margin: 0 auto;
        }

        .hero h1 {
            font-family: var(--font-heading);
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 700;
            margin-bottom: var(--space-md);
        }

        .hero p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* ============================================
           🌊 OCEAN DIVIDER
        ============================================ */
        .ocean-divider {
            position: relative;
            width: 100%;
            overflow: hidden;
            line-height: 0;
        }

        .ocean-divider svg {
            position: relative;
            display: block;
            width: calc(100% + 1.3px);
            height: 60px;
        }

        /* ============================================
           📦 CONTAINER SYSTEM
        ============================================ */
        .container {
            max-width: 500px;
            margin: 0 auto;
            padding: 0 var(--space-md);
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ============================================
           🎴 LOGIN CARD
        ============================================ */
        .login-card {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-xl);
            box-shadow: var(--shadow-strong);
            width: 100%;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: -60px; /* Sobrepor ao divider */
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: 
                repeating-linear-gradient(45deg, transparent, transparent 20px, rgba(43, 122, 75, 0.02) 20px, rgba(43, 122, 75, 0.02) 40px);
            opacity: 0.3;
            pointer-events: none;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--dourado-sol);
        }

        .login-header {
            text-align: center;
            margin-bottom: var(--space-lg);
        }

        .login-header h2 {
            font-family: var(--font-heading);
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--carvao);
            margin-bottom: var(--space-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-xs);
        }

        .login-header p {
            color: var(--cinza-baobab);
        }

        /* ============================================
           📝 FORMS
        ============================================ */
        .form-group {
            margin-bottom: var(--space-md);
        }

        label {
            display: block;
            margin-bottom: var(--space-xs);
            font-weight: 600;
            color: var(--carvao);
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: var(--space-sm);
            border: 2px solid var(--areia-quente);
            border-radius: 12px;
            font-size: 1rem;
            font-family: var(--font-body);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--dourado-sol);
            box-shadow: 0 0 0 3px rgba(255, 176, 59, 0.2);
        }

        .password-toggle {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--cinza-baobab);
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .toggle-password:hover {
            background: var(--areia-quente);
            color: var(--carvao);
        }

        /* ============================================
           🔘 BUTTONS
        ============================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-xs);
            padding: var(--space-sm) var(--space-md);
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-soft);
            width: 100%;
            margin-bottom: var(--space-sm);
        }

        .btn-primary {
            background: var(--gradient-oceano);
            color: var(--branco-puro);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-secondary {
            background: var(--cinza-baobab);
            color: var(--branco-puro);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        /* ============================================
           🎭 ALERTAS
        ============================================ */
        .alert {
            padding: var(--space-md);
            border-radius: 12px;
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: flex-start;
            gap: var(--space-sm);
        }

        .alert-error {
            background: rgba(255, 107, 107, 0.1);
            color: var(--coral-vivo);
            border-left: 4px solid var(--coral-vivo);
        }

        /* ============================================
           📱 FOOTER
        ============================================ */
        .login-footer {
            text-align: center;
            margin-top: var(--space-lg);
            padding-top: var(--space-md);
            border-top: 1px solid var(--areia-quente);
            font-size: 0.9rem;
            color: var(--cinza-baobab);
        }

        .login-footer a {
            color: var(--azul-indigo);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .login-footer a:hover {
            color: var(--verde-esperanca);
        }

        .security-notice {
            background: rgba(255, 176, 59, 0.1);
            border: 1px solid var(--dourado-sol);
            border-radius: 12px;
            padding: var(--space-md);
            margin-top: var(--space-lg);
            font-size: 0.8em;
            color: #856404;
            display: flex;
            align-items: flex-start;
            gap: var(--space-xs);
        }

        /* ============================================
           📱 RESPONSIVE DESIGN
        ============================================ */
        @media (max-width: 768px) {
            .hero {
                padding: var(--space-lg) var(--space-md);
            }
            .login-card {
                padding: var(--space-lg);
                margin-top: -40px;
            }
        }
    </style>
</head>
<body>
    <!-- 🌅 HERO SECTION -->
    <div class="hero">
        <div class="hero-content">
            <h1>
                <i data-lucide="shield-check" style="width: 48px; height: 48px;"></i>
                Acesso Administrativo
            </h1>
            <p>Entre com suas credenciais para gerenciar a plataforma</p>
        </div>
    </div>

    <!-- 🌊 OCEAN DIVIDER -->
    <div class="ocean-divider">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                  style="fill: var(--gradient-oceano); opacity: 0.8;"></path>
        </svg>
    </div>

    <!-- 📊 LOGIN CONTENT -->
    <div class="container">
        <div class="login-card">
            <div class="login-header">
                <h2>
                    <i data-lucide="log-in" style="width: 28px; height: 28px;"></i>
                    Admin Login
                </h2>
                <p>Painel de Controle da Plataforma</p>
            </div>

            <?php if (!empty($erro)): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle" style="width: 20px; height: 20px; margin-top: 2px;"></i>
                    <div><?php echo htmlspecialchars($erro); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">
                        <i data-lucide="mail" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 5px;"></i>
                        Email Administrativo
                    </label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                           placeholder="admin@plataforma.co.mz" required>
                </div>

                <div class="form-group">
                    <label for="senha">
                        <i data-lucide="lock" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 5px;"></i>
                        Senha
                    </label>
                    <div class="password-toggle">
                        <input type="password" id="senha" name="senha" 
                               placeholder="Sua senha administrativa" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i data-lucide="eye" style="width: 20px; height: 20px;"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i data-lucide="rocket" style="width: 18px; height: 18px;"></i>
                    Entrar no Painel Admin
                </button>
            </form>

            <div class="security-notice">
                <i data-lucide="shield-alert" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
                <div>
                    <strong>Aviso de Segurança:</strong><br>
                    Esta área é restrita ao pessoal autorizado. Todas as atividades são monitorizadas e registadas.
                </div>
            </div>

            <a href="../index.php" class="btn btn-secondary">
                <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
                Voltar ao Site Principal
            </a>

            <div class="login-footer">
                <p>
                    <a href="recuperar_senha.php">
                        <i data-lucide="key" style="width: 16px; height: 16px; vertical-align: middle;"></i>
                        Esqueceu a senha?
                    </a>
                </p>
                <p style="margin-top: var(--space-sm);">
                    &copy; <?php echo date('Y'); ?> Plataforma Emprego MZ
                </p>
            </div>
        </div>
    </div>

    <!-- ✨ MICRO-INTERACTION SCRIPT -->
    <script>
        // Inicializar ícones Lucide
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            
            // Animação de entrada do card
            const loginCard = document.querySelector('.login-card');
            loginCard.style.opacity = '0';
            loginCard.style.transform = 'translateY(20px)';
            loginCard.style.transition = 'opacity 0.6s ease, transform 0.6s ease';

            setTimeout(() => {
                loginCard.style.opacity = '1';
                loginCard.style.transform = 'translateY(0)';
            }, 300);
            
            // Adicionar efeito de onda ao clicar nos botões
            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;

                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.style.position = 'absolute';
                    ripple.style.borderRadius = '50%';
                    ripple.style.background = 'rgba(255, 255, 255, 0.5)';
                    ripple.style.transform = 'scale(0)';
                    ripple.style.animation = 'ripple 0.6s ease-out';
                    ripple.style.pointerEvents = 'none';

                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);

                    setTimeout(() => ripple.remove(), 600);
                });
            });
            
            // Animação CSS para ripple
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        });

        function togglePassword() {
            const passwordInput = document.getElementById('senha');
            const toggleButton = document.querySelector('.toggle-password i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleButton.setAttribute('data-lucide', 'eye-off');
            } else {
                passwordInput.type = 'password';
                toggleButton.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }

        // Prevenir envio duplo do formulário
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i data-lucide="loader-2" style="width: 18px; height: 18px; animation: spin 1s linear infinite;"></i> A processar...';
            lucide.createIcons();
        });

        // Adicionar animação de spin
        const spinStyle = document.createElement('style');
        spinStyle.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(spinStyle);

        // Foco automático no campo de email
        document.getElementById('email').focus();
    </script>
</body>
</html>