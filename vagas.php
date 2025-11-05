<?php
session_start();
require_once 'config/db.php';

// ========================================
// PAGINAÇÃO
// ========================================
$vagas_por_pagina = 20;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $vagas_por_pagina;

// ========================================
// FILTROS
// ========================================
$filtro_pesquisa = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtro_localizacao = isset($_GET['local']) ? trim($_GET['local']) : '';
$filtro_area = isset($_GET['area']) ? trim($_GET['area']) : '';
$filtro_modalidade = isset($_GET['modalidade']) ? trim($_GET['modalidade']) : '';
$filtro_salario_min = isset($_GET['salario_min']) ? (float)$_GET['salario_min'] : 0;
$filtro_salario_max = isset($_GET['salario_max']) ? (float)$_GET['salario_max'] : 0;
$filtro_data = isset($_GET['data']) ? trim($_GET['data']) : '';
$ordenacao = isset($_GET['ordem']) ? $_GET['ordem'] : 'recentes';

// ========================================
// QUERY SQL COM FILTROS
// ========================================
$sql = "SELECT v.*, e.nome_empresa, e.logotipo 
        FROM vaga v 
        JOIN empresa e ON v.empresa_id = e.id 
        WHERE v.ativa = TRUE AND v.data_expiracao >= CURDATE()";

$params = [];

// Filtro de pesquisa
if (!empty($filtro_pesquisa)) {
    $sql .= " AND (v.titulo LIKE ? OR v.descricao LIKE ? OR v.area LIKE ? OR e.nome_empresa LIKE ?)";
    $termo_pesquisa = "%$filtro_pesquisa%";
    $params[] = $termo_pesquisa;
    $params[] = $termo_pesquisa;
    $params[] = $termo_pesquisa;
    $params[] = $termo_pesquisa;
}

// Filtro de localização
if (!empty($filtro_localizacao)) {
    $sql .= " AND (v.localizacao LIKE ? OR e.localizacao LIKE ?)";
    $local_pesquisa = "%$filtro_localizacao%";
    $params[] = $local_pesquisa;
    $params[] = $local_pesquisa;
}

// Filtro de área
if (!empty($filtro_area)) {
    $sql .= " AND v.area = ?";
    $params[] = $filtro_area;
}

// Filtro de modalidade
if (!empty($filtro_modalidade)) {
    $sql .= " AND v.modalidade = ?";
    $params[] = $filtro_modalidade;
}

// Filtro de salário
if ($filtro_salario_min > 0) {
    $sql .= " AND v.salario_estimado >= ?";
    $params[] = $filtro_salario_min;
}
if ($filtro_salario_max > 0) {
    $sql .= " AND v.salario_estimado <= ?";
    $params[] = $filtro_salario_max;
}

