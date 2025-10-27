<?php
session_start();
require_once '../config/db.php';

// Verificar se o usuário está logado e é uma empresa
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'empresa') {
    header("Location: ../auth/login.php");
    exit;
}

 $pdo = getPDO();
 $empresa_id = $_SESSION['user_id'];
 $sucesso = '';
 $erro = '';

// Buscar informações da empresa para mostrar no formulário
 $stmt_empresa = $pdo->prepare("SELECT nome_empresa, localizacao FROM empresa WHERE id = ?");
 $stmt_empresa->execute([$empresa_id]);
 $empresa = $stmt_empresa->fetch();

// Processar o formulário de criação de vaga
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coletar e sanitizar dados
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $area = $_POST['area'] ?? '';
    $localizacao = $_POST['localizacao'] ?? '';
    $tipo_contrato = $_POST['tipo_contrato'] ?? '';
    $modalidade = $_POST['modalidade'] ?? '';
    $nivel_experiencia = $_POST['nivel_experiencia'] ?? '';
    $salario_estimado = $_POST['salario_estimado'] ?? null;
    $data_expiracao = $_POST['data_expiracao'] ?? '';

    // Validações
    $erros_validacao = [];

    if (empty($titulo) || strlen($titulo) < 5) {
        $erros_validacao[] = "O título deve ter pelo menos 5 caracteres.";
    }

    if (empty($descricao) || strlen($descricao) < 50) {
        $erros_validacao[] = "A descrição deve ter pelo menos 50 caracteres.";
    }

    if (empty($area)) {
        $erros_validacao[] = "Selecione uma área de atuação.";
    }

    if (empty($localizacao)) {
        $erros_validacao[] = "Selecione uma localização.";
    }

    if (empty($tipo_contrato)) {
        $erros_validacao[] = "Selecione o tipo de contrato.";
    }

    if (empty($modalidade)) {
        $erros_validacao[] = "Selecione a modalidade de trabalho.";
    }

    if (empty($nivel_experiencia)) {
        $erros_validacao[] = "Selecione o nível de experiência.";
    }

    if (!empty($salario_estimado) && (!is_numeric($salario_estimado) || $salario_estimado < 0)) {
        $erros_validacao[] = "O salário estimado deve ser um valor numérico positivo.";
    }

    if (empty($data_expiracao)) {
        $erros_validacao[] = "A data de expiração é obrigatória.";
    } else {
        $data_expiracao_obj = DateTime::createFromFormat('Y-m-d', $data_expiracao);
        $hoje = new DateTime();
        if (!$data_expiracao_obj || $data_expiracao_obj < $hoje) {
            $erros_validacao[] = "A data de expiração deve ser uma data futura.";
        }
    }

    // Se não há erros, inserir no banco
    if (empty($erros_validacao)) {
        try {
            $pdo->beginTransaction();

            $sql = "INSERT INTO vaga (
                empresa_id, titulo, descricao, area, localizacao, 
                tipo_contrato, modalidade, nivel_experiencia, 
                salario_estimado, data_expiracao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $empresa_id,
                $titulo,
                $descricao,
                $area,
                $localizacao,
                $tipo_contrato,
                $modalidade,
                $nivel_experiencia,
                $salario_estimado ? (float)$salario_estimado : null,
                $data_expiracao
            ]);

            $pdo->commit();
            $sucesso = "Vaga publicada com sucesso!";
            
            // Limpar o formulário após sucesso
            $_POST = [];

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = "Erro ao publicar vaga: " . $e->getMessage();
        }
    } else {
        $erro = implode("<br>", $erros_validacao);
    }
}

