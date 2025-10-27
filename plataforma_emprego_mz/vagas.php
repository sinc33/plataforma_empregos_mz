<?php
session_start();
require_once 'config/db.php';

$pdo = getPDO();

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
// LOCALIZAÇÕES PARA FILTRO
// ========================================
$locais = ['Maputo', 'Matola', 'Beira', 'Nampula', 'Chimoio', 'Quelimane', 'Tete', 'Inhambane'];

// ========================================
// FUNÇÕES AUXILIARES
// ========================================

// Obter logotipo da empresa (ou imagem padrão)
function getLogoEmpresa($logotipo) {
    if (!empty($logotipo) && file_exists('uploads/' . $logotipo)) {
        return 'uploads/' . $logotipo;
    }
    // Imagem padrão que você baixou
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
        return 'Publicada hoje';
    } elseif ($diferenca->days == 1) {
        return 'Publicada há 1 dia';
    } elseif ($diferenca->days < 7) {
        return 'Publicada há ' . $diferenca->days . ' dias';
    } elseif ($diferenca->days < 30) {
        $semanas = floor($diferenca->days / 7);
        return 'Publicada há ' . $semanas . ($semanas > 1 ? ' semanas' : ' semana');
    } else {
        $meses = floor($diferenca->days / 30);
        return 'Publicada há ' . $meses . ($meses > 1 ? ' meses' : ' mês');
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
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vagas de Emprego em Moçambique - Emprego MZ</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        /* ========================================
           🎨 RESET & BASE
        ======================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --cor-primaria: #0088CC;
            --cor-secundaria: #FF8C00;
            --cor-texto: #1A1A1A;
            --cor-texto-claro: #5A5A5A;
            --cor-borda: #E0E0E0;
            --cor-fundo: #F5F5F5;
            --cor-branco: #FFFFFF;
            --cor-hover: #006699;

            --font-principal: 'Inter', -apple-system, sans-serif;
            --border-radius: 8px;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: var(--font-principal);
            color: var(--cor-texto);
            background: var(--cor-fundo);
            line-height: 1.6;
        }

        /* ========================================
           📱 HEADER SIMPLES
        ======================================== */
        .header {
            background: var(--cor-branco);
            border-bottom: 1px solid var(--cor-borda);
            padding: 16px 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: var(--shadow-sm);
        }

        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--cor-primaria);
            font-size: 24px;
            font-weight: 800;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--cor-primaria);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .btn-voltar {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--cor-primaria);
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: color 0.2s;
        }

        .btn-voltar:hover {
            color: var(--cor-hover);
        }

        /* ========================================
           📋 LAYOUT PRINCIPAL
        ======================================== */
        .main-container {
            max-width: 1400px;
            margin: 100px auto 60px;
            padding: 0 24px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 32px;
        }

        /* ========================================
           🔍 SIDEBAR FILTROS
        ======================================== */
        .sidebar {
            position: sticky;
            top: 100px;
            height: fit-content;
        }

        .filter-section {
            background: var(--cor-branco);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-sm);
        }

        .filter-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--cor-texto);
        }

        .filter-input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--cor-borda);
            border-radius: var(--border-radius);
            font-size: 14px;
            font-family: var(--font-principal);
            margin-bottom: 12px;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--cor-primaria);
        }

        .filter-select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--cor-borda);
            border-radius: var(--border-radius);
            font-size: 14px;
            font-family: var(--font-principal);
            margin-bottom: 12px;
            background: white;
        }

        .filter-radio {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--cor-primaria);
        }

        .radio-option label {
            font-size: 14px;
            color: var(--cor-texto);
            cursor: pointer;
        }

        .filter-buttons {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }

        .btn-limpar {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--cor-borda);
            background: white;
            color: var(--cor-texto);
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-limpar:hover {
            background: var(--cor-fundo);
        }

        .btn-aplicar {
            flex: 1;
            padding: 10px;
            border: none;
            background: var(--cor-primaria);
            color: white;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-aplicar:hover {
            background: var(--cor-hover);
        }

        .salary-range {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 12px;
        }

        .salary-range input {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--cor-borda);
            border-radius: var(--border-radius);
            font-size: 13px;
        }

        .salary-range span {
            color: var(--cor-texto-claro);
            font-size: 14px;
        }

        /* ========================================
           📄 ÁREA DE VAGAS
        ======================================== */
        .vagas-area {
            background: transparent;
        }

        .vagas-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .vagas-count {
            font-size: 20px;
            font-weight: 700;
            color: var(--cor-texto);
        }

        .vagas-count span {
            color: var(--cor-primaria);
        }

        .ordenacao {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ordenacao label {
            font-size: 14px;
            color: var(--cor-texto-claro);
        }

        .ordenacao select {
            padding: 8px 12px;
            border: 1px solid var(--cor-borda);
            border-radius: var(--border-radius);
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        /* ========================================
           💼 CARD DE VAGA COM LOGO
        ======================================== */
        .vaga-card {
            background: var(--cor-branco);
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s;
            position: relative;
        }

        .vaga-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .vaga-header {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }

        /* 🖼️ LOGO DA EMPRESA */
        .empresa-logo {
            width: 56px;
            height: 56px;
            flex-shrink: 0;
            border-radius: var(--border-radius);
            overflow: hidden;
            background: var(--cor-fundo);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--cor-borda);
        }

        .empresa-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .empresa-logo i {
            width: 28px;
            height: 28px;
            color: var(--cor-texto-claro);
        }

        .vaga-content {
            flex: 1;
        }

        .vaga-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .vaga-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--cor-texto);
            margin-bottom: 8px;
            text-decoration: none;
            display: block;
        }

        .vaga-title:hover {
            color: var(--cor-primaria);
        }

        .vaga-empresa {
            font-size: 15px;
            color: var(--cor-texto-claro);
            margin-bottom: 12px;
        }

        .btn-compartilhar {
            padding: 8px 16px;
            border: 1px solid var(--cor-borda);
            background: white;
            color: var(--cor-texto-claro);
            border-radius: var(--border-radius);
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .btn-compartilhar:hover {
            background: var(--cor-fundo);
            color: var(--cor-primaria);
        }

        .vaga-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 16px;
            padding: 12px 0;
            border-top: 1px solid var(--cor-borda);
            border-bottom: 1px solid var(--cor-borda);
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            color: var(--cor-texto-claro);
        }

        .info-item i {
            width: 16px;
            height: 16px;
            color: var(--cor-primaria);
        }

        .vaga-salario {
            font-weight: 600;
            color: var(--cor-texto);
        }

        .vaga-descricao {
            font-size: 14px;
            color: var(--cor-texto-claro);
            line-height: 1.6;
            margin-bottom: 16px;
            max-height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .vaga-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .data-publicacao {
            font-size: 13px;
            color: var(--cor-texto-claro);
        }

        .vaga-actions {
            display: flex;
            gap: 12px;
        }

        .btn-detalhes {
            padding: 10px 20px;
            border: 1px solid var(--cor-primaria);
            background: white;
            color: var(--cor-primaria);
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-detalhes:hover {
            background: var(--cor-fundo);
        }

        .btn-candidatar {
            padding: 10px 24px;
            border: none;
            background: var(--cor-primaria);
            color: white;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-candidatar:hover {
            background: var(--cor-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        /* ========================================
           ⚠️ MENSAGEM VAZIA
        ======================================== */
        .empty-state {
            background: var(--cor-branco);
            border-radius: var(--border-radius);
            padding: 60px 40px;
            text-align: center;
            box-shadow: var(--shadow-sm);
        }

        .empty-state i {
            width: 64px;
            height: 64px;
            color: var(--cor-texto-claro);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: var(--cor-texto);
            margin-bottom: 12px;
        }

        .empty-state p {
            font-size: 15px;
            color: var(--cor-texto-claro);
            margin-bottom: 24px;
        }

        /* ========================================
           📖 PAGINAÇÃO
        ======================================== */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 32px;
            padding: 20px 0;
        }

        .page-link {
            padding: 10px 16px;
            border: 1px solid var(--cor-borda);
            background: white;
            color: var(--cor-texto);
            border-radius: var(--border-radius);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .page-link:hover {
            background: var(--cor-fundo);
            color: var(--cor-primaria);
        }

        .page-link.active {
            background: var(--cor-primaria);
            color: white;
            border-color: var(--cor-primaria);
        }

        .page-link:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ========================================
           📱 RESPONSIVE
        ======================================== */
        @media (max-width: 1024px) {
            .main-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .vagas-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }

            .vaga-card {
                padding: 16px;
            }

            .vaga-header {
                flex-direction: row;
            }

            .empresa-logo {
                width: 48px;
                height: 48px;
            }

            .vaga-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .btn-compartilhar {
                align-self: flex-start;
            }

            .vaga-info {
                flex-direction: column;
                gap: 12px;
            }

            .vaga-footer {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .vaga-actions {
                width: 100%;
                flex-direction: column;
            }

            .btn-detalhes,
            .btn-candidatar {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <!-- ========================================
         📱 HEADER
    ======================================== -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i data-lucide="briefcase" style="width: 24px; height: 24px;"></i>
                </div>
                emprego<strong>s</strong>
            </a>
            <a href="index.php" class="btn-voltar">
                <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
                Voltar ao início
            </a>
        </div>
    </header>

    <!-- ========================================
         📋 LAYOUT PRINCIPAL
    ======================================== -->
    <div class="main-container">

        <!-- ========================================
             🔍 SIDEBAR FILTROS
        ======================================== -->
        <aside class="sidebar">
            <form method="GET" action="vagas.php" id="filtrosForm">

                <!-- Buscar -->
                <div class="filter-section">
                    <h3 class="filter-title">Buscar</h3>
                    <input 
                        type="text" 
                        name="q" 
                        class="filter-input" 
                        placeholder="Cargo, palavra-chave"
                        value="<?php echo htmlspecialchars($filtro_pesquisa); ?>"
                    >
                </div>

                <!-- Data -->
                <div class="filter-section">
                    <h3 class="filter-title">Data</h3>
                    <select name="data" class="filter-select">
                        <option value="">Todas as datas</option>
                        <option value="hoje" <?php echo $filtro_data === 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                        <option value="ultimos_7_dias" <?php echo $filtro_data === 'ultimos_7_dias' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                        <option value="ultimos_30_dias" <?php echo $filtro_data === 'ultimos_30_dias' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                    </select>
                </div>

                <!-- Salário -->
                <div class="filter-section">
                    <h3 class="filter-title">Salário (MT)</h3>
                    <div class="salary-range">
                        <input 
                            type="number" 
                            name="salario_min" 
                            placeholder="De"
                            value="<?php echo $filtro_salario_min > 0 ? $filtro_salario_min : ''; ?>"
                        >
                        <span>até</span>
                        <input 
                            type="number" 
                            name="salario_max" 
                            placeholder="Até"
                            value="<?php echo $filtro_salario_max > 0 ? $filtro_salario_max : ''; ?>"
                        >
                    </div>
                </div>

                <!-- Tipo de vaga -->
                <div class="filter-section">
                    <h3 class="filter-title">Tipo de vaga</h3>
                    <div class="filter-radio">
                        <div class="radio-option">
                            <input type="radio" name="modalidade" value="" id="todas" <?php echo empty($filtro_modalidade) ? 'checked' : ''; ?>>
                            <label for="todas">Todas</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="modalidade" value="presencial" id="presencial" <?php echo $filtro_modalidade === 'presencial' ? 'checked' : ''; ?>>
                            <label for="presencial">Presencial</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="modalidade" value="hibrido" id="hibrido" <?php echo $filtro_modalidade === 'hibrido' ? 'checked' : ''; ?>>
                            <label for="hibrido">Híbrido</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="modalidade" value="remoto" id="remoto" <?php echo $filtro_modalidade === 'remoto' ? 'checked' : ''; ?>>
                            <label for="remoto">Remoto</label>
                        </div>
                    </div>
                </div>

                <!-- Área/Cargo -->
                <div class="filter-section">
                    <h3 class="filter-title">Área</h3>
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
                <div class="filter-section">
                    <h3 class="filter-title">Localização</h3>
                    <select name="local" class="filter-select">
                        <option value="">Todas as localizações</option>
                        <?php foreach ($locais as $local): ?>
                            <option value="<?php echo htmlspecialchars($local); ?>" <?php echo $filtro_localizacao === $local ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($local); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Botões -->
                <div class="filter-section">
                    <div class="filter-buttons">
                        <button type="button" class="btn-limpar" onclick="limparFiltros()">Limpar</button>
                        <button type="submit" class="btn-aplicar">Aplicar filtro</button>
                    </div>
                </div>

            </form>
        </aside>

        <!-- ========================================
             📄 LISTA DE VAGAS
        ======================================== -->
        <main class="vagas-area">

            <!-- Header com contagem -->
            <div class="vagas-header">
                <div class="vagas-count">
                    <span><?php echo number_format($total_vagas, 0, ',', '.'); ?></span> 
                    vagas de emprego na Empregos.com.mz
                </div>

                <div class="ordenacao">
                    <label for="ordem">Ordenar por:</label>
                    <select name="ordem" id="ordem" onchange="ordenarVagas(this.value)">
                        <option value="recentes" <?php echo $ordenacao === 'recentes' ? 'selected' : ''; ?>>Mais recentes</option>
                        <option value="salario_desc" <?php echo $ordenacao === 'salario_desc' ? 'selected' : ''; ?>>Maior salário</option>
                        <option value="salario_asc" <?php echo $ordenacao === 'salario_asc' ? 'selected' : ''; ?>>Menor salário</option>
                        <option value="empresa" <?php echo $ordenacao === 'empresa' ? 'selected' : ''; ?>>Empresa (A-Z)</option>
                    </select>
                </div>
            </div>

            <!-- Lista de vagas -->
            <?php if (count($vagas) > 0): ?>
                <?php foreach ($vagas as $vaga): ?>
                    <article class="vaga-card">
                        <div class="vaga-header">
                            <!-- 🖼️ LOGO DA EMPRESA -->
                            <div class="empresa-logo">
                                <?php if (!empty($vaga['logotipo']) && file_exists('uploads/' . $vaga['logotipo'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($vaga['logotipo']); ?>" 
                                         alt="<?php echo htmlspecialchars($vaga['nome_empresa']); ?>">
                                <?php else: ?>
                                    <img src="assets/images/empresa-default.png" 
                                         alt="Logo padrão">
                                <?php endif; ?>
                            </div>

                            <div class="vaga-content">
                                <div class="vaga-top">
                                    <div>
                                        <a href="vaga_detalhe.php?id=<?php echo $vaga['id']; ?>" class="vaga-title">
                                            <?php echo htmlspecialchars($vaga['titulo']); ?>
                                        </a>
                                        <div class="vaga-empresa">
                                            <?php echo htmlspecialchars($vaga['nome_empresa']); ?>
                                        </div>
                                    </div>
                                    <button class="btn-compartilhar" onclick="compartilharVaga(<?php echo $vaga['id']; ?>)">
                                        <i data-lucide="share-2" style="width: 14px; height: 14px;"></i>
                                        Compartilhar vaga
                                    </button>
                                </div>

                                <div class="vaga-info">
                                    <div class="info-item">
                                        <i data-lucide="map-pin"></i>
                                        <?php echo htmlspecialchars($vaga['localizacao']); ?>
                                    </div>
                                    <div class="info-item">
                                        <i data-lucide="monitor"></i>
                                        <?php echo traduzirModalidade($vaga['modalidade']); ?>
                                    </div>
                                    <div class="info-item vaga-salario">
                                        <i data-lucide="banknote"></i>
                                        <?php echo formatarSalario($vaga['salario_estimado']); ?>
                                    </div>
                                </div>

                                <p class="vaga-descricao">
                                    <?php echo htmlspecialchars(substr($vaga['descricao'], 0, 200)) . '...'; ?>
                                </p>

                                <div class="vaga-footer">
                                    <span class="data-publicacao">
                                        <?php echo tempoDecorrido($vaga['data_publicacao']); ?>
                                    </span>
                                    <div class="vaga-actions">
                                        <a href="vaga_detalhe.php?id=<?php echo $vaga['id']; ?>" class="btn-detalhes">
                                            Mais detalhes
                                        </a>
                                        <a href="vaga_detalhe.php?id=<?php echo $vaga['id']; ?>" class="btn-candidatar">
                                            Me candidatar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>

                <!-- Paginação -->
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
                <!-- Mensagem quando não há vagas -->
                <div class="empty-state">
                    <i data-lucide="search-x"></i>
                    <h3>Nenhuma vaga encontrada</h3>
                    <p>Tente ajustar os filtros ou limpar todos os filtros para ver mais resultados</p>
                    <button onclick="limparFiltros()" class="btn-aplicar" style="margin: 0 auto; display: block;">
                        Limpar filtros
                    </button>
                </div>
            <?php endif; ?>

        </main>

    </div>

    <!-- ========================================
         ✨ SCRIPTS
    ======================================== -->
    <script>
        // Inicializar ícones Lucide
        lucide.createIcons();

        // Limpar todos os filtros
        function limparFiltros() {
            window.location.href = 'vagas.php';
        }

        // Ordenar vagas
        function ordenarVagas(ordem) {
            const url = new URL(window.location.href);
            url.searchParams.set('ordem', ordem);
            url.searchParams.set('pagina', '1');
            window.location.href = url.toString();
        }

        // Compartilhar vaga
        function compartilharVaga(vagaId) {
            const url = window.location.origin + '/vaga_detalhe.php?id=' + vagaId;

            if (navigator.share) {
                navigator.share({
                    title: 'Vaga de Emprego',
                    text: 'Confira esta vaga de emprego!',
                    url: url
                });
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    alert('Link copiado para a área de transferência!');
                });
            }
        }
    </script>
</body>
</html>