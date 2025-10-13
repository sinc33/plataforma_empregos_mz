<?php
session_start();
require_once '../config/db.php';

// Verificar se o usuário está logado como admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

 $pdo = getPDO();

// Processar ações administrativas
 $sucesso = '';
 $erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    try {
        switch ($acao) {
            case 'ativar_desativar_utilizador':
                $user_id = (int)$_POST['user_id'];
                $novo_estado = (int)$_POST['novo_estado'];
                
                $stmt = $pdo->prepare("UPDATE utilizador SET ativo = ? WHERE id = ?");
                $stmt->execute([$novo_estado, $user_id]);
                
                $sucesso = $novo_estado ? "Utilizador ativado com sucesso!" : "Utilizador desativado com sucesso!";
                break;
                
            case 'ativar_desativar_vaga':
                $vaga_id = (int)$_POST['vaga_id'];
                $novo_estado = (int)$_POST['novo_estado'];
                
                $stmt = $pdo->prepare("UPDATE vaga SET ativa = ? WHERE id = ?");
                $stmt->execute([$novo_estado, $vaga_id]);
                
                $sucesso = $novo_estado ? "Vaga ativada com sucesso!" : "Vaga desativada com sucesso!";
                break;
                
            case 'excluir_utilizador':
                $user_id = (int)$_POST['user_id'];
                
                // Verificar se é admin (não permitir excluir a si mesmo)
                if ($user_id == $_SESSION['admin_id']) {
                    $erro = "Não pode excluir a sua própria conta!";
                    break;
                }
                
                $stmt = $pdo->prepare("DELETE FROM utilizador WHERE id = ?");
                $stmt->execute([$user_id]);
                
                $sucesso = "Utilizador excluído com sucesso!";
                break;
        }
    } catch (PDOException $e) {
        $erro = "Erro ao processar ação: " . $e->getMessage();
    }
}

