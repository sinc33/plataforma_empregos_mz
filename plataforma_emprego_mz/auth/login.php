<?php
session_start();
require_once '../config/db.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $tipo_usuario_form = $_POST['tipo_usuario'] ?? 'candidato'; // Tipo selecionado no formulário

    if (!empty($email) && !empty($senha)) {
        $pdo = getPDO();

        try {
            // Buscar utilizador pelo email
            $stmt = $pdo->prepare("SELECT id, email, senha, tipo, ativo FROM utilizador WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && $user['ativo'] && password_verify($senha, $user['senha'])) {
                // Verificar se o tipo de conta coincide com o selecionado no formulário
                if ($user['tipo'] !== $tipo_usuario_form) {
                    $erro = "Tipo de conta incorreto para este email.";
                } else {
                    // Login bem-sucedido
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = $user['tipo'];
                    $_SESSION['user_email'] = $user['email'];

                    // Buscar nome específico com base no tipo
                    if ($user['tipo'] === 'empresa') {
                        $stmt_nome = $pdo->prepare("SELECT nome_empresa FROM empresa WHERE id = ?");
                    } else { // candidato
                        $stmt_nome = $pdo->prepare("SELECT nome_completo FROM candidato WHERE id = ?");
                    }
                    $stmt_nome->execute([$user['id']]);
                    $perfil = $stmt_nome->fetch();

                    if ($perfil) {
                        $_SESSION['user_name'] = $user['tipo'] === 'empresa' ? $perfil['nome_empresa'] : $perfil['nome_completo'];
                    } else {
                        $_SESSION['user_name'] = 'Usuário'; // Nome padrão se o perfil não existir
                    }

                    // Atualizar último login
                    $pdo->prepare("UPDATE utilizador SET ultimo_login = NOW() WHERE id = ?")
                        ->execute([$user['id']]);

                    // Redirecionar conforme o tipo de utilizador
                    if ($user['tipo'] === 'empresa') {
                        header("Location: ../empresa/dashboard.php");
                    } else {
                        // Verificar redirecionamento após login
                        if (isset($_SESSION['redirect_after_login'])) {
                            $redirect = $_SESSION['redirect_after_login'];
                            // Validação: permite apenas URLs relativas para segurança
                            if (filter_var($redirect, FILTER_VALIDATE_URL) === false && preg_match('/^\/[a-zA-Z0-9\/\-_\.]*$/', $redirect)) {
                                unset($_SESSION['redirect_after_login']);
                                header("Location: $redirect");
                            } else {
                                header("Location: ../candidato/perfil.php");
                            }
                        } else {
                            header("Location: ../candidato/perfil.php");
                        }
                    }
                    exit;
                }
            } else {
                $erro = "Credenciais inválidas ou conta inativa.";
            }

        } catch (PDOException $e) {
            $erro = "Erro no sistema. Tente novamente.";
        }
    } else {
        $erro = "Por favor, preencha todos os campos.";
    }
}

// Se chegou aqui, mostra o formulário de login
?>


