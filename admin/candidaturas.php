<?php
session_start();
require_once '../config/db.php';
require_once '../config/admin_functions.php';

// Verificar se é admin logado
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$pdo = getPDO();

// ========================================
// PROCESSAR AÇÕES ADMINISTRATIVAS
// ========================================
$sucesso = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    try {
        if ($acao === 'excluir_candidatura') {
            $candidatura_id = (int)$_POST['candidatura_id'];
            
            // Buscar dados da candidatura para o log
            $stmt_info = $pdo->prepare("
                SELECT c.nome_completo, v.titulo, e.nome_empresa 
                FROM candidatura ca
                JOIN candidato c ON ca.candidato_id = c.id
                JOIN vaga v ON ca.vaga_id = v.id
                JOIN empresa e ON v.empresa_id = e.id
                WHERE ca.id = ?
            ");
            $stmt_info->execute([$candidatura_id]);
            $candidatura_info = $stmt_info->fetch();
            
            $stmt = $pdo->prepare("DELETE FROM candidatura WHERE id = ?");
            $stmt->execute([$candidatura_id]);
            
            // Registrar log de auditoria
            if ($candidatura_info) {
                logAdminAction($pdo, $admin_id, 'DELETE', 'candidatura', $candidatura_id, 
                    "Excluiu candidatura: {$candidatura_info['nome_completo']} para {$candidatura_info['titulo']} ({$candidatura_info['nome_empresa']})");
            }
            
            $sucesso = "Candidatura excluída com sucesso!";
        }
    } catch (PDOException $e) {
        $erro = "❌ Erro no sistema. Por favor, tente novamente.";
        error_log("Erro PDO em admin/candidaturas.php (Admin: $admin_id): " . $e->getMessage());
    } catch (Exception $e) {
        $erro = "❌ Erro inesperado. Por favor, tente novamente.";
        error_log("Erro inesperado em admin/candidaturas.php: " . $e->getMessage());
    }
}

// ========================================
// FILTROS E PAGINAÇÃO
// ========================================
$filtro_estado = $_GET['estado'] ?? '';
$filtro_vaga = $_GET['vaga'] ?? '';
$filtro_candidato = $_GET['candidato'] ?? '';
$filtro_empresa = $_GET['empresa'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';

$candidaturas_por_pagina = 20;
$pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_atual - 1) * $candidaturas_por_pagina;

// ========================================
// ESTATÍSTICAS PARA CARDS
// ========================================
$stmt = $pdo->query("SELECT COUNT(*) FROM candidatura");
$total_candidaturas = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM candidatura WHERE estado = 'submetida'");
$candidaturas_novas = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM candidatura WHERE estado = 'em_analise'");
$candidaturas_analise = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM candidatura WHERE estado = 'entrevista'");
$candidaturas_entrevista = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM candidatura WHERE estado = 'contratado'");
$candidaturas_aprovadas = $stmt->fetchColumn();

// ========================================
// QUERY PRINCIPAL COM FILTROS
// ========================================
$sql = "SELECT c.*, 
               cand.nome_completo as candidato_nome,
               cand.telefone as candidato_telefone,
               v.titulo as vaga_titulo,
               v.area as vaga_area,
               e.nome_empresa,
               e.logotipo as empresa_logo,
               u.email as usuario_email
        FROM candidatura c
        JOIN candidato cand ON c.candidato_id = cand.id
        JOIN vaga v ON c.vaga_id = v.id
        JOIN empresa e ON v.empresa_id = e.id
        JOIN utilizador u ON cand.id = u.id";

$where_conditions = [];
$params = [];

// Aplicar filtros
if (!empty($filtro_estado)) {
    $where_conditions[] = "c.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_vaga)) {
    $where_conditions[] = "v.titulo LIKE ?";
    $params[] = "%$filtro_vaga%";
}

if (!empty($filtro_candidato)) {
    $where_conditions[] = "cand.nome_completo LIKE ?";
    $params[] = "%$filtro_candidato%";
}