// Buscar estatísticas gerais
 $estatisticas = [
    'total_utilizadores' => $pdo->query("SELECT COUNT(*) FROM utilizador")->fetchColumn(),
    'total_candidatos' => $pdo->query("SELECT COUNT(*) FROM utilizador WHERE tipo = 'candidato'")->fetchColumn(),
    'total_empresas' => $pdo->query("SELECT COUNT(*) FROM utilizador WHERE tipo = 'empresa'")->fetchColumn(),
    'total_vagas' => $pdo->query("SELECT COUNT(*) FROM vaga")->fetchColumn(),
    'vagas_ativas' => $pdo->query("SELECT COUNT(*) FROM vaga WHERE ativa = TRUE AND data_expiracao >= CURDATE()")->fetchColumn(),
    'total_candidaturas' => $pdo->query("SELECT COUNT(*) FROM candidatura")->fetchColumn(),
    'novos_utilizadores_7dias' => $pdo->query("SELECT COUNT(*) FROM utilizador WHERE data_registo >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'novas_vagas_7dias' => $pdo->query("SELECT COUNT(*) FROM vaga WHERE data_publicacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
];

// Buscar últimas atividades
 $sql_atividades = "
    (SELECT 'candidatura' as tipo, c.data_candidatura as data, 
            CONCAT('Nova candidatura de ', cand.nome_completo, ' para ', v.titulo) as descricao,
            NULL as user_id
     FROM candidatura c
     JOIN candidato cand ON c.candidato_id = cand.id
     JOIN vaga v ON c.vaga_id = v.id
     ORDER BY c.data_candidatura DESC LIMIT 5)
    
    UNION ALL
    
    (SELECT 'registro' as tipo, u.data_registo as data,
            CONCAT('Novo ', u.tipo, ': ', 
                   CASE WHEN u.tipo = 'candidato' THEN c.nome_completo 
                        WHEN u.tipo = 'empresa' THEN e.nome_empresa 
                        ELSE u.email END) as descricao,
            u.id as user_id
     FROM utilizador u
     LEFT JOIN candidato c ON u.id = c.id AND u.tipo = 'candidato'
     LEFT JOIN empresa e ON u.id = e.id AND u.tipo = 'empresa'
     ORDER BY u.data_registo DESC LIMIT 5)
    
    UNION ALL
    
    (SELECT 'vaga' as tipo, v.data_publicacao as data,
            CONCAT('Nova vaga: ', v.titulo, ' por ', e.nome_empresa) as descricao,
            NULL as user_id
     FROM vaga v
     JOIN empresa e ON v.empresa_id = e.id
     ORDER BY v.data_publicacao DESC LIMIT 5)
     
    ORDER BY data DESC LIMIT 10
";

 $atividades = $pdo->query($sql_atividades)->fetchAll();

// Buscar utilizadores recentes
 $sql_utilizadores_recentes = "
    SELECT u.*, 
           CASE WHEN u.tipo = 'candidato' THEN c.nome_completo 
                WHEN u.tipo = 'empresa' THEN e.nome_empresa 
                ELSE u.email END as nome_exibicao
    FROM utilizador u
    LEFT JOIN candidato c ON u.id = c.id AND u.tipo = 'candidato'
    LEFT JOIN empresa e ON u.id = e.id AND u.tipo = 'empresa'
    ORDER BY u.data_registo DESC 
    LIMIT 10
";

 $utilizadores_recentes = $pdo->query($sql_utilizadores_recentes)->fetchAll();

// Buscar vagas recentes
 $sql_vagas_recentes = "
    SELECT v.*, e.nome_empresa, 
           (SELECT COUNT(*) FROM candidatura c WHERE c.vaga_id = v.id) as total_candidaturas
    FROM vaga v
    JOIN empresa e ON v.empresa_id = e.id
    ORDER BY v.data_publicacao DESC 
    LIMIT 10
";

 $vagas_recentes = $pdo->query($sql_vagas_recentes)->fetchAll();

// Buscar estatísticas por mês (para gráfico)
 $sql_estatisticas_mensais = "
    SELECT 
        DATE_FORMAT(data_registo, '%Y-%m') as mes,
        COUNT(*) as total_utilizadores,
        SUM(CASE WHEN tipo = 'candidato' THEN 1 ELSE 0 END) as candidatos,
        SUM(CASE WHEN tipo = 'empresa' THEN 1 ELSE 0 END) as empresas
    FROM utilizador 
    WHERE data_registo >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(data_registo, '%Y-%m')
    ORDER BY mes
";

 $estatisticas_mensais = $pdo->query($sql_estatisticas_mensais)->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo - Emprego MZ</title>
    
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
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-lg);
        }

        .hero h1 {
            font-family: var(--font-heading);
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 700;
            margin-bottom: var(--space-md);
            text-align: left;
        }

        .hero p {
            font-size: 1.1rem;
            opacity: 0.9;
            text-align: left;
        }

        .admin-info {
            text-align: right;
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
            max-width: 1400px;
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

        .stat-trend {
            font-size: 0.8em;
            margin-top: var(--space-xs);
            font-weight: 600;
        }
        .trend-positive { color: var(--verde-esperanca); }
        .trend-negative { color: var(--coral-vivo); }

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
            justify-content: space-between;
            align-items: center;
            gap: var(--space-xs);
        }

        /* ============================================
           📱 NAVIGATION & TABS
        ============================================ */
        .tabs {
            display: flex;
            border-bottom: 2px solid var(--areia-quente);
            margin-bottom: var(--space-lg);
            gap: var(--space-sm);
            flex-wrap: wrap;
        }

        .tab {
            padding: var(--space-sm) var(--space-md);
            cursor: pointer;
            border: none;
            background: none;
            font-size: 1rem;
            font-weight: 600;
            color: var(--cinza-baobab);
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            border-radius: 8px 8px 0 0;
        }

        .tab.active {
            color: var(--azul-indigo);
            border-bottom-color: var(--azul-indigo);
        }
        
        .tab:hover {
            color: var(--verde-esperanca);
            background: rgba(43, 122, 75, 0.05);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
        
        /* ============================================
           📊 TABLES
        ============================================ */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: var(--shadow-soft);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--branco-puro);
        }

        th, td {
            padding: var(--space-sm);
            text-align: left;
            border-bottom: 1px solid var(--areia-quente);
        }

        th {
            background: var(--areia-quente);
            font-weight: 700;
            color: var(--carvao);
            font-family: var(--font-heading);
        }

        tr:hover {
            background: rgba(244, 228, 193, 0.3);
        }
        
        /* ============================================
           🔘 BUTTONS
        ============================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-xs);
            padding: var(--space-xs) var(--space-sm);
            border-radius: 50px;
            font-size: 0.8rem;
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

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .btn-group {
            display: flex;
            gap: var(--space-xs);
            flex-wrap: wrap;
        }

        /* ============================================
           🎭 ALERTAS & BADGES
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

        .badge {
            padding: var(--space-xs) var(--space-sm);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-success { background: rgba(43, 122, 75, 0.1); color: var(--verde-esperanca); }
        .badge-danger { background: rgba(255, 107, 107, 0.1); color: var(--coral-vivo); }
        .badge-warning { background: rgba(255, 176, 59, 0.1); color: #cc8a2e; }
        .badge-info { background: rgba(30, 58, 95, 0.1); color: var(--azul-indigo); }

        /* ============================================
           📋 ACTIVITY LIST
        ============================================ */
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            padding: var(--space-md);
            border-left: 3px solid var(--azul-indigo);
            background: var(--areia-quente);
            margin-bottom: var(--space-sm);
            border-radius: 0 12px 12px 0;
            transition: all 0.3s;
        }
        .activity-item:hover {
            transform: translateX(5px);
        }
        .activity-item.registro { border-left-color: var(--verde-esperanca); }
        .activity-item.vaga { border-left-color: #9b59b6; }
        .activity-item.candidatura { border-left-color: var(--dourado-sol); }

        .activity-time {
            font-size: 0.8em;
            color: var(--cinza-baobab);
            margin-top: var(--space-xs);
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

        .empty-state h3 {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            color: var(--carvao);
            margin-bottom: var(--space-sm);
        }

        /* ============================================
           📱 RESPONSIVE DESIGN
        ============================================ */
        @media (max-width: 768px) {
            .hero {
                padding: var(--space-lg) var(--space-md);
            }
            .hero-content {
                flex-direction: column;
                text-align: center;
            }
            .hero h1, .hero p, .admin-info {
                text-align: center;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-md);
            }
            .tabs {
                justify-content: center;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- 🌅 HERO SECTION -->
    <div class="hero">
        <div class="hero-content">
            <div>
                <h1>
                    <i data-lucide="shield-check" style="width: 48px; height: 48px; vertical-align: middle; margin-right: var(--space-sm);"></i>
                    Dashboard Administrativo
                </h1>
                <p>Gestão completa da Plataforma Emprego MZ</p>
            </div>
            <div class="admin-info">
                <div style="font-size: 0.9rem; opacity: 0.8;">Administrador</div>
                <div style="font-weight: bold; font-size: 1.2rem;"><?php echo $_SESSION['admin_nome'] ?? 'Admin'; ?></div>
                <a href="../auth/logout.php" class="btn btn-secondary" style="margin-top: var(--space-sm);">
                    <i data-lucide="log-out" style="width: 18px; height: 18px;"></i>
                    Sair
                </a>
            </div>
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

            <!-- Sistema de Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="openTab('dashboard')">
                    <i data-lucide="bar-chart" style="width: 18px; height: 18px;"></i>
                    Dashboard
                </button>
                <button class="tab" onclick="openTab('utilizadores')">
                    <i data-lucide="users" style="width: 18px; height: 18px;"></i>
                    Utilizadores
                </button>
                <button class="tab" onclick="openTab('vagas')">
                    <i data-lucide="briefcase" style="width: 18px; height: 18px;"></i>
                    Vagas
                </button>
                <button class="tab" onclick="openTab('atividades')">
                    <i data-lucide="trending-up" style="width: 18px; height: 18px;"></i>
                    Atividades
                </button>
            </div>

            <!-- Tab: Dashboard -->
            <div id="dashboard" class="tab-content active">
                <!-- Estatísticas Principais -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i data-lucide="users" style="width: 30px; height: 30px; color: var(--azul-indigo);"></i>
                        </div>
                        <div class="stat-number"><?php echo $estatisticas['total_utilizadores']; ?></div>
                        <div class="stat-label">Total de Utilizadores</div>
                        <div class="stat-trend trend-positive">
                            <i data-lucide="trending-up" style="width: 16px; height: 16px;"></i>
                            +<?php echo $estatisticas['novos_utilizadores_7dias']; ?> esta semana
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i data-lucide="user-check" style="width: 30px; height: 30px; color: var(--verde-esperanca);"></i>
                        </div>
                        <div class="stat-number"><?php echo $estatisticas['total_candidatos']; ?></div>
                        <div class="stat-label">Candidatos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i data-lucide="building" style="width: 30px; height: 30px; color: var(--coral-vivo);"></i>
                        </div>
                        <div class="stat-number"><?php echo $estatisticas['total_empresas']; ?></div>
                        <div class="stat-label">Empresas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i data-lucide="briefcase" style="width: 30px; height: 30px; color: #9b59b6;"></i>
                        </div>
                        <div class="stat-number"><?php echo $estatisticas['total_vagas']; ?></div>
                        <div class="stat-label">Total de Vagas</div>
                        <div class="stat-trend trend-positive">
                            <i data-lucide="trending-up" style="width: 16px; height: 16px;"></i>
                            +<?php echo $estatisticas['novas_vagas_7dias']; ?> esta semana
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i data-lucide="check-circle" style="width: 30px; height: 30px; color: var(--dourado-sol);"></i>
                        </div>
                        <div class="stat-number"><?php echo $estatisticas['vagas_ativas']; ?></div>
                        <div class="stat-label">Vagas Ativas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i data-lucide="send" style="width: 30px; height: 30px; color: var(--cinza-baobab);"></i>
                        </div>
                        <div class="stat-number"><?php echo $estatisticas['total_candidaturas']; ?></div>
                        <div class="stat-label">Candidaturas</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-lg);">
                    <!-- Gráfico de Crescimento -->
                    <div class="section">
                        <h2 class="section-title">
                            <i data-lucide="trending-up" style="width: 24px; height: 24px;"></i>
                            Crescimento da Plataforma (Últimos 6 Meses)
                        </h2>
                        <div style="height: 300px;">
                            <canvas id="crescimentoChart"></canvas>
                        </div>
                    </div>

                    <!-- Atividades Recentes -->
                    <div class="section">
                        <h2 class="section-title">
                            <i data-lucide="activity" style="width: 24px; height: 24px;"></i>
                            Atividades Recentes
                        </h2>
                        <ul class="activity-list">
                            <?php if (empty($atividades)): ?>
                                <div class="empty-state">
                                    <p>Nenhuma atividade recente</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($atividades as $atividade): ?>
                                    <li class="activity-item <?php echo $atividade['tipo']; ?>">
                                        <div><?php echo htmlspecialchars($atividade['descricao']); ?></div>
                                        <div class="activity-time">
                                            <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($atividade['data'])); ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Tab: Utilizadores -->
            <div id="utilizadores" class="tab-content">
                <div class="section">
                    <h2 class="section-title">
                        <i data-lucide="users" style="width: 24px; height: 24px;"></i>
                        Gestão de Utilizadores
                    </h2>
                    <div class="table-container">
                        <?php if (empty($utilizadores_recentes)): ?>
                            <div class="empty-state">
                                <h3>Nenhum utilizador encontrado</h3>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome/Empresa</th>
                                        <th>Email</th>
                                        <th>Tipo</th>
                                        <th>Data Registo</th>
                                        <th>Estado</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($utilizadores_recentes as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['nome_exibicao']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $user['tipo'] === 'candidato' ? 'badge-success' : 'badge-info'; ?>">
                                                    <i data-lucide="<?php echo $user['tipo'] === 'candidato' ? 'user' : 'building'; ?>" style="width: 14px; height: 14px;"></i>
                                                    <?php echo ucfirst($user['tipo']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($user['data_registo'])); ?></td>
                                            <td>
                                                <span class="badge <?php echo $user['ativo'] ? 'badge-success' : 'badge-danger'; ?>">
                                                    <?php echo $user['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="acao" value="ativar_desativar_utilizador">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="novo_estado" value="<?php echo $user['ativo'] ? '0' : '1'; ?>">
                                                        <button type="submit" class="btn <?php echo $user['ativo'] ? 'btn-warning' : 'btn-success'; ?>">
                                                            <i data-lucide="<?php echo $user['ativo'] ? 'pause-circle' : 'play-circle'; ?>" style="width: 16px; height: 16px;"></i>
                                                        </button>
                                                    </form>
                                                    <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="acao" value="excluir_utilizador">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="btn btn-danger" 
                                                                    onclick="return confirm('Tem certeza que deseja excluir este utilizador?')">
                                                                <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tab: Vagas -->
            <div id="vagas" class="tab-content">
                <div class="section">
                    <h2 class="section-title">
                        <i data-lucide="briefcase" style="width: 24px; height: 24px;"></i>
                        Gestão de Vagas
                    </h2>
                    <div class="table-container">
                        <?php if (empty($vagas_recentes)): ?>
                            <div class="empty-state">
                                <h3>Nenhuma vaga encontrada</h3>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Título</th>
                                        <th>Empresa</th>
                                        <th>Localização</th>
                                        <th>Candidaturas</th>
                                        <th>Data Publicação</th>
                                        <th>Estado</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vagas_recentes as $vaga): ?>
                                        <tr>
                                            <td><?php echo $vaga['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($vaga['titulo']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($vaga['nome_empresa']); ?></td>
                                            <td>
                                                <i data-lucide="map-pin" style="width: 14px; height: 14px;"></i>
                                                <?php echo htmlspecialchars($vaga['localizacao']); ?>
                                            </td>
                                            <td><?php echo $vaga['total_candidaturas']; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($vaga['data_publicacao'])); ?></td>
                                            <td>
                                                <span class="badge <?php echo $vaga['ativa'] ? 'badge-success' : 'badge-danger'; ?>">
                                                    <?php echo $vaga['ativa'] ? 'Ativa' : 'Inativa'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="acao" value="ativar_desativar_vaga">
                                                        <input type="hidden" name="vaga_id" value="<?php echo $vaga['id']; ?>">
                                                        <input type="hidden" name="novo_estado" value="<?php echo $vaga['ativa'] ? '0' : '1'; ?>">
                                                        <button type="submit" class="btn <?php echo $vaga['ativa'] ? 'btn-warning' : 'btn-success'; ?>">
                                                            <i data-lucide="<?php echo $vaga['ativa'] ? 'pause-circle' : 'play-circle'; ?>" style="width: 16px; height: 16px;"></i>
                                                        </button>
                                                    </form>
                                                    <a href="admin_vaga_detalhe.php?id=<?php echo $vaga['id']; ?>" class="btn btn-primary">
                                                        <i data-lucide="eye" style="width: 16px; height: 16px;"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tab: Atividades -->
            <div id="atividades" class="tab-content">
                <div class="section">
                    <h2 class="section-title">
                        <i data-lucide="trending-up" style="width: 24px; height: 24px;"></i>
                        Relatórios e Estatísticas
                    </h2>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-lg);">
                        <div>
                            <h3 style="font-family: var(--font-heading); color: var(--carvao); margin-bottom: var(--space-md);">📊 Distribuição de Utilizadores</h3>
                            <div style="height: 300px;">
                                <canvas id="usersChart"></canvas>
                            </div>
                        </div>
                        <div>
                            <h3 style="font-family: var(--font-heading); color: var(--carvao); margin-bottom: var(--space-md);">💼 Status das Vagas</h3>
                            <div style="height: 300px;">
                                <canvas id="vagasChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: var(--space-xl);">
                        <h3 style="font-family: var(--font-heading); color: var(--carvao); margin-bottom: var(--space-md);">📋 Estatísticas Detalhadas</h3>
                        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i data-lucide="user-plus" style="width: 30px; height: 30px; color: var(--verde-esperanca);"></i>
                                </div>
                                <div class="stat-number"><?php echo $estatisticas['novos_utilizadores_7dias']; ?></div>
                                <div class="stat-label">Novos Utilizadores (7 dias)</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i data-lucide="briefcase" style="width: 30px; height: 30px; color: var(--dourado-sol);"></i>
                                </div>
                                <div class="stat-number"><?php echo $estatisticas['novas_vagas_7dias']; ?></div>
                                <div class="stat-label">Novas Vagas (7 dias)</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i data-lucide="percent" style="width: 30px; height: 30px; color: var(--azul-indigo);"></i>
                                </div>
                                <div class="stat-number">
                                    <?php 
                                    $taxa_crescimento = $estatisticas['total_utilizadores'] > 0 ? 
                                        (($estatisticas['novos_utilizadores_7dias'] / $estatisticas['total_utilizadores']) * 100) : 0;
                                    echo number_format($taxa_crescimento, 1);
                                    ?>%
                                </div>
                                <div class="stat-label">Taxa de Crescimento</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ✨ MICRO-INTERACTION SCRIPT -->
    <script>
        // Inicializar ícones Lucide
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            
            // Animação de entrada dos cards
            const animateElements = document.querySelectorAll('.stat-card, .section, .activity-item');
            
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
        });

        // Sistema de Tabs
        function openTab(tabName) {
            // Esconder todas as tabs
            var tabContents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Remover active de todas as tabs
            var tabs = document.getElementsByClassName('tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Mostrar a tab selecionada
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
            
            // Re-inicializar ícones Lucide após a mudança de tab
            setTimeout(() => lucide.createIcons(), 100);
        }

        // Gráficos
        document.addEventListener('DOMContentLoaded', function() {
            // Cores do Marrabenta UI
            const chartColors = {
                verde: '#2B7A4B',
                dourado: '#FFB03B',
                azul: '#1E3A5F',
                coral: '#FF6B6B',
                cinza: '#95A5A6'
            };

            // Gráfico de Crescimento
            var crescimentoCtx = document.getElementById('crescimentoChart').getContext('2d');
            var crescimentoChart = new Chart(crescimentoCtx, {
                type: 'line',
                data: {
                    labels: [<?php echo implode(',', array_map(function($item) { return "'" . date('M Y', strtotime($item['mes'] . '-01')) . "'"; }, $estatisticas_mensais)); ?>],
                    datasets: [
                        {
                            label: 'Candidatos',
                            data: [<?php echo implode(',', array_column($estatisticas_mensais, 'candidatos')); ?>],
                            borderColor: chartColors.verde,
                            backgroundColor: 'rgba(43, 122, 75, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Empresas',
                            data: [<?php echo implode(',', array_column($estatisticas_mensais, 'empresas')); ?>],
                            borderColor: chartColors.coral,
                            backgroundColor: 'rgba(255, 107, 107, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: { color: '#2C3E50' }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#2C3E50' },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            ticks: { color: '#2C3E50' },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        }
                    }
                }
            });

            // Gráfico de Distribuição de Utilizadores
            var usersCtx = document.getElementById('usersChart').getContext('2d');
            var usersChart = new Chart(usersCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Candidatos', 'Empresas'],
                    datasets: [{
                        data: [<?php echo $estatisticas['total_candidatos']; ?>, <?php echo $estatisticas['total_empresas']; ?>],
                        backgroundColor: [chartColors.verde, chartColors.coral],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#2C3E50' }
                        }
                    }
                }
            });

            // Gráfico de Status das Vagas
            var vagasCtx = document.getElementById('vagasChart').getContext('2d');
            var vagasChart = new Chart(vagasCtx, {
                type: 'pie',
                data: {
                    labels: ['Vagas Ativas', 'Vagas Inativas/Expiradas'],
                    datasets: [{
                        data: [
                            <?php echo $estatisticas['vagas_ativas']; ?>,
                            <?php echo $estatisticas['total_vagas'] - $estatisticas['vagas_ativas']; ?>
                        ],
                        backgroundColor: [chartColors.verde, chartColors.cinza],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#2C3E50' }
                        }
                    }
                }
            });
        });

        // Confirmações para ações destrutivas
        document.addEventListener('submit', function(e) {
            if (e.target.querySelector('input[name="acao"][value="excluir_utilizador"]')) {
                if (!confirm('⚠️ Tem certeza que deseja excluir permanentemente este utilizador?\n\nEsta ação não pode ser desfeita e todos os dados associados serão perdidos.')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>