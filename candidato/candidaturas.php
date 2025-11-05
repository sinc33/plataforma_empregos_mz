<?php
session_start();
require_once '../config/db.php';

// Verificar se está logado e é candidato
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidato') {
    header("Location: ../auth/login.php");
    exit;
}

$candidato_id = $_SESSION['user_id'];

// ========================================
// FILTROS
// ========================================
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// ========================================
// BUSCAR CANDIDATURAS
// ========================================
$sql = "SELECT c.*, v.titulo, v.descricao, v.area, v.localizacao, v.salario_estimado, v.modalidade,
               e.nome_empresa, e.logotipo
        FROM candidatura c
        JOIN vaga v ON c.vaga_id = v.id
        JOIN empresa e ON v.empresa_id = e.id
        WHERE c.candidato_id = ?";

$params = [$candidato_id];

if (!empty($filtro_estado)) {
    $sql .= " AND c.estado = ?";
    $params[] = $filtro_estado;
}

$sql .= " ORDER BY c.data_candidatura DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$candidaturas = $stmt->fetchAll();

// ========================================
// ESTATÍSTICAS
// ========================================
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'submetida' THEN 1 ELSE 0 END) as submetidas,
                SUM(CASE WHEN estado = 'em_analise' THEN 1 ELSE 0 END) as em_analise,
                SUM(CASE WHEN estado = 'entrevista' THEN 1 ELSE 0 END) as entrevistas,
                SUM(CASE WHEN estado = 'contratado' THEN 1 ELSE 0 END) as contratados,
                SUM(CASE WHEN estado = 'rejeitada' THEN 1 ELSE 0 END) as rejeitadas
              FROM candidatura
              WHERE candidato_id = ?";

$stmt_stats = $pdo->prepare($stats_sql);
$stmt_stats->execute([$candidato_id]);
$stats = $stmt_stats->fetch();

// Funções auxiliares
function formatarSalario($salario) {
    if (empty($salario) || $salario == 0) {
        return 'À combinar';
    }
    return number_format($salario, 2, ',', '.') . ' MT';
}

function traduzirEstado($estado) {
    $estados = [
        'submetida' => 'Submetida',
        'em_analise' => 'Em Análise',
        'entrevista' => 'Entrevista Agendada',
        'rejeitada' => 'Não Selecionado',
        'contratado' => 'Contratado'
    ];
    return $estados[$estado] ?? $estado;
}

function getCorEstado($estado) {
    $cores = [
        'submetida' => '#0088CC',
        'em_analise' => '#FF8C00',
        'entrevista' => '#10B981',
        'rejeitada' => '#EF4444',
        'contratado' => '#6F42C1'
    ];
    return $cores[$estado] ?? '#6B7280';
}

function getIconeEstado($estado) {
    $icones = [
        'submetida' => 'send',
        'em_analise' => 'eye',
        'entrevista' => 'calendar',
        'rejeitada' => 'x-circle',
        'contratado' => 'check-circle'
    ];
    return $icones[$estado] ?? 'file-text';
}

function traduzirModalidade($modalidade) {
    $traducoes = [
        'presencial' => 'Presencial',
        'hibrido' => 'Híbrido',
        'remoto' => 'Remoto'
    ];
    return $traducoes[$modalidade] ?? $modalidade;
}