if (!empty($filtro_empresa)) {
    $where_conditions[] = "e.nome_empresa LIKE ?";
    $params[] = "%$filtro_empresa%";
}

if (!empty($filtro_data_inicio)) {
    $where_conditions[] = "c.data_candidatura >= ?";
    $params[] = $filtro_data_inicio;
}

if (!empty($filtro_data_fim)) {
    $where_conditions[] = "c.data_candidatura <= ?";
    $params[] = $filtro_data_fim . ' 23:59:59';
}

// Construir WHERE clause
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// Contar total para paginação
$sql_count = preg_replace('/^SELECT.*FROM/', 'SELECT COUNT(*) FROM', $sql);
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $candidaturas_por_pagina);

// Adicionar ORDER BY e LIMIT
$sql .= " ORDER BY c.data_candidatura DESC LIMIT ? OFFSET ?";
$params[] = $candidaturas_por_pagina;
$params[] = $offset;

// Executar query principal
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$candidaturas = $stmt->fetchAll();

// ========================================
// FUNÇÕES AUXILIARES
// ========================================
function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

function formatarDataHora($data) {
    return date('d/m/Y H:i', strtotime($data));
}

function getEstadoBadge($estado) {
    $badges = [
        'submetida' => ['label' => 'Nova', 'class' => 'estado-nova'],
        'em_analise' => ['label' => 'Em Análise', 'class' => 'estado-analise'],
        'entrevista' => ['label' => 'Entrevista', 'class' => 'estado-entrevista'],
        'contratado' => ['label' => 'Aprovado', 'class' => 'estado-aprovado'],
        'rejeitada' => ['label' => 'Rejeitada', 'class' => 'estado-rejeitada']
    ];
    
    return $badges[$estado] ?? ['label' => $estado, 'class' => ''];
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Candidaturas - Administração</title>
    
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
            /* 🎨 CORES PRINCIPAIS - Nova Identidade Visual Profissional */
            --cor-primaria: #14213d;              /* Oxford Blue */
            --cor-primaria-escura: #0f1a2e;       /* Oxford Blue Dark */
            --cor-primaria-clara: #1e2c47;        /* Oxford Blue Light */
            --cor-primaria-alpha: rgba(20, 33, 61, 0.08);
            
            --cor-secundaria: #fca311;            /* Orange Web */
            --cor-secundaria-hover: #e3940f;      /* Orange Hover */
            --cor-secundaria-alpha: rgba(252, 163, 17, 0.08);
            
            /* Aliases */
            --primary: var(--cor-primaria);
            --primary-dark: var(--cor-primaria-escura);
            --primary-light: var(--cor-primaria-alpha);
            --secondary: var(--cor-secundaria);
            --text: #1A202C;
            --text-medium: #475569;
            --text-light: #64748B;
            --border: #e5e7eb;
            --border-light: #f1f5f9;
            --background: #f8fafc;
            --cor-fundo: #f8fafc;
            --cor-cards: #ffffff;
            --cor-acento: #f1f5ff;
            --white: #FFFFFF;
            --success: #10B981;
            --success-light: rgba(16, 185, 129, 0.08);
            --warning: var(--cor-secundaria);
            --warning-light: var(--cor-secundaria-alpha);
            --error: #EF4444;
            --error-light: rgba(239, 68, 68, 0.08);
            --info: var(--cor-primaria);
            --info-light: var(--cor-primaria-alpha);
            
            /* Admin Executive Colors - usando Oxford Blue */
            --admin-primary: var(--cor-primaria);
            --admin-primary-light: var(--cor-primaria-alpha);
            --admin-dark: #1E293B;
            --admin-darker: var(--cor-primaria-escura);
            --cor-primaria-admin: var(--cor-primaria);
            --cor-primaria-admin-escura: var(--cor-primaria-escura);
            --cor-primaria-admin-clara: var(--cor-primaria-clara);
            --cor-secundaria-admin: var(--cor-secundaria);
            --cor-secundaria-admin-hover: var(--cor-secundaria-hover);
            
            /* === GRADIENTES ADMIN === */
            --gradiente-admin-primario: linear-gradient(135deg, var(--cor-primaria-admin), var(--cor-primaria-admin-clara));
            --gradiente-admin-secundario: linear-gradient(135deg, var(--cor-secundaria-admin), var(--cor-secundaria-admin-hover));
            --gradiente-admin-fundo: linear-gradient(135deg, rgba(20, 33, 61, 0.02), rgba(20, 33, 61, 0.01));
            
            /* === SIDEBAR E NAVEGAÇÃO === */
            --cor-sidebar-admin: var(--cor-primaria-admin);
            --cor-sidebar-hover: rgba(252, 163, 17, 0.1);
            --cor-sidebar-ativo: var(--cor-secundaria-admin);
            --cor-topbar-admin: var(--cor-cards-admin);
            
            /* === BORDAS E SOMBRAS ADMIN === */
            --cor-borda-admin: var(--border);
            --cor-borda-admin-ativa: var(--cor-primaria-admin);
            --sombra-admin-suave: 0 1px 3px rgba(20, 33, 61, 0.05);
            --sombra-admin-card: 0 4px 24px rgba(20, 33, 61, 0.08);
            --sombra-admin-elevada: 0 8px 40px rgba(20, 33, 61, 0.12);
            --sombra-admin-modal: 0 16px 64px rgba(20, 33, 61, 0.20);
            --sombra-admin-button: 0 2px 8px rgba(252, 163, 17, 0.3);
            --sombra-admin-input-focus: 0 0 0 4px rgba(20, 33, 61, 0.1);
            
            /* === TRANSIÇÕES ADMIN === */
            --transicao-admin-suave: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --transicao-admin-elevacao: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            
            /* Typography Scale */
            --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-size-xs: 11px;
            --font-size-sm: 13px;
            --font-size-base: 14px;
            --font-size-md: 16px;
            --font-size-lg: 18px;
            --font-size-xl: 20px;
            --font-size-2xl: 24px;
            --font-size-3xl: 32px;
            --font-size-4xl: 40px;
            --font-weight-normal: 400;
            --font-weight-medium: 500;
            --font-weight-semibold: 600;
            --font-weight-bold: 700;
            --font-weight-extrabold: 800;
            --line-height-tight: 1.25;
            --line-height-normal: 1.5;
            --line-height-relaxed: 1.625;
            
            /* Spacing System - 8px Grid */
            --space-0: 0;
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
            
            /* Border Radius System */
            --radius-sm: 6px;
            --radius: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-2xl: 20px;
            --radius-full: 9999px;
            
            /* Shadow System - Elevation Hierarchy */
            --shadow-xs: 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.08), 0 1px 2px 0 rgba(0, 0, 0, 0.04);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 6px 12px -2px rgba(0, 0, 0, 0.08), 0 3px 7px -3px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.12);
            
            /* Transition */
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: var(--font-primary);
            background: var(--background);
            color: var(--text);
            font-size: var(--font-size-base);
            line-height: var(--line-height-normal);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ==========================================
           🏛️ ADMIN HEADER - Organized & Structured
        ========================================== */
        .admin-header {
            background: var(--gradiente-admin-primario);
            color: var(--white);
            box-shadow: var(--sombra-admin-card);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 2px solid var(--cor-secundaria-admin);
        }
        
        .header-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 var(--space-8);
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
            gap: var(--space-8);
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: var(--space-6);
        }
        
        .admin-logo {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            text-decoration: none;
            color: var(--white);
        }
        
        .admin-logo img {
            height: 40px;
            width: auto;
            /* Removido filtro para manter cores originais do logo */
        }
        
        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: 8px var(--space-4);
            background: rgba(252, 163, 17, 0.15);
            border: 2px solid var(--cor-secundaria-admin);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-extrabold);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            line-height: 1;
            color: var(--cor-secundaria-admin);
            box-shadow: var(--sombra-admin-button);
        }
        
        .admin-badge svg {
            width: 14px;
            height: 14px;
        }
        
        .header-nav {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: 10px var(--space-4);
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
            border-radius: var(--radius-lg);
            transition: var(--transicao-admin-suave);
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }
        
        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--cor-sidebar-hover);
            transition: var(--transicao-admin-suave);
        }
        
        .nav-item:hover::before {
            left: 0;
        }
        
        .nav-item:hover {
            color: var(--white);
        }
        
        .nav-item.active {
            background: var(--cor-sidebar-hover);
            color: var(--white);
            font-weight: var(--font-weight-semibold);
            border-left: 3px solid var(--cor-secundaria-admin);
            padding-left: calc(var(--space-4) - 3px);
        }
        
        .nav-item svg {
            width: 18px;
            height: 18px;
            position: relative;
            z-index: 1;
        }
        
        .nav-item span {
            position: relative;
            z-index: 1;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: var(--space-4);
        }
        
        .admin-user {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-2) var(--space-3);
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
        }
        
        .admin-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--admin-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }
        
        .admin-user-info {
            display: flex;
            flex-direction: column;
        }
        
        .admin-user-name {
            font-size: 14px;
            font-weight: 600;
        }
        
        .admin-user-role {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .btn-logout {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
            background: rgba(239, 68, 68, 0.2);
            color: var(--white);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .btn-logout:hover {
            background: var(--error);
            border-color: var(--error);
        }
        
        .btn-logout svg {
            width: 16px;
            height: 16px;
        }
        
        /* ==========================================
           📍 BREADCRUMB - Clear Navigation Path
        ========================================== */
        .breadcrumb-section {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: var(--space-4) var(--space-8);
        }
        
        .breadcrumb-container {
            max-width: 1440px;
            margin: 0 auto;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: var(--font-size-sm);
            color: var(--text-light);
            font-weight: var(--font-weight-medium);
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .breadcrumb a:hover {
            color: var(--primary-dark);
        }
        
        .breadcrumb svg {
            width: 16px;
            height: 16px;
        }

        /* ========================================
           📊 MAIN CONTENT & OVERVIEW CARDS
        ======================================== */
        .main-content {
            flex: 1;
            max-width: 1440px;
            margin: 0 auto;
            padding: var(--space-10) var(--space-8) var(--space-12);
            width: 100%;
        }

        .page-header {
            margin-bottom: var(--space-10);
        }

        .page-title {
            font-size: var(--font-size-3xl);
            font-weight: var(--font-weight-extrabold);
            color: var(--text);
            margin-bottom: var(--space-2);
            letter-spacing: -0.02em;
            line-height: var(--line-height-tight);
        }

        .page-description {
            font-size: var(--font-size-md);
            color: var(--text-medium);
            font-weight: var(--font-weight-normal);
            line-height: var(--line-height-relaxed);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: var(--space-6);
            margin-bottom: var(--space-12);
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--border-light);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .stat-icon.total { background: var(--primary); }
        .stat-icon.novas { background: var(--info); }
        .stat-icon.analise { background: var(--warning); }
        .stat-icon.entrevista { background: var(--admin-primary); }
        .stat-icon.aprovadas { background: var(--success); }

        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--text-medium);
            font-size: 14px;
            font-weight: 500;
        }

        /* ========================================
           🔍 FILTROS
        ======================================== */
        .filters-section {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .filters-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .filters-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }

        .filter-input,
        .filter-select {
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 14px;
            font-family: var(--font-primary);
            background: var(--white);
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .filters-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--background);
        }

        /* ========================================
           📋 TABELA PRINCIPAL
        ======================================== */
        .table-section {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }

        .table-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        .table-container {
            overflow-x: auto;
        }

        .candidaturas-table {
            width: 100%;
            border-collapse: collapse;
        }

        .candidaturas-table th {
            background: var(--background);
            padding: 16px 12px;
            text-align: left;
            font-size: var(--font-size-base);
            font-weight: var(--font-weight-semibold);
            color: var(--text);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .candidaturas-table td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border);
            font-size: var(--font-size-base);
        }

        .candidaturas-table tbody tr:hover {
            background: var(--background);
        }

        .candidato-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .candidato-nome {
            font-weight: 600;
            color: var(--text);
        }

        .candidato-email {
            font-size: 12px;
            color: var(--text-light);
        }

        .vaga-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .empresa-logo-mini {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            background: var(--background);
            border: 1px solid var(--border);
        }

        .estado-badge {
            padding: 6px 12px;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .estado-nova {
            background: var(--info-light);
            color: var(--info);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .estado-analise {
            background: var(--warning-light);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .estado-entrevista {
            background: var(--admin-primary-light);
            color: var(--admin-primary);
            border: 1px solid rgba(111, 66, 193, 0.2);
        }

        .estado-aprovado {
            background: var(--success-light);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .estado-rejeitada {
            background: var(--error-light);
            color: var(--error);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .table-actions {
            display: flex;
            gap: var(--space-2);
            align-items: center;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 4px;
        }

        .btn-danger {
            background: var(--error);
            color: white;
            border: none;
        }
        
        .btn-danger:hover {
            background: #DC2626;
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        /* ========================================
           📄 PAGINAÇÃO
        ======================================== */
        .pagination-section {
            padding: 20px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .pagination-info {
            color: var(--text-light);
            font-size: 14px;
        }

        .pagination {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        .page-link {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--text);
            font-size: 14px;
            transition: all 0.2s;
        }

        .page-link:hover {
            background: var(--background);
        }

        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* ========================================
           🔔 NOTIFICAÇÕES
        ======================================== */
        .notification {
            padding: 16px 20px;
            border-radius: var(--radius-xl);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .notification.success {
            background: #DCFCE7;
            color: #166534;
            border: 1px solid #BBF7D0;
        }

        .notification.error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        /* ==========================================
           🦶 FOOTER - Organized Structure
        ========================================== */
        .footer {
            background: var(--cor-primaria-admin);
            color: rgba(255, 255, 255, 0.95);
            padding: var(--space-12) var(--space-8) var(--space-8);
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .footer-container {
            max-width: 1440px;
            margin: 0 auto;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-10);
            margin-bottom: var(--space-10);
        }
        
        .footer-column h3,
        .footer-column h4 {
            color: #ffffff !important;
            font-size: 17px;
            font-weight: 800;
            margin-bottom: var(--space-5);
            line-height: var(--line-height-tight);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .footer-links {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }
        
        .footer-link {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition-fast);
            font-weight: 500;
            line-height: var(--line-height-relaxed);
        }
        
        .footer-link:hover {
            color: var(--cor-secundaria-admin);
            padding-left: var(--space-2);
            text-decoration: none;
        }
        
        .footer-bottom {
            padding-top: var(--space-8);
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            font-weight: 500;
            line-height: var(--line-height-relaxed);
        }

        /* ==========================================
           📱 RESPONSIVE
        ========================================== */
        @media (max-width: 1024px) {
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-8);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
                gap: var(--space-6);
            }
        }
    </style>
</head>
<body>
    <!-- ==========================================
         🏛️ ADMIN HEADER
    ========================================== -->
    <header class="admin-header">
        <div class="header-container">
            <div class="header-left">
                <a href="../index.php" class="admin-logo">
                    <img src="../assets/images/empregos-logo.svg" alt="Emprego MZ">
                    <span class="admin-badge">
                        <i data-lucide="shield-check"></i>
                        ADMIN
                    </span>
                </a>
                
                <nav class="header-nav">
                    <a href="index.php" class="nav-item">
                        <i data-lucide="layout-dashboard"></i>
                        Dashboard
                    </a>
                    <a href="vagas.php" class="nav-item">
                        <i data-lucide="briefcase"></i>
                        Gestão de Vagas
                    </a>
                    <a href="usuarios.php" class="nav-item">
                        <i data-lucide="users"></i>
                        Usuários
                    </a>
                    <a href="candidaturas.php" class="nav-item active">
                        <i data-lucide="file-check"></i>
                        Candidaturas
                    </a>
                </nav>
            </div>
            
            <div class="header-right">
                <div class="admin-user">
                    <div class="admin-user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin_nome'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div class="admin-user-info">
                        <div class="admin-user-name"><?php echo htmlspecialchars($_SESSION['admin_nome'] ?? 'Administrador'); ?></div>
                        <div class="admin-user-role">Administrador</div>
                    </div>
                </div>
                
                <a href="../auth/logout.php" class="btn-logout">
                    <i data-lucide="log-out"></i>
                    Sair
                </a>
            </div>
        </div>
    </header>

    <!-- ==========================================
         📍 BREADCRUMB
    ========================================== -->
    <div class="breadcrumb-section">
        <div class="breadcrumb-container">
            <div class="breadcrumb">
                <i data-lucide="home"></i>
                <span>Painel Administrativo</span>
                <i data-lucide="chevron-right"></i>
                <a href="index.php">Dashboard</a>
                <i data-lucide="chevron-right"></i>
                <span>Gestão de Candidaturas</span>
            </div>
        </div>
    </div>

    <!-- ==========================================
         📊 MAIN CONTENT
    ========================================== -->
    <main class="main-content">
        <!-- Cabeçalho da Página -->
        <div class="page-header">
            <h1 class="page-title">Gestão de Candidaturas</h1>
            <p class="page-description">Visualize e monitore todas as candidaturas da plataforma</p>
        </div>

        <!-- Notificações -->
        <?php if (!empty($sucesso)): ?>
            <div class="notification success">
                <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
                <?php echo htmlspecialchars($sucesso); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($erro)): ?>
            <div class="notification error">
                <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <!-- Cards de Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon total">
                        <i data-lucide="file-text" style="width: 24px; height: 24px;"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($total_candidaturas); ?></div>
                <div class="stat-label">Total de Candidaturas</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon novas">
                        <i data-lucide="inbox" style="width: 24px; height: 24px;"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($candidaturas_novas); ?></div>
                <div class="stat-label">Novas</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon analise">
                        <i data-lucide="search" style="width: 24px; height: 24px;"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($candidaturas_analise); ?></div>
                <div class="stat-label">Em Análise</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon entrevista">
                        <i data-lucide="user-check" style="width: 24px; height: 24px;"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($candidaturas_entrevista); ?></div>
                <div class="stat-label">Para Entrevista</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon aprovadas">
                        <i data-lucide="check-circle" style="width: 24px; height: 24px;"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($candidaturas_aprovadas); ?></div>
                <div class="stat-label">Aprovadas</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section" id="filtros-candidaturas">
            <div class="filters-header">
                <h2 class="filters-title">Filtros de Busca</h2>
            </div>
            
            <form method="GET" action="candidaturas.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Estado</label>
                        <select name="estado" class="filter-select">
                            <option value="">Todos os estados</option>
                            <option value="submetida" <?php echo $filtro_estado === 'submetida' ? 'selected' : ''; ?>>Novas</option>
                            <option value="em_analise" <?php echo $filtro_estado === 'em_analise' ? 'selected' : ''; ?>>Em Análise</option>
                            <option value="entrevista" <?php echo $filtro_estado === 'entrevista' ? 'selected' : ''; ?>>Para Entrevista</option>
                            <option value="contratado" <?php echo $filtro_estado === 'contratado' ? 'selected' : ''; ?>>Aprovadas</option>
                            <option value="rejeitada" <?php echo $filtro_estado === 'rejeitada' ? 'selected' : ''; ?>>Rejeitadas</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Vaga</label>
                        <input type="text" name="vaga" class="filter-input" 
                               placeholder="Título da vaga"
                               value="<?php echo htmlspecialchars($filtro_vaga); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Candidato</label>
                        <input type="text" name="candidato" class="filter-input" 
                               placeholder="Nome do candidato"
                               value="<?php echo htmlspecialchars($filtro_candidato); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Empresa</label>
                        <input type="text" name="empresa" class="filter-input" 
                               placeholder="Nome da empresa"
                               value="<?php echo htmlspecialchars($filtro_empresa); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Data Início</label>
                        <input type="date" name="data_inicio" class="filter-input"
                               value="<?php echo htmlspecialchars($filtro_data_inicio); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Data Fim</label>
                        <input type="date" name="data_fim" class="filter-input"
                               value="<?php echo htmlspecialchars($filtro_data_fim); ?>">
                    </div>
                </div>

                <div class="filters-actions">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="search" style="width: 16px; height: 16px;"></i>
                        Aplicar Filtros
                    </button>
                    <a href="candidaturas.php" class="btn btn-secondary">
                        <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                        Limpar Filtros
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabela de Candidaturas -->
        <div class="table-section">
            <div class="table-header">
                <h2 class="table-title">
                    Candidaturas Encontradas (<?php echo number_format($total_registros); ?>)
                </h2>
            </div>

            <div class="table-container">
                <table class="candidaturas-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Candidato</th>
                            <th>Vaga</th>
                            <th>Empresa</th>
                            <th>Área</th>
                            <th>Data</th>
                            <th>Estado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($candidaturas)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-light);">
                                    <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 16px;"></i>
                                    <br>Nenhuma candidatura encontrada com os filtros aplicados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($candidaturas as $candidatura): ?>
                                <?php $estado = getEstadoBadge($candidatura['estado']); ?>
                                <tr>
                                    <td><?php echo $candidatura['id']; ?></td>
                                    <td>
                                        <div class="candidato-info">
                                            <span class="candidato-nome"><?php echo htmlspecialchars($candidatura['candidato_nome']); ?></span>
                                            <span class="candidato-email"><?php echo htmlspecialchars($candidatura['usuario_email']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($candidatura['vaga_titulo']); ?></td>
                                    <td>
                                        <div class="vaga-info">
                                            <?php if (!empty($candidatura['empresa_logo'])): ?>
                                                <img src="../<?php echo htmlspecialchars($candidatura['empresa_logo']); ?>" 
                                                     class="empresa-logo-mini" alt="Logo">
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($candidatura['nome_empresa']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($candidatura['vaga_area']); ?></td>
                                    <td><?php echo formatarDataHora($candidatura['data_candidatura']); ?></td>
                                    <td>
                                        <span class="estado-badge <?php echo $estado['class']; ?>">
                                            <?php echo $estado['label']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <form method="POST" style="display: inline;" 
                                                  class="form-excluir-candidatura"
                                                  data-candidato="<?php echo htmlspecialchars($candidatura['candidato_nome']); ?>"
                                                  data-vaga="<?php echo htmlspecialchars($candidatura['vaga_titulo']); ?>">
                                                <input type="hidden" name="acao" value="excluir_candidatura">
                                                <input type="hidden" name="candidatura_id" value="<?php echo $candidatura['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Excluir">
                                                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_paginas > 1): ?>
                <div class="pagination-section">
                    <div class="pagination-info">
                        Exibindo <?php echo min($offset + 1, $total_registros); ?>-<?php echo min($offset + $candidaturas_por_pagina, $total_registros); ?> 
                        de <?php echo number_format($total_registros); ?> candidaturas
                    </div>
                    
                    <nav class="pagination">
                        <?php if ($pagina_atual > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])); ?>" 
                               class="page-link">Anterior</a>
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
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])); ?>" 
                               class="page-link">Próxima</a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ==========================================
         🦶 FOOTER
    ========================================== -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-column">
                    <img src="../assets/images/empregos-logo.svg" alt="Emprego MZ" class="footer-logo">
                    <p class="footer-description">
                        Painel administrativo da plataforma líder de empregos em Moçambique.
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
                <div class="footer-column">
                    <h4>Administração</h4>
                    <a href="index.php" class="footer-link">Dashboard</a>
                    <a href="vagas.php" class="footer-link">Gestão de Vagas</a>
                    <a href="usuarios.php" class="footer-link">Gestão de Usuários</a>
                    <a href="candidaturas.php" class="footer-link">Candidaturas</a>
                </div>
                <div class="footer-column">
                    <h4>Plataforma</h4>
                    <a href="../index.php" class="footer-link">Site Principal</a>
                    <a href="../vagas.php" class="footer-link">Vagas</a>
                    <a href="../sobre.php" class="footer-link">Sobre Nós</a>
                    <a href="../contacto.php" class="footer-link">Contacto</a>
                </div>
                <div class="footer-column">
                    <h4>Suporte</h4>
                    <a href="#" class="footer-link">Documentação</a>
                    <a href="#" class="footer-link">Ajuda</a>
                    <a href="#" class="footer-link">Segurança</a>
                    <a href="../auth/logout.php" class="footer-link">Sair</a>
                </div>
            </div>

            <div class="footer-bottom">
                <div>&copy; <?php echo date('Y'); ?> Emprego MZ. Todos os direitos reservados. | Painel Administrativo</div>
            </div>
        </div>
    </footer>

    <!-- ==========================================
         ✨ SCRIPTS
    ========================================== -->
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Auto-scroll para a seção de filtros
        const urlParams = new URLSearchParams(window.location.search);
        const hasFilters = urlParams.has('estado') || 
                          urlParams.has('vaga') || 
                          urlParams.has('candidato') || 
                          urlParams.has('empresa') ||
                          urlParams.has('data_inicio') ||
                          urlParams.has('data_fim') ||
                          urlParams.has('pagina');
        
        if (hasFilters) {
            setTimeout(() => {
                const filtrosSection = document.getElementById('filtros-candidaturas');
                if (filtrosSection) {
                    filtrosSection.scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }, 100);
        }

        // Auto-hide notifications
        setTimeout(() => {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            });
        }, 5000);
    </script>

    <!-- 🎭 Sistema de Modal de Confirmação -->
    <link rel="stylesheet" href="../assets/css/modal-confirm.css">
    <script src="../assets/js/modal-confirm.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                // Formulários de excluir candidatura
                document.querySelectorAll('.form-excluir-candidatura').forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const candidato = this.getAttribute('data-candidato');
                        const vaga = this.getAttribute('data-vaga');
                        const formElement = this;
                        
                        if (typeof window.confirmModal === 'function') {
                            window.confirmModal({
                                type: 'danger',
                                title: 'Excluir Candidatura',
                                subtitle: 'Esta ação não pode ser desfeita',
                                message: 'Tem certeza que deseja excluir esta candidatura permanentemente?',
                                highlightTitle: 'Candidato: ' + candidato,
                                highlightText: 'Vaga: ' + vaga,
                                confirmText: 'Sim, Excluir Permanentemente',
                                confirmIcon: 'trash-2',
                                onConfirm: function() { 
                                    formElement.submit(); 
                                }
                            });
                        } else {
                            if (confirm('Excluir candidatura de ' + candidato + '?')) {
                                formElement.submit();
                            }
                        }
                    });
                });
            }, 300);
        });
    </script>
    
    <!-- 🔒 Proteção de Sessão - Encerra ao fechar aba -->
    <script src="../assets/js/session-guard.js"></script>

</body>
</html>

