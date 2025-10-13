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
 $vaga_id = isset($_GET['vaga_id']) ? (int)$_GET['vaga_id'] : 0;

// Verificar se a vaga existe e pertence à empresa
if ($vaga_id) {
    $stmt_vaga = $pdo->prepare("SELECT v.*, e.nome_empresa FROM vaga v JOIN empresa e ON v.empresa_id = e.id WHERE v.id = ? AND v.empresa_id = ?");
    $stmt_vaga->execute([$vaga_id, $empresa_id]);
    $vaga = $stmt_vaga->fetch();
    
    if (!$vaga) {
        header("Location: dashboard.php");
        exit;
    }
} else {
    header("Location: dashboard.php");
    exit;
}

// Processar ações nas candidaturas
 $sucesso = '';
 $erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidato_id = (int)$_POST['candidato_id'];
    $acao = $_POST['acao'];
    
    // Verificar se a candidatura pertence a uma vaga da empresa
    $stmt_verificar = $pdo->prepare("SELECT c.id FROM candidatura c JOIN vaga v ON c.vaga_id = v.id WHERE c.id = ? AND v.empresa_id = ?");
    $stmt_verificar->execute([$candidato_id, $empresa_id]);
    
    if ($stmt_verificar->fetch()) {
        try {
            switch ($acao) {
                case 'mudar_estado':
                    $novo_estado = $_POST['estado'];
                    $estados_validos = ['submetida', 'em_analise', 'entrevista', 'rejeitada', 'contratado'];
                    
                    if (in_array($novo_estado, $estados_validos)) {
                        $stmt = $pdo->prepare("UPDATE candidatura SET estado = ? WHERE id = ?");
                        $stmt->execute([$novo_estado, $candidato_id]);
                        $sucesso = "Estado da candidatura atualizado com sucesso!";
                    }
                    break;
                    
                case 'adicionar_nota':
                    $nota = trim($_POST['nota_interna']);
                    if (!empty($nota)) {
                        $stmt = $pdo->prepare("UPDATE candidatura SET nota_interna = ? WHERE id = ?");
                        $stmt->execute([$nota, $candidato_id]);
                        $sucesso = "Nota interna adicionada com sucesso!";
                    }
                    break;
            }
        } catch (PDOException $e) {
            $erro = "Erro ao processar ação: " . $e->getMessage();
        }
    } else {
        $erro = "Candidatura não encontrada ou não pertence à sua empresa.";
    }
}

// Buscar candidaturas para a vaga com JOIN correto
 $sql_candidaturas = "
    SELECT 
        c.*,
        cand.nome_completo,
        cand.telefone,
        cand.localizacao,
        cand.competencias,
        cand.cv_pdf,
        cand.foto_perfil,
        cand.id as candidato_id,
        u.email,
        (SELECT COUNT(*) FROM experiencia e WHERE e.candidato_id = cand.id) as total_experiencias,
        (SELECT COUNT(*) FROM formacao f WHERE f.candidato_id = cand.id) as total_formacoes
    FROM candidatura c
    JOIN candidato cand ON c.candidato_id = cand.id
    JOIN utilizador u ON cand.id = u.id
    WHERE c.vaga_id = ?
    ORDER BY 
        CASE c.estado 
            WHEN 'submetida' THEN 1
            WHEN 'em_analise' THEN 2
            WHEN 'entrevista' THEN 3
            WHEN 'rejeitada' THEN 4
            WHEN 'contratado' THEN 5
        END,
        c.data_candidatura DESC
";

 $stmt_candidaturas = $pdo->prepare($sql_candidaturas);
 $stmt_candidaturas->execute([$vaga_id]);
 $candidaturas = $stmt_candidaturas->fetchAll();

// Estatísticas por estado
 $estatisticas_estado = [
    'submetida' => 0,
    'em_analise' => 0,
    'entrevista' => 0,
    'rejeitada' => 0,
    'contratado' => 0
];

foreach ($candidaturas as $candidatura) {
    $estatisticas_estado[$candidatura['estado']]++;
}

// Estados disponíveis
 $estados = [
    'submetida' => ['label' => 'Submetida', 'cor' => '#3498db'],
    'em_analise' => ['label' => 'Em Análise', 'cor' => '#f39c12'],
    'entrevista' => ['label' => 'Entrevista', 'cor' => '#9b59b6'],
    'rejeitada' => ['label' => 'Foi rejeitada', 'cor' => '#e74c3c'],
    'contratado' => ['label' => 'Contratado', 'cor' => '#27ae60']
];

