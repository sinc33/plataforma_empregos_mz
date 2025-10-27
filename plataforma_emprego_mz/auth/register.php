<?php
session_start();
require_once '../config/db.php';

 $erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $tipo = $_POST['tipo']; // 'candidato' ou 'empresa'
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $nome_empresa = trim($_POST['nome_empresa'] ?? '');
    
    // Validações básicas
    if (empty($email) || empty($senha) || empty($tipo)) {
        $erro = "Por favor, preencha todos os campos obrigatórios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Email inválido.";
    } elseif (strlen($senha) < 6) {
        $erro = "A senha deve ter pelo menos 6 caracteres.";
    } elseif ($tipo === 'candidato' && empty($nome_completo)) {
        $erro = "Nome completo é obrigatório para candidatos.";
    } elseif ($tipo === 'empresa' && empty($nome_empresa)) {
        $erro = "Nome da empresa é obrigatório.";
    } else {
        $pdo = getPDO();
        
        try {
            $pdo->beginTransaction();

            // Verificar se email já existe
            $stmt = $pdo->prepare("SELECT id FROM utilizador WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $erro = "Este email já está registado.";
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
                
                // Redirecionar conforme o tipo
                if ($tipo === 'empresa') {
                    header("Location: ../empresa/dashboard.php");
                } else {
                    header("Location: ../candidato/perfil.php");
                }
                exit;
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = "Erro no registo: " . $e->getMessage();
        }
    }
}

