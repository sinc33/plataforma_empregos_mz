<?php
session_start();
require_once '../config/db.php';

// Verificar se é empresa autenticada
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'empresa') {
    header("Location: ../auth/login.php");
    exit;
}

$empresa_id = $_SESSION['user_id'];

// Processar ações nas vagas
$sucesso = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vaga_id'])) {
    $vaga_id = (int)$_POST['vaga_id'];
    
    // Verificar se a vaga pertence à empresa
    $stmt_verificar = $pdo->prepare("SELECT id FROM vaga WHERE id = ? AND empresa_id = ?");
    $stmt_verificar->execute([$vaga_id, $empresa_id]);
    
    if ($stmt_verificar->fetch()) {
        try {
            switch ($_POST['acao']) {
                case 'desativar':
                    $stmt = $pdo->prepare("UPDATE vaga SET ativa = FALSE WHERE id = ?");
                    $stmt->execute([$vaga_id]);
                    $sucesso = "Vaga desativada com sucesso!";
                    break;
                
                case 'reativar':
                    $stmt = $pdo->prepare("UPDATE vaga SET ativa = TRUE WHERE id = ?");
                    $stmt->execute([$vaga_id]);
                    $sucesso = "Vaga reativada com sucesso!";
                    break;
                
                case 'excluir':
                    // Verificar se há candidaturas
                    $stmt_candidaturas = $pdo->prepare("SELECT COUNT(*) FROM candidatura WHERE vaga_id = ?");
                    $stmt_candidaturas->execute([$vaga_id]);
                    $total_candidaturas = $stmt_candidaturas->fetchColumn();
                    
                    if ($total_candidaturas > 0) {
                        $erro = "Não é possível excluir uma vaga com candidaturas. Desative-a em vez disso.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM vaga WHERE id = ?");
                        $stmt->execute([$vaga_id]);
                        $sucesso = "Vaga excluída com sucesso!";
                    }
                    break;
            }
        } catch (PDOException $e) {
            $erro = "Erro ao processar ação: " . $e->getMessage();
        }
    } else {
        $erro = "Vaga não encontrada ou não pertence à sua empresa.";
    }
}

// Buscar estatísticas da empresa
$sql_estatisticas = "
    SELECT 
        COUNT(*) as total_vagas,
        SUM(CASE WHEN ativa = TRUE AND data_expiracao >= CURDATE() THEN 1 ELSE 0 END) as vagas_ativas,
        SUM(CASE WHEN ativa = FALSE OR data_expiracao < CURDATE() THEN 1 ELSE 0 END) as vagas_inativas,
        (SELECT COUNT(*) FROM candidatura c JOIN vaga v ON c.vaga_id = v.id WHERE v.empresa_id = ?) as total_candidaturas,
        (SELECT COUNT(DISTINCT candidato_id) FROM candidatura c JOIN vaga v ON c.vaga_id = v.id WHERE v.empresa_id = ?) as candidatos_unicos
    FROM vaga 
    WHERE empresa_id = ?
";
$stmt_estatisticas = $pdo->prepare($sql_estatisticas);
$stmt_estatisticas->execute([$empresa_id, $empresa_id, $empresa_id]);
$estatisticas = $stmt_estatisticas->fetch();

// Buscar vagas da empresa
$sql_vagas = "
    SELECT v.*,
        COUNT(c.id) as total_candidaturas,
        SUM(CASE WHEN c.estado = 'submetida' THEN 1 ELSE 0 END) as candidaturas_novas,
        SUM(CASE WHEN c.estado = 'em_analise' THEN 1 ELSE 0 END) as em_analise,
        SUM(CASE WHEN c.estado = 'entrevista' THEN 1 ELSE 0 END) as entrevistas,
        SUM(CASE WHEN c.estado = 'rejeitada' THEN 1 ELSE 0 END) as rejeitadas,
        SUM(CASE WHEN c.estado = 'contratado' THEN 1 ELSE 0 END) as contratados
    FROM vaga v
    LEFT JOIN candidatura c ON v.id = c.vaga_id
    WHERE v.empresa_id = ?
    GROUP BY v.id
    ORDER BY v.data_publicacao DESC
";
$stmt_vagas = $pdo->prepare($sql_vagas);
$stmt_vagas->execute([$empresa_id]);
$vagas = $stmt_vagas->fetchAll();

