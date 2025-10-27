<?php
session_start();
require_once '../config/db.php';

// Verificar se o usuário está logado e é um candidato
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidato') {
    header("Location: ../auth/login.php");
    exit;
}

 $pdo = getPDO();
 $candidato_id = $_SESSION['user_id'];
 $sucesso = '';
 $erro = '';

// Buscar dados do candidato
 $sql_candidato = "SELECT nome_completo FROM candidato WHERE id = ?";
 $stmt_candidato = $pdo->prepare($sql_candidato);
 $stmt_candidato->execute([$candidato_id]);
 $candidato = $stmt_candidato->fetch();

// Processar cancelamento de candidatura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_candidatura'])) {
    $candidatura_id = (int)$_POST['candidatura_id'];
    
    try {
        // Verificar se a candidatura pertence ao candidato
        $stmt_verificar = $pdo->prepare("SELECT id, estado FROM candidatura WHERE id = ? AND candidato_id = ?");
        $stmt_verificar->execute([$candidatura_id, $candidato_id]);
        $candidatura = $stmt_verificar->fetch();
        
        if ($candidatura) {
            // Só permitir cancelar se estiver em estados iniciais
            if (in_array($candidatura['estado'], ['submetida', 'em_analise'])) {
                $stmt_cancelar = $pdo->prepare("DELETE FROM candidatura WHERE id = ?");
                $stmt_cancelar->execute([$candidatura_id]);
                $sucesso = "Candidatura cancelada com sucesso!";
            } else {
                $erro = "Não é possível cancelar candidaturas em estágios avançados.";
            }
        } else {
            $erro = "Candidatura não encontrada.";
        }
    } catch (PDOException $e) {
        $erro = "Erro ao cancelar candidatura: " . $e->getMessage();
    }
}

// Filtros
 $filtro_estado = $_GET['estado'] ?? '';
 $filtro_pesquisa = $_GET['pesquisa'] ?? '';

// Construir query base para candidaturas
 $sql_candidaturas = "
    SELECT 
        c.*,
        v.titulo,
        v.empresa_id,
        v.localizacao as localizacao_vaga,
        v.area,
        v.tipo_contrato,
        v.modalidade,
        v.salario_estimado,
        v.data_expiracao,
        v.ativa as vaga_ativa,
        e.nome_empresa,
        e.logotipo
    FROM candidatura c
    JOIN vaga v ON c.vaga_id = v.id
    JOIN empresa e ON v.empresa_id = e.id
    WHERE c.candidato_id = ?
";

 $params = [$candidato_id];

// Aplicar filtros
if (!empty($filtro_estado) && $filtro_estado !== 'todos') {
    $sql_candidaturas .= " AND c.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_pesquisa)) {
    $sql_candidaturas .= " AND (v.titulo LIKE ? OR e.nome_empresa LIKE ? OR v.area LIKE ?)";
    $termo_pesquisa = "%$filtro_pesquisa%";
    $params[] = $termo_pesquisa;
    $params[] = $termo_pesquisa;
    $params[] = $termo_pesquisa;
}

 $sql_candidaturas .= " ORDER BY c.data_candidatura DESC";

// Executar query
 $stmt_candidaturas = $pdo->prepare($sql_candidaturas);
 $stmt_candidaturas->execute($params);
 $candidaturas = $stmt_candidaturas->fetchAll();

// Estatísticas
 $sql_estatisticas = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'submetida' THEN 1 ELSE 0 END) as submetidas,
        SUM(CASE WHEN estado = 'em_analise' THEN 1 ELSE 0 END) as em_analise,
        SUM(CASE WHEN estado = 'entrevista' THEN 1 ELSE 0 END) as entrevistas,
        SUM(CASE WHEN estado = 'rejeitada' THEN 1 ELSE 0 END) as rejeitadas,
        SUM(CASE WHEN estado = 'contratado' THEN 1 ELSE 0 END) as contratados
    FROM candidatura 
    WHERE candidato_id = ?
";

 $stmt_estatisticas = $pdo->prepare($sql_estatisticas);
 $stmt_estatisticas->execute([$candidato_id]);
 $estatisticas = $stmt_estatisticas->fetch();