<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Login - Emprego MZ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Google Fonts - Inter & Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Link para o CSS Global Marrabenta UI -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Incluindo o Header Reutilizável -->
    <?php require '../includes/header.php'; ?>

    <!-- 🌅 HERO SECTION -->
    <div class="hero hero--login">
        <div class="hero-content">
            <h1>Bem-vindo de Volta</h1>
            <p>Conectamos talentos moçambicanos com as melhores oportunidades em todas as províncias</p>
        </div>
    </div>

    <!-- 🌊 OCEAN DIVIDER -->
    <div class="ocean-divider">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z"
                  style="fill: var(--gradient-oceano); opacity: 0.8;"></path>
            <defs>
                <linearGradient id="gradient-oceano" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" style="stop-color:#1E3A5F;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#2B7A4B;stop-opacity:1" />
                </linearGradient>
            </defs>
        </svg>
    </div>

    <!-- 🔐 CONTEÚDO PRINCIPAL -->
    <main class="main-content">
        <div class="container">
            <div class="login-container">
                <div class="login-card">
                    <div class="login-header">
                        <h2 class="login-title">Acessar Conta</h2>
                        <p class="login-subtitle">Entre para encontrar ou publicar vagas</p>
                    </div>

                    <!-- Mensagem de Erro -->
                    <?php if (!empty($erro)): ?>
                        <div class="alert alert-error">
                            <i data-lucide="alert-circle" style="width: 20px; height: 20px; margin-top: 2px;"></i>
                            <div><?php echo htmlspecialchars($erro); ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Formulário de Login -->
                    <form method="POST" id="loginForm">
                        <!-- Tipo de Usuário -->
                        <div class="user-type-toggle" id="userTypeToggle">
                            <div class="user-type-slider"></div>
                            <div class="user-type-option active" data-type="candidato">
                                <i data-lucide="user" style="width: 16px; height: 16px;"></i>
                                Candidato
                            </div>
                            <div class="user-type-option" data-type="empresa">
                                <i data-lucide="building" style="width: 16px; height: 16px;"></i>
                                Empresa
                            </div>
                        </div>
                        <input type="hidden" name="tipo_usuario" id="tipoUsuario" value="candidato">

                        <!-- Email -->
                        <div class="form-group">
                            <label for="email" class="form-label">
                                <i data-lucide="mail" style="width: 16px; height: 16px;"></i>
                                Email
                            </label>
                            <div class="input-icon">
                                <input type="email" id="email" name="email" class="form-control"
                                       placeholder="seu@email.com" required>
                                <i data-lucide="mail"></i>
                            </div>
                            <div class="form-error" id="emailError" style="display: none;">
                                <i data-lucide="alert-circle" style="width: 14px; height: 14px;"></i>
                                <span>Por favor, insira um email válido</span>
                            </div>
                        </div>

                        <!-- Senha -->
                        <div class="form-group">
                            <label for="senha" class="form-label">
                                <i data-lucide="lock" style="width: 16px; height: 16px;"></i>
                                Senha
                            </label>
                            <div class="input-icon">
                                <input type="password" id="senha" name="senha" class="form-control"
                                       placeholder="Sua senha" required>
                                <i data-lucide="lock"></i>
                            </div>
                            <div class="form-error" id="senhaError" style="display: none;">
                                <i data-lucide="alert-circle" style="width: 14px; height: 14px;"></i>
                                <span>A senha deve ter pelo menos 6 caracteres</span>
                            </div>
                        </div>

                        <!-- Botão de Login -->
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="log-in" style="width: 18px; height: 18px;"></i>
                            Entrar
                        </button>

                        <!-- Links Úteis -->
                        <div class="form-links">
                            <a href="recuperar_senha.php" class="form-link">
                                <i data-lucide="help-circle" style="width: 14px; height: 14px;"></i>
                                Esqueceu a senha?
                            </a>
                            <a href="register.php" class="form-link">
                                <i data-lucide="user-plus" style="width: 14px; height: 14px;"></i>
                                Criar conta
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Incluindo o Footer Reutilizável -->
    <?php require '../includes/footer.php'; ?>

    <!-- ✨ MICRO-INTERACTION SCRIPT -->
    <script>
        // Inicializar ícones Lucide
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();

            // Toggle entre Candidato e Empresa
            const userTypeToggle = document.getElementById('userTypeToggle');
            const userTypeOptions = document.querySelectorAll('.user-type-option');
            const tipoUsuarioInput = document.getElementById('tipoUsuario');

            userTypeOptions.forEach(option => {
                option.addEventListener('click', () => {
                    // Remover classe active de todas as opções
                    userTypeOptions.forEach(opt => opt.classList.remove('active'));

                    // Adicionar classe active à opção clicada
                    option.classList.add('active');

                    // Atualizar a classe do container
                    userTypeToggle.classList.remove('candidato', 'empresa');
                    userTypeToggle.classList.add(option.dataset.type);

                    // Atualizar o input hidden
                    tipoUsuarioInput.value = option.dataset.type;

                    // Re-inicializar ícones
                    lucide.createIcons();
                });
            });

            // Validação do formulário
            const loginForm = document.getElementById('loginForm');
            const emailInput = document.getElementById('email');
            const senhaInput = document.getElementById('senha');
            const emailError = document.getElementById('emailError');
            const senhaError = document.getElementById('senhaError');

            // Função para validar email
            function validateEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }

            // Validação em tempo real
            emailInput.addEventListener('blur', () => {
                if (!validateEmail(emailInput.value)) {
                    emailInput.classList.add('error');
                    emailError.style.display = 'flex';
                } else {
                    emailInput.classList.remove('error');
                    emailError.style.display = 'none';
                }
            });

            senhaInput.addEventListener('blur', () => {
                if (senhaInput.value.length < 6) {
                    senhaInput.classList.add('error');
                    senhaError.style.display = 'flex';
                } else {
                    senhaInput.classList.remove('error');
                    senhaError.style.display = 'none';
                }
            });

            // Validação ao submeter o formulário
            loginForm.addEventListener('submit', (e) => {
                let isValid = true;

                if (!validateEmail(emailInput.value)) {
                    emailInput.classList.add('error');
                    emailError.style.display = 'flex';
                    isValid = false;
                }

                if (senhaInput.value.length < 6) {
                    senhaInput.classList.add('error');
                    senhaError.style.display = 'flex';
                    isValid = false;
                }

                if (!isValid) {
                    e.preventDefault();
                }
            });

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

            // Animação de entrada para o card
            const loginCard = document.querySelector('.login-card');
            loginCard.style.opacity = '0';
            loginCard.style.transform = 'translateY(30px)';
            loginCard.style.transition = 'opacity 0.6s ease, transform 0.6s ease';

            setTimeout(() => {
                loginCard.style.opacity = '1';
                loginCard.style.transform = 'translateY(0)';
            }, 300);
        });
    </script>
</body>
</html>