// Filtro de data
if (!empty($filtro_data)) {
    switch ($filtro_data) {
        case 'hoje':
            $sql .= " AND DATE(v.data_publicacao) = CURDATE()";
            break;
        case 'ultimos_7_dias':
            $sql .= " AND v.data_publicacao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'ultimos_30_dias':
            $sql .= " AND v.data_publicacao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
}

// Ordenação
switch ($ordenacao) {
    case 'salario_desc':
        $sql .= " ORDER BY v.salario_estimado DESC";
        break;
    case 'salario_asc':
        $sql .= " ORDER BY v.salario_estimado ASC";
        break;
    case 'empresa':
        $sql .= " ORDER BY e.nome_empresa ASC";
        break;
    default:
        $sql .= " ORDER BY v.data_publicacao DESC";
}

// Contar total de vagas (sem paginação)
$sql_count = str_replace("SELECT v.*, e.nome_empresa, e.logotipo", "SELECT COUNT(*)", $sql);
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_vagas = $stmt_count->fetchColumn();
$total_paginas = ceil($total_vagas / $vagas_por_pagina);

// Adicionar paginação
$sql .= " LIMIT ? OFFSET ?";
$params[] = $vagas_por_pagina;
$params[] = $offset;

// Executar query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vagas = $stmt->fetchAll();

// ========================================
// ÁREAS POPULARES PARA FILTRO
// ========================================
$areas_sql = "SELECT DISTINCT area FROM vaga WHERE ativa = TRUE AND area IS NOT NULL ORDER BY area";
$areas_stmt = $pdo->query($areas_sql);
$areas_disponiveis = $areas_stmt->fetchAll(PDO::FETCH_COLUMN);

// ========================================
// LOCALIZAÇÕES PARA FILTRO - Províncias de Moçambique
// ========================================
$locais = [
    'Maputo',
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

// ========================================
// FUNÇÕES AUXILIARES
// ========================================

// Obter logotipo da empresa (ou imagem padrão)
function getLogoEmpresa($logotipo) {
    if (!empty($logotipo) && file_exists('uploads/' . $logotipo)) {
        return 'uploads/' . $logotipo;
    }
    return 'assets/images/empresa-default.png';
}

// Formatar salário em Meticais
function formatarSalario($salario) {
    if (empty($salario) || $salario == 0) {
        return 'À combinar';
    }
    return number_format($salario, 2, ',', '.') . ' MT';
}

// Calcular tempo desde publicação
function tempoDecorrido($data) {
    $agora = new DateTime();
    $publicacao = new DateTime($data);
    $diferenca = $agora->diff($publicacao);

    if ($diferenca->days == 0) {
        return 'Hoje';
    } elseif ($diferenca->days == 1) {
        return 'Há 1 dia';
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

// Traduzir modalidade
function traduzirModalidade($modalidade) {
    $traducoes = [
        'presencial' => 'Presencial',
        'hibrido' => 'Híbrido',
        'remoto' => 'Remoto'
    ];
    return $traducoes[$modalidade] ?? $modalidade;
}

// Verificar se vaga é nova (publicada nos últimos 3 dias)
function isVagaNova($data_publicacao) {
    $agora = new DateTime();
    $publicacao = new DateTime($data_publicacao);
    $diferenca = $agora->diff($publicacao);
    return $diferenca->days <= 3;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vagas de Emprego em Moçambique - Emprego MZ</title>
    <meta name="description" content="Encontre as melhores oportunidades de emprego em Moçambique. <?php echo number_format($total_vagas, 0, ',', '.'); ?> vagas disponíveis.">

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
            /* === 🎨 PALETA DEFINITIVA PARA VAGAS === */
            
            /* 🔵 CORES PRINCIPAIS */
            --cor-primaria: #14213d;              /* Oxford Blue - Header, filtros principais */
            --cor-primaria-escura: #0f1a2e;       /* Oxford Blue Dark - Hover em botões primários */
            --cor-primaria-clara: #1e2c47;        /* Oxford Blue Light - Elementos secundários */
            --cor-primaria-alpha: rgba(20, 33, 61, 0.1);
            
            /* 🟠 CORES SECUNDÁRIAS */
            --cor-secundaria: #fca311;            /* Orange Web - Botões "Candidatar-se", destaques */
            --cor-secundaria-hover: #e3940f;      /* Orange Hover - Hover em "Candidatar-se" */
            --cor-secundaria-clara: #fdb541;      /* Orange Light */
            --cor-secundaria-muito-clara: #fec871; /* Orange Very Light */
            --cor-secundaria-alpha: rgba(252, 163, 17, 0.1);
            
            /* 🌫️ HIERARQUIA VISUAL - Soft Gray System */
            --cor-fundo: #f8fafc;                 /* Soft Gray - Fundo geral da página */
            --cor-cards: #ffffff;                 /* White - Cards de vagas, formulários */
            --cor-acento: #f1f5ff;                /* Light Blue - Hover, filtros ativos */
            --cor-acento-escuro: #e1ebff;         /* Light Blue Dark - Filtros selecionados */
            
            /* 📝 TEXTO REFINADO - Hierarquia */
            --cor-texto: #1a202c;                 /* Títulos de vagas */
            --cor-texto-claro: #64748b;           /* Descrições, detalhes */
            --cor-texto-muito-claro: #94a3b8;     /* Placeholders, datas */
            
            /* 🏷️ ESTADOS ESPECÍFICOS DE VAGAS */
            --cor-vaga-nova: #10B981;             /* Badge "Nova" */
            --cor-vaga-urgente: #EF4444;          /* Badge "Urgente" */
            --cor-salario: #059669;               /* Valor de salário */
            --cor-empresa: #14213d;               /* Nome da empresa - Oxford Blue */
            
            /* 🎯 BORDAS E SOMBRAS ESPECÍFICAS */
            --cor-borda: #e2e8f0;                 /* Bordas de cards */
            --cor-borda-clara: #f1f5f9;           /* Bordas suaves */
            --cor-borda-ativa: #fca311;           /* Bordas em hover - Orange */
            --sombra-card: 0 2px 8px rgba(20, 33, 61, 0.08);
            --sombra-card-hover: 0 8px 24px rgba(20, 33, 61, 0.15);
            --sombra-filtros: 0 1px 3px rgba(20, 33, 61, 0.06);
            
            /* ✅ ESTADOS GERAIS */
            --cor-sucesso: #10B981;
            --cor-erro: #EF4444;
            --cor-aviso: #F59E0B;
            --cor-info: #14213d;
            
            /* 🔄 Aliases compatibilidade */
            --primary: var(--cor-primaria);
            --primary-dark: var(--cor-primaria-escura);
            --secondary: var(--cor-secundaria);
            --secondary-hover: var(--cor-secundaria-hover);
            --accent: var(--cor-sucesso);
            
            /* Texto */
            --text: var(--cor-texto);
            --text-light: var(--cor-texto-claro);
            --text-lighter: var(--cor-texto-muito-claro);
            
            /* Backgrounds e Bordas */
            --border: var(--cor-borda);
            --border-light: var(--cor-borda-clara);
            --gray-50: var(--cor-fundo);
            --gray-100: #f1f5f9;
            --gray-200: var(--cor-borda);
            --white: #ffffff;
            
            /* Estados */
            --success: var(--cor-sucesso);
            --error: var(--cor-erro);
            --warning: var(--cor-aviso);
            
            /* Typography */
            --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            
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
            
            /* Borders */
            --radius: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-full: 9999px;
            
            /* Shadows - Usando as variáveis específicas */
            --shadow-sm: var(--sombra-filtros);
            --shadow: var(--sombra-card);
            --shadow-md: var(--sombra-card);
            --shadow-lg: var(--sombra-card-hover);
            --shadow-xl: 0 12px 32px rgba(20, 33, 61, 0.18);
            
            /* Transitions */
            --transition: all 0.2s ease;
        }

        body {
            font-family: var(--font-primary);
            color: var(--cor-texto);             /* #1a202c - Texto principal */
            background: var(--cor-fundo);        /* #f8fafc - Soft Gray */
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ==========================================
           📱 HEADER PROFISSIONAL - Oxford Blue (CONSISTENTE COM INDEX)
        ========================================== */
        .header {
            background: var(--cor-primaria);     /* #14213d - Oxford Blue */
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
            color: rgba(255, 255, 255, 0.9);     /* Branco sobre Oxford Blue */
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .nav-link:hover {
            color: var(--cor-secundaria);        /* #fca311 - Orange */
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--cor-secundaria);   /* #fca311 - Orange */
            transition: width 0.3s ease;
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }

        /* Dropdown */
        .nav-dropdown {
            position: relative;
        }

        .nav-link.has-dropdown {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .nav-link.has-dropdown svg {
            width: 14px;
            height: 14px;
            transition: transform 0.2s;
        }

        .nav-dropdown:hover .nav-link.has-dropdown svg {
            transform: rotate(180deg);
        }

        .dropdown-content {
            position: absolute;
            top: calc(100% + 12px);
            left: 0;
            background: var(--cor-cards);           /* #ffffff - White card */
            border: 1px solid var(--cor-borda);     /* #e2e8f0 - Borda refinada */
            border-radius: var(--radius-lg);
            box-shadow: var(--sombra-forte);        /* Sombra profissional */
            min-width: 240px;
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
            color: var(--cor-texto-claro);           /* #64748b - Texto secundário */
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
        }

        .dropdown-item svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--gray-200);
            margin: var(--space-2) 0;
        }

        /* Header Buttons */
        .header-actions {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .btn {
            padding: var(--space-3) var(--space-5);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--secondary);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: var(--secondary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: var(--white);
        }

        .btn-outline:hover {
            border-color: var(--cor-secundaria);     /* #fca311 - Orange */
            color: var(--cor-secundaria);
            background: rgba(252, 163, 17, 0.1);
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: var(--space-2);
            color: var(--gray-700);
        }

        .mobile-menu-toggle svg {
            width: 24px;
            height: 24px;
        }

        /* ==========================================
           🍞 BREADCRUMB
        ========================================== */
        .breadcrumb {
            background: var(--cor-cards);            /* #ffffff - White */
            border-bottom: 1px solid var(--cor-borda); /* #e2e8f0 */
            margin-top: 73px;
            box-shadow: var(--sombra-filtros);
        }

        .breadcrumb-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-3) var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 14px;
            color: var(--cor-texto-claro);           /* #64748b */
        }

        .breadcrumb-link {
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb-link:hover {
            color: var(--cor-secundaria);            /* #fca311 - Orange */
            text-decoration: underline;
        }

        .breadcrumb-separator {
            width: 16px;
            height: 16px;
            color: var(--cor-texto-muito-claro);     /* #94a3b8 */
        }

        /* ==========================================
           📋 MAIN LAYOUT - FULL WIDTH
        ========================================== */
        .main-container {
            max-width: 1600px;                      /* Telas muito grandes */
            margin: 0 auto;
            padding: 0 var(--space-6) var(--space-8);
        }

        /* Desktop grande */
        @media (max-width: 1600px) {
            .main-container {
                max-width: 1400px;
            }
        }

        /* Desktop médio (1366x768 e similares) - Otimizado para 2 colunas */
        @media (max-width: 1500px) {
            .main-container {
                max-width: 1300px;                  /* Largura otimizada para 1366px */
                padding: 0 var(--space-8) var(--space-8); /* Padding maior nas laterais */
            }
        }

        /* ==========================================
           🔍 FILTROS HORIZONTAL - STICKY NO TOPO (Estilo Airbnb)
        ========================================== */
        .filters-container {
            position: sticky;
            top: 70px;                              /* Altura do header */
            background: var(--cor-cards);           /* #ffffff - White */
            border-bottom: 1px solid var(--cor-borda);
            box-shadow: var(--sombra-filtros);
            z-index: 100;
            transition: var(--transition);
            margin: 0 calc(-1 * var(--space-6));    /* Full width */
            padding: var(--space-4) var(--space-6);
        }
        
        .filters-container.is-stuck {
            box-shadow: var(--sombra-media);
        }

        /* Barra de Filtros Compacta */
        .filters-bar {
            max-width: 1600px;                      /* Mesmo que main-container */
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: var(--space-3);
            flex-wrap: wrap;
        }

        @media (max-width: 1600px) {
            .filters-bar {
                max-width: 1400px;
            }
        }

        @media (max-width: 1500px) {
            .filters-bar {
                max-width: 1300px;                  /* Otimizado para 1366x768 */
            }
        }

        /* Botão de Toggle dos Filtros */
        .filters-toggle {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-4);
            background: var(--cor-primaria);        /* #14213d - Oxford Blue */
            color: var(--white);
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .filters-toggle:hover {
            background: var(--cor-primaria-escura);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .filters-toggle svg {
            width: 18px;
            height: 18px;
        }

        .filter-count {
            background: var(--cor-secundaria);       /* #fca311 - Orange */
            color: var(--white);
            padding: 2px var(--space-2);
            border-radius: var(--radius-full);
            font-size: 11px;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
        }

        /* Filtros Inline Compactos */
        .filters-inline {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            flex: 1;
            flex-wrap: wrap;
        }

        .filter-input-compact,
        .filter-select-compact {
            padding: var(--space-2) var(--space-3);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius);
            font-size: 14px;
            font-family: var(--font-primary);
            color: var(--cor-texto);
            background: var(--cor-cards);
            transition: var(--transition);
            min-width: 150px;
        }

        .filter-input-compact:focus,
        .filter-select-compact:focus {
            outline: none;
            border-color: var(--cor-primaria);
            box-shadow: 0 0 0 3px rgba(20, 33, 61, 0.1);
        }

        .filter-input-compact::placeholder {
            color: var(--cor-texto-muito-claro);
        }

        /* Chips de Filtros Ativos */
        .active-filters-chips {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            flex-wrap: wrap;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-1) var(--space-3);
            background: var(--cor-acento-escuro);    /* #e1ebff */
            color: var(--cor-primaria);
            border: 1px solid var(--cor-primaria);
            border-radius: var(--radius-full);
            font-size: 13px;
            font-weight: 600;
        }

        .filter-chip button {
            display: flex;
            align-items: center;
            background: none;
            border: none;
            color: var(--cor-primaria);
            cursor: pointer;
            padding: 0;
            margin-left: var(--space-1);
        }

        .filter-chip button svg {
            width: 14px;
            height: 14px;
        }

        .filter-chip button:hover {
            color: var(--cor-erro);
        }

        .btn-clear-all-filters {
            padding: var(--space-2) var(--space-3);
            background: transparent;
            color: var(--cor-texto-claro);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-clear-all-filters:hover {
            background: var(--cor-acento);
            color: var(--cor-primaria);
            border-color: var(--cor-primaria);
        }

        /* Painel de Filtros Expandido (Toggle) */
        .filters-panel {
            max-width: 1600px;                       /* Mesmo que main-container */
            margin: var(--space-4) auto 0;
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            border: 1px solid var(--cor-acento-escuro);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            display: none;                           /* Oculto por padrão */
            animation: slideDown 0.3s ease-out;
        }

        @media (max-width: 1600px) {
            .filters-panel {
                max-width: 1400px;
            }
        }

        @media (max-width: 1500px) {
            .filters-panel {
                max-width: 1300px;                   /* Otimizado para 1366x768 */
            }
        }

        .filters-panel.active {
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

        /* Grid de Filtros no Painel Expandido */
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-5);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }

        .filter-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--cor-texto);                 /* #1a202c - Texto principal */
            margin-bottom: var(--space-1);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-input,
        .filter-select {
            width: 100%;
            padding: var(--space-3);
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: var(--radius);
            font-size: 14px;
            font-family: var(--font-primary);
            color: var(--cor-texto);                 /* #1a202c */
            background: var(--cor-cards);            /* #ffffff - White */
            transition: var(--transition);
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            border-color: var(--cor-primaria);       /* #14213d - Oxford Blue */
            box-shadow: 0 0 0 3px rgba(20, 33, 61, 0.1);
        }

        .filter-input::placeholder {
            color: var(--cor-texto-muito-claro);     /* #94a3b8 - Placeholders */
        }

        .salary-inputs {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: var(--space-2);
            align-items: center;
        }

        .salary-inputs input {
            padding: var(--space-3);
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: var(--radius);
            font-size: 14px;
            font-family: var(--font-primary);
            background: var(--cor-cards);            /* #ffffff - White */
            color: var(--cor-texto);                 /* #1a202c */
        }

        .salary-inputs input:focus {
            outline: none;
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            border-color: var(--cor-primaria);       /* #14213d - Oxford Blue */
            box-shadow: 0 0 0 3px rgba(20, 33, 61, 0.1);
        }

        .salary-separator {
            color: var(--cor-texto-claro);           /* #64748b */
            font-size: 14px;
            font-weight: 600;
        }

        .filter-radio {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            cursor: pointer;
        }

        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--cor-secundaria);     /* #fca311 - Orange */
            cursor: pointer;
        }

        .radio-option label {
            font-size: 14px;
            color: var(--cor-texto);                 /* #1a202c */
            cursor: pointer;
        }

        .filter-actions {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: var(--space-3);
            margin-top: var(--space-5);
            padding-top: var(--space-5);
            border-top: 1px solid var(--cor-borda);
        }

        .btn-clear {
            padding: var(--space-3) var(--space-4);
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            background: transparent;
            color: var(--cor-texto-claro);           /* #64748b */
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-clear:hover {
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            border-color: var(--cor-primaria);
            color: var(--cor-primaria);
        }

        .btn-apply {
            padding: var(--space-3) var(--space-4);
            border: none;
            background: var(--cor-secundaria);       /* #fca311 - Orange */
            color: var(--white);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-apply:hover {
            background: var(--cor-secundaria-hover); /* #e3940f */
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .active-filters-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--cor-texto-claro);           /* #64748b */
            margin-bottom: var(--space-2);
        }

        .filter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-1) var(--space-3);
            background: var(--cor-acento-escuro);    /* #e1ebff - Filtros selecionados */
            border: 1px solid var(--cor-primaria);   /* #14213d - Oxford Blue */
            border-radius: var(--radius-full);
            font-size: 13px;
            color: var(--cor-primaria);
            font-weight: 600;
        }

        .filter-tag button {
            display: flex;
            align-items: center;
            background: none;
            border: none;
            color: var(--cor-primaria);
            cursor: pointer;
            padding: 0;
        }

        .filter-tag button svg {
            width: 14px;
            height: 14px;
        }

        .filter-tag button:hover {
            color: var(--cor-erro);                  /* #EF4444 */
        }

        /* ==========================================
           📄 CONTENT AREA
        ========================================== */
        .content-area {
            min-width: 0;
        }

        .results-header {
            background: var(--cor-cards);            /* #ffffff - White */
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            margin-bottom: var(--space-6);
            box-shadow: var(--sombra-filtros);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: var(--space-4);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .results-header {
                padding: var(--space-5);             /* Padding menor */
            }
        }

        .results-info {
            display: flex;
            flex-direction: column;
            gap: var(--space-1);
        }

        .results-count {
            font-size: 20px;
            font-weight: 700;
            color: var(--cor-texto);                 /* #1a202c - Texto principal */
        }

        .results-count span {
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
        }

        .results-subtitle {
            font-size: 14px;
            color: var(--cor-texto-claro);           /* #64748b - Texto secundário */
        }

        .results-actions {
            display: flex;
            align-items: center;
            gap: var(--space-4);
        }

        .sort-control {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .sort-control label {
            font-size: 14px;
            color: var(--cor-texto-claro);           /* #64748b */
            white-space: nowrap;
        }

        .sort-control select {
            padding: var(--space-2) var(--space-3);
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: var(--radius);
            font-size: 14px;
            background: var(--cor-cards);            /* #ffffff - White */
            color: var(--cor-texto);                 /* #1a202c */
            cursor: pointer;
            min-width: 160px;
            transition: var(--transition);
        }
        
        .sort-control select:focus {
            outline: none;
            border-color: var(--cor-primaria);       /* #14213d - Oxford Blue */
            box-shadow: 0 0 0 3px rgba(20, 33, 61, 0.1);
        }

        /* ==========================================
           💼 JOB CARDS - GRID LAYOUT RESPONSIVO
        ========================================== */
        .jobs-list {
            display: grid;
            grid-template-columns: repeat(3, 1fr);   /* 3 colunas apenas em telas muito grandes */
            gap: var(--space-6);                     /* Gap maior em desktop */
            margin-top: var(--space-6);
        }

        /* Desktop médio - 2 colunas (inclui 1366x768) */
        @media (max-width: 1500px) {
            .jobs-list {
                grid-template-columns: repeat(2, 1fr);  /* 2 colunas confortáveis */
                gap: var(--space-6);                    /* Mantém gap generoso */
            }
        }

        .job-card {
            background: var(--cor-cards);            /* #ffffff - White */
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            box-shadow: var(--sombra-card);          /* Sombra suave */
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            min-height: 320px;                       /* Altura mínima para uniformidade */
        }

        /* Otimização para 1366x768 e resoluções similares */
        @media (max-width: 1500px) {
            .job-card {
                min-height: 300px;                   /* Altura otimizada para 2 colunas */
                padding: var(--space-5);             /* Padding um pouco menor */
            }
        }

        .job-card:hover {
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            box-shadow: var(--sombra-card-hover);    /* Sombra forte */
            transform: translateY(-2px);
            border-color: var(--cor-borda-ativa);    /* #fca311 - Orange */
        }

        .job-card-header {
            display: flex;
            gap: var(--space-4);
            margin-bottom: var(--space-4);
        }

        .company-logo {
            width: 64px;
            height: 64px;
            flex-shrink: 0;
            border-radius: var(--radius);
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            overflow: hidden;
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .company-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .job-card-content {
            flex: 1;
            min-width: 0;
        }

        .job-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--space-4);
            margin-bottom: var(--space-2);
        }

        .job-title-area {
            flex: 1;
            min-width: 0;
        }

        .job-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            text-decoration: none;
            display: block;
            margin-bottom: var(--space-1);
            transition: var(--transition);
        }

        .job-title:hover {
            color: var(--cor-secundaria);            /* #fca311 - Orange */
            text-decoration: underline;
        }

        .company-name {
            font-size: 15px;
            color: var(--cor-empresa);               /* #14213d - Oxford Blue */
            font-weight: 600;
            margin-bottom: var(--space-3);
        }

        .job-badges {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-1);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 600;
        }

        .badge-new {
            background: var(--cor-vaga-nova);        /* #10B981 - Verde "Nova" */
            color: var(--white);
            font-size: 12px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .badge-remote {
            background: var(--cor-acento-escuro);    /* #e1ebff - Light Blue */
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            border: 1px solid var(--cor-primaria);
        }

        .badge-urgent {
            background: var(--cor-vaga-urgente);     /* #EF4444 - Vermelho "Urgente" */
            color: var(--white);
            font-size: 12px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .job-actions-top {
            display: flex;
            gap: var(--space-2);
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            background: var(--white);
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-light);
        }

        .btn-icon:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-icon.saved {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: var(--success);
        }

        .btn-icon svg {
            width: 18px;
            height: 18px;
        }

        .job-info {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-5);
            padding: var(--space-4) 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            margin-bottom: var(--space-4);
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 14px;
            color: var(--cor-texto-claro);           /* #64748b - Detalhes */
        }

        .info-item svg {
            width: 18px;
            height: 18px;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            flex-shrink: 0;
        }

        .info-item.salary {
            font-weight: 700;
            font-size: 16px;
            color: var(--cor-salario);               /* #059669 - Verde para salário */
        }

        .job-description {
            font-size: 14px;
            line-height: 1.6;
            color: var(--cor-texto-claro);           /* #64748b - Descrições */
            margin-bottom: var(--space-4);
        }

        .job-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-4);
            margin-top: auto;                        /* Empurra o footer para o final do card */
            padding-top: var(--space-4);
        }

        .job-time {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 13px;
            color: var(--cor-texto-muito-claro);     /* #94a3b8 - Datas */
        }

        .job-time svg {
            width: 16px;
            height: 16px;
        }

        .job-card-actions {
            display: flex;
            gap: var(--space-3);
        }

        .btn-secondary {
            padding: var(--space-3) var(--space-5);
            border: 2px solid var(--cor-primaria);   /* #14213d - Oxford Blue */
            background: transparent;
            color: var(--cor-primaria);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-secondary:hover {
            background: var(--cor-primaria);         /* #14213d - Oxford Blue */
            color: var(--white);
        }

        .btn-apply {
            padding: var(--space-3) var(--space-6);
            border: 2px solid var(--cor-secundaria);
            background: var(--cor-secundaria);       /* #fca311 - Orange "Candidatar-se" */
            color: var(--white);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
        }

        .btn-apply:hover {
            background: var(--cor-secundaria-hover); /* #e3940f - Orange hover */
            border-color: var(--cor-secundaria-hover);
            transform: translateY(-1px);
            box-shadow: var(--sombra-media);
        }

        .btn-apply svg {
            width: 18px;
            height: 18px;
        }

        /* ==========================================
           ⚠️ EMPTY STATE
        ========================================== */
        .empty-state {
            background: var(--cor-cards);            /* #ffffff - White */
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: var(--radius-lg);
            padding: var(--space-16);
            text-align: center;
            box-shadow: var(--sombra-card);
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto var(--space-6);
            color: var(--cor-texto-muito-claro);     /* #94a3b8 */
        }

        .empty-state h3 {
            font-size: 22px;
            font-weight: 700;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            margin-bottom: var(--space-3);
        }

        .empty-state p {
            font-size: 15px;
            color: var(--cor-texto-claro);           /* #64748b */
            margin-bottom: var(--space-6);
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .empty-state .btn-primary {
            padding: var(--space-3) var(--space-8);
        }

        /* ==========================================
           📖 PAGINATION
        ========================================== */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: var(--space-2);
            margin-top: var(--space-8);
        }

        .page-link {
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 var(--space-3);
            border: 1px solid var(--cor-borda);     /* #e2e8f0 */
            background: var(--cor-cards);            /* #ffffff - White */
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            border-radius: var(--radius);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition);
        }

        .page-link:hover:not(.active) {
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            border-color: var(--cor-primaria);
            color: var(--cor-primaria);
        }

        .page-link.active {
            background: var(--cor-primaria);         /* #14213d - Oxford Blue */
            color: var(--white);
            border-color: var(--cor-primaria);
        }

        .page-link:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ==========================================
           🎨 FOOTER RICO (CONSISTENTE COM INDEX)
        ========================================== */
        .footer {
            background: var(--cor-primaria);         /* #14213d - Oxford Blue */
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
            color: var(--cor-secundaria);            /* #fca311 - Orange */
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
            background: var(--cor-secundaria);       /* #fca311 - Orange */
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
            .filters-inline {
                display: none;                      /* Oculta filtros inline em tablets */
            }

            .filters-toggle {
                width: 100%;
                justify-content: center;
            }

            .nav-menu {
                display: none;
            }

            .mobile-menu-toggle {
                display: block;
            }
        }

        /* Tablet - 1 coluna */
        @media (max-width: 900px) {
            .jobs-list {
                grid-template-columns: 1fr;         /* 1 coluna em tablets pequenos */
                gap: var(--space-5);                /* Gap médio para 1 coluna */
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
                padding: 0 var(--space-4) var(--space-6);
            }

            .filters-container {
                margin: 0 calc(-1 * var(--space-4));
                padding: var(--space-3) var(--space-4);
            }

            .filters-panel {
                margin: var(--space-3) 0 0;
            }

            .filters-grid {
                grid-template-columns: 1fr;            /* 1 coluna em mobile */
                gap: var(--space-4);
            }

            .results-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .results-actions {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
            }

            .sort-control {
                width: 100%;
            }

            .sort-control select {
                flex: 1;
            }

            .job-card {
                padding: var(--space-5);
                min-height: 240px;                  /* Altura menor em tablets */
            }

            .job-card-header {
                flex-direction: row;
            }

            .company-logo {
                width: 56px;
                height: 56px;
            }

            .job-card-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .job-actions-top {
                width: 100%;
                justify-content: flex-end;
            }

            .job-info {
                flex-direction: column;
                gap: var(--space-3);
            }

            .job-card-footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .job-card-actions {
                width: 100%;
                flex-direction: column;
            }

            .btn-secondary,
            .btn-apply {
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
            .results-count {
                font-size: 18px;
            }

            .job-title {
                font-size: 16px;
            }

            .job-card {
                padding: var(--space-4);            /* Padding menor em mobile */
                min-height: auto;                   /* Remove altura mínima em mobile */
            }

            .footer-grid {
                grid-template-columns: 1fr;
            }

            .active-filters-chips {
                display: none;                      /* Oculta chips em telas muito pequenas */
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

        .job-card {
            animation: fadeIn 0.3s ease-out;
        }

        /* ==========================================
           🔔 NOTIFICAÇÕES (Toast)
        ========================================== */
        .notification {
            position: fixed;
            top: 90px;                              /* Abaixo do header */
            right: var(--space-6);
            background: var(--cor-cards);           /* #ffffff */
            border: 1px solid var(--cor-borda);     /* #e2e8f0 */
            border-left: 4px solid var(--cor-primaria); /* #14213d - Oxford Blue */
            border-radius: var(--radius-lg);
            padding: var(--space-4) var(--space-5);
            box-shadow: var(--sombra-card-hover);   /* Sombra forte */
            z-index: 10000;
            min-width: 320px;
            max-width: 400px;
            opacity: 0;
            transform: translateX(400px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .notification.show {
            opacity: 1;
            transform: translateX(0);
        }

        .notification-content {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            color: var(--cor-texto);                /* #1a202c */
            font-size: 14px;
            font-weight: 500;
        }

        .notification-content i {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
        }

        /* Tipos de notificação */
        .notification-success {
            border-left-color: var(--cor-sucesso);  /* #10B981 */
        }

        .notification-success .notification-content i {
            color: var(--cor-sucesso);
        }

        .notification-error {
            border-left-color: var(--cor-erro);     /* #EF4444 */
        }

        .notification-error .notification-content i {
            color: var(--cor-erro);
        }

        .notification-warning {
            border-left-color: var(--cor-aviso);    /* #F59E0B */
        }

        .notification-warning .notification-content i {
            color: var(--cor-aviso);
        }

        .notification-info {
            border-left-color: var(--cor-primaria); /* #14213d */
        }

        .notification-info .notification-content i {
            color: var(--cor-primaria);
        }

        /* Mobile */
        @media (max-width: 768px) {
            .notification {
                top: 80px;
                right: var(--space-4);
                left: var(--space-4);
                min-width: auto;
                max-width: none;
            }
        }
    </style>
</head>
<body>

    <!-- ==========================================
         📱 HEADER - CONSISTENT WITH INDEX
    ========================================== -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <img src="assets/images/empregos-logo.svg" alt="Emprego MZ" class="logo-img">
            </a>

            <nav class="nav-menu">
                <a href="index.php" class="nav-link">Início</a>
                <a href="vagas.php" class="nav-link active">Vagas</a>
                
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
                        <a href="candidato/perfil.php" class="dropdown-item">
                            <i data-lucide="user"></i>
                            <span>Meu Perfil</span>
                        </a>
                        <a href="candidato/vagas_guardadas.php" class="dropdown-item">
                            <i data-lucide="bookmark"></i>
                            <span>Vagas Guardadas</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="candidato/candidaturas.php" class="dropdown-item">
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
                        <a href="empresa/dashboard.php" class="dropdown-item">
                            <i data-lucide="layout-dashboard"></i>
                            <span>Painel de Controle</span>
                        </a>
                        <a href="empresa/criar_vaga.php" class="dropdown-item">
                            <i data-lucide="plus-circle"></i>
                            <span>Publicar Vaga</span>
                        </a>
                        <a href="empresa/candidaturas.php" class="dropdown-item">
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
                        <a href="empresa/dashboard.php" class="btn btn-primary">
                            <i data-lucide="layout-dashboard"></i>
                            Dashboard
                        </a>
                        <a href="auth/logout.php" class="btn btn-outline">
                            <i data-lucide="log-out"></i>
                            Sair
                        </a>
                    <?php elseif ($_SESSION['user_type'] === 'candidato'): ?>
                        <!-- Ações para Candidato - Ordem priorizada -->
                        <a href="vagas.php" class="btn btn-primary">
                            <i data-lucide="search"></i>
                            Buscar Vagas
                        </a>
                        <a href="candidato/candidaturas.php" class="btn btn-outline">
                            <i data-lucide="briefcase"></i>
                            Candidaturas
                        </a>
                        <a href="candidato/perfil.php" class="btn btn-outline">
                            <i data-lucide="user"></i>
                            Perfil
                        </a>
                        <a href="auth/logout.php" class="btn btn-outline">
                            <i data-lucide="log-out"></i>
                            Sair
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Ações para não logado -->
                    <a href="auth/login.php" class="btn btn-outline">Entrar</a>
                    <a href="auth/register.php" class="btn btn-primary">Criar Conta</a>
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
            <a href="index.php" class="breadcrumb-link">Início</a>
            <i data-lucide="chevron-right" class="breadcrumb-separator"></i>
            <span>Vagas</span>
        </div>
    </div>

    <!-- ==========================================
         🔍 FILTROS HORIZONTAL - STICKY NO TOPO
    ========================================== -->
    <?php 
    $active_filters_count = 0;
    if (!empty($filtro_pesquisa)) $active_filters_count++;
    if (!empty($filtro_localizacao)) $active_filters_count++;
    if (!empty($filtro_area)) $active_filters_count++;
    if (!empty($filtro_modalidade)) $active_filters_count++;
    if ($filtro_salario_min > 0 || $filtro_salario_max > 0) $active_filters_count++;
    if (!empty($filtro_data)) $active_filters_count++;
    ?>
    
    <div class="filters-container">
        <!-- Barra de Filtros Compacta -->
        <div class="filters-bar">
            <!-- Botão Toggle de Filtros -->
            <button type="button" class="filters-toggle" onclick="toggleFiltersPanel()">
                <i data-lucide="sliders-horizontal"></i>
                Filtros
                <?php if ($active_filters_count > 0): ?>
                <span class="filter-count"><?php echo $active_filters_count; ?></span>
                <?php endif; ?>
            </button>

            <!-- Filtros Inline (Desktop) -->
            <div class="filters-inline">
                <form method="GET" action="vagas.php" id="quick-filters" style="display: flex; gap: var(--space-2); flex: 1; flex-wrap: wrap; align-items: center;">
                    <input 
                        type="text" 
                        name="q" 
                        class="filter-input-compact" 
                        placeholder="Buscar cargo..."
                        value="<?php echo htmlspecialchars($filtro_pesquisa); ?>"
                    >
                    
                    <select name="area" class="filter-select-compact" onchange="this.form.submit()">
                        <option value="">Todas as áreas</option>
                        <?php foreach ($areas_disponiveis as $area): ?>
                            <option value="<?php echo htmlspecialchars($area); ?>" <?php echo $filtro_area === $area ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($area); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="local" class="filter-select-compact" onchange="this.form.submit()">
                        <option value="">Todas as localizações</option>
                        <?php foreach ($locais as $local): ?>
                            <option value="<?php echo htmlspecialchars($local); ?>" <?php echo $filtro_localizacao === $local ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($local); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="modalidade" class="filter-select-compact" onchange="this.form.submit()">
                        <option value="">Modalidade</option>
                        <option value="presencial" <?php echo $filtro_modalidade === 'presencial' ? 'selected' : ''; ?>>Presencial</option>
                        <option value="hibrido" <?php echo $filtro_modalidade === 'hibrido' ? 'selected' : ''; ?>>Híbrido</option>
                        <option value="remoto" <?php echo $filtro_modalidade === 'remoto' ? 'selected' : ''; ?>>Remoto</option>
                    </select>

                    <!-- Hidden fields para manter ordenação -->
                    <input type="hidden" name="ordem" value="<?php echo htmlspecialchars($ordenacao); ?>">
                </form>
            </div>

            <!-- Chips de Filtros Ativos -->
            <?php if ($active_filters_count > 0): ?>
            <div class="active-filters-chips">
                <?php if (!empty($filtro_pesquisa)): ?>
                <div class="filter-chip">
                    <span><?php echo htmlspecialchars(substr($filtro_pesquisa, 0, 15)); ?></span>
                    <button type="button" onclick="removeFilter('q')"><i data-lucide="x"></i></button>
                </div>
                <?php endif; ?>
                <?php if (!empty($filtro_area)): ?>
                <div class="filter-chip">
                    <span><?php echo htmlspecialchars($filtro_area); ?></span>
                    <button type="button" onclick="removeFilter('area')"><i data-lucide="x"></i></button>
                </div>
                <?php endif; ?>
                <?php if (!empty($filtro_localizacao)): ?>
                <div class="filter-chip">
                    <span><?php echo htmlspecialchars($filtro_localizacao); ?></span>
                    <button type="button" onclick="removeFilter('local')"><i data-lucide="x"></i></button>
                </div>
                <?php endif; ?>
                <?php if (!empty($filtro_modalidade)): ?>
                <div class="filter-chip">
                    <span><?php echo traduzirModalidade($filtro_modalidade); ?></span>
                    <button type="button" onclick="removeFilter('modalidade')"><i data-lucide="x"></i></button>
                </div>
                <?php endif; ?>
                
                <button type="button" class="btn-clear-all-filters" onclick="clearFilters()">
                    Limpar tudo
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Painel de Filtros Expandido (Toggle) -->
        <div class="filters-panel" id="filters-panel">
            <form method="GET" action="vagas.php" class="filters-grid">
                
                <!-- Buscar -->
                <div class="filter-group">
                    <label class="filter-label">Buscar Vaga</label>
                    <input 
                        type="text" 
                        name="q" 
                        class="filter-input" 
                        placeholder="Ex: Desenvolvedor, Marketing..."
                        value="<?php echo htmlspecialchars($filtro_pesquisa); ?>"
                    >
                </div>

                <!-- Área -->
                <div class="filter-group">
                    <label class="filter-label">Área</label>
                    <select name="area" class="filter-select">
                        <option value="">Todas as áreas</option>
                        <?php foreach ($areas_disponiveis as $area): ?>
                            <option value="<?php echo htmlspecialchars($area); ?>" <?php echo $filtro_area === $area ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($area); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Localização -->
                <div class="filter-group">
                    <label class="filter-label">Localização</label>
                    <select name="local" class="filter-select">
                        <option value="">Todas as localizações</option>
                        <?php foreach ($locais as $local): ?>
                            <option value="<?php echo htmlspecialchars($local); ?>" <?php echo $filtro_localizacao === $local ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($local); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Modalidade -->
                <div class="filter-group">
                    <label class="filter-label">Tipo de Trabalho</label>
                    <select name="modalidade" class="filter-select">
                        <option value="">Todas as modalidades</option>
                        <option value="presencial" <?php echo $filtro_modalidade === 'presencial' ? 'selected' : ''; ?>>Presencial</option>
                        <option value="hibrido" <?php echo $filtro_modalidade === 'hibrido' ? 'selected' : ''; ?>>Híbrido</option>
                        <option value="remoto" <?php echo $filtro_modalidade === 'remoto' ? 'selected' : ''; ?>>Remoto</option>
                    </select>
                </div>

                <!-- Data -->
                <div class="filter-group">
                    <label class="filter-label">Data de Publicação</label>
                    <select name="data" class="filter-select">
                        <option value="">Todas as datas</option>
                        <option value="hoje" <?php echo $filtro_data === 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                        <option value="ultimos_7_dias" <?php echo $filtro_data === 'ultimos_7_dias' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                        <option value="ultimos_30_dias" <?php echo $filtro_data === 'ultimos_30_dias' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                    </select>
                </div>

                <!-- Salário -->
                <div class="filter-group">
                    <label class="filter-label">Faixa Salarial (MT)</label>
                    <div class="salary-inputs">
                        <input 
                            type="number" 
                            name="salario_min" 
                            placeholder="Mínimo"
                            value="<?php echo $filtro_salario_min > 0 ? $filtro_salario_min : ''; ?>"
                        >
                        <span class="salary-separator">-</span>
                        <input 
                            type="number" 
                            name="salario_max" 
                            placeholder="Máximo"
                            value="<?php echo $filtro_salario_max > 0 ? $filtro_salario_max : ''; ?>"
                        >
                    </div>
                </div>

                <!-- Hidden fields para manter ordenação -->
                <input type="hidden" name="ordem" value="<?php echo htmlspecialchars($ordenacao); ?>">

                <!-- Actions -->
                <div class="filter-actions">
                    <button type="button" class="btn-clear" onclick="clearFilters()">
                        Limpar
                    </button>
                    <button type="submit" class="btn-apply">
                        Aplicar Filtros
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- ==========================================
         📋 MAIN CONTENT
    ========================================== -->
    <div class="main-container">

            <!-- Results Header -->
            <div class="results-header">
                <div class="results-info">
                    <div class="results-count">
                        <span><?php echo number_format($total_vagas, 0, ',', '.'); ?></span> 
                        <?php echo $total_vagas === 1 ? 'vaga encontrada' : 'vagas encontradas'; ?>
                    </div>
                    <div class="results-subtitle">
                        <?php if ($active_filters_count > 0): ?>
                            Com os filtros aplicados
                        <?php else: ?>
                            Todas as oportunidades disponíveis
                        <?php endif; ?>
                    </div>
                </div>

                <div class="results-actions">
                    <div class="sort-control">
                        <label for="ordem">Ordenar por:</label>
                        <select name="ordem" id="ordem" onchange="sortJobs(this.value)">
                            <option value="recentes" <?php echo $ordenacao === 'recentes' ? 'selected' : ''; ?>>Mais recentes</option>
                            <option value="salario_desc" <?php echo $ordenacao === 'salario_desc' ? 'selected' : ''; ?>>Maior salário</option>
                            <option value="salario_asc" <?php echo $ordenacao === 'salario_asc' ? 'selected' : ''; ?>>Menor salário</option>
                            <option value="empresa" <?php echo $ordenacao === 'empresa' ? 'selected' : ''; ?>>Empresa (A-Z)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Jobs List -->
            <?php if (count($vagas) > 0): ?>
                <div class="jobs-list">
                    <?php foreach ($vagas as $vaga): ?>
                        <article class="job-card">
                            <div class="job-card-header">
                                <!-- Company Logo -->
                                <div class="company-logo">
                                    <?php if (!empty($vaga['logotipo']) && file_exists('uploads/' . $vaga['logotipo'])): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($vaga['logotipo']); ?>" 
                                             alt="<?php echo htmlspecialchars($vaga['nome_empresa']); ?>">
                                    <?php else: ?>
                                        <img src="assets/images/empresa-default.png" alt="Logo padrão">
                                    <?php endif; ?>
                                </div>

                                <div class="job-card-content">
                                    <div class="job-card-top">
                                        <div class="job-title-area">
                                            <a href="vaga_detalhe.php?id=<?php echo $vaga['id']; ?>" class="job-title">
                                                <?php echo htmlspecialchars($vaga['titulo']); ?>
                                            </a>
                                            <div class="company-name">
                                                <?php echo htmlspecialchars($vaga['nome_empresa']); ?>
                                            </div>
                                            <div class="job-badges">
                                                <?php if (isVagaNova($vaga['data_publicacao'])): ?>
                                                <span class="badge badge-new">
                                                    <i data-lucide="sparkles" style="width: 12px; height: 12px;"></i>
                                                    Nova
                                                </span>
                                                <?php endif; ?>
                                                <?php if ($vaga['modalidade'] === 'remoto'): ?>
                                                <span class="badge badge-remote">
                                                    <i data-lucide="home" style="width: 12px; height: 12px;"></i>
                                                    Remoto
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="job-actions-top">
                                            <button class="btn-icon" onclick="saveJob(<?php echo $vaga['id']; ?>)" title="Guardar vaga">
                                                <i data-lucide="bookmark"></i>
                                            </button>
                                            <button class="btn-icon" onclick="shareJob(<?php echo $vaga['id']; ?>)" title="Partilhar vaga">
                                                <i data-lucide="share-2"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="job-info">
                                        <div class="info-item">
                                            <i data-lucide="map-pin"></i>
                                            <?php echo htmlspecialchars($vaga['localizacao']); ?>
                                        </div>
                                        <div class="info-item">
                                            <i data-lucide="monitor"></i>
                                            <?php echo traduzirModalidade($vaga['modalidade']); ?>
                                        </div>
                                        <div class="info-item salary">
                                            <i data-lucide="banknote"></i>
                                            <?php echo formatarSalario($vaga['salario_estimado']); ?>
                                        </div>
                                        <div class="info-item">
                                            <i data-lucide="briefcase"></i>
                                            <?php echo htmlspecialchars($vaga['area']); ?>
                                        </div>
                                    </div>

                                    <p class="job-description">
                                        <?php echo htmlspecialchars(substr($vaga['descricao'], 0, 200)); ?>...
                                    </p>

                                    <div class="job-card-footer">
                                        <div class="job-time">
                                            <i data-lucide="clock"></i>
                                            <?php echo tempoDecorrido($vaga['data_publicacao']); ?>
                                        </div>
                                        <div class="job-card-actions">
                                            <a href="vaga_detalhe.php?id=<?php echo $vaga['id']; ?>" class="btn-secondary">
                                                Ver detalhes
                                            </a>
                                            <a href="vaga_detalhe.php?id=<?php echo $vaga['id']; ?>" class="btn-apply">
                                                <i data-lucide="send"></i>
                                                Candidatar-me
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_paginas > 1): ?>
                    <nav class="pagination">
                        <?php if ($pagina_atual > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])); ?>" class="page-link">
                                Anterior
                            </a>
                        <?php endif; ?>

                        <?php
                        $inicio = max(1, $pagina_atual - 2);
                        $fim = min($total_paginas, $pagina_atual + 2);

                        for ($i = $inicio; $i <= $fim; $i++):
                        ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
                               class="page-link <?php echo $i === $pagina_atual ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($pagina_atual < $total_paginas): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])); ?>" class="page-link">
                                Próxima
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <i data-lucide="search-x" class="empty-state-icon"></i>
                    <h3>Nenhuma vaga encontrada</h3>
                    <p>Não encontramos vagas que correspondam aos seus critérios de busca. Tente ajustar ou limpar os filtros para ver mais resultados.</p>
                    <button onclick="clearFilters()" class="btn-primary">
                        <i data-lucide="refresh-cw"></i>
                        Limpar todos os filtros
                    </button>
                </div>
            <?php endif; ?>

        </main>

    </div>

    <!-- ==========================================
         🎨 FOOTER RICO
    ========================================== -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-column">
                    <img src="assets/images/empregos-logo.svg" alt="Emprego MZ" class="footer-logo">
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
                    <a href="vagas.php" class="footer-link">Buscar Vagas</a>
                    <a href="auth/register.php" class="footer-link">Criar Currículo</a>
                    <a href="candidato/perfil.php" class="footer-link">Meu Perfil</a>
                    <a href="candidato/vagas_guardadas.php" class="footer-link">Vagas Guardadas</a>
                    <a href="candidato/candidaturas.php" class="footer-link">Candidaturas</a>
                </div>
                <?php endif; ?>

                <?php 
                // Mostrar "Para Empresas" apenas se não for candidato
                if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'candidato'): 
                ?>
                <div class="footer-column">
                    <h4>Para Empresas</h4>
                    <a href="empresa/dashboard.php" class="footer-link">Dashboard</a>
                    <a href="empresa/criar_vaga.php" class="footer-link">Publicar Vaga</a>
                    <a href="empresa/candidaturas.php" class="footer-link">Candidatos</a>
                    <a href="auth/register.php" class="footer-link">Criar Conta</a>
                </div>
                <?php endif; ?>

                <div class="footer-column">
                    <h4>Empresa</h4>
                    <a href="sobre.php" class="footer-link">Sobre Nós</a>
                        <a href="contacto.php" class="footer-link">Contacto</a>
                        <a href="termos.php" class="footer-link">Termos de Uso</a>
                        <a href="privacidade.php" class="footer-link">Privacidade</a>
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

        // Toggle Filters Panel (Expandir/Recolher)
        function toggleFiltersPanel() {
            const panel = document.getElementById('filters-panel');
            const container = document.querySelector('.filters-container');
            
            if (panel.classList.contains('active')) {
                panel.classList.remove('active');
            } else {
                panel.classList.add('active');
            }
        }

        // Sticky Filters - Feedback visual quando colado no topo
        const observeStickyFilters = () => {
            const filtersContainer = document.querySelector('.filters-container');
            if (!filtersContainer) return;

            const observer = new IntersectionObserver(
                ([entry]) => {
                    // Quando os filtros ficam "colados" no topo
                    if (entry.intersectionRatio < 1) {
                        filtersContainer.classList.add('is-stuck');
                    } else {
                        filtersContainer.classList.remove('is-stuck');
                    }
                },
                { 
                    threshold: [1],
                    rootMargin: '-71px 0px 0px 0px' // Altura do header + 1px
                }
            );

            observer.observe(filtersContainer);
        };

        // Inicializar observador de sticky
        observeStickyFilters();

        // Clear all filters
        function clearFilters() {
            window.location.href = 'vagas.php';
        }

        // Remove specific filter
        function removeFilter(filterName) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filterName);
            url.searchParams.set('pagina', '1');
            window.location.href = url.toString();
        }

        // Sort jobs
        function sortJobs(order) {
            const url = new URL(window.location.href);
            url.searchParams.set('ordem', order);
            url.searchParams.set('pagina', '1');
            window.location.href = url.toString();
        }

        // ==========================================
        // 💾 GUARDAR VAGA - AJAX
        // ==========================================
        async function saveJob(jobId) {
            const btn = event.currentTarget;
            const isSaved = btn.classList.contains('saved');
            const action = isSaved ? 'remove' : 'save';
            
            // Desabilitar botão durante requisição
            btn.disabled = true;
            btn.style.opacity = '0.6';
            
            try {
                const response = await fetch('api/guardar_vaga.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        vaga_id: jobId,
                        action: action
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Atualizar visual do botão
                    if (data.is_saved) {
                        btn.classList.add('saved');
                    } else {
                        btn.classList.remove('saved');
                    }
                    
                    // Mostrar notificação de sucesso
                    showNotification(data.message, 'success');
                } else {
                    // Erro - verificar se precisa de login
                    if (data.redirect) {
                        showNotification(data.message, 'warning');
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 2000);
                    } else {
                        showNotification(data.message, 'error');
                    }
                }
                
            } catch (error) {
                console.error('Erro ao guardar vaga:', error);
                showNotification('Erro ao processar requisição. Tente novamente.', 'error');
            } finally {
                // Reabilitar botão
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        }
        
        // ==========================================
        // 📥 CARREGAR VAGAS GUARDADAS (Estado Inicial)
        // ==========================================
        async function loadSavedJobs() {
            try {
                const response = await fetch('api/obter_vagas_guardadas.php');
                const data = await response.json();
                
                if (data.success && data.vagas_ids.length > 0) {
                    // Marcar botões das vagas guardadas
                    data.vagas_ids.forEach(vagaId => {
                        const btn = document.querySelector(`[onclick="saveJob(${vagaId})"]`);
                        if (btn) {
                            btn.classList.add('saved');
                        }
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar vagas guardadas:', error);
            }
        }
        
        // ==========================================
        // 🔔 SISTEMA DE NOTIFICAÇÕES
        // ==========================================
        function showNotification(message, type = 'info') {
            // Remover notificações anteriores
            const oldNotification = document.querySelector('.notification');
            if (oldNotification) {
                oldNotification.remove();
            }
            
            // Criar notificação
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i data-lucide="${getNotificationIcon(type)}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            // Adicionar ao DOM
            document.body.appendChild(notification);
            
            // Inicializar ícones
            lucide.createIcons();
            
            // Mostrar com animação
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            // Remover após 4 segundos
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 4000);
        }
        
        function getNotificationIcon(type) {
            const icons = {
                'success': 'check-circle',
                'error': 'x-circle',
                'warning': 'alert-circle',
                'info': 'info'
            };
            return icons[type] || 'info';
        }

        // Share job
        function shareJob(jobId) {
            const url = window.location.origin + '/vaga_detalhe.php?id=' + jobId;

            if (navigator.share) {
                navigator.share({
                    title: 'Vaga de Emprego',
                    text: 'Confira esta oportunidade de emprego!',
                    url: url
                }).catch(err => console.log('Erro ao partilhar:', err));
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(url).then(() => {
                    alert('Link copiado para a área de transferência!');
                }).catch(err => {
                    console.error('Erro ao copiar:', err);
                });
            }
        }

        // Reinitialize Lucide icons after dynamic content
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            
            // Carregar vagas guardadas ao carregar a página
            loadSavedJobs();
        });
    </script>

</body>
</html>