// Estatísticas por estado
 $sql_estatisticas = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'submetida' THEN 1 ELSE 0 END) as submetidas,
        SUM(CASE WHEN estado = 'em_analise' THEN 1 ELSE 0 END) as em_analise,
        SUM(CASE WHEN estado = 'entrevista' THEN 1 ELSE 0 END) as entrevistas,
        SUM(CASE WHEN estado = 'rejeitada' THEN 1 ELSE 0 END) as rejeitadas,
        SUM(CASE WHEN estado = 'contratado' THEN 1 ELSE 0 END) as contratados
    FROM candidatura c
    WHERE c.vaga_id = ?
";

 $stmt_estatisticas = $pdo->prepare($sql_estatisticas);
 $stmt_estatisticas->execute([$vaga_id]);
 $estatisticas = $stmt_estatisticas->fetch();

 $total_candidaturas = count($candidaturas);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidaturas - <?php echo htmlspecialchars($vaga['titulo']); ?> - Emprego MZ</title>
    
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
            background: var(--gradient-por-do-sol);
            color: var(--branco-puro);
            padding: var(--space-xl) var(--space-md);
            text-align: center;
            position: relative;
            overflow: hidden;
            min-height: 400px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
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
        }

        .hero h1 {
            font-family: var(--font-heading);
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 700;
            margin-bottom: var(--space-md);
            text-shadow: 0 2px 12px rgba(0,0,0,0.15);
            animation: slideDown 0.8s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-md);
        }

        .main-content {
            padding: var(--space-xl) 0;
        }

        /* ============================================
           📋 ESTATS GRID
        ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: var(--space-md);
            margin: var(--space-lg) 0;
        }

        .stat-card {
            background: var(--branco-puro);
            border-radius: 16px;
            padding: var(--space-md);
            box-shadow: var(--shadow-medium);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-number {
            font-family: var(--font-heading);
            font-size: 2rem;
            font-weight: 700;
            display: block;
            background: var(--gradient-por-do-sol);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--cinza-baobab);
        }

        /* ============================================
           📋 CANDIDATURAS SECTION
        ============================================ */
        .candidaturas-section {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-xl);
            box-shadow: var(--shadow-medium);
            margin-bottom: var(--space-xl);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }

        /* Padrão Capulana muito sutil na seção */
        .candidaturas-section::before {
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

        /* ============================================
           🎴 FILTROS
        ============================================ */
        .filtros {
            background: var(--branco-puro);
            border-radius: 16px;
            padding: var(--space-md);
            box-shadow: var(--shadow-soft);
            margin-bottom: var(--space-lg);
        }

        .filtros h3 {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            color: var(--carvao);
            margin: 0 0 var(--space-sm);
        }

        .filtro-buttons {
            display: flex;
            gap: var(--space-sm);
            flex-wrap: wrap;
            justify-content: center;
        }

        .filtro-btn {
            padding: var(--space-sm) var(--space-md);
            border: 1px solid var(--areia-quente);
            background: var(--branco-puro);
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            color: var(--carvao);
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .filtro-btn.active {
            background: var(--gradient-oceano);
            color: var(--branco-puro);
            border-color: var(--azul-indico);
        }

        .filtro-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        /* ============================================
           🎴 CANDIDATURA CARD
        ============================================ */
        .candidatura-card {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: var(--space-md);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }

        /* Padrão Capulana muito sutil no card */
        .candidatura-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: 
                repeating-linear-gradient(45deg, transparent, transparent 20px, rgba(43, 122, 75, 0.02) 20px, rgba(43,  122, 75, 0.02) 40px);
            opacity: 0;
            transition: opacity 0.4s ease;
            pointer-events: none;
        }

        .candidatura-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--dourado-sol);
        }

        .candidatura-card:hover::before {
            opacity: 1;
        }

        .candidato-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-md);
        }

        .candidato-info {
            flex: 1;
            min-width: 0;
        }

        .candidato-name {
            font-family: var(--font-heading);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--carvao);
            margin: 0 0 var(--space-xs);
            line-height: 1.3;
        }

        .candidato-meta {
            color: var(--cinza-baobab);
            font-size: 0.9rem;
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-xs);
        }

        .candidato-meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        /* ============================================
           🎭 ESTADO BADGES
        ============================================ */
        .estado-badge {
            padding: var(--space-xs) var(--space-sm);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .status-submetida {
            background: rgba(52, 152, 219, 0.1);
            color: var(--azul-indico);
        }

        .status-em_analise {
            background: rgba(243, 176, 59, 0.1);
            color: #cc8a2e;
        }

        .status-entrevista {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .status-rejeitada {
            background: rgba(255, 107, 107, 0.1);
            color: var(--coral-vivo);
        }

        .status-contratado {
            background: rgba(39, 174, 96, 0.1);
            color: var(--verde-esperanca);
        }

        /* ============================================
           📝 CARTA DE APRESENTAÇÃO
        ============================================ */
        .carta-apresentacao {
            background: var(--areia-quente);
            border-radius: 12px;
            padding: var(--space-md);
            margin: var(--space-md) 0 0 0;
        }

        .carta-label {
            font-weight: 600;
            color: var(--carvao);
            margin-bottom: var(--space-xs);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .candidatura-text {
            color: var(--carvao);
            line-height: 1.5;
        }

        /* ============================================
           🔘 BOTÕES - Sistema de Botões
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

        .btn-warning {
            background: var(--dourado-sol);
            color: var(--carvao);
        }

        .btn-danger {
            background: var(--coral-vivo);
            color: var(--branco-puro);
        }

        .btn-secondary {
            background: var(--cinza-baobab);
            color: var(--branco-puro);
        }

        .btn-purple {
            background: #9b59b6;
            color: var(--branco-puro);
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
           🎭 EMPTY STATE
        ============================================ */
        .empty-state {
            text-align: center;
            padding: var(--space-xl);
            background: var(--branco-puro);
            border-radius: 20px;
            box-shadow: var(--shadow-medium);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: var(--space-md);
            color: var(--cinza-baobab);
        }

        .empty-state h3 {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            color: var(--carvao);
            margin-bottom: var(--space-sm);
        }

        .empty-state p {
            color: var(--cinza-baobab);
            font-size: 1.1rem;
        }

        /* ============================================
           📱 RESPONSIVE DESIGN
        ============================================ */
        @media (max-width: 768px) {
            .hero {
                padding: var(--space-lg) var(--space-md);
                min-height: 300px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-md);
            }

            .candidatura-header {
                flex-direction: column;
                gap: var(--space-sm);
            }

            .candidato-info {
                text-align: center;
            }

            .candidato-meta {
                justify-content: center;
            }

            .dropdown {
                width: 100%;
            }

            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="hero">
        <div class="hero-content">
            <h1>👥 Candidaturas Recebidas</h1>
            <p>Encontre os melhores talentos para sua empresa em toda Moçambique</p>
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

    <!-- 📋 CANDIDATURAS SECTION -->
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
                <span>Candidaturas</span>
            </nav>

            <!-- Mensagens de Sucesso/Erro -->
            <?php if ($sucesso): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" style="width: 20px; height: 20px; margin-top: 2px;"></i>
                    <div><?php echo htmlspecialchars($sucesso); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle" style="width: 20px; height: 20px; margin-top: 2px;"></i>
                    <div><?php echo htmlspecialchars($erro); ?></div>
                </div>
            <?php endif; ?>

            <!-- Estatísticas por Estado -->
            <div class="stats-grid">
                <?php foreach ($estados as $key => $estado): ?>
                    <div class="stat-card">
                        <span class="stat-number" style="color: <?php echo $estado['cor']; ?>">
                            <?php echo $estatisticas_estado[$key]; ?>
                        </span>
                        <span class="stat-label"><?php echo $estado['label']; ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="stat-card">
                    <span class="stat-number" style="color: var(--carvao);">
                        <?php echo count($candidaturas); ?>
                    </span>
                    <span class="stat-label">Total</span>
                </div>
            </div>

            <!-- Filtros por Estado -->
            <div class="filtros">
                <h3 style="margin: 0 0 10px 0;">🔍 Filtrar por Estado:</h3>
                <div class="filtro-buttons">
                    <a href="?vaga_id=<?php echo $vaga_id; ?>" class="filtro-btn <?php echo !isset($_GET['estado']) ? 'active' : ''; ?>">
                        Todos (<?php echo count($candidaturas); ?>)
                    </a>
                    <?php foreach ($estados as $key => $estado): ?>
                        <a href="?vaga_id=<?php echo $vaga_id; ?>&estado=<?php echo $key; ?>" 
                           class="filtro-btn <?php echo (isset($_GET['estado']) && $_GET['estado'] === $key ? 'active' : ''); ?>">
                            <?php echo $estado['label']; ?> (<?php echo $estatisticas_estado[$key]; ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Lista de Candidaturas -->
            <?php 
            // Aplicar filtro se especificado
            $candidaturas_filtradas = $candidaturas;
            if (isset($_GET['estado']) && array_key_exists($_GET['estado'], $estados)) {
                $candidaturas_filtradas = array_filter($candidaturas, function($c) {
                    return $c['estado'] === $_GET['estado'];
                });
            }
            ?>

            <?php if (empty($candidaturas_filtradas)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i data-lucide="inbox" style="width: 64px; height: 64px;"></i>
                    </div>
                    <h3>📭 Nenhuma candidatura encontrada</h3>
                    <p>
                        <?php if (isset($_GET['estado'])): ?>
                            Não há candidaturas com o estado "<?php echo $estados[$_GET['estado']]['label']; ?>".
                        <?php else: ?>
                            Esta vaga ainda não recebeu nenhuma candidatura.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="candidatura-grid">
                    <?php foreach ($candidaturas_filtradas as $candidato): ?>
                        <div class="candidatura-card">
                            <div class="candidato-header">
                                <div class="candidato-info">
                                    <div class="candidato-header">
                                        <?php if ($candidato['foto_perfil']): ?>
                                            <img src="../uploads/fotos/<?php echo htmlspecialchars($candidato['foto_perfil']); ?>" 
                                                 alt="Foto de perfil" class="candidato-foto">
                                        <?php else: ?>
                                            <div style="width: 60px; height: 60px; border-radius: 50%; background: var(--gradient-terra); 
                                                display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-right: 15px;">
                                                <i data-lucide="user" style="width: 30px; height: 30px; color: var(--azul-indico);"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div style="flex: 1;">
                                            <h3 class="candidato-name"><?php echo htmlspecialchars($candidato['nome_completo']); ?></h3>
                                            <div class="candidato-meta">
                                                ✉️ <?php echo htmlspecialchars($candidato['email']); ?>
                                                <?php if ($candidato['telefone']): ?>
                                                    📞 <?php echo htmlspecialchars($candidato['telefone']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="candidato-meta">
                                        📍 <?php echo htmlspecialchars($candidato['localizacao']); ?>
                                        💼 <?php echo $candidato['total_experiencias']; ?> experiências
                                        🎓 <?php echo $candidato['total_formacoes']; ?> formações
                                    </div>
                                    
                                    <div class="candidato-meta">
                                        📅 <?php echo date('d/m/Y H:i', strtotime($candidato['data_candidatura'])); ?>
                                    </div>
                                </div>

                                <?php if ($candidato['carta_apresentacao']): ?>
                                    <div class="carta-apresentacao">
                                        <div class="carta-label">
                                            <i data-lucide="message-square" style="width: 16px; height: 16px;"></i>
                                            Carta de Apresentação
                                        </div>
                                        <div class="candidatura-text">
                                            <?php echo nl2br(htmlspecialchars($candidato['carta_apresentacao'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($candidato['nota_interna']): ?>
                                    <div class="nota-interna">
                                        <div class="carta-label">
                                            <i data-lucide="message-square" style="width: 16px; height: 16px;"></i>
                                            Nota Interna
                                        </div>
                                        <div class="candidatura-text">
                                            <?php echo nl2br(htmlspecialchars($candidato['nota_interna'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div style="text-align: right;">
                                <span class="estado-badge" style="background: <?php echo $estados[$candidato['estado']]['cor']; ?>">
                                    <?php echo $estados[$candidato['estado']]['label']; ?>
                                </span>
                            </div>

                            <!-- Ações -->
                            <div class="acoes">
                                <?php if ($candidato['cv_pdf']): ?>
                                    <a href="../uploads/cv/<?php echo htmlspecialchars($candidato['cv_pdf']); ?>" 
                                           target="_blank" class="btn btn-primary">
                                        <i data-lucide="file-text" style="width: 16px; height: 16px;"></i>
                                        Ver CV
                                    </a>
                                <?php endif; ?>

                                <a href="ver_candidato.php?id=<?php echo $candidato['candidato_id']; ?>" 
                                   class="btn btn-purple">
                                    <i data-lucide="user" style="width: 16px; height: 16px;"></i>
                                    Ver Perfil Completo
                                </a>

                                <div class="dropdown" style="display: inline-block;">
                                    <button class="btn btn-warning">🔄 Alterar Estado</button>
                                    <div class="dropdown-content">
                                        <form method="POST">
                                            <input type="hidden" name="candidato_id" value="<?php echo $candidato['candidato_id']; ?>">
                                            <input type="hidden" name="acao" value="mudar_estado">
                                            <div class="form-group">
                                                <label>Novo Estado:</label>
                                                <select name="estado" required>
                                                    <?php foreach ($estados as $key => $estado): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo ($candidato['estado'] == $key) ? 'selected' : ''; ?>>
                                                            <?php echo $estado['label']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-success" style="width: 100%;">Aplicar</button>
                                        </form>
                                    </div>
                                </div>

                                <div class="dropdown" style="display: inline-block;">
                                    <button class="btn btn-secondary">📝 Nota Interna</button>
                                    <div class="dropdown-content">
                                        <form method="POST">
                                            <input type="hidden" name="candidato_id" value="<?php echo $candidato['candidato_id']; ?>">
                                            <input type="hidden" name="acao" value="adicionar_nota">
                                            <div class="form-group">
                                                <label>Nota Interna:</label>
                                                <textarea name="nota_interna" placeholder="Adicione uma observação interna..." 
                                                          rows="3"><?php echo htmlspecialchars($candidato['nota_interna'] ?? ''); ?></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-success" style="width: 100%;">Salvar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 🌊 OCEAN DIVIDER -->
    <div class="ocean-divider">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none" transform="rotate(180)">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                  style="fill: url(#gradient-oceano); opacity: 0.8;"></path>
        </svg>
    </div>

    <!-- 📱 NAVIGATION -->
    <nav class="navigation">
        <div class="container">
            <div class="nav-links">
                <a href="../index.php">
                    <i data-lucide="home" style="width: 16px; height: 16px;"></i>
                    Início
                </a>
                <span style="color: var(--cinza-baobab);">|</span>
                <a href="dashboard.php">
                    <i data-lucide="bar-chart" style="width: 16px; height: 16px;"></i>
                    Dashboard
                </a>
                <span style="color: var(--cinza-baobab);">|</span>
                <a href="dashboard.php">
                    <i data-lucide="users" style="width: 16px; height: 16px;"></i>
                    Candidaturas
                </a>
            </div>
            
            <div class="footer">
                <div style="display: flex; justify-content: center; gap: var(--space-md); flex-wrap: wrap;">
                    <a href="../index.php">
                        <i data-lucide="globe" style="width: 16px; height: 16px;"></i>
                        Início
                    </a>
                    <span style="color: var(--cinza-baobab);">|</span>
                    <a href="../sobre.php">
                        <i data-lucide="info" style="width: 16px; height: 16px;"></i>
                        Sobre Nós
                    </a>
                    <span style="color: var(--cinza-baobab);">|</span>
                    <a href="../contato.php">
                        <i data-lucide="phone" style="width: 16px; height: 16px;"></i>
                        Contato
                    </a>
                </div>
                
                <div style="text-align: center; color: var(--cinza-baobab); font-size: 0.9rem; padding-top: var(--space-md); border-top: 1px solid var(--areia-quente);">
                    &copy; <?php echo date('Y'); ?> Emprego MZ - Conectando Moçambique ao Futuro
                </div>
            </div>
        </div>
    </nav>

    <!-- ✨ MICRO-INTERACTION SCRIPT -->
    <script>
        // Inicializar ícones Lucide
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            
            // Fechar dropdowns ao clicar fora
            document.addEventListener('click', function(event) {
                if (!event.target.matches('.dropdown *')) {
                    var dropdowns = document.getElementsByClassName("dropdown-content");
                    for (var i = 0; i < dropdowns.length; i++) {
                        var openDropdown = dropdowns[i];
                        if (openDropdown.style.display === 'block') {
                            openDropdown.style.display = 'none';
                        }
                    }
                }
            });
            
            // Animação de entrada dos cards
            const animateElements = document.querySelectorAll('.candidatura-card');
            
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 100);
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            animateElements.forEach(element => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(element);
            });
            
            // Animação de entrada para o header
            const hero = document.querySelector('.hero');
            hero.style.opacity = '0';
            hero.style.transform = 'translateY(30px)';
            hero.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
            
            setTimeout(() => {
                hero.style.opacity = '1';
                hero.style.transform = 'translateY(0)';
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
    </script>
</body>
</html>