// Estados disponíveis
 $estados = [
    'todos' => ['label' => 'Todas', 'cor' => 'var(--carvao)'],
    'submetida' => ['label' => 'Submetidas', 'cor' => 'var(--azul-indico)'],
    'em_analise' => ['label' => 'Em Análise', 'cor' => 'var(--dourado-sol)'],
    'entrevista' => ['label' => 'Entrevista', 'cor' => '#9b59b6'],
    'rejeitada' => ['label' => 'Rejeitadas', 'cor' => 'var(--coral-vivo)'],
    'contratado' => ['label' => 'Contratados', 'cor' => 'var(--verde-esperanca)']
];

 $status_labels = [
    'submetida' => 'Submetida',
    'em_analise' => 'Em Análise',
    'entrevista' => 'Entrevista',
    'rejeitada' => 'Rejeitada',
    'contratado' => 'Contratado'
];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Candidaturas - Emprego MZ</title>
    
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-md);
        }

        .main-content {
            padding: var(--space-xl) 0;
        }

        /* ============================================
           📊 STATS GRID
        ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        .stat-card {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-lg);
            box-shadow: var(--shadow-medium);
            text-align: center;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: inherit;
        }

        .stat-card::before {
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

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--dourado-sol);
        }
        
        .stat-card.active {
            border-color: var(--dourado-sol);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--gradient-terra);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-md);
            box-shadow: var(--shadow-soft);
        }

        .stat-number {
            font-family: var(--font-heading);
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--gradient-por-do-sol);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
            margin-bottom: var(--space-xs);
        }

        .stat-label {
            color: var(--cinza-baobab);
            font-size: 1rem;
            font-weight: 500;
        }

        /* ============================================
           🎴 XIMA CARDS
        ============================================ */
        .section {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-lg);
            box-shadow: var(--shadow-medium);
            margin-bottom: var(--space-lg);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .section::before {
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

        .section:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--dourado-sol);
        }

        .section-title {
            font-family: var(--font-heading);
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--carvao);
            margin-bottom: var(--space-md);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .item-card {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-lg);
            box-shadow: var(--shadow-medium);
            margin-bottom: var(--space-lg);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .item-card::before {
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

        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--dourado-sol);
        }
        
        .item-card.vaga-expirada {
            opacity: 0.7;
            background: var(--areia-quente);
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-md);
        }

        .item-title {
            font-family: var(--font-heading);
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--carvao);
            margin-bottom: var(--space-xs);
            line-height: 1.3;
        }

        .item-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s;
        }

        .item-title a:hover {
            color: var(--verde-esperanca);
        }

        .item-subtitle {
            color: var(--azul-indigo);
            font-weight: 600;
            margin-bottom: var(--space-xs);
        }

        .item-meta {
            color: var(--cinza-baobab);
            font-size: 0.9rem;
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-md);
            margin-bottom: var(--space-xs);
        }
        
        .item-meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
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

        .btn-warning {
            background: var(--dourado-sol);
            color: var(--carvao);
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-danger {
            background: var(--coral-vivo);
            color: var(--branco-puro);
        }

        .btn-danger:hover {
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

        .btn-group {
            display: flex;
            gap: var(--space-sm);
            flex-wrap: wrap;
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

        .alert-error {
            background: rgba(255, 107, 107, 0.1);
            color: var(--coral-vivo);
            border-left: 4px solid var(--coral-vivo);
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
            margin-bottom: var(--space-lg);
        }

        /* ============================================
           📱 NAVIGATION & FORMS
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
            color: var(--azul-indigo);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            transition: color 0.3s;
        }

        .breadcrumb a:hover {
            color: var(--verde-esperanca);
        }

        .form-group {
            margin-bottom: var(--space-md);
        }

        label {
            display: block;
            margin-bottom: var(--space-xs);
            font-weight: 600;
            color: var(--carvao);
        }

        input, select, textarea {
            width: 100%;
            padding: var(--space-sm);
            border: 2px solid var(--areia-quente);
            border-radius: 12px;
            font-size: 1rem;
            font-family: var(--font-body);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--dourado-sol);
            box-shadow: 0 0 0 3px rgba(255, 176, 59, 0.2);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: var(--space-md);
            align-items: end;
        }

        .candidatura-detalhes {
            margin-top: var(--space-md);
            padding: var(--space-md);
            background: var(--areia-quente);
            border-radius: 16px;
        }
        
        .carta-apresentacao {
            margin-top: var(--space-sm);
            padding: var(--space-sm);
            background: var(--branco-puro);
            border-radius: 12px;
            border-left: 4px solid var(--azul-indigo);
        }
        
        .acoes {
            margin-top: var(--space-md);
            padding-top: var(--space-md);
            border-top: 1px solid var(--areia-quente);
        }

        .estado-badge {
            padding: var(--space-xs) var(--space-sm);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--branco-puro);
        }
        
        .expirada-badge {
            background: var(--cinza-baobab);
            color: var(--branco-puro);
            padding: var(--space-xs) var(--space-sm);
            border-radius: 20px;
            font-size: 0.8em;
            margin-left: var(--space-sm);
        }
        
        .salario {
            font-weight: bold;
            color: var(--verde-esperanca);
        }

        /* ============================================
           📱 RESPONSIVE DESIGN
        ============================================ */
        @media (max-width: 768px) {
            .hero {
                padding: var(--space-lg) var(--space-md);
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-md);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .item-header {
                flex-direction: column;
                gap: var(--space-sm);
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
        .btn:focus, .breadcrumb a:focus {
            outline: 3px solid var(--dourado-sol);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <!-- 🌅 HERO SECTION -->
    <div class="hero">
        <div class="hero-content">
            <h1>Minhas Candidaturas</h1>
            <p>Acompanhe o status de todas as suas candidaturas e encontre novas oportunidades.</p>
        </div>
    </div>

    <!-- 🌊 OCEAN DIVIDER -->
    <div class="ocean-divider">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                  style="fill: var(--gradient-oceano); opacity: 0.8;"></path>
        </svg>
    </div>

    <!-- 📊 DASHBOARD CONTENT -->
    <div class="main-content">
        <div class="container">
            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <a href="../index.php">
                    <i data-lucide="home" style="width: 16px; height: 16px;"></i>
                    Início
                </a>
                <span>/</span>
                <a href="perfil.php">
                    <i data-lucide="user" style="width: 16px; height: 16px;"></i>
                    Meu Perfil
                </a>
                <span>/</span>
                <span>Minhas Candidaturas</span>
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

            <!-- Estatísticas -->
            <div class="stats-grid">
                <?php foreach ($estados as $key => $estado): ?>
                    <a href="?estado=<?php echo $key; ?><?php echo $filtro_pesquisa ? '&pesquisa=' . urlencode($filtro_pesquisa) : ''; ?>" 
                       class="stat-card <?php echo ($filtro_estado === $key || ($key === 'todos' && empty($filtro_estado))) ? 'active' : ''; ?>">
                        <div class="stat-icon">
                            <i data-lucide="<?php 
                                echo match($key) {
                                    'todos' => 'layers',
                                    'submetida' => 'send',
                                    'em_analise' => 'search',
                                    'entrevista' => 'users',
                                    'rejeitada' => 'x-circle',
                                    'contratado' => 'check-circle'
                                }; 
                            ?>" style="width: 30px; height: 30px; color: var(--carvao);"></i>
                        </div>
                        <div class="stat-number"><?php 
                            echo match($key) {
                                'todos' => $estatisticas['total'],
                                'submetida' => $estatisticas['submetidas'],
                                'em_analise' => $estatisticas['em_analise'],
                                'entrevista' => $estatisticas['entrevistas'],
                                'rejeitada' => $estatisticas['rejeitadas'],
                                'contratado' => $estatisticas['contratados']
                            };
                        ?></div>
                        <div class="stat-label"><?php echo $estado['label']; ?></div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Filtros -->
            <div class="section">
                <h2 class="section-title">
                    <i data-lucide="filter" style="width: 24px; height: 24px;"></i>
                    Filtrar Candidaturas
                </h2>
                <form method="GET">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="pesquisa">Pesquisar vagas ou empresas</label>
                            <input type="text" id="pesquisa" name="pesquisa" 
                                   value="<?php echo htmlspecialchars($filtro_pesquisa); ?>"
                                   placeholder="Título da vaga, nome da empresa ou área...">
                        </div>
                        
                        <div class="form-group">
                            <label for="estado">Estado da Candidatura</label>
                            <select id="estado" name="estado">
                                <?php foreach ($estados as $key => $estado): ?>
                                    <option value="<?php echo $key; ?>" 
                                        <?php echo $filtro_estado === $key ? 'selected' : ''; ?>>
                                        <?php echo $estado['label']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i data-lucide="search" style="width: 18px; height: 18px;"></i>
                                Aplicar Filtros
                            </button>
                            <?php if ($filtro_estado || $filtro_pesquisa): ?>
                                <a href="candidaturas.php" class="btn btn-secondary" style="display: inline-block; margin-top: var(--space-sm);">
                                    <i data-lucide="x" style="width: 18px; height: 18px;"></i>
                                    Limpar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Lista de Candidaturas -->
            <div class="section">
                <h2 class="section-title">
                    <i data-lucide="list" style="width: 24px; height: 24px;"></i>
                    <?php 
                    if ($filtro_estado && $filtro_estado !== 'todos') {
                        echo $estados[$filtro_estado]['label'] . ' (' . count($candidaturas) . ')';
                    } else {
                        echo 'Todas as Candidaturas (' . count($candidaturas) . ')';
                    }
                    ?>
                </h2>

                <?php if (empty($candidaturas)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i data-lucide="inbox" style="width: 64px; height: 64px;"></i>
                        </div>
                        <h3>Nenhuma candidatura encontrada</h3>
                        <p>
                            <?php if ($filtro_estado || $filtro_pesquisa): ?>
                                Não foram encontradas candidaturas com os filtros aplicados.
                                <br>
                                <a href="candidaturas.php" style="color: var(--azul-indigo);">Ver todas as candidaturas</a>
                            <?php else: ?>
                                Você ainda não se candidatou a nenhuma vaga.
                                <br>
                                Explore as oportunidades disponíveis e candidate-se às vagas que combinam com seu perfil.
                            <?php endif; ?>
                        </p>
                        <a href="../vagas.php" class="btn btn-success">
                            <i data-lucide="search" style="width: 18px; height: 18px;"></i>
                            Explorar Vagas Disponíveis
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($candidaturas as $candidatura): 
                        $vaga_expirada = strtotime($candidatura['data_expiracao']) < time() || !$candidatura['vaga_ativa'];
                    ?>
                        <div class="item-card <?php echo $vaga_expirada ? 'vaga-expirada' : ''; ?>">
                            <div class="item-header">
                                <div class="vaga-info" style="flex: 1;">
                                    <h3 class="item-title">
                                        <a href="../vaga_detalhe.php?id=<?php echo $candidatura['vaga_id']; ?>">
                                            <?php echo htmlspecialchars($candidatura['titulo']); ?>
                                        </a>
                                        <?php if ($vaga_expirada): ?>
                                            <span class="expirada-badge">⏰ Vaga Expirada</span>
                                        <?php endif; ?>
                                    </h3>
                                    
                                    <div class="item-subtitle">
                                        <i data-lucide="building" style="width: 16px; height: 16px;"></i>
                                        <?php echo htmlspecialchars($candidatura['nome_empresa']); ?>
                                    </div>
                                    
                                    <div class="item-meta">
                                        <div class="item-meta-item">
                                            <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i>
                                            <?php echo htmlspecialchars($candidatura['localizacao_vaga']); ?>
                                        </div>
                                        <div class="item-meta-item">
                                            <i data-lucide="briefcase" style="width: 16px; height: 16px;"></i>
                                            <?php echo htmlspecialchars($candidatura['area']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="item-meta">
                                        <div class="item-meta-item">
                                            <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                                            <?php 
                                                echo [
                                                    'tempo_inteiro' => 'Tempo Inteiro',
                                                    'tempo_parcial' => 'Tempo Parcial', 
                                                    'estagio' => 'Estágio',
                                                    'freelance' => 'Freelance'
                                                ][$candidatura['tipo_contrato']] ?? $candidatura['tipo_contrato'];
                                            ?>
                                        </div>
                                        <div class="item-meta-item">
                                            <i data-lucide="monitor" style="width: 16px; height: 16px;"></i>
                                            <?php 
                                                echo [
                                                    'presencial' => 'Presencial',
                                                    'hibrido' => 'Híbrido',
                                                    'remoto' => 'Remoto'
                                                ][$candidatura['modalidade']] ?? $candidatura['modalidade'];
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($candidatura['salario_estimado']): ?>
                                        <div class="item-meta salario">
                                            <i data-lucide="dollar-sign" style="width: 16px; height: 16px;"></i>
                                            Salário: <?php echo number_format($candidatura['salario_estimado'], 0, ',', ' '); ?> MT
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="item-meta">
                                        <i data-lucide="send" style="width: 16px; height: 16px;"></i>
                                        Candidatou-se em: <?php echo date('d/m/Y à\s H:i', strtotime($candidatura['data_candidatura'])); ?>
                                    </div>
                                </div>
                                
                                <div style="text-align: right;">
                                    <span class="estado-badge" style="background: <?php echo $estados[$candidatura['estado']]['cor']; ?>;">
                                        <?php echo $status_labels[$candidatura['estado']]; ?>
                                    </span>
                                    <div style="margin-top: var(--space-sm); font-size: 0.8em; color: var(--cinza-baobab);">
                                        <i data-lucide="calendar" style="width: 14px; height: 14px;"></i>
                                        Expira: <?php echo date('d/m/Y', strtotime($candidatura['data_expiracao'])); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Detalhes da Candidatura -->
                            <div class="candidatura-detalhes">
                                <?php if ($candidatura['carta_apresentacao']): ?>
                                    <div class="carta-apresentacao">
                                        <strong style="display: flex; align-items: center; gap: var(--space-xs);">
                                            <i data-lucide="file-text" style="width: 18px; height: 18px;"></i>
                                            Sua Carta de Apresentação:
                                        </strong>
                                        <p style="margin: var(--space-sm) 0 0 0; line-height: 1.5;">
                                            <?php echo nl2br(htmlspecialchars($candidatura['carta_apresentacao'])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($candidatura['nota_interna']): ?>
                                    <div style="margin-top: var(--space-sm); padding: var(--space-sm); background: var(--branco-puro); border-radius: 12px; border-left: 4px solid var(--dourado-sol);">
                                        <strong style="display: flex; align-items: center; gap: var(--space-xs);">
                                            <i data-lucide="message-square" style="width: 18px; height: 18px;"></i>
                                            Observação da Empresa:
                                        </strong>
                                        <p style="margin: var(--space-sm) 0 0 0; color: var(--carvao);">
                                            <?php echo nl2br(htmlspecialchars($candidatura['nota_interna'])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Ações -->
                            <div class="acoes">
                                <a href="../vaga_detalhe.php?id=<?php echo $candidatura['vaga_id']; ?>" class="btn btn-primary">
                                    <i data-lucide="eye" style="width: 16px; height: 16px;"></i>
                                    Ver Detalhes da Vaga
                                </a>
                                
                                <?php if (in_array($candidatura['estado'], ['submetida', 'em_analise']) && !$vaga_expirada): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="candidatura_id" value="<?php echo $candidatura['id']; ?>">
                                        <button type="submit" name="cancelar_candidatura" class="btn btn-danger"
                                                onclick="return confirm('Tem certeza que deseja cancelar esta candidatura?')">
                                            <i data-lucide="x-circle" style="width: 16px; height: 16px;"></i>
                                            Cancelar Candidatura
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($candidatura['estado'] === 'rejeitada'): ?>
                                    <span style="color: var(--coral-vivo); font-size: 0.9em; display: inline-flex; align-items: center; gap: var(--space-xs);">
                                        <i data-lucide="x-circle" style="width: 16px; height: 16px;"></i>
                                        Esta candidatura foi rejeitada pela empresa
                                    </span>
                                <?php elseif ($candidatura['estado'] === 'contratado'): ?>
                                    <span style="color: var(--verde-esperanca); font-size: 0.9em; font-weight: bold; display: inline-flex; align-items: center; gap: var(--space-xs);">
                                        <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i>
                                        Parabéns! Você foi contratado para esta vaga
                                    </span>
                                <?php elseif ($vaga_expirada): ?>
                                    <span style="color: var(--cinza-baobab); font-size: 0.9em; display: inline-flex; align-items: center; gap: var(--space-xs);">
                                        <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                                        Esta vaga não está mais disponível
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Dicas e Informações -->
            <?php if (!empty($candidaturas)): ?>
                <div class="section">
                    <h2 class="section-title">
                        <i data-lucide="lightbulb" style="width: 24px; height: 24px;"></i>
                        Dicas para Suas Candidaturas
                    </h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-md);">
                        <div style="background: var(--areia-quente); padding: var(--space-md); border-radius: 16px;">
                            <strong style="display: flex; align-items: center; gap: var(--space-xs); margin-bottom: var(--space-sm);">
                                <i data-lucide="user-check" style="width: 20px; height: 20px; color: var(--azul-indigo);"></i>
                                Mantenha seu perfil atualizado
                            </strong>
                            <p style="margin: 0; color: var(--carvao); font-size: 0.9rem;">
                                Empresas revisam seu perfil - certifique-se de que suas experiências, formações e competências estão atualizadas.
                            </p>
                        </div>
                        <div style="background: var(--areia-quente); padding: var(--space-md); border-radius: 16px;">
                            <strong style="display: flex; align-items: center; gap: var(--space-xs); margin-bottom: var(--space-sm);">
                                <i data-lucide="edit-3" style="width: 20px; height: 20px; color: var(--dourado-sol);"></i>
                                Personalize suas cartas
                            </strong>
                            <p style="margin: 0; color: var(--carvao); font-size: 0.9rem;">
                                Cartas de apresentação personalizadas aumentam suas chances de ser selecionado.
                            </p>
                        </div>
                        <div style="background: var(--areia-quente); padding: var(--space-md); border-radius: 16px;">
                            <strong style="display: flex; align-items: center; gap: var(--space-xs); margin-bottom: var(--space-sm);">
                                <i data-lucide="clock" style="width: 20px; height: 20px; color: var(--coral-vivo);"></i>
                                Acompanhe os prazos
                            </strong>
                            <p style="margin: 0; color: var(--carvao); font-size: 0.9rem;">
                                Fique atento às datas de expiração das vagas e aos prazos de resposta das empresas.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 🌊 OCEAN DIVIDER -->
    <div class="ocean-divider">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none" transform="rotate(180)">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                  style="fill: var(--gradient-oceano); opacity: 0.8;"></path>
        </svg>
    </div>

    <!-- ✨ MICRO-INTERACTION SCRIPT -->
    <script>
        // Inicializar ícones Lucide
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            
            // Animação de entrada dos cards
            const animateElements = document.querySelectorAll('.stat-card, .item-card, .section');
            
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

            // Animação de contagem para estatísticas
            const animateCounter = (element) => {
                const target = parseInt(element.textContent);
                const duration = 2000;
                const step = target / (duration / 16);
                let current = 0;

                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) {
                        element.textContent = target;
                        clearInterval(timer);
                    } else {
                        element.textContent = Math.floor(current);
                    }
                }, 16);
            };

            const statNumbers = document.querySelectorAll('.stat-number');
            const statsObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        statsObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            statNumbers.forEach(stat => statsObserver.observe(stat));
            
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

        // Confirmação para cancelamento de candidatura
        document.addEventListener('submit', function(e) {
            if (e.target.querySelector('button[name="cancelar_candidatura"]')) {
                if (!confirm('Tem certeza que deseja cancelar esta candidatura?\n\nEsta ação não pode ser desfeita.')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>