// Dados para os selects
 $areas = [
    'TI e Tecnologia',
    'Agricultura e Pecuária',
    'Construção Civil',
    'Educação e Formação',
    'Saúde e Medicina',
    'Comércio e Vendas',
    'Hotelaria e Turismo',
    'Administração e Secretariado',
    'Finanças e Contabilidade',
    'Recursos Humanos',
    'Marketing e Publicidade',
    'Logística e Transportes',
    'Mineração e Recursos Naturais',
    'Pesca e Aquicultura',
    'Energia e Água',
    'Telecomunicações',
    'Segurança',
    'Social e Comunidade'
];

 $provincias_mz = [
    'Maputo Cidade',
    'Maputo Província',
    'Gaza',
    'Inhambane',
    'Sofala',
    'Manica',
    'Tete',
    'Zambézia',
    'Nampula',
    'Cabo Delgado',
    'Niassa',
    'Remoto'
];

 $tipos_contrato = [
    'tempo_inteiro' => 'Tempo Inteiro',
    'tempo_parcial' => 'Tempo Parcial',
    'estagio' => 'Estágio',
    'freelance' => 'Freelance'
];

 $modalidades = [
    'presencial' => 'Presencial',
    'hibrido' => 'Híbrido',
    'remoto' => 'Remoto'
];

 $niveis_experiencia = [
    'Estagiário' => 'Estagiário',
    'Júnior' => 'Júnior',
    'Pleno' => 'Pleno',
    'Sénior' => 'Sénior',
    'Gestor' => 'Gestor',
    'Diretor' => 'Diretor'
];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Nova Vaga - Emprego MZ</title>
    
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
        }

        /* ============================================
           🌅 HERO SECTION - Pôr do Sol de Maputo
        ============================================ */
        .hero {
            background: var(--gradient-oceano);
            color: var(--branco-puro);
            padding: var(--space-xl) var(--space-md);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        /* Padrão Capulana Sutil no Background */
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
            max-width: 800px;
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
            max-width: 600px;
            margin: 0 auto;
        }

        /* ============================================
           🌊 OCEAN DIVIDER - Elemento Assinatura
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 var(--space-md);
        }

        .main-content {
            padding: var(--space-xl) 0;
        }

        /* ============================================
           🎴 XIMA CARD - Formulário de Criação
        ============================================ */
        .form-card {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-xl);
            box-shadow: var(--shadow-strong);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            margin-bottom: var(--space-xl);
        }

        /* Padrão Capulana muito sutil no card */
        .form-card::before {
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

        .form-header {
            text-align: center;
            margin-bottom: var(--space-lg);
        }

        .form-title {
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

        .form-subtitle {
            color: var(--cinza-baobab);
            font-size: 1rem;
        }

        /* ============================================
           📝 FORMULÁRIOS
        ============================================ */
        .form-group {
            margin-bottom: var(--space-md);
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--carvao);
            margin-bottom: var(--space-xs);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .form-control {
            width: 100%;
            padding: var(--space-md);
            border: 2px solid var(--areia-quente);
            border-radius: 12px;
            font-size: 1rem;
            font-family: var(--font-body);
            transition: all 0.3s;
            background: var(--branco-marfim);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--verde-esperanca);
            background: var(--branco-puro);
            box-shadow: 0 0 0 3px rgba(43, 122, 75, 0.1);
        }

        .form-control.error {
            border-color: var(--coral-vivo);
            background: rgba(255, 107, 107, 0.05);
        }

        .form-error {
            color: var(--coral-vivo);
            font-size: 0.85rem;
            margin-top: var(--space-xs);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .form-hint {
            font-size: 0.85rem;
            color: var(--cinza-baobab);
            margin-top: var(--space-xs);
        }

        .char-count {
            font-size: 0.8rem;
            color: var(--cinza-baobab);
            text-align: right;
            margin-top: var(--space-xs);
        }

        .char-count.warning {
            color: var(--dourado-sol);
        }

        .char-count.error {
            color: var(--coral-vivo);
        }

        /* Form rows para campos lado a lado */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-md);
        }

        /* ============================================
           🔘 BUTTONS - Sistema de Botões
        ============================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-xs);
            padding: var(--space-md) var(--space-lg);
            border-radius: 50px;
            font-size: 1.05rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary {
            background: var(--gradient-oceano);
            color: var(--branco-puro);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-success {
            background: var(--verde-esperanca);
            color: var(--branco-puro);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-secondary {
            background: transparent;
            color: var(--azul-indico);
            border: 2px solid var(--azul-indico);
        }

        .btn-secondary:hover {
            background: var(--azul-indico);
            color: var(--branco-puro);
            transform: translateY(-2px);
        }

        .btn-group {
            display: flex;
            gap: var(--space-sm);
            margin-top: var(--space-md);
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

        .alert-success {
            background: rgba(43, 122, 75, 0.1);
            color: var(--verde-esperanca);
            border-left: 4px solid var(--verde-esperanca);
        }

        .alert-danger {
            background: rgba(255, 107, 107, 0.1);
            color: var(--coral-vivo);
            border-left: 4px solid var(--coral-vivo);
        }

        .alert-actions {
            margin-top: var(--space-md);
            display: flex;
            gap: var(--space-sm);
            flex-wrap: wrap;
        }

        /* ============================================
           📱 NAVIGATION
        ============================================ */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            margin-bottom: var(--space-md);
            font-size: 0.9rem;
            color: var(--cinza-baobab);
        }

        .breadcrumb a {
            color: var(--azul-indico);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            transition: color 0.3s;
        }

        .breadcrumb a:hover {
            color: var(--verde-esperanca);
        }

        /* ============================================
           📱 RESPONSIVE DESIGN
        ============================================ */
        @media (max-width: 768px) {
            .hero {
                padding: var(--space-lg) var(--space-md);
            }

            .form-card {
                padding: var(--space-lg);
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .alert-actions {
                flex-direction: column;
            }
        }

        /* ============================================
           ✨ MICRO-ANIMATIONS
        ============================================ */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .floating {
            animation: float 3s ease-in-out infinite;
        }

        /* Smooth scroll */
        html {
            scroll-behavior: smooth;
        }

        /* Selection colors */
        ::selection {
            background: var(--dourado-sol);
            color: var(--carvao);
        }

        /* Foco melhorado para acessibilidade */
        .btn:focus, .form-control:focus, .breadcrumb a:focus {
            outline: 3px solid var(--dourado-sol);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <!-- 🌅 HERO SECTION -->
    <div class="hero">
        <div class="hero-content">
            <h1>Criar Nova Vaga</h1>
            <p>Encontre o talento ideal para sua empresa em toda Moçambique</p>
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

    <!-- 📝 FORM SECTION -->
    <div class="main-content">
        <div class="container">
            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <a href="../index.php">
                    <i data-lucide="home" style="width: 16px; height: 16px;"></i>
                    Início
                </a>
                <span>/</span>
                <a href="dashboard.php">
                    <i data-lucide="bar-chart" style="width: 16px; height: 16px;"></i>
                    Dashboard
                </a>
                <span>/</span>
                <span>Criar Vaga</span>
            </nav>

            <!-- Mensagem de Sucesso -->
            <?php if ($sucesso): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" style="width: 20px; height: 20px; margin-top: 2px;"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($sucesso); ?></strong>
                        <div class="alert-actions">
                            <a href="dashboard.php" class="btn btn-primary">
                                <i data-lucide="bar-chart" style="width: 16px; height: 16px;"></i>
                                Ver Minhas Vagas
                            </a>
                            <a href="criar_vaga.php" class="btn btn-success">
                                <i data-lucide="plus-circle" style="width: 16px; height: 16px;"></i>
                                Criar Outra Vaga
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Mensagem de Erro -->
            <?php if ($erro): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" style="width: 20px; height: 20px; margin-top: 2px;"></i>
                    <div><?php echo $erro; ?></div>
                </div>
            <?php endif; ?>

            <!-- Formulário de Criação de Vaga -->
            <div class="form-card">
                <div class="form-header">
                    <h2 class="form-title">
                        <i data-lucide="briefcase" style="width: 24px; height: 24px;"></i>
                        Detalhes da Vaga
                    </h2>
                    <p class="form-subtitle">Preencha as informações para encontrar o candidato ideal</p>
                </div>

                <form method="POST" id="formVaga">
                    <!-- Título da Vaga -->
                    <div class="form-group">
                        <label for="titulo" class="form-label">
                            <i data-lucide="tag" style="width: 16px; height: 16px;"></i>
                            Título da Vaga *
                        </label>
                        <input type="text" id="titulo" name="titulo" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>" 
                               placeholder="Ex: Desenvolvedor Web PHP Sénior" 
                               required maxlength="255">
                        <div class="char-count" id="tituloCount">0/255</div>
                    </div>

                    <!-- Descrição Detalhada -->
                    <div class="form-group">
                        <label for="descricao" class="form-label">
                            <i data-lucide="file-text" style="width: 16px; height: 16px;"></i>
                            Descrição Detalhada *
                        </label>
                        <textarea id="descricao" name="descricao" class="form-control" 
                                  placeholder="Descreva as responsabilidades, requisitos, benefícios e tudo que for importante sobre esta vaga..."
                                  required minlength="50"><?php echo htmlspecialchars($_POST['descricao'] ?? ''); ?></textarea>
                        <div class="char-count" id="descricaoCount">0 caracteres (mínimo: 50)</div>
                    </div>

                    <!-- Área e Localização -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="area" class="form-label">
                                <i data-lucide="briefcase" style="width: 16px; height: 16px;"></i>
                                Área de Atuação *
                            </label>
                            <select id="area" name="area" class="form-control" required>
                                <option value="">Selecione uma área</option>
                                <?php foreach ($areas as $area_option): ?>
                                    <option value="<?php echo htmlspecialchars($area_option); ?>" 
                                        <?php echo ($_POST['area'] ?? '') === $area_option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($area_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="localizacao" class="form-label">
                                <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i>
                                Localização *
                            </label>
                            <select id="localizacao" name="localizacao" class="form-control" required>
                                <option value="">Selecione uma localização</option>
                                <?php foreach ($provincias_mz as $provincia): ?>
                                    <option value="<?php echo htmlspecialchars($provincia); ?>" 
                                        <?php echo ($_POST['localizacao'] ?? '') === $provincia ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($provincia); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Tipo de Contrato e Modalidade -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo_contrato" class="form-label">
                                <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                                Tipo de Contrato *
                            </label>
                            <select id="tipo_contrato" name="tipo_contrato" class="form-control" required>
                                <option value="">Selecione o tipo</option>
                                <?php foreach ($tipos_contrato as $key => $value): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" 
                                        <?php echo ($_POST['tipo_contrato'] ?? '') === $key ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="modalidade" class="form-label">
                                <i data-lucide="monitor" style="width: 16px; height: 16px;"></i>
                                Modalidade de Trabalho *
                            </label>
                            <select id="modalidade" name="modalidade" class="form-control" required>
                                <option value="">Selecione a modalidade</option>
                                <?php foreach ($modalidades as $key => $value): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" 
                                        <?php echo ($_POST['modalidade'] ?? '') === $key ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Nível de Experiência e Salário -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nivel_experiencia" class="form-label">
                                <i data-lucide="award" style="width: 16px; height: 16px;"></i>
                                Nível de Experiência *
                            </label>
                            <select id="nivel_experiencia" name="nivel_experiencia" class="form-control" required>
                                <option value="">Selecione o nível</option>
                                <?php foreach ($niveis_experiencia as $key => $value): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" 
                                        <?php echo ($_POST['nivel_experiencia'] ?? '') === $key ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="salario_estimado" class="form-label">
                                <i data-lucide="dollar-sign" style="width: 16px; height: 16px;"></i>
                                Salário Estimado (MT)
                            </label>
                            <input type="number" id="salario_estimado" name="salario_estimado" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['salario_estimado'] ?? ''); ?>" 
                                   placeholder="Ex: 25000" min="0" step="100">
                            <div class="form-hint">Opcional - valor em Meticais</div>
                        </div>
                    </div>

                    <!-- Data de Expiração -->
                    <div class="form-group">
                        <label for="data_expiracao" class="form-label">
                            <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                            Data de Expiração *
                        </label>
                        <input type="date" id="data_expiracao" name="data_expiracao" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['data_expiracao'] ?? ''); ?>" 
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                        <div class="form-hint">A vaga ficará visível até esta data</div>
                    </div>

                    <!-- Botões de Ação -->
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="send" style="width: 18px; height: 18px;"></i>
                            Publicar Vaga
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i data-lucide="x-circle" style="width: 18px; height: 18px;"></i>
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 🌊 OCEAN DIVIDER -->
    <div class="ocean-divider">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none" transform="rotate(180)">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                  style="fill: url(#gradient-oceano); opacity: 0.8;"></path>
        </svg>
    </div>

    <!-- ✨ MICRO-INTERACTION SCRIPT -->
    <script>
        // Inicializar ícones Lucide
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            
            // Contadores de caracteres
            const tituloInput = document.getElementById('titulo');
            const descricaoInput = document.getElementById('descricao');
            const tituloCount = document.getElementById('tituloCount');
            const descricaoCount = document.getElementById('descricaoCount');
            
            tituloInput.addEventListener('input', function() {
                const count = this.value.length;
                tituloCount.textContent = `${count}/255`;
                
                // Mudar cor conforme se aproxima do limite
                if (count > 230) {
                    tituloCount.classList.add('error');
                    tituloCount.classList.remove('warning');
                } else if (count > 200) {
                    tituloCount.classList.add('warning');
                    tituloCount.classList.remove('error');
                } else {
                    tituloCount.classList.remove('warning', 'error');
                }
            });
            
            descricaoInput.addEventListener('input', function() {
                const count = this.value.length;
                descricaoCount.textContent = `${count} caracteres${count < 50 ? ' (mínimo: 50)' : ''}`;
                
                // Mudar cor conforme se aproxima do mínimo
                if (count < 50) {
                    descricaoCount.classList.add('error');
                    descricaoCount.classList.remove('warning');
                } else if (count < 100) {
                    descricaoCount.classList.add('warning');
                    descricaoCount.classList.remove('error');
                } else {
                    descricaoCount.classList.remove('warning', 'error');
                }
            });
            
            // Inicializar contadores
            tituloInput.dispatchEvent(new Event('input'));
            descricaoInput.dispatchEvent(new Event('input'));
            
            // Validação do formulário
            const formVaga = document.getElementById('formVaga');
            
            formVaga.addEventListener('submit', function(e) {
                const titulo = tituloInput.value.trim();
                const descricao = descricaoInput.value.trim();
                let isValid = true;
                
                // Validar título
                if (titulo.length < 5) {
                    tituloInput.classList.add('error');
                    isValid = false;
                }
                
                // Validar descrição
                if (descricao.length < 50) {
                    descricaoInput.classList.add('error');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    
                    // Mostrar mensagem de erro personalizada
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger';
                    errorDiv.innerHTML = `
                        <i data-lucide="alert-circle" style="width: 20px; height: 20px; margin-top: 2px;"></i>
                        <div>Por favor, corrija os erros no formulário antes de submeter.</div>
                    `;
                    
                    // Inserir no início do formulário
                    formVaga.insertBefore(errorDiv, formVaga.firstChild);
                    
                    // Re-inicializar ícones
                    lucide.createIcons();
                    
                    // Remover após 5 segundos
                    setTimeout(() => {
                        if (errorDiv.parentNode) {
                            errorDiv.parentNode.removeChild(errorDiv);
                        }
                    }, 5000);
                    
                    // Rolar para o topo
                    window.scrollTo({ top: 0, behavior: 'smooth' });
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
            const formCard = document.querySelector('.form-card');
            formCard.style.opacity = '0';
            formCard.style.transform = 'translateY(30px)';
            formCard.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            
            setTimeout(() => {
                formCard.style.opacity = '1';
                formCard.style.transform = 'translateY(0)';
            }, 300);
        });
    </script>
</body>
</html>