// Buscar informações da empresa
$stmt_empresa = $pdo->prepare("SELECT nome_empresa, logotipo FROM empresa WHERE id = ?");
$stmt_empresa->execute([$empresa_id]);
$empresa = $stmt_empresa->fetch();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Empresarial - <?php echo htmlspecialchars($empresa['nome_empresa'] ?? 'Empresa'); ?> | Emprego MZ</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        /* ==========================================
           🎨 CSS VARIABLES & RESET
        ========================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            /* === 🎨 PALETA DEFINITIVA PROFISSIONAL PARA DASHBOARD === */
            
            /* 🔵 CORES PRINCIPAIS */
            --cor-primaria: #14213d;              /* Oxford Blue - Headers, títulos principais */
            --cor-primaria-escura: #0f1a2e;       /* Oxford Blue Dark - Hover intenso */
            --cor-primaria-clara: #1e2c47;        /* Oxford Blue Light - Elementos secundários */
            --cor-primaria-alpha: rgba(20, 33, 61, 0.1);
            
            /* 🟠 CORES SECUNDÁRIAS */
            --cor-secundaria: #fca311;            /* Orange Web - Botões principais, CTAs */
            --cor-secundaria-hover: #e3940f;      /* Orange Hover */
            --cor-secundaria-clara: #fdb541;      /* Orange Light */
            --cor-secundaria-muito-clara: #fec871; /* Orange Very Light */
            --cor-secundaria-alpha: rgba(252, 163, 17, 0.1);
            
            /* 🌫️ HIERARQUIA VISUAL - Soft Gray System */
            --cor-fundo: #f8fafc;                 /* Soft Gray - Fundo geral da página */
            --cor-cards: #ffffff;                 /* White - Widgets, cards, painéis */
            --cor-acento: #f1f5ff;                /* Light Blue - Hover states, seções ativas */
            --cor-acento-escuro: #e1ebff;         /* Light Blue Dark - Bordas ativas */
            
            /* 📝 TEXTO - Hierarquia Refinada */
            --cor-texto: #1a202c;                 /* Títulos, dados importantes */
            --cor-texto-claro: #64748b;           /* Texto secundário */
            --cor-texto-muito-claro: #94a3b8;     /* Metadados, labels */
            
            /* 👥 TIPOS DE USUÁRIO - DIFERENCIAÇÃO VISUAL */
            --cor-candidato: #10B981;             /* Verde para candidatos */
            --cor-candidato-light: #D1FAE5;       /* Fundo suave candidato */
            --cor-empresa: #8B5CF6;               /* Roxo para empresas */
            --cor-empresa-light: #EDE9FE;         /* Fundo suave empresa */
            
            /* 📊 WIDGETS E MÉTRICAS */
            --cor-metrica-positiva: #10B981;      /* Crescimento, sucesso */
            --cor-metrica-negativa: #EF4444;      /* Declínio, alerta */
            --cor-metrica-neutra: #6B7280;        /* Estável */
            --cor-progresso: var(--cor-secundaria); /* Barras de progresso */
            --cor-progresso-fundo: #E5E7EB;       /* Fundo das barras */
            
            /* 🎯 STATUS E ESTADOS */
            --cor-ativo: #10B981;                 /* Status ativo */
            --cor-inativo: #6B7280;               /* Status inativo */
            --cor-pendente: #F59E0B;              /* Status pendente */
            --cor-novo: #06B6D4;                  /* Itens novos */
            --cor-destaque: var(--cor-secundaria); /* Elementos importantes */
            
            /* 📈 GRÁFICOS E VISUALIZAÇÕES */
            --cor-grafico-primaria: var(--cor-primaria);
            --cor-grafico-secundaria: var(--cor-secundaria);
            --cor-grafico-acento: var(--cor-acento-escuro);
            --cor-grafico-candidato: var(--cor-candidato);
            --cor-grafico-empresa: var(--cor-empresa);
            
            /* 🎨 BORDAS E SOMBRAS */
            --cor-borda: #e2e8f0;                 /* Bordas padrão */
            --cor-borda-ativa: var(--cor-primaria);
            --sombra-widget: 0 4px 20px rgba(20, 33, 61, 0.08);
            --sombra-card-hover: 0 8px 32px rgba(20, 33, 61, 0.12);
            --sombra-stat: 0 2px 12px rgba(252, 163, 17, 0.15);
            
            /* 🔄 Aliases para compatibilidade */
            --primary: var(--cor-primaria);
            --primary-dark: var(--cor-primaria-escura);
            --primary-light: var(--cor-primaria-clara);
            --secondary: var(--cor-secundaria);
            --secondary-dark: var(--cor-secundaria-hover);
            --success: var(--cor-ativo);
            --error: var(--cor-metrica-negativa);
            --warning: var(--cor-pendente);
            --info: var(--cor-primaria);
            --text: var(--cor-texto);
            --text-light: var(--cor-texto-claro);
            --text-lighter: var(--cor-texto-muito-claro);
            --border: var(--cor-borda);
            --white: var(--cor-cards);
            
            /* 🎨 Grays - Refinados */
            --gray-900: var(--cor-texto);
            --gray-800: #333333;
            --gray-700: var(--cor-texto-claro);
            --gray-600: var(--cor-texto-muito-claro);
            --gray-500: #8a8a8a;
            --gray-400: #b8b8b8;
            --gray-300: var(--cor-borda);
            --gray-200: #ebebeb;
            --gray-100: #f1f5f9;
            --gray-50: var(--cor-fundo);
            
            /* Typography */
            --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --font-family: var(--font-primary);
            
            /* Spacing */
            --space-1: 4px;
            --space-2: 8px;
            --space-3: 12px;
            --space-4: 16px;
            --space-5: 20px;
            --space-6: 24px;
            --space-8: 32px;
            --space-10: 40px;
            --space-12: 48px;
            --space-16: 64px;
            --space-20: 80px;
            
            /* Border Radius */
            --radius-sm: 4px;
            --radius: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-2xl: 20px;
            --radius-full: 9999px;
            
            /* Shadows - Profissionais com Oxford Blue */
            --shadow-sm: 0 1px 2px 0 rgba(20, 33, 61, 0.05);
            --shadow: 0 1px 3px 0 rgba(20, 33, 61, 0.1), 0 1px 2px 0 rgba(20, 33, 61, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(20, 33, 61, 0.1), 0 2px 4px -1px rgba(20, 33, 61, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(20, 33, 61, 0.1), 0 4px 6px -2px rgba(20, 33, 61, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(20, 33, 61, 0.1), 0 10px 10px -5px rgba(20, 33, 61, 0.04);
            
            /* Transitions */
            --transition: all 0.2s ease;
            --transition-slow: all 0.3s ease;
        }
        
        body {
            font-family: var(--font-primary);
            color: var(--cor-texto);                 /* #1a202c - Texto principal */
            background: var(--cor-fundo);            /* #f8fafc - Soft Gray */
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            margin: 0;
            padding: 0;
        }
        
        /* ==========================================
           📱 HEADER PROFISSIONAL - Oxford Blue (IGUAL INDEX.PHP)
        ========================================== */
        .header {
            background: var(--cor-primaria);         /* #14213d - Oxford Blue */
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: var(--shadow-md);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-6);
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 70px;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo-img {
            height: 36px;
            width: auto;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: var(--space-10);  /* 40px - Mais espaçamento entre itens */
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9);         /* Branco sobre Oxford Blue */
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .nav-link:hover {
            color: var(--cor-secundaria);            /* #fca311 - Orange */
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--cor-secundaria);       /* #fca311 - Orange */
            transition: width 0.3s ease;
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }

        .nav-dropdown {
            position: relative;
        }

        .has-dropdown svg {
            width: 16px;
            height: 16px;
            transition: var(--transition);
        }

        .nav-dropdown:hover .has-dropdown svg {
            transform: rotate(180deg);
        }

        .dropdown-content {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            min-width: 220px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: var(--transition);
            z-index: 100;
        }

        .nav-dropdown:hover .dropdown-content {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3) var(--space-4);
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition);
        }

        .dropdown-item:first-child {
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .dropdown-item:last-child {
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
        }

        .dropdown-item:hover {
            background: var(--gray-50);
            color: var(--primary);
        }

        .dropdown-item svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--border);
            margin: var(--space-2) 0;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            white-space: nowrap;
        }

        .btn svg {
            width: 18px;
            height: 18px;
        }

        .btn-outline {
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--text);
        }

        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-4);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: 1.5px solid var(--primary);
            background: rgba(20, 33, 61, 0.05);
            color: var(--primary);
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(20, 33, 61, 0.12);
        }

        .btn-primary:hover {
            background: rgba(20, 33, 61, 0.1);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(20, 33, 61, 0.2);
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(20, 33, 61, 0.12);
        }

        .btn-primary svg {
            width: 16px;
            height: 16px;
            transition: transform 0.2s ease;
            color: var(--primary);
        }

        .btn-primary:hover svg {
            transform: scale(1.1);
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text);
            cursor: pointer;
            padding: var(--space-2);
        }

        .mobile-menu-toggle svg {
            width: 24px;
            height: 24px;
        }

        /* ==========================================
           🍞 BREADCRUMB
        ========================================== */
        .breadcrumb {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            margin-top: 73px;
        }

        .breadcrumb-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-3) var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 14px;
            color: var(--text-light);
        }

        .breadcrumb-link {
            color: var(--primary);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb-link:hover {
            text-decoration: underline;
        }

        .breadcrumb-separator {
            width: 16px;
            height: 16px;
            color: var(--text-lighter);
        }

        /* ==========================================
           📋 MAIN LAYOUT
        ========================================== */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-8) var(--space-6);
        }

        .page-header {
            margin-bottom: var(--space-8);
        }

        .page-title-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--space-6);
            margin-bottom: var(--space-6);
        }

        .page-title-content {
            flex: 1;
        }

        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: var(--space-2);
        }

        .page-subtitle {
            font-size: 16px;
            color: var(--text-light);
        }

        .page-actions {
            display: flex;
            gap: var(--space-3);
        }

        /* Alerts */
        .alert {
            padding: var(--space-4);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-size: 14px;
            animation: fadeIn 0.3s ease-out;
        }

        .alert svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* ==========================================
           📊 STATISTICS CARDS
        ========================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: var(--space-6);
            margin-bottom: var(--space-8);
        }

        .stat-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            opacity: 0;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: var(--space-4);
        }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
            margin-bottom: var(--space-2);
        }

        .stat-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-light);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon svg {
            width: 28px;
            height: 28px;
            color: var(--white);
        }

        .stat-icon-blue {
            background: linear-gradient(135deg, #0088CC, #006699);
        }

        .stat-icon-green {
            background: linear-gradient(135deg, #10B981, #059669);
        }

        .stat-icon-orange {
            background: linear-gradient(135deg, #FF8C00, #E67600);
        }

        .stat-icon-purple {
            background: linear-gradient(135deg, #6F42C1, #5A369D);
        }

        /* ==========================================
           💼 VAGAS SECTION
        ========================================== */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-6);
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .section-title svg {
            width: 28px;
            height: 28px;
            color: var(--primary);
        }

        /* ==========================================
           🔍 FILTROS DE VAGAS
        ========================================== */
        .filters-container {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            margin-bottom: var(--space-6);
            box-shadow: var(--shadow);
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-4);
        }

        .filters-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .filters-title svg {
            width: 20px;
            height: 20px;
            color: var(--primary);
        }

        .filter-reset {
            font-size: 13px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: var(--space-1);
            transition: var(--transition);
            cursor: pointer;
            border: none;
            background: none;
            padding: var(--space-1) var(--space-2);
            border-radius: var(--radius);
        }

        .filter-reset:hover {
            background: var(--gray-50);
            color: var(--primary-dark);
        }

        .filter-reset svg {
            width: 16px;
            height: 16px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-3);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }

        .filter-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-buttons {
            display: flex;
            gap: var(--space-2);
            flex-wrap: wrap;
        }

        .filter-btn {
            flex: 1;
            min-width: fit-content;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 600;
            border: 1.5px solid var(--border);
            background: var(--white);
            color: var(--text-light);
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .filter-btn svg {
            width: 16px;
            height: 16px;
        }

        .filter-btn:hover {
            border-color: var(--primary);
            background: var(--gray-50);
            color: var(--primary);
            transform: translateY(-1px);
        }

        .filter-btn.active {
            border-color: var(--primary);
            background: var(--primary);
            color: var(--white);
            box-shadow: var(--shadow-md);
        }

        .filter-btn .badge-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 var(--space-1);
            border-radius: var(--radius-full);
            background: rgba(255, 255, 255, 0.2);
            font-size: 11px;
            font-weight: 700;
            margin-left: var(--space-1);
        }

        .filter-btn.active .badge-count {
            background: rgba(255, 255, 255, 0.3);
        }

        .filter-stats {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-4);
            padding: var(--space-3);
            margin-top: var(--space-4);
            background: var(--gray-50);
            border-radius: var(--radius);
            font-size: 13px;
            color: var(--text-light);
        }

        .filter-stat-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .filter-stat-value {
            font-weight: 700;
            color: var(--primary);
            font-size: 16px;
        }

        .vagas-grid {
            display: flex;
            flex-direction: column;
            gap: var(--space-6);
        }

        .vaga-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .vaga-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
            border-color: var(--primary);
        }

        .vaga-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--space-6);
            margin-bottom: var(--space-5);
        }

        .vaga-title-area {
            flex: 1;
            min-width: 0;
        }

        .vaga-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: var(--space-2);
        }

        .vaga-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-5);
            font-size: 14px;
            color: var(--text-light);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .meta-item svg {
            width: 16px;
            height: 16px;
            color: var(--primary);
            flex-shrink: 0;
        }

        .vaga-status {
            display: flex;
            gap: var(--space-2);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-full);
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-badge svg {
            width: 14px;
            height: 14px;
        }

        .badge-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-inactive {
            background: rgba(107, 114, 128, 0.1);
            color: var(--text-light);
            border: 1px solid rgba(107, 114, 128, 0.3);
        }

        .badge-expired {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .vaga-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: var(--space-4);
            padding: var(--space-5);
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-5);
        }

        .vaga-stat-item {
            text-align: center;
        }

        .vaga-stat-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
            margin-bottom: var(--space-1);
        }

        .vaga-stat-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .vaga-actions {
            display: flex;
            gap: var(--space-3);
            flex-wrap: wrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-4);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: 1.5px solid var(--border);
            background: var(--white);
            color: var(--text);
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(20, 33, 61, 0.08);
        }

        .btn-action:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(20, 33, 61, 0.12);
        }

        .btn-action:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(20, 33, 61, 0.08);
        }

        .btn-action svg {
            width: 16px;
            height: 16px;
            transition: transform 0.2s ease;
        }

        .btn-action:hover svg {
            transform: scale(1.1);
        }

        .btn-action-primary {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-4);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: 1.5px solid var(--primary);
            background: rgba(20, 33, 61, 0.05);
            color: var(--primary);
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(20, 33, 61, 0.12);
        }

        .btn-action-primary:hover {
            background: rgba(20, 33, 61, 0.1);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(20, 33, 61, 0.2);
        }

        .btn-action-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(20, 33, 61, 0.12);
        }

        .btn-action-primary svg {
            width: 16px;
            height: 16px;
            transition: transform 0.2s ease;
            color: var(--primary);
        }

        .btn-action-primary:hover svg {
            transform: scale(1.1);
        }

        .btn-action-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(245, 158, 11, 0.1) 100%);
            border: 1.5px solid rgba(245, 158, 11, 0.4);
            color: var(--warning);
            box-shadow: 0 2px 6px rgba(245, 158, 11, 0.15);
            font-weight: 600;
        }

        .btn-action-warning:hover {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.25) 0%, rgba(245, 158, 11, 0.15) 100%);
            border-color: rgba(245, 158, 11, 0.6);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
        }

        .btn-action-warning:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(245, 158, 11, 0.15);
        }

        .btn-action-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.1) 100%);
            border: 1.5px solid rgba(239, 68, 68, 0.4);
            color: var(--error);
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.15);
            font-weight: 600;
        }

        .btn-action-danger:hover {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.25) 0%, rgba(239, 68, 68, 0.15) 100%);
            border-color: rgba(239, 68, 68, 0.6);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
        }

        .btn-action-danger:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.15);
        }

        /* ==========================================
           ⚠️ EMPTY STATE
        ========================================== */
        .empty-state {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-20);
            text-align: center;
            box-shadow: var(--shadow);
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto var(--space-6);
            color: var(--text-lighter);
        }

        .empty-state-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: var(--space-3);
        }

        .empty-state-text {
            font-size: 15px;
            color: var(--text-light);
            margin-bottom: var(--space-6);
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ==========================================
           🦶 FOOTER
        ========================================== */
        .footer {
            background: var(--cor-primaria);
            color: rgba(255, 255, 255, 0.8);
            padding: var(--space-16) var(--space-6) var(--space-8);
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: var(--space-10);
            margin-bottom: var(--space-10);
        }

        .footer-column h4 {
            color: var(--white);
            font-size: 16px;
            font-weight: 700;
            margin-bottom: var(--space-4);
        }

        .footer-logo {
            height: 32px;
            margin-bottom: var(--space-4);
        }

        .footer-description {
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: var(--space-4);
        }

        .footer-link {
            display: block;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: var(--space-3);
            transition: var(--transition);
        }

        .footer-link:hover {
            color: var(--cor-secundaria);
        }

        .footer-bottom {
            padding-top: var(--space-8);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            text-align: center;
        }

        .footer-social {
            display: flex;
            gap: var(--space-4);
        }

        .social-link {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.7);
            transition: var(--transition);
        }

        .social-link:hover {
            background: var(--cor-secundaria);
            color: var(--white);
            transform: translateY(-2px);
        }

        .social-link svg {
            width: 18px;
            height: 18px;
        }

        /* ==========================================
           📱 RESPONSIVE
        ========================================== */
        @media (max-width: 1024px) {
            .nav-menu {
                display: none;
            }

            .mobile-menu-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .header-container {
                padding: var(--space-3) var(--space-4);
            }

            .logo-img {
                height: 32px;
            }

            .breadcrumb-container {
                padding: var(--space-2) var(--space-4);
            }

            .main-container {
                padding: var(--space-6) var(--space-4);
            }

            .page-title {
                font-size: 24px;
            }

            .page-title-section {
                flex-direction: column;
                align-items: flex-start;
            }

            .page-actions {
                width: 100%;
                flex-direction: column;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-buttons {
                flex-direction: column;
            }

            .filter-btn {
                width: 100%;
                justify-content: flex-start;
            }

            .filter-stats {
                flex-direction: column;
                gap: var(--space-2);
            }

            .vaga-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .vaga-meta {
                flex-direction: column;
                gap: var(--space-3);
            }

            .vaga-actions {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
                justify-content: center;
            }

            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-6);
            }

            .header-actions .btn {
                font-size: 13px;
                padding: var(--space-2) var(--space-3);
            }

            .header-actions .btn svg {
                width: 16px;
                height: 16px;
            }
        }

        @media (max-width: 480px) {
            .footer-grid {
                grid-template-columns: 1fr;
            }

            .vaga-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* ==========================================
           ✨ ANIMATIONS
        ========================================== */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card,
        .vaga-card {
            animation: fadeIn 0.3s ease-out;
        }
    </style>
</head>
<body>

    <!-- ==========================================
         📱 HEADER - CONSISTENT WITH OTHER PAGES
    ========================================== -->
    <header class="header">
        <div class="header-container">
            <a href="../index.php" class="logo">
                <img src="../assets/images/empregos-logo.svg" alt="Emprego MZ" class="logo-img">
            </a>

            <nav class="nav-menu">
                <a href="../index.php" class="nav-link">Início</a>
                <a href="../vagas.php" class="nav-link">Vagas</a>
                
                <?php 
                // ==========================================
                // LÓGICA DE NAVEGAÇÃO PARA CANDIDATOS
                // ==========================================
                // Mostrar "Para Candidatos" APENAS se logado como candidato
                if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'candidato'): 
                ?>
                <div class="nav-dropdown">
                    <a href="#" class="nav-link has-dropdown">
                        Para Candidatos
                        <i data-lucide="chevron-down"></i>
                    </a>
                    <div class="dropdown-content">
                        <a href="../candidato/perfil.php" class="dropdown-item">
                            <i data-lucide="user"></i>
                            <span>Meu Perfil</span>
                        </a>
                        <a href="../candidato/vagas_guardadas.php" class="dropdown-item">
                            <i data-lucide="bookmark"></i>
                            <span>Vagas Guardadas</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../candidato/candidaturas.php" class="dropdown-item">
                            <i data-lucide="briefcase"></i>
                            <span>Minhas Candidaturas</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php 
                // ==========================================
                // LÓGICA DE NAVEGAÇÃO PARA EMPRESAS
                // ==========================================
                // Mostrar "Para Empresas" APENAS se logado como empresa
                if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'empresa'): 
                ?>
                <div class="nav-dropdown">
                    <a href="#" class="nav-link has-dropdown">
                        Para Empresas
                        <i data-lucide="chevron-down"></i>
                    </a>
                    <div class="dropdown-content">
                        <a href="dashboard.php" class="dropdown-item">
                            <i data-lucide="layout-dashboard"></i>
                            <span>Painel de Controle</span>
                        </a>
                        <a href="criar_vaga.php" class="dropdown-item">
                            <i data-lucide="plus-circle"></i>
                            <span>Publicar Vaga</span>
                        </a>
                        <a href="dashboard.php?filtro=candidatos" class="dropdown-item">
                            <i data-lucide="users"></i>
                            <span>Candidatos</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </nav>

            <div class="header-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['user_type'] === 'empresa'): ?>
                        <!-- Ações para Empresa - Ordem priorizada -->
                        <a href="dashboard.php" class="btn btn-primary">
                            <i data-lucide="layout-dashboard"></i>
                            Dashboard
                        </a>
                        <a href="criar_vaga.php" class="btn btn-outline">
                            <i data-lucide="plus-circle"></i>
                            Publicar Vaga
                        </a>
                        <a href="dashboard.php?filtro=candidatos" class="btn btn-outline">
                            <i data-lucide="users"></i>
                            Candidatos
                        </a>
                        <a href="../auth/logout.php" class="btn btn-outline">
                            <i data-lucide="log-out"></i>
                            Sair
                        </a>
                    <?php elseif ($_SESSION['user_type'] === 'candidato'): ?>
                        <!-- Ações para Candidato - Ordem priorizada -->
                        <a href="../vagas.php" class="btn btn-primary">
                            <i data-lucide="search"></i>
                            Buscar Vagas
                        </a>
                        <a href="../candidato/candidaturas.php" class="btn btn-outline">
                            <i data-lucide="briefcase"></i>
                            Candidaturas
                        </a>
                        <a href="../candidato/perfil.php" class="btn btn-outline">
                            <i data-lucide="user"></i>
                            Perfil
                        </a>
                        <a href="../auth/logout.php" class="btn btn-outline">
                            <i data-lucide="log-out"></i>
                            Sair
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Ações para não logado -->
                    <a href="../auth/login.php" class="btn btn-outline">Entrar</a>
                    <a href="../auth/register.php" class="btn btn-primary">Criar Conta</a>
                <?php endif; ?>
                
                <button class="mobile-menu-toggle" aria-label="Menu">
                    <i data-lucide="menu"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- ==========================================
         🍞 BREADCRUMB
    ========================================== -->
    <div class="breadcrumb">
        <div class="breadcrumb-container">
            <a href="../index.php" class="breadcrumb-link">Início</a>
            <i data-lucide="chevron-right" class="breadcrumb-separator"></i>
            <span>Dashboard Empresarial</span>
        </div>
    </div>

    <!-- ==========================================
         📋 MAIN CONTENT
    ========================================== -->
    <div class="main-container">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title-section">
                <div class="page-title-content">
                    <h1 class="page-title">Dashboard Empresarial</h1>
                    <p class="page-subtitle">Bem-vindo, <?php echo htmlspecialchars($empresa['nome_empresa'] ?? 'Empresa'); ?>. Gerencie suas vagas e acompanhe as candidaturas em tempo real.</p>
                </div>
                <div class="page-actions">
                    <a href="criar_vaga.php" class="btn-primary">
                        <i data-lucide="plus-circle"></i>
                        Publicar Nova Vaga
                    </a>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($sucesso): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle"></i>
                    <?php echo htmlspecialchars($sucesso); ?>
                </div>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle"></i>
                    <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $estatisticas['vagas_ativas'] ?? 0; ?></div>
                        <div class="stat-label">Vagas Ativas</div>
                    </div>
                    <div class="stat-icon stat-icon-blue">
                        <i data-lucide="trending-up"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $estatisticas['total_candidaturas'] ?? 0; ?></div>
                        <div class="stat-label">Total de Candidaturas</div>
                    </div>
                    <div class="stat-icon stat-icon-green">
                        <i data-lucide="users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $estatisticas['candidatos_unicos'] ?? 0; ?></div>
                        <div class="stat-label">Candidatos Únicos</div>
                    </div>
                    <div class="stat-icon stat-icon-orange">
                        <i data-lucide="user-check"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $estatisticas['total_vagas'] ?? 0; ?></div>
                        <div class="stat-label">Total de Vagas</div>
                    </div>
                    <div class="stat-icon stat-icon-purple">
                        <i data-lucide="briefcase"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vagas Section -->
        <div class="section-header">
            <h2 class="section-title">
                <i data-lucide="briefcase"></i>
                Gestão de Vagas
            </h2>
        </div>

        <!-- Filtros de Vagas -->
        <?php if (!empty($vagas)): ?>
            <?php
            // Calcular estatísticas para os filtros
            $vagas_com_candidatos = 0;
            $vagas_sem_candidatos = 0;
            $vagas_ativas_count = 0;
            $vagas_inativas_count = 0;
            $vagas_expiradas_count = 0;
            
            foreach ($vagas as $vaga) {
                $hoje = new DateTime();
                $data_expiracao = new DateTime($vaga['data_expiracao']);
                $is_expirada = $data_expiracao < $hoje;
                $is_ativa = $vaga['ativa'] && !$is_expirada;
                
                if ($vaga['total_candidaturas'] > 0) {
                    $vagas_com_candidatos++;
                } else {
                    $vagas_sem_candidatos++;
                }
                
                if ($is_expirada) {
                    $vagas_expiradas_count++;
                } elseif ($is_ativa) {
                    $vagas_ativas_count++;
                } else {
                    $vagas_inativas_count++;
                }
            }
            ?>
            
            <div class="filters-container">
                <div class="filters-header">
                    <div class="filters-title">
                        <i data-lucide="filter"></i>
                        Filtrar Vagas
                    </div>
                    <button class="filter-reset" id="resetFilters">
                        <i data-lucide="x"></i>
                        Limpar Filtros
                    </button>
                </div>
                
                <div class="filters-grid">
                    <!-- Filtro por Candidaturas -->
                    <div class="filter-group">
                        <div class="filter-label">Candidaturas</div>
                        <div class="filter-buttons">
                            <?php 
                            // Verificar se há parâmetro na URL para ativar filtro "Com Candidatos"
                            $filtro_candidatos_ativo = isset($_GET['filtro']) && $_GET['filtro'] === 'candidatos';
                            ?>
                            <button class="filter-btn <?php echo !$filtro_candidatos_ativo ? 'active' : ''; ?>" data-filter="candidaturas" data-value="todos">
                                <i data-lucide="list"></i>
                                Todas
                                <span class="badge-count"><?php echo count($vagas); ?></span>
                            </button>
                            <button class="filter-btn <?php echo $filtro_candidatos_ativo ? 'active' : ''; ?>" data-filter="candidaturas" data-value="com">
                                <i data-lucide="users"></i>
                                Com Candidatos
                                <span class="badge-count"><?php echo $vagas_com_candidatos; ?></span>
                            </button>
                            <button class="filter-btn" data-filter="candidaturas" data-value="sem">
                                <i data-lucide="user-x"></i>
                                Sem Candidatos
                                <span class="badge-count"><?php echo $vagas_sem_candidatos; ?></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Filtro por Status -->
                    <div class="filter-group">
                        <div class="filter-label">Status</div>
                        <div class="filter-buttons">
                            <button class="filter-btn active" data-filter="status" data-value="todos">
                                <i data-lucide="list"></i>
                                Todos
                            </button>
                            <button class="filter-btn" data-filter="status" data-value="ativa">
                                <i data-lucide="check-circle"></i>
                                Ativas
                                <span class="badge-count"><?php echo $vagas_ativas_count; ?></span>
                            </button>
                            <button class="filter-btn" data-filter="status" data-value="inativa">
                                <i data-lucide="pause-circle"></i>
                                Inativas
                                <span class="badge-count"><?php echo $vagas_inativas_count; ?></span>
                            </button>
                            <button class="filter-btn" data-filter="status" data-value="expirada">
                                <i data-lucide="alert-circle"></i>
                                Expiradas
                                <span class="badge-count"><?php echo $vagas_expiradas_count; ?></span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="filter-stats">
                    <div class="filter-stat-item">
                        <span>Mostrando:</span>
                        <span class="filter-stat-value" id="vagasVisiveis"><?php echo count($vagas); ?></span>
                        <span>de <?php echo count($vagas); ?> vagas</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Vagas List -->
        <?php if (empty($vagas)): ?>
            <div class="empty-state">
                <i data-lucide="inbox" class="empty-state-icon"></i>
                <h3 class="empty-state-title">Nenhuma vaga criada ainda</h3>
                <p class="empty-state-text">Comece criando sua primeira vaga de emprego para encontrar os melhores talentos para sua empresa.</p>
                <a href="criar_vaga.php" class="btn-primary" style="display: inline-flex;">
                    <i data-lucide="plus-circle"></i>
                    Criar Primeira Vaga
                </a>
            </div>
        <?php else: ?>
            <div class="vagas-grid">
                <?php foreach ($vagas as $vaga): ?>
                    <?php
                    $hoje = new DateTime();
                    $data_expiracao = new DateTime($vaga['data_expiracao']);
                    $is_expirada = $data_expiracao < $hoje;
                    $is_ativa = $vaga['ativa'] && !$is_expirada;
                    
                    // Determinar status para filtro
                    if ($is_expirada) {
                        $status_filter = 'expirada';
                    } elseif ($is_ativa) {
                        $status_filter = 'ativa';
                    } else {
                        $status_filter = 'inativa';
                    }
                    
                    // Determinar se tem candidatos
                    $tem_candidatos = $vaga['total_candidaturas'] > 0 ? 'com' : 'sem';
                    ?>
                    
                    <div class="vaga-card" 
                         data-candidaturas="<?php echo $tem_candidatos; ?>"
                         data-status="<?php echo $status_filter; ?>"
                         data-total-candidaturas="<?php echo $vaga['total_candidaturas']; ?>">
                        <div class="vaga-header">
                            <div class="vaga-title-area">
                                <h3 class="vaga-title"><?php echo htmlspecialchars($vaga['titulo']); ?></h3>
                                <div class="vaga-meta">
                                    <div class="meta-item">
                                        <i data-lucide="map-pin"></i>
                                        <?php echo htmlspecialchars($vaga['localizacao']); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i data-lucide="briefcase"></i>
                                        <?php echo htmlspecialchars($vaga['tipo_contrato']); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i data-lucide="calendar"></i>
                                        <?php echo date('d/m/Y', strtotime($vaga['data_publicacao'])); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i data-lucide="clock"></i>
                                        Expira: <?php echo date('d/m/Y', strtotime($vaga['data_expiracao'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="vaga-status">
                                <?php if ($is_expirada): ?>
                                    <span class="status-badge badge-expired">
                                        <i data-lucide="alert-circle"></i>
                                        Expirada
                                    </span>
                                <?php elseif ($is_ativa): ?>
                                    <span class="status-badge badge-active">
                                        <i data-lucide="check-circle"></i>
                                        Ativa
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge badge-inactive">
                                        <i data-lucide="pause-circle"></i>
                                        Inativa
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="vaga-stats">
                            <div class="vaga-stat-item">
                                <div class="vaga-stat-value"><?php echo $vaga['total_candidaturas'] ?? 0; ?></div>
                                <div class="vaga-stat-label">Total</div>
                            </div>
                            <div class="vaga-stat-item">
                                <div class="vaga-stat-value"><?php echo $vaga['candidaturas_novas'] ?? 0; ?></div>
                                <div class="vaga-stat-label">Novas</div>
                            </div>
                            <div class="vaga-stat-item">
                                <div class="vaga-stat-value"><?php echo $vaga['em_analise'] ?? 0; ?></div>
                                <div class="vaga-stat-label">Análise</div>
                            </div>
                            <div class="vaga-stat-item">
                                <div class="vaga-stat-value"><?php echo $vaga['entrevistas'] ?? 0; ?></div>
                                <div class="vaga-stat-label">Entrevistas</div>
                            </div>
                            <div class="vaga-stat-item">
                                <div class="vaga-stat-value"><?php echo $vaga['contratados'] ?? 0; ?></div>
                                <div class="vaga-stat-label">Contratados</div>
                            </div>
                        </div>

                        <div class="vaga-actions">
                            <a href="candidaturas.php?vaga_id=<?php echo $vaga['id']; ?>" class="btn-action-primary">
                                <i data-lucide="users"></i>
                                Ver Candidaturas
                            </a>
                            <a href="editar_vaga.php?id=<?php echo $vaga['id']; ?>" class="btn-action">
                                <i data-lucide="edit"></i>
                                Editar
                            </a>
                            
                            <form method="POST" style="display: inline;" 
                                  class="form-toggle-vaga"
                                  data-vaga="<?php echo htmlspecialchars($vaga['titulo']); ?>"
                                  data-ativa="<?php echo $is_ativa ? '1' : '0'; ?>">
                                <input type="hidden" name="vaga_id" value="<?php echo $vaga['id']; ?>">
                                <?php if ($is_ativa): ?>
                                    <button type="submit" name="acao" value="desativar" class="btn-action btn-action-warning">
                                        <i data-lucide="pause-circle"></i>
                                        Desativar
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="acao" value="reativar" class="btn-action">
                                        <i data-lucide="play-circle"></i>
                                        Reativar
                                    </button>
                                <?php endif; ?>
                            </form>

                            <?php if ($vaga['total_candidaturas'] == 0): ?>
                                <form method="POST" style="display: inline;" 
                                      class="form-excluir-vaga-empresa"
                                      data-vaga="<?php echo htmlspecialchars($vaga['titulo']); ?>">
                                    <input type="hidden" name="vaga_id" value="<?php echo $vaga['id']; ?>">
                                    <button type="submit" name="acao" value="excluir" class="btn-action btn-action-danger">
                                        <i data-lucide="trash-2"></i>
                                        Excluir
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- ==========================================
         🦶 FOOTER
    ========================================== -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-column">
                    <img src="../assets/images/empregos-logo.svg" alt="Emprego MZ" class="footer-logo">
                    <p class="footer-description">
                        A maior plataforma de empregos de Moçambique. Conectamos talentos com oportunidades em todas as províncias.
                    </p>
                    <div class="footer-social">
                        <a href="#" class="social-link" aria-label="Facebook">
                            <i data-lucide="facebook"></i>
                        </a>
                        <a href="#" class="social-link" aria-label="Twitter">
                            <i data-lucide="twitter"></i>
                        </a>
                        <a href="#" class="social-link" aria-label="LinkedIn">
                            <i data-lucide="linkedin"></i>
                        </a>
                        <a href="#" class="social-link" aria-label="Instagram">
                            <i data-lucide="instagram"></i>
                        </a>
                    </div>
                </div>

                <?php 
                // Mostrar "Para Candidatos" apenas se não for empresa
                if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'empresa'): 
                ?>
                <div class="footer-column">
                    <h4>Para Candidatos</h4>
                    <a href="../vagas.php" class="footer-link">Buscar Vagas</a>
                    <a href="../candidato/perfil.php" class="footer-link">Meu Perfil</a>
                    <a href="../candidato/candidaturas.php" class="footer-link">Candidaturas</a>
                </div>
                <?php endif; ?>

                <?php 
                // Mostrar "Para Empresas" apenas se não for candidato
                if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'candidato'): 
                ?>
                <div class="footer-column">
                    <h4>Para Empresas</h4>
                    <a href="dashboard.php" class="footer-link">Dashboard</a>
                    <a href="criar_vaga.php" class="footer-link">Publicar Vaga</a>
                    <a href="candidaturas.php" class="footer-link">Candidatos</a>
                    <a href="../auth/register.php" class="footer-link">Criar Conta</a>
                </div>
                <?php endif; ?>

                <div class="footer-column">
                    <h4>Empresa</h4>
                    <a href="../sobre.php" class="footer-link">Sobre Nós</a>
                    <a href="../contacto.php" class="footer-link">Contacto</a>
                    <a href="../termos.php" class="footer-link">Termos de Uso</a>
                    <a href="../privacidade.php" class="footer-link">Privacidade</a>
                </div>
            </div>

            <div class="footer-bottom">
                <div>&copy; <?php echo date('Y'); ?> Emprego MZ. Todos os direitos reservados.</div>
            </div>
        </div>
    </footer>

    <!-- ==========================================
         ✨ SCRIPTS
    ========================================== -->
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Header scroll effect
        window.addEventListener('scroll', () => {
            const header = document.querySelector('.header');
            if (window.scrollY > 20) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Reinitialize Lucide icons after dynamic content
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
        });
    </script>

    <!-- 🎭 Sistema de Modal de Confirmação -->
    <link rel="stylesheet" href="../assets/css/modal-confirm.css">
    <script src="../assets/js/modal-confirm.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                // Formulários de ativar/desativar vaga
                document.querySelectorAll('.form-toggle-vaga').forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const vaga = this.getAttribute('data-vaga');
                        const isAtiva = this.getAttribute('data-ativa') === '1';
                        const formElement = this;
                        
                        if (typeof window.confirmModal === 'function') {
                            window.confirmModal({
                                type: 'warning',
                                title: isAtiva ? 'Desativar Vaga' : 'Reativar Vaga',
                                message: isAtiva ? 'A vaga não aparecerá mais nas buscas públicas. Você pode reativá-la a qualquer momento.' : 'A vaga voltará a aparecer nas buscas públicas.',
                                highlightTitle: 'Vaga: ' + vaga,
                                confirmText: isAtiva ? 'Sim, Desativar' : 'Sim, Reativar',
                                confirmIcon: isAtiva ? 'pause-circle' : 'play-circle',
                                onConfirm: function() { 
                                    formElement.submit(); 
                                }
                            });
                        } else {
                            if (confirm((isAtiva ? 'Desativar' : 'Ativar') + ' vaga: ' + vaga + '?')) {
                                formElement.submit();
                            }
                        }
                    });
                });

                // Formulários de excluir vaga
                document.querySelectorAll('.form-excluir-vaga-empresa').forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const vaga = this.getAttribute('data-vaga');
                        const formElement = this;
                        
                        if (typeof window.confirmModal === 'function') {
                            window.confirmModal({
                                type: 'danger',
                                title: 'Excluir Vaga',
                                subtitle: 'Esta ação não pode ser desfeita',
                                message: 'Tem certeza que deseja excluir esta vaga permanentemente?',
                                highlightTitle: 'Vaga: ' + vaga,
                                highlightText: 'Todos os dados associados a esta vaga serão removidos.',
                                confirmText: 'Sim, Excluir Permanentemente',
                                confirmIcon: 'trash-2',
                                onConfirm: function() { 
                                    formElement.submit(); 
                                }
                            });
                        } else {
                            if (confirm('Excluir vaga: ' + vaga + '?')) {
                                formElement.submit();
                            }
                        }
                    });
                });
            }, 300);
        });
    </script>
    
    <!-- 🔍 Sistema de Filtros de Vagas -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterBtns = document.querySelectorAll('.filter-btn');
            const vagasCards = document.querySelectorAll('.vaga-card');
            const resetBtn = document.getElementById('resetFilters');
            const vagasVisiveisEl = document.getElementById('vagasVisiveis');
            
            // Verificar se há parâmetro na URL para ativar filtro
            const urlParams = new URLSearchParams(window.location.search);
            const filtroCandidatos = urlParams.get('filtro');
            
            // Estado dos filtros
            let filtros = {
                candidaturas: filtroCandidatos === 'candidatos' ? 'com' : 'todos',
                status: 'todos'
            };
            
            // Se o filtro vier da URL, aplicar automaticamente ao carregar
            if (filtroCandidatos === 'candidatos') {
                // Remover classe 'active' de todos os botões de candidaturas
                document.querySelectorAll('[data-filter="candidaturas"]').forEach(function(b) {
                    b.classList.remove('active');
                });
                
                // Adicionar classe 'active' ao botão "Com Candidatos"
                const btnComCandidatos = document.querySelector('[data-filter="candidaturas"][data-value="com"]');
                if (btnComCandidatos) {
                    btnComCandidatos.classList.add('active');
                }
            }
            
            // Função para aplicar filtros
            function aplicarFiltros() {
                let visiveisCount = 0;
                
                vagasCards.forEach(function(card) {
                    const candidaturas = card.getAttribute('data-candidaturas');
                    const status = card.getAttribute('data-status');
                    
                    let mostrar = true;
                    
                    // Aplicar filtro de candidaturas
                    if (filtros.candidaturas !== 'todos' && candidaturas !== filtros.candidaturas) {
                        mostrar = false;
                    }
                    
                    // Aplicar filtro de status
                    if (filtros.status !== 'todos' && status !== filtros.status) {
                        mostrar = false;
                    }
                    
                    // Mostrar ou esconder card
                    if (mostrar) {
                        card.style.display = 'block';
                        visiveisCount++;
                        
                        // Animação suave de entrada
                        setTimeout(function() {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        }, 10);
                    } else {
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(-10px)';
                        setTimeout(function() {
                            card.style.display = 'none';
                        }, 200);
                    }
                });
                
                // Atualizar contador
                if (vagasVisiveisEl) {
                    vagasVisiveisEl.textContent = visiveisCount;
                }
                
                // Reinicializar ícones Lucide
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
            
            // Event listeners para os botões de filtro
            filterBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const filterType = this.getAttribute('data-filter');
                    const filterValue = this.getAttribute('data-value');
                    
                    // Remover classe 'active' de todos os botões do mesmo grupo
                    document.querySelectorAll(`[data-filter="${filterType}"]`).forEach(function(b) {
                        b.classList.remove('active');
                    });
                    
                    // Adicionar classe 'active' ao botão clicado
                    this.classList.add('active');
                    
                    // Atualizar filtro
                    filtros[filterType] = filterValue;
                    
                    // Aplicar filtros
                    aplicarFiltros();
                });
            });
            
            // Event listener para resetar filtros
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    // Resetar estado dos filtros
                    filtros = {
                        candidaturas: 'todos',
                        status: 'todos'
                    };
                    
                    // Remover classe 'active' de todos os botões
                    filterBtns.forEach(function(btn) {
                        btn.classList.remove('active');
                    });
                    
                    // Adicionar classe 'active' aos botões "Todos"
                    document.querySelectorAll('[data-value="todos"]').forEach(function(btn) {
                        btn.classList.add('active');
                    });
                    
                    // Aplicar filtros
                    aplicarFiltros();
                });
            }
            
            // Adicionar transições CSS aos cards
            vagasCards.forEach(function(card) {
                card.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            });
            
            // Se o filtro vier da URL, aplicar automaticamente ao carregar
            if (filtroCandidatos === 'candidatos') {
                aplicarFiltros();
            }
        });
    </script>
    
    <!-- 🔒 Proteção de Sessão - Encerra ao fechar aba -->
    <script src="../assets/js/session-guard.js"></script>

</body>
</html>