// Se não foi submetido ou houve erro, mostrar formulário básico
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Emprego MZ</title>
    
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
    <div class="hero">
        <div class="hero-content">
            <h1>Criar Conta</h1>
            <p>Junte-se a milhares de profissionais e empresas que já encontraram sucesso na nossa plataforma</p>
        </div>
    </div>

    <!-- 🌊 OCEAN DIVIDER -->
    <div class="ocean-divider">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                  style="fill: url(#gradient-oceano); opacity: 0.8;"></path>
            <defs>
                <linearGradient id="gradient-oceano" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" style="stop-color:#1E3A5F;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#2B7A4B;stop-opacity:1" />
                </linearGradient>
            </defs>
        </svg>
    </div>

    <!-- 🔐 REGISTER SECTION -->
    <div class="main-content">
        <div class="container">
            <div class="register-container">
                <div class="register-card">
                    <div class="register-header">
                        <h2 class="register-title">Criar Nova Conta</h2>
                        <p class="register-subtitle">Preencha os dados abaixo para começar</p>
                    </div>

                    <!-- Mensagem de Erro -->
                    <?php if (!empty($erro)): ?>
                        <div class="alert alert-error">
                            <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
                            <?php echo htmlspecialchars($erro); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Formulário de Registro -->
                    <form method="POST" id="registerForm">
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
                        <input type="hidden" name="tipo" id="tipoUsuario" value="candidato">

                        <!-- Campos Específicos para Candidato -->
                        <div class="user-specific-fields active" id="camposCandidato">
                            <div class="form-group">
                                <label for="nome_completo" class="form-label">
                                    <i data-lucide="user" style="width: 16px; height: 16px;"></i>
                                    Nome Completo
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="nome_completo" name="nome_completo" class="form-control" 
                                           placeholder="Seu nome completo">
                                    <i data-lucide="user"></i>
                                </div>
                                <div class="form-error" id="nomeCompletoError" style="display: none;">
                                    <i data-lucide="alert-circle" style="width: 14px; height: 14px;"></i>
                                    <span>Por favor, insira seu nome completo</span>
                                </div>
                            </div>
                        </div>

                        <!-- Campos Específicos para Empresa -->
                        <div class="user-specific-fields" id="camposEmpresa">
                            <div class="form-group">
                                <label for="nome_empresa" class="form-label">
                                    <i data-lucide="building" style="width: 16px; height: 16px;"></i>
                                    Nome da Empresa
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="nome_empresa" name="nome_empresa" class="form-control" 
                                           placeholder="Nome da sua empresa">
                                    <i data-lucide="building"></i>
                                </div>
                                <div class="form-error" id="nomeEmpresaError" style="display: none;">
                                    <i data-lucide="alert-circle" style="width: 14px; height: 14px;"></i>
                                    <span>Por favor, insira o nome da empresa</span>
                                </div>
                            </div>
                        </div>

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
                                Senha (mínimo 6 caracteres)
                            </label>
                            <div class="input-icon">
                                <input type="password" id="senha" name="senha" class="form-control" 
                                       placeholder="Crie uma senha segura" required>
                                <i data-lucide="lock"></i>
                            </div>
                            <div class="form-error" id="senhaError" style="display: none;">
                                <i data-lucide="alert-circle" style="width: 14px; height: 14px;"></i>
                                <span>A senha deve ter pelo menos 6 caracteres</span>
                            </div>
                        </div>

                        <!-- Botão de Registro -->
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="user-plus" style="width: 18px; height: 18px;"></i>
                            Criar Conta
                        </button>

                        <!-- Links Úteis -->
                        <div class="form-links">
                            <a href="login.php" class="form-link">
                                <i data-lucide="log-in" style="width: 14px; height: 14px;"></i>
                                Já tem conta? Faça login
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

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
            const camposCandidato = document.getElementById('camposCandidato');
            const camposEmpresa = document.getElementById('camposEmpresa');
            
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
                    
                    // Mostrar/ocultar campos específicos
                    if (option.dataset.type === 'candidato') {
                        camposCandidato.classList.add('active');
                        camposEmpresa.classList.remove('active');
                    } else {
                        camposCandidato.classList.remove('active');
                        camposEmpresa.classList.add('active');
                    }
                    
                    // Re-inicializar ícones
                    lucide.createIcons();
                });
            });
            
            // Validação do formulário
            const registerForm = document.getElementById('registerForm');
            const emailInput = document.getElementById('email');
            const senhaInput = document.getElementById('senha');
            const nomeCompletoInput = document.getElementById('nome_completo');
            const nomeEmpresaInput = document.getElementById('nome_empresa');
            
            // Elementos de erro
            const emailError = document.getElementById('emailError');
            const senhaError = document.getElementById('senhaError');
            const nomeCompletoError = document.getElementById('nomeCompletoError');
            const nomeEmpresaError = document.getElementById('nomeEmpresaError');
            
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
            
            nomeCompletoInput.addEventListener('blur', () => {
                if (tipoUsuarioInput.value === 'candidato' && nomeCompletoInput.value.trim() === '') {
                    nomeCompletoInput.classList.add('error');
                    nomeCompletoError.style.display = 'flex';
                } else {
                    nomeCompletoInput.classList.remove('error');
                    nomeCompletoError.style.display = 'none';
                }
            });
            
            nomeEmpresaInput.addEventListener('blur', () => {
                if (tipoUsuarioInput.value === 'empresa' && nomeEmpresaInput.value.trim() === '') {
                    nomeEmpresaInput.classList.add('error');
                    nomeEmpresaError.style.display = 'flex';
                } else {
                    nomeEmpresaInput.classList.remove('error');
                    nomeEmpresaError.style.display = 'none';
                }
            });
            
            // Validação ao submeter o formulário
            registerForm.addEventListener('submit', (e) => {
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
                
                if (tipoUsuarioInput.value === 'candidato' && nomeCompletoInput.value.trim() === '') {
                    nomeCompletoInput.classList.add('error');
                    nomeCompletoError.style.display = 'flex';
                    isValid = false;
                }
                
                if (tipoUsuarioInput.value === 'empresa' && nomeEmpresaInput.value.trim() === '') {
                    nomeEmpresaInput.classList.add('error');
                    nomeEmpresaError.style.display = 'flex';
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
            const registerCard = document.querySelector('.register-card');
            registerCard.style.opacity = '0';
            registerCard.style.transform = 'translateY(30px)';
            registerCard.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            
            setTimeout(() => {
                registerCard.style.opacity = '1';
                registerCard.style.transform = 'translateY(0)';
            }, 300);
        });
    </script>
</body>
</html>