function tempoDecorrido($data) {
    $agora = new DateTime();
    $candidatura = new DateTime($data);
    $diferenca = $agora->diff($candidatura);
    
    if ($diferenca->days == 0) {
        return 'Hoje';
    } elseif ($diferenca->days == 1) {
        return 'Ontem';
    } elseif ($diferenca->days < 7) {
        return 'Há ' . $diferenca->days . ' dias';
    } elseif ($diferenca->days < 30) {
        $semanas = floor($diferenca->days / 7);
        return 'Há ' . $semanas . ($semanas > 1 ? ' semanas' : ' semana');
    } else {
        $meses = floor($diferenca->days / 30);
        return 'Há ' . $meses . ($meses > 1 ? ' meses' : ' mês');
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Candidaturas | Emprego MZ</title>
    
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
            /* === 🎨 PALETA DEFINITIVA PROFISSIONAL PARA CANDIDATURAS === */
            
            /* 🔵 CORES PRINCIPAIS */
            --cor-primaria: #14213d;              /* Oxford Blue - Headers, títulos principais */
            --cor-primaria-escura: #0f1a2e;       /* Oxford Blue Dark - Hover intenso em botões */
            --cor-primaria-clara: #1e2c47;        /* Oxford Blue Light - Elementos secundários */
            --cor-primaria-alpha: rgba(20, 33, 61, 0.1);
            
            /* 🟠 CORES SECUNDÁRIAS */
            --cor-secundaria: #fca311;            /* Orange Web - Botões de ação, destaques */
            --cor-secundaria-hover: #e3940f;      /* Orange Hover */
            --cor-secundaria-clara: #fdb541;      /* Orange Light */
            --cor-secundaria-muito-clara: #fec871; /* Orange Very Light */
            --cor-secundaria-alpha: rgba(252, 163, 17, 0.1);
            
            /* 🌫️ HIERARQUIA VISUAL - Soft Gray System */
            --cor-fundo: #f8fafc;                 /* Soft Gray - Fundo geral da página */
            --cor-cards: #ffffff;                 /* White - Cards, candidaturas, modais */
            --cor-acento: #f1f5ff;                /* Light Blue - Hover states, filtros ativos */
            --cor-acento-escuro: #e1ebff;         /* Light Blue Dark - Bordas ativas, filtros selecionados */
            
            /* 📝 TEXTO - Hierarquia Refinada */
            --cor-texto: #1a202c;                 /* Títulos, informações principais */
            --cor-texto-claro: #64748b;           /* Texto secundário, descrições */
            --cor-texto-muito-claro: #94a3b8;     /* Placeholders, metadados */
            
            /* 🎯 STATUS DAS CANDIDATURAS */
            --cor-pendente: #F59E0B;              /* Laranja para candidaturas pendentes (submetida) */
            --cor-pendente-light: #FEF3C7;        /* Fundo suave para pendentes */
            --cor-aceita: #10B981;                /* Verde para candidaturas aceitas (contratado) */
            --cor-aceita-light: #D1FAE5;          /* Fundo suave para aceitas */
            --cor-rejeitada: #EF4444;             /* Vermelho para candidaturas rejeitadas */
            --cor-rejeitada-light: #FEE2E2;       /* Fundo suave para rejeitadas */
            --cor-em-analise: #8B5CF6;            /* Roxo para em análise */
            --cor-em-analise-light: #EDE9FE;      /* Fundo suave para em análise */
            --cor-entrevista: #06B6D4;            /* Azul para entrevista marcada */
            --cor-entrevista-light: #CFFAFE;      /* Fundo suave para entrevista */
            
            /* 🎯 ELEMENTOS ESPECÍFICOS */
            --cor-nova-candidatura: #10B981;      /* Badge nova candidatura */
            --cor-prazo-vencendo: #EF4444;        /* Avisos de prazo */
            --cor-destaque: var(--cor-secundaria); /* Elementos em destaque */
            
            /* 🎨 BORDAS E SOMBRAS */
            --cor-borda: #e2e8f0;                 /* Bordas padrão */
            --cor-borda-ativa: var(--cor-primaria); /* Bordas em foco */
            --sombra-card: 0 4px 20px rgba(20, 33, 61, 0.08);
            --sombra-filtro: 0 2px 12px rgba(20, 33, 61, 0.06);
            --sombra-status: 0 2px 8px rgba(0, 0, 0, 0.1);
            --sombra-input-focus: 0 0 0 4px rgba(20, 33, 61, 0.1);
            
            /* 🔄 Aliases para compatibilidade */
            --primary: var(--cor-primaria);
            --primary-dark: var(--cor-primaria-escura);
            --primary-light: var(--cor-primaria-clara);
            --secondary: var(--cor-secundaria);
            --secondary-dark: var(--cor-secundaria-hover);
            --success: var(--cor-aceita);
            --error: var(--cor-rejeitada);
            --warning: var(--cor-pendente);
            --info: var(--cor-em-analise);
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
        }
        
        /* ==========================================
           📱 HEADER PROFISSIONAL - Oxford Blue (Igual outros arquivos)
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
            align-items: center;
            gap: var(--space-1);
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
            background: linear-gradient(135deg, var(--cor-secundaria) 0%, #e3940f 100%);
            color: var(--white);
            border: none;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: 0.3px;
            border-radius: var(--radius);
            box-shadow: 0 2px 8px rgba(252, 163, 17, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #e3940f 0%, var(--cor-secundaria) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(252, 163, 17, 0.4);
            text-decoration: none;
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(252, 163, 17, 0.3);
        }

        .btn-primary i {
            width: 18px;
            height: 18px;
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

        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            margin-bottom: var(--space-2);
        }

        .page-subtitle {
            font-size: 16px;
            color: var(--cor-texto-claro);           /* #64748b */
        }

        /* ==========================================
           📊 STATISTICS CARDS
        ========================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: var(--space-6);
            margin-bottom: var(--space-8);
        }

        .stat-card {
            background: var(--cor-cards);            /* #ffffff - White */
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            box-shadow: var(--sombra-card);          /* Sombra profissional */
            transition: var(--transition);
            cursor: pointer;
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
        }

        .stat-card.total::before {
            background: var(--cor-primaria);         /* #14213d - Oxford Blue para total */
        }

        .stat-card.pendentes::before,
        .stat-card.submetidas::before {
            background: var(--cor-pendente);         /* #F59E0B - Laranja para pendentes */
        }

        .stat-card.aceitas::before,
        .stat-card.contratados::before {
            background: var(--cor-aceita);           /* #10B981 - Verde para aceitas */
        }

        .stat-card.rejeitadas::before {
            background: var(--cor-rejeitada);        /* #EF4444 - Vermelho para rejeitadas */
        }

        .stat-card.em-analise::before {
            background: var(--cor-em-analise);       /* #8B5CF6 - Roxo para em análise */
        }

        .stat-card.entrevistas::before {
            background: var(--cor-entrevista);       /* #06B6D4 - Azul para entrevistas */
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(20, 33, 61, 0.12);
        }

        .stat-header {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            margin-bottom: var(--space-3);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--cor-cards);                 /* White icon */
        }

        .stat-icon svg {
            width: 24px;
            height: 24px;
        }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: var(--space-2);
            display: block;
        }

        .stat-card.total .stat-number {
            color: var(--cor-primaria);              /* Oxford Blue para total */
        }

        .stat-card.pendentes .stat-number,
        .stat-card.submetidas .stat-number {
            color: var(--cor-pendente);              /* Laranja para pendentes */
        }

        .stat-card.aceitas .stat-number,
        .stat-card.contratados .stat-number {
            color: var(--cor-aceita);                /* Verde para aceitas */
        }

        .stat-card.rejeitadas .stat-number {
            color: var(--cor-rejeitada);             /* Vermelho para rejeitadas */
        }

        .stat-card.em-analise .stat-number {
            color: var(--cor-em-analise);            /* Roxo para em análise */
        }

        .stat-card.entrevistas .stat-number {
            color: var(--cor-entrevista);            /* Azul para entrevistas */
        }

        .stat-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--cor-texto-claro);           /* #64748b */
        }

        /* ==========================================
           🔍 FILTROS PROFISSIONAIS
        ========================================== */
        .filters-card {
            background: var(--cor-cards);            /* #ffffff - White */
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            margin-bottom: var(--space-8);
            box-shadow: var(--sombra-filtro);       /* Sombra específica para filtros */
        }

        .filters-header {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-bottom: var(--space-5);
            padding-bottom: var(--space-4);
            border-bottom: 2px solid var(--cor-acento); /* #f1f5ff - Light Blue */
        }

        .filters-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
        }

        .filters-header svg {
            width: 20px;
            height: 20px;
            color: var(--cor-secundaria);            /* #fca311 - Orange */
        }

        .filter-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-3);
        }

        .filter-tab {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-5);
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            border: 2px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: var(--radius-full);
            color: var(--cor-texto-claro);           /* #64748b */
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition);
        }

        .filter-tab:hover {
            border-color: var(--cor-primaria);       /* #14213d - Oxford Blue */
            color: var(--cor-primaria);
            background: var(--cor-acento-escuro);    /* #e1ebff */
            transform: translateY(-1px);
        }

        .filter-tab.active {
            background: var(--cor-secundaria);       /* #fca311 - Orange */
            border-color: var(--cor-secundaria);
            color: var(--cor-cards);                 /* White */
            box-shadow: var(--sombra-status);
            position: relative;
        }
        
        .filter-tab.active::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background: var(--cor-secundaria);
            border-radius: 50%;
        }

        /* ==========================================
           🔍 SEARCH BOX
        ========================================== */
        .search-container {
            margin-bottom: var(--space-5);
        }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
            background: var(--cor-fundo);
            border: 2px solid var(--cor-borda);
            border-radius: var(--radius-lg);
            padding: var(--space-2) var(--space-4);
            transition: var(--transition);
        }

        .search-box:focus-within {
            border-color: var(--cor-secundaria);
            box-shadow: 0 0 0 3px var(--cor-secundaria-alpha);
        }

        .search-box svg {
            width: 20px;
            height: 20px;
            color: var(--cor-texto-claro);
            margin-right: var(--space-2);
        }

        .search-input {
            flex: 1;
            border: none;
            background: transparent;
            font-size: 15px;
            color: var(--cor-texto);
            outline: none;
            padding: var(--space-2) 0;
        }

        .search-input::placeholder {
            color: var(--cor-texto-muito-claro);
        }

        .search-clear {
            background: none;
            border: none;
            color: var(--cor-texto-claro);
            cursor: pointer;
            padding: var(--space-1);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius);
            transition: var(--transition);
            margin-left: var(--space-2);
        }

        .search-clear:hover {
            background: var(--cor-acento);
            color: var(--cor-texto);
        }

        .search-clear svg {
            width: 18px;
            height: 18px;
        }

        .filter-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            padding: 0 var(--space-2);
            background: rgba(0, 0, 0, 0.1);
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 700;
        }

        .filter-tab.active .filter-count {
            background: rgba(255, 255, 255, 0.3);
        }

        /* ==========================================
           💼 CANDIDATURA CARDS
        ========================================== */
        .candidaturas-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-6);
        }

        .candidatura-card {
            background: var(--cor-cards);            /* #ffffff - White */
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            box-shadow: var(--sombra-card);          /* Sombra profissional */
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .candidatura-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }

        .candidatura-card.submetida::before {
            background: var(--cor-pendente);         /* #F59E0B - Laranja */
        }

        .candidatura-card.em_analise::before {
            background: var(--cor-em-analise);       /* #8B5CF6 - Roxo */
        }

        .candidatura-card.entrevista::before {
            background: var(--cor-entrevista);       /* #06B6D4 - Azul */
        }

        .candidatura-card.contratado::before {
            background: var(--cor-aceita);           /* #10B981 - Verde */
        }

        .candidatura-card.rejeitada::before {
            background: var(--cor-rejeitada);        /* #EF4444 - Vermelho */
        }

        .candidatura-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(20, 33, 61, 0.12);
            border-color: var(--cor-secundaria);
        }


        .candidatura-header {
            display: flex;
            gap: var(--space-5);
            margin-bottom: var(--space-5);
        }

        .empresa-logo {
            width: 72px;
            height: 72px;
            flex-shrink: 0;
            border-radius: var(--radius-lg);
            overflow: hidden;
            background: var(--gray-50);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empresa-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .candidatura-main {
            flex: 1;
            min-width: 0;
        }

        .candidatura-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--space-4);
            margin-bottom: var(--space-3);
        }

        .candidatura-title-area {
            flex: 1;
            min-width: 0;
        }

        .candidatura-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            margin-bottom: var(--space-1);
            transition: var(--transition);
        }

        .candidatura-title:hover {
            color: var(--cor-secundaria);            /* #fca311 - Orange */
        }

        .candidatura-empresa {
            font-size: 15px;
            font-weight: 600;
            color: var(--cor-texto-claro);           /* #64748b */
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--sombra-status);
            border: 2px solid;
            white-space: nowrap;
        }

        .status-badge.submetida {
            background: var(--cor-pendente-light);   /* #FEF3C7 - Fundo laranja claro */
            color: var(--cor-pendente);              /* #F59E0B - Laranja */
            border-color: var(--cor-pendente);
        }

        .status-badge.em_analise {
            background: var(--cor-em-analise-light); /* #EDE9FE - Fundo roxo claro */
            color: var(--cor-em-analise);            /* #8B5CF6 - Roxo */
            border-color: var(--cor-em-analise);
        }

        .status-badge.entrevista {
            background: var(--cor-entrevista-light); /* #CFFAFE - Fundo azul claro */
            color: var(--cor-entrevista);            /* #06B6D4 - Azul */
            border-color: var(--cor-entrevista);
        }

        .status-badge.contratado {
            background: var(--cor-aceita-light);     /* #D1FAE5 - Fundo verde claro */
            color: var(--cor-aceita);                /* #10B981 - Verde */
            border-color: var(--cor-aceita);
        }

        .status-badge.rejeitada {
            background: var(--cor-rejeitada-light);  /* #FEE2E2 - Fundo vermelho claro */
            color: var(--cor-rejeitada);             /* #EF4444 - Vermelho */
            border-color: var(--cor-rejeitada);
        }

        .status-badge svg {
            width: 16px;
            height: 16px;
        }

        .candidatura-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-5);
            padding: var(--space-4) 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            margin-bottom: var(--space-4);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 14px;
            font-weight: 500;
            color: var(--cor-texto-claro);           /* #64748b */
        }

        .meta-item svg {
            width: 16px;
            height: 16px;
            color: var(--cor-secundaria);            /* #fca311 - Orange para ícones */
            flex-shrink: 0;
        }

        .candidatura-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-4);
        }

        .candidatura-time {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 13px;
            color: var(--cor-texto-muito-claro);     /* #94a3b8 */
        }

        .candidatura-time svg {
            width: 16px;
            height: 16px;
        }

        .candidatura-actions {
            display: flex;
            gap: var(--space-3);
        }

        .btn-action {
            padding: var(--space-2) var(--space-4);
            border: 2px solid var(--cor-borda);      /* #e2e8f0 */
            background: var(--cor-cards);            /* #ffffff */
            color: var(--cor-texto-claro);           /* #64748b */
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
        }

        .btn-action:hover {
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            border-color: var(--cor-primaria);       /* #14213d - Oxford Blue */
            color: var(--cor-primaria);
            transform: translateY(-1px);
        }

        .btn-action-primary {
            padding: var(--space-2) var(--space-4);
            border: 2px solid var(--cor-borda);      /* #e2e8f0 */
            background: var(--cor-cards);            /* #ffffff */
            color: var(--cor-texto-claro);           /* #64748b */
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
        }

        .btn-action-primary:hover {
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            border-color: var(--cor-primaria);       /* #14213d - Oxford Blue */
            color: var(--cor-primaria);
            transform: translateY(-1px);
        }

        .btn-action-primary i {
            width: 16px;
            height: 16px;
        }

        .btn-action-secondary {
            background: var(--cor-secundaria);       /* #fca311 - Orange */
            border-color: var(--cor-secundaria);
            color: var(--cor-cards);                 /* White */
        }

        .btn-action-secondary:hover {
            background: var(--cor-secundaria-hover); /* #e3940f */
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(252, 163, 17, 0.3);
        }

        /* ==========================================
           ⚠️ EMPTY STATE PROFISSIONAL
        ========================================== */
        .empty-state {
            background: var(--cor-cards);            /* #ffffff - White */
            border: 2px dashed var(--cor-borda);     /* #e2e8f0 */
            border-radius: var(--radius-2xl);
            padding: var(--space-20);
            text-align: center;
            box-shadow: var(--sombra-card);
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto var(--space-6);
            color: var(--cor-texto-muito-claro);     /* #94a3b8 */
        }

        .empty-state-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            margin-bottom: var(--space-3);
        }

        .empty-state-text {
            font-size: 16px;
            color: var(--cor-texto-claro);           /* #64748b */
            margin-bottom: var(--space-8);
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        .empty-action {
            background: var(--cor-secundaria);       /* #fca311 - Orange */
            color: var(--cor-cards);                 /* White */
            border: none;
            border-radius: var(--radius-lg);
            padding: var(--space-4) var(--space-8);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }

        .empty-action:hover {
            background: var(--cor-secundaria-hover); /* #e3940f */
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(252, 163, 17, 0.4);
        }

        /* ==========================================
           📋 MODAL PROFISSIONAL
        ========================================== */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(20, 33, 61, 0.8);       /* Oxford Blue com transparência */
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: var(--space-6);
            animation: fadeIn 0.2s ease-out;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: var(--cor-cards);            /* #ffffff - White */
            border-radius: var(--radius-xl);
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(20, 33, 61, 0.3);
            scroll-behavior: smooth;
            animation: slideUp 0.3s ease-out;
        }

        .modal-header {
            padding: var(--space-6);
            border-bottom: 2px solid var(--cor-acento); /* #f1f5ff - Light Blue */
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            flex: 1;
            padding-right: var(--space-4);
        }

        .modal-close {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            border: none;
            color: var(--cor-texto-claro);           /* #64748b */
            cursor: pointer;
            border-radius: var(--radius);
            transition: var(--transition);
            flex-shrink: 0;
        }

        .modal-close:hover {
            background: var(--cor-rejeitada);        /* #EF4444 - Vermelho */
            color: var(--cor-cards);                 /* White */
        }

        .modal-close svg {
            width: 24px;
            height: 24px;
        }

        .modal-body {
            padding: var(--space-6);
        }

        .modal-section {
            margin-bottom: var(--space-6);
        }

        .modal-section:last-child {
            margin-bottom: 0;
        }

        .modal-section-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: var(--space-3);
        }

        .modal-section-content {
            font-size: 14px;
            color: var(--text-light);
            line-height: 1.7;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-5);
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: var(--space-1);
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-lighter);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
        }

        /* ==========================================
           💡 TIPS SECTION
        ========================================== */
        .tips-section {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-8);
            margin-top: var(--space-8);
            box-shadow: var(--shadow);
        }

        .tips-header {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-bottom: var(--space-6);
        }

        .tips-header svg {
            width: 24px;
            height: 24px;
            color: var(--primary);
        }

        .tips-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
        }

        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-6);
        }

        .tip-card {
            display: flex;
            gap: var(--space-4);
            padding: var(--space-5);
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            transition: var(--transition);
        }

        .tip-card:hover {
            background: rgba(0, 136, 204, 0.05);
        }

        .tip-icon {
            width: 48px;
            height: 48px;
            background: var(--primary);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            flex-shrink: 0;
        }

        .tip-icon svg {
            width: 24px;
            height: 24px;
        }

        .tip-content {
            flex: 1;
        }

        .tip-content h4 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: var(--space-2);
        }

        .tip-content p {
            font-size: 14px;
            color: var(--text-light);
            line-height: 1.6;
        }

        /* ==========================================
           🦶 FOOTER
        ========================================== */
        .footer {
            background: var(--text);
            color: var(--white);
            padding: var(--space-12) var(--space-6) var(--space-6);
            margin-top: var(--space-20);
        }

        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-8);
            margin-bottom: var(--space-8);
        }

        .footer-column h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: var(--space-4);
        }

        .footer-links {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }

        .footer-link {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition);
        }

        .footer-link:hover {
            color: var(--white);
            padding-left: var(--space-2);
        }

        .footer-bottom {
            padding-top: var(--space-6);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
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

            .tips-grid {
                grid-template-columns: 1fr;
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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-tabs {
                flex-direction: column;
            }

            .filter-tab {
                justify-content: center;
            }

            .candidatura-header {
                flex-direction: row;
            }

            .empresa-logo {
                width: 56px;
                height: 56px;
            }

            .candidatura-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .status-badge {
                align-self: flex-start;
            }

            .candidatura-meta {
                flex-direction: column;
                gap: var(--space-3);
            }

            .candidatura-footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .candidatura-actions {
                width: 100%;
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
                justify-content: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
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
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ==========================================
           ✨ ANIMATIONS
        ========================================== */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                        <a href="perfil.php" class="dropdown-item">
                            <i data-lucide="user"></i>
                            <span>Meu Perfil</span>
                        </a>
                        <a href="vagas_guardadas.php" class="dropdown-item">
                            <i data-lucide="bookmark"></i>
                            <span>Vagas Guardadas</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="candidaturas.php" class="dropdown-item">
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
                        <a href="../empresa/dashboard.php" class="dropdown-item">
                            <i data-lucide="layout-dashboard"></i>
                            <span>Painel de Controle</span>
                        </a>
                        <a href="../empresa/criar_vaga.php" class="dropdown-item">
                            <i data-lucide="plus-circle"></i>
                            <span>Publicar Vaga</span>
                        </a>
                        <a href="../empresa/candidaturas.php" class="dropdown-item">
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
                        <!-- Ações para Empresa -->
                        <a href="../empresa/dashboard.php" class="btn btn-primary">
                            <i data-lucide="layout-dashboard"></i>
                            Dashboard
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
                        <a href="candidaturas.php" class="btn btn-outline">
                            <i data-lucide="briefcase"></i>
                            Candidaturas
                        </a>
                        <a href="perfil.php" class="btn btn-outline">
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
            <a href="perfil.php" class="breadcrumb-link">Meu Perfil</a>
            <i data-lucide="chevron-right" class="breadcrumb-separator"></i>
            <span>Candidaturas</span>
        </div>
    </div>

    <!-- ==========================================
         📋 MAIN CONTENT
    ========================================== -->
    <div class="main-container">

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Minhas Candidaturas</h1>
            <p class="page-subtitle">Acompanhe o status de todas as suas candidaturas e gerencie suas oportunidades</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='candidaturas.php'">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                        <i data-lucide="briefcase"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total de Candidaturas</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='?estado=em_analise'">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--secondary), #E67600);">
                        <i data-lucide="eye"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['em_analise']; ?></div>
                        <div class="stat-label">Em Análise</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='?estado=entrevista'">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), #0D9C3D);">
                        <i data-lucide="calendar"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['entrevistas']; ?></div>
                        <div class="stat-label">Entrevistas Agendadas</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='?estado=contratado'">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #6F42C1, #5A369D);">
                        <i data-lucide="check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['contratados']; ?></div>
                        <div class="stat-label">Contratações</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <div class="filters-header">
                <i data-lucide="filter"></i>
                <h3>Filtrar Candidaturas</h3>
            </div>
            
            <!-- Search Bar -->
            <div class="search-container" style="margin-bottom: var(--space-5);">
                <div class="search-box">
                    <i data-lucide="search"></i>
                    <input type="text" id="searchInput" placeholder="Buscar por título da vaga ou nome da empresa..." class="search-input">
                    <button type="button" id="clearSearch" class="search-clear" style="display: none;">
                        <i data-lucide="x"></i>
                    </button>
                </div>
            </div>
            
            <div class="filter-tabs">
                <a href="candidaturas.php" class="filter-tab <?php echo empty($filtro_estado) ? 'active' : ''; ?>">
                    Todas
                    <span class="filter-count"><?php echo $stats['total']; ?></span>
                </a>
                <a href="?estado=submetida" class="filter-tab <?php echo $filtro_estado === 'submetida' ? 'active' : ''; ?>">
                    Submetidas
                    <span class="filter-count"><?php echo $stats['submetidas']; ?></span>
                </a>
                <a href="?estado=em_analise" class="filter-tab <?php echo $filtro_estado === 'em_analise' ? 'active' : ''; ?>">
                    Em Análise
                    <span class="filter-count"><?php echo $stats['em_analise']; ?></span>
                </a>
                <a href="?estado=entrevista" class="filter-tab <?php echo $filtro_estado === 'entrevista' ? 'active' : ''; ?>">
                    Entrevistas
                    <span class="filter-count"><?php echo $stats['entrevistas']; ?></span>
                </a>
                <a href="?estado=contratado" class="filter-tab <?php echo $filtro_estado === 'contratado' ? 'active' : ''; ?>">
                    Contratados
                    <span class="filter-count"><?php echo $stats['contratados']; ?></span>
                </a>
                <a href="?estado=rejeitada" class="filter-tab <?php echo $filtro_estado === 'rejeitada' ? 'active' : ''; ?>">
                    Não Selecionados
                    <span class="filter-count"><?php echo $stats['rejeitadas']; ?></span>
                </a>
            </div>
        </div>

        <!-- Candidaturas List -->
        <?php if (count($candidaturas) > 0): ?>
            <div class="candidaturas-list">
                <?php foreach ($candidaturas as $cand): ?>
                    <div class="candidatura-card <?php echo $cand['estado']; ?>" onclick="abrirModal(<?php echo $cand['id']; ?>)" 
                         data-titulo="<?php echo htmlspecialchars(strtolower($cand['titulo'])); ?>"
                         data-empresa="<?php echo htmlspecialchars(strtolower($cand['nome_empresa'])); ?>">
                        <div class="candidatura-header">
                            <div class="empresa-logo">
                                <?php if (!empty($cand['logotipo']) && file_exists('../uploads/' . $cand['logotipo'])): ?>
                                    <img src="../uploads/<?php echo htmlspecialchars($cand['logotipo']); ?>" 
                                         alt="<?php echo htmlspecialchars($cand['nome_empresa']); ?>">
                                <?php else: ?>
                                    <img src="../assets/images/empresa-default.png" alt="Logo">
                                <?php endif; ?>
                            </div>
                            
                            <div class="candidatura-main">
                                <div class="candidatura-top">
                                    <div class="candidatura-title-area">
                                        <h3 class="candidatura-title"><?php echo htmlspecialchars($cand['titulo']); ?></h3>
                                        <p class="candidatura-empresa"><?php echo htmlspecialchars($cand['nome_empresa']); ?></p>
                                    </div>
                                    <span class="status-badge" style="background: <?php echo getCorEstado($cand['estado']); ?>;">
                                        <i data-lucide="<?php echo getIconeEstado($cand['estado']); ?>"></i>
                                        <?php echo traduzirEstado($cand['estado']); ?>
                                    </span>
                                </div>
                                
                                <div class="candidatura-meta">
                                    <div class="meta-item">
                                        <i data-lucide="map-pin"></i>
                                        <?php echo htmlspecialchars($cand['localizacao']); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i data-lucide="monitor"></i>
                                        <?php echo traduzirModalidade($cand['modalidade']); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i data-lucide="banknote"></i>
                                        <?php echo formatarSalario($cand['salario_estimado']); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i data-lucide="briefcase"></i>
                                        <?php echo htmlspecialchars($cand['area']); ?>
                                    </div>
                                </div>
                                
                                <div class="candidatura-footer">
                                    <div class="candidatura-time">
                                        <i data-lucide="clock"></i>
                                        Candidatura enviada <?php echo tempoDecorrido($cand['data_candidatura']); ?>
                                    </div>
                                    <div class="candidatura-actions">
                                        <button class="btn-action" onclick="event.stopPropagation(); abrirModal(<?php echo $cand['id']; ?>)">
                                            <i data-lucide="eye"></i>
                                            Ver Detalhes
                                        </button>
                                        <a href="../vaga_detalhe.php?id=<?php echo $cand['vaga_id']; ?>" 
                                           class="btn-action-primary" onclick="event.stopPropagation()">
                                            <i data-lucide="external-link"></i>
                                            Ver Vaga
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal para cada candidatura -->
                    <div class="modal-overlay" id="modal_<?php echo $cand['id']; ?>">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title"><?php echo htmlspecialchars($cand['titulo']); ?></h3>
                                <button class="modal-close" onclick="fecharModal(<?php echo $cand['id']; ?>)">
                                    <i data-lucide="x"></i>
                                </button>
                            </div>
                            
                            <div class="modal-body">
                                <div class="modal-section">
                                    <span class="status-badge" style="background: <?php echo getCorEstado($cand['estado']); ?>;">
                                        <i data-lucide="<?php echo getIconeEstado($cand['estado']); ?>"></i>
                                        <?php echo traduzirEstado($cand['estado']); ?>
                                    </span>
                                </div>
                                
                                <div class="modal-section">
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <span class="info-label">Empresa</span>
                                            <span class="info-value"><?php echo htmlspecialchars($cand['nome_empresa']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Localização</span>
                                            <span class="info-value"><?php echo htmlspecialchars($cand['localizacao']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Área</span>
                                            <span class="info-value"><?php echo htmlspecialchars($cand['area']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Salário</span>
                                            <span class="info-value"><?php echo formatarSalario($cand['salario_estimado']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Data da Candidatura</span>
                                            <span class="info-value"><?php echo date('d/m/Y', strtotime($cand['data_candidatura'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Modalidade</span>
                                            <span class="info-value"><?php echo traduzirModalidade($cand['modalidade']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($cand['carta_apresentacao'])): ?>
                                    <div class="modal-section">
                                        <h4 class="modal-section-title">Sua Carta de Apresentação</h4>
                                        <div class="modal-section-content">
                                            <?php echo nl2br(htmlspecialchars($cand['carta_apresentacao'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($cand['nota_interna'])): ?>
                                    <div class="modal-section">
                                        <h4 class="modal-section-title">Observação da Empresa</h4>
                                        <div class="modal-section-content">
                                            <?php echo nl2br(htmlspecialchars($cand['nota_interna'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="modal-section">
                                    <a href="../vaga_detalhe.php?id=<?php echo $cand['vaga_id']; ?>" class="btn-primary">
                                        <i data-lucide="external-link"></i>
                                        Ver Vaga Completa
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i data-lucide="inbox" class="empty-state-icon"></i>
                <h3 class="empty-state-title">Nenhuma candidatura encontrada</h3>
                <p class="empty-state-text">
                    <?php if (!empty($filtro_estado)): ?>
                        Não foram encontradas candidaturas com o filtro aplicado. Tente remover os filtros para ver todas as suas candidaturas.
                    <?php else: ?>
                        Você ainda não se candidatou a nenhuma vaga. Explore as oportunidades disponíveis e candidate-se às vagas que combinam com seu perfil profissional.
                    <?php endif; ?>
                </p>
                <a href="../vagas.php" class="btn-primary" style="display: inline-flex; padding: 12px 24px;">
                    <i data-lucide="search"></i>
                    Explorar Vagas
                </a>
            </div>
        <?php endif; ?>

        <!-- Tips Section -->
        <div class="tips-section">
            <div class="tips-header">
                <i data-lucide="lightbulb"></i>
                <h3 class="tips-title">Dicas para Aumentar Suas Chances</h3>
            </div>
            
            <div class="tips-grid">
                <div class="tip-card">
                    <div class="tip-icon">
                        <i data-lucide="user-check"></i>
                    </div>
                    <div class="tip-content">
                        <h4>Perfil Completo</h4>
                        <p>Mantenha seu perfil atualizado com experiências, formações e competências relevantes. Empresas revisam seu histórico profissional.</p>
                    </div>
                </div>
                
                <div class="tip-card">
                    <div class="tip-icon">
                        <i data-lucide="file-text"></i>
                    </div>
                    <div class="tip-content">
                        <h4>Cartas Personalizadas</h4>
                        <p>Cartas de apresentação personalizadas demonstram interesse genuíno e aumentam suas chances de ser selecionado para entrevistas.</p>
                    </div>
                </div>
                
                <div class="tip-card">
                    <div class="tip-icon">
                        <i data-lucide="clock"></i>
                    </div>
                    <div class="tip-content">
                        <h4>Acompanhe Prazos</h4>
                        <p>Fique atento às datas de expiração das vagas e aos prazos de resposta. Candidate-se rapidamente às oportunidades relevantes.</p>
                    </div>
                </div>
            </div>
        </div>

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
                    <a href="perfil.php" class="footer-link">Meu Perfil</a>
                    <a href="candidaturas.php" class="footer-link">Candidaturas</a>
                </div>
                <?php endif; ?>

                <?php 
                // Mostrar "Para Empresas" apenas se não for candidato
                if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'candidato'): 
                ?>
                <div class="footer-column">
                    <h4>Para Empresas</h4>
                    <a href="../empresa/dashboard.php" class="footer-link">Dashboard</a>
                    <a href="../empresa/criar_vaga.php" class="footer-link">Publicar Vaga</a>
                    <a href="../empresa/candidaturas.php" class="footer-link">Candidatos</a>
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

        // Modal functions
        function abrirModal(id) {
            const modal = document.getElementById('modal_' + id);
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Reinitialize icons in modal
            setTimeout(() => {
                lucide.createIcons();
            }, 100);
        }

        function fecharModal(id) {
            const modal = document.getElementById('modal_' + id);
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    const id = this.id.replace('modal_', '');
                    fecharModal(id);
                }
            });
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    const id = modal.id.replace('modal_', '');
                    fecharModal(id);
                });
            }
        });

        // Reinitialize Lucide icons after dynamic content
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            const clearSearch = document.getElementById('clearSearch');
            const cards = document.querySelectorAll('.candidatura-card');
            
            if (searchInput) {
                // Show/hide clear button
                searchInput.addEventListener('input', function() {
                    if (this.value.length > 0) {
                        clearSearch.style.display = 'flex';
                    } else {
                        clearSearch.style.display = 'none';
                    }
                    filterCards(this.value);
                });
                
                // Clear search
                clearSearch.addEventListener('click', function() {
                    searchInput.value = '';
                    this.style.display = 'none';
                    filterCards('');
                });
                
                // Filter cards based on search
                function filterCards(searchTerm) {
                    const term = searchTerm.toLowerCase().trim();
                    let visibleCount = 0;
                    
                    cards.forEach(card => {
                        const titulo = card.getAttribute('data-titulo') || '';
                        const empresa = card.getAttribute('data-empresa') || '';
                        
                        if (titulo.includes(term) || empresa.includes(term)) {
                            card.style.display = 'block';
                            visibleCount++;
                        } else {
                            card.style.display = 'none';
                        }
                    });
                    
                    // Show empty state if no results
                    const existingEmptyState = document.querySelector('.search-empty-state');
                    
                    if (visibleCount === 0 && term.length > 0) {
                        if (!existingEmptyState) {
                            const listContainer = document.querySelector('.candidaturas-list');
                            if (listContainer) {
                                const emptyDiv = document.createElement('div');
                                emptyDiv.className = 'empty-state search-empty-state';
                                emptyDiv.style.marginTop = 'var(--space-8)';
                                emptyDiv.innerHTML = `
                                    <i data-lucide="search-x" class="empty-state-icon"></i>
                                    <h3 class="empty-state-title">Nenhuma candidatura encontrada</h3>
                                    <p class="empty-state-text">Tente buscar com outros termos</p>
                                `;
                                listContainer.appendChild(emptyDiv);
                                lucide.createIcons();
                            }
                        }
                    } else {
                        if (existingEmptyState) {
                            existingEmptyState.remove();
                        }
                    }
                }
            }
        });
    </script>
    
    <!-- 🔒 Proteção de Sessão - Encerra ao fechar aba -->
    <script src="../assets/js/session-guard.js"></script>

</body>
</html>
