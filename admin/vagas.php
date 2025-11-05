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
        switch ($acao) {
            case 'ativar_vaga':
                $vaga_id = (int)$_POST['vaga_id'];
                
                // Buscar dados da vaga para o log
                $stmt_info = $pdo->prepare("SELECT v.titulo, e.nome_empresa FROM vaga v JOIN empresa e ON v.empresa_id = e.id WHERE v.id = ?");
                $stmt_info->execute([$vaga_id]);
                $vaga_info = $stmt_info->fetch();
                
                $stmt = $pdo->prepare("UPDATE vaga SET ativa = TRUE WHERE id = ?");
                $stmt->execute([$vaga_id]);
                
                // Registrar log de auditoria
                logAdminAction($pdo, $admin_id, 'ACTIVATE', 'vaga', $vaga_id, 
                    "Ativou vaga: {$vaga_info['titulo']} - {$vaga_info['nome_empresa']}");
                
                $sucesso = "Vaga ativada com sucesso!";
                break;
                
            case 'desativar_vaga':
                $vaga_id = (int)$_POST['vaga_id'];
                
                // Buscar dados da vaga para o log
                $stmt_info = $pdo->prepare("SELECT v.titulo, e.nome_empresa FROM vaga v JOIN empresa e ON v.empresa_id = e.id WHERE v.id = ?");
                $stmt_info->execute([$vaga_id]);
                $vaga_info = $stmt_info->fetch();
                
                $stmt = $pdo->prepare("UPDATE vaga SET ativa = FALSE WHERE id = ?");
                $stmt->execute([$vaga_id]);
                
                // Registrar log de auditoria
                logAdminAction($pdo, $admin_id, 'DEACTIVATE', 'vaga', $vaga_id, 
                    "Desativou vaga: {$vaga_info['titulo']} - {$vaga_info['nome_empresa']}");
                
                $sucesso = "Vaga desativada com sucesso!";
                break;
                
            case 'excluir_vaga':
                $vaga_id = (int)$_POST['vaga_id'];
                
                // Buscar dados da vaga para o log
                $stmt_info = $pdo->prepare("SELECT v.titulo, e.nome_empresa FROM vaga v JOIN empresa e ON v.empresa_id = e.id WHERE v.id = ?");
                $stmt_info->execute([$vaga_id]);
                $vaga_info = $stmt_info->fetch();
                
                // Verificar se tem candidaturas
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM candidatura WHERE vaga_id = ?");
                $stmt_check->execute([$vaga_id]);
                $tem_candidaturas = $stmt_check->fetchColumn() > 0;
                
                if ($tem_candidaturas) {
                    $erro = "Não é possível excluir vaga com candidaturas.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM vaga WHERE id = ?");
                    $stmt->execute([$vaga_id]);
                    
                    // Registrar log de auditoria
                    logAdminAction($pdo, $admin_id, 'DELETE', 'vaga', $vaga_id, 
                        "Excluiu vaga: {$vaga_info['titulo']} - {$vaga_info['nome_empresa']}");
                    
                    $sucesso = "Vaga excluída com sucesso!";
                }
                break;
                
            case 'bulk_action':
                $vagas_ids = $_POST['vagas_selecionadas'] ?? [];
                $bulk_acao = $_POST['bulk_acao'];
                
                if (empty($vagas_ids)) {
                    $erro = "Nenhuma vaga selecionada.";
                } else {
                    $placeholders = str_repeat('?,', count($vagas_ids) - 1) . '?';
                    
                    switch ($bulk_acao) {
                        case 'ativar':
                            $stmt = $pdo->prepare("UPDATE vaga SET ativa = TRUE WHERE id IN ($placeholders)");
                            $stmt->execute($vagas_ids);
                            $sucesso = count($vagas_ids) . " vagas ativadas com sucesso!";
                            break;
                        case 'desativar':
                            $stmt = $pdo->prepare("UPDATE vaga SET ativa = FALSE WHERE id IN ($placeholders)");
                            $stmt->execute($vagas_ids);
                            $sucesso = count($vagas_ids) . " vagas desativadas com sucesso!";
                            break;
                    }
                }
                break;
        }
    } catch (PDOException $e) {
        $erro = "❌ Erro no sistema. Por favor, tente novamente.";
        error_log("Erro PDO em admin/vagas.php (Admin: $admin_id): " . $e->getMessage());
    } catch (Exception $e) {
        $erro = "❌ Erro inesperado. Por favor, tente novamente.";
        error_log("Erro inesperado em admin/vagas.php: " . $e->getMessage());
    }
}

// ========================================
// FILTROS E PAGINAÇÃO
// ========================================
$filtro_status = $_GET['status'] ?? '';
$filtro_area = $_GET['area'] ?? '';
$filtro_empresa = $_GET['empresa'] ?? '';
$filtro_busca = $_GET['busca'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';

$vagas_por_pagina = 20;
$pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_atual - 1) * $vagas_por_pagina;

// ========================================
// ESTATÍSTICAS PARA CARDS
// ========================================
$stmt = $pdo->query("SELECT COUNT(*) FROM vaga");
$total_vagas = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM vaga WHERE ativa = TRUE AND data_expiracao >= CURDATE()");
$vagas_ativas = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM vaga WHERE ativa = FALSE OR data_expiracao < CURDATE()");
$vagas_inativas = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM vaga WHERE DATE(data_publicacao) = CURDATE()");
$vagas_hoje = $stmt->fetchColumn();

// ========================================
// QUERY PRINCIPAL COM FILTROS
// ========================================
$sql = "SELECT v.*, e.nome_empresa, e.logotipo,
               (SELECT COUNT(*) FROM candidatura c WHERE c.vaga_id = v.id) as total_candidaturas
        FROM vaga v 
        JOIN empresa e ON v.empresa_id = e.id";

$where_conditions = [];
$params = [];

// Aplicar filtros
if (!empty($filtro_status)) {
    switch ($filtro_status) {
        case 'ativas':
            $where_conditions[] = "v.ativa = TRUE AND v.data_expiracao >= CURDATE()";
            break;
        case 'inativas':
            $where_conditions[] = "v.ativa = FALSE";
            break;
        case 'expiradas':
            $where_conditions[] = "v.data_expiracao < CURDATE()";
            break;
    }
}

if (!empty($filtro_area)) {
    $where_conditions[] = "v.area = ?";
    $params[] = $filtro_area;
}

if (!empty($filtro_empresa)) {
    $where_conditions[] = "e.nome_empresa LIKE ?";
    $params[] = "%$filtro_empresa%";
}

if (!empty($filtro_busca)) {
    $where_conditions[] = "(v.titulo LIKE ? OR e.nome_empresa LIKE ?)";
    $params[] = "%$filtro_busca%";
    $params[] = "%$filtro_busca%";
}

if (!empty($filtro_data_inicio)) {
    $where_conditions[] = "v.data_publicacao >= ?";
    $params[] = $filtro_data_inicio;
}

if (!empty($filtro_data_fim)) {
    $where_conditions[] = "v.data_publicacao <= ?";
    $params[] = $filtro_data_fim;
}

// Construir WHERE clause
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// Contar total para paginação
$sql_count = str_replace("SELECT v.*, e.nome_empresa, e.logotipo,
               (SELECT COUNT(*) FROM candidatura c WHERE c.vaga_id = v.id) as total_candidaturas", "SELECT COUNT(*)", $sql);
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $vagas_por_pagina);

// Adicionar ORDER BY e LIMIT
$sql .= " ORDER BY v.data_publicacao DESC LIMIT ? OFFSET ?";
$params[] = $vagas_por_pagina;
$params[] = $offset;

// Executar query principal
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vagas = $stmt->fetchAll();

// ========================================
// BUSCAR ÁREAS PARA FILTRO
// ========================================
$stmt = $pdo->query("SELECT DISTINCT area FROM vaga WHERE area IS NOT NULL ORDER BY area");
$areas_disponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ========================================
// FUNÇÕES AUXILIARES
// ========================================
function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

function getStatusVaga($ativa, $data_expiracao) {
    if (!$ativa) {
        return ['label' => 'Inativa', 'class' => 'status-inativo'];
    }
    
    if (strtotime($data_expiracao) < time()) {
        return ['label' => 'Expirada', 'class' => 'status-expirado'];
    }
    
    return ['label' => 'Ativa', 'class' => 'status-ativo'];
}

function tempoExpiracao($data_expiracao) {
    $dias = (strtotime($data_expiracao) - time()) / (60 * 60 * 24);
    
    if ($dias < 0) {
        return 'Expirou';
    } elseif ($dias < 7) {
        return 'Expira em ' . ceil($dias) . ' dias';
    } else {
        return formatarData($data_expiracao);
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Vagas - Administração</title>
    
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
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        .stat-icon.ativas { background: var(--success); }
        .stat-icon.inativas { background: var(--error); }
        .stat-icon.hoje { background: var(--warning); }

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
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        .bulk-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .bulk-select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 14px;
        }

        .table-container {
            overflow-x: auto;
        }

        .jobs-table {
            width: 100%;
            border-collapse: collapse;
        }

        .jobs-table th {
            background: var(--background);
            padding: 16px 12px;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .jobs-table td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .jobs-table tbody tr:hover {
            background: var(--background);
        }

        .job-title {
            font-weight: 600;
            color: var(--text);
        }

        .empresa-info {
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

        .area-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            background: var(--primary);
            color: white;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }

        .status-ativo {
            background: #DCFCE7;
            color: #166534;
        }

        .status-inativo {
            background: #FEE2E2;
            color: #991B1B;
        }

        .status-expirado {
            background: #FEF3C7;
            color: #92400E;
        }

        .table-actions {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 4px;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--error);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        /* ========================================
           📄 PAGINAÇÃO
        ======================================== */
        .pagination-section {
            padding: 20px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: between;
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
           📱 RESPONSIVE - Mobile-First Organization
        ========================================== */
        
        /* Tablet Landscape */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Tablet */
        @media (max-width: 1024px) {
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-8);
            }
        }
        
        /* Mobile */
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
            
            .table-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
            
            .bulk-actions {
                width: 100%;
                justify-content: space-between;
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
                    <a href="vagas.php" class="nav-item active">
                        <i data-lucide="briefcase"></i>
                        Gestão de Vagas
                    </a>
                    <a href="usuarios.php" class="nav-item">
                        <i data-lucide="users"></i>
                        Usuários
                    </a>
                    <a href="candidaturas.php" class="nav-item">
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
                    <span>Gestão de Vagas</span>
                </div>
                </div>
        </div>

    <!-- ==========================================
         📊 MAIN CONTENT
    ========================================== -->
    <main class="main-content">
        <!-- Cabeçalho da Página -->
        <div class="page-header">
            <h1 class="page-title">Gestão de Vagas</h1>
            <p class="page-description">Controle todas as vagas publicadas na plataforma</p>
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
                        <i data-lucide="briefcase" style="width: 24px; height: 24px;"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($total_vagas); ?></div>
                <div class="stat-label">Total de Vagas</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon ativas">
                        <i data-lucide="check-circle" style="width: 24px; height: 24px;"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($vagas_ativas); ?></div>
                <div class="stat-label">Vagas Ativas</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon inativas">
                        <i data-lucide="x-circle" style="width: 24px; height: 24px;"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($vagas_inativas); ?></div>
                <div class="stat-label">Vagas Inativas</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon hoje">
                        <i data-lucide="calendar" style="width: 24px; height: 24px;"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($vagas_hoje); ?></div>
                <div class="stat-label">Publicadas Hoje</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section" id="filtros-vagas">
            <div class="filters-header">
                <h2 class="filters-title">Filtros de Busca</h2>
            </div>
            
            <form method="GET" action="vagas.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="">Todos os status</option>
                            <option value="ativas" <?php echo $filtro_status === 'ativas' ? 'selected' : ''; ?>>Ativas</option>
                            <option value="inativas" <?php echo $filtro_status === 'inativas' ? 'selected' : ''; ?>>Inativas</option>
                            <option value="expiradas" <?php echo $filtro_status === 'expiradas' ? 'selected' : ''; ?>>Expiradas</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Área</label>
                        <select name="area" class="filter-select">
                            <option value="">Todas as áreas</option>
                            <?php foreach ($areas_disponiveis as $area): ?>
                                <option value="<?php echo htmlspecialchars($area); ?>" 
                                        <?php echo $filtro_area === $area ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($area); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Empresa</label>
                        <input type="text" name="empresa" class="filter-input" 
                               placeholder="Nome da empresa"
                               value="<?php echo htmlspecialchars($filtro_empresa); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Buscar</label>
                        <input type="text" name="busca" class="filter-input" 
                               placeholder="Título da vaga"
                               value="<?php echo htmlspecialchars($filtro_busca); ?>">
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
                    <a href="vagas.php" class="btn btn-secondary">
                        <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                        Limpar Filtros
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabela de Vagas -->
        <div class="table-section">
            <div class="table-header">
                <h2 class="table-title">
                    Vagas Encontradas (<?php echo number_format($total_registros); ?>)
                </h2>
                
                <form method="POST" class="bulk-actions" id="bulkForm">
                    <input type="hidden" name="acao" value="bulk_action">
                    <select name="bulk_acao" class="bulk-select">
                        <option value="">Ações em lote</option>
                        <option value="ativar">Ativar selecionadas</option>
                        <option value="desativar">Desativar selecionadas</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary" onclick="return confirmarBulkAction()">
                        Executar
                    </button>
                </form>
            </div>

            <div class="table-container">
                <table class="jobs-table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes()">
                            </th>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Empresa</th>
                            <th>Área</th>
                            <th>Localização</th>
                            <th>Candidaturas</th>
                            <th>Publicação</th>
                            <th>Expiração</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vagas)): ?>
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 40px; color: var(--cor-texto-claro);">
                                    <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 16px;"></i>
                                    <br>Nenhuma vaga encontrada com os filtros aplicados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vagas as $vaga): ?>
                                <?php $status = getStatusVaga($vaga['ativa'], $vaga['data_expiracao']); ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="vagas_selecionadas[]" 
                                               value="<?php echo $vaga['id']; ?>" 
                                               form="bulkForm" class="vaga-checkbox">
                                    </td>
                                    <td><?php echo $vaga['id']; ?></td>
                                    <td>
                                        <span class="job-title" style="cursor: default; text-decoration: none;">
                                            <?php echo htmlspecialchars($vaga['titulo']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="empresa-info">
                                            <img src="<?php echo !empty($vaga['logotipo']) && file_exists('../uploads/' . $vaga['logotipo']) 
                                                                ? '../uploads/' . htmlspecialchars($vaga['logotipo']) 
                                                                : '../assets/images/empresa-default.png'; ?>" 
                                                 class="empresa-logo-mini" alt="Logo">
                                            <span><?php echo htmlspecialchars($vaga['nome_empresa']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="area-badge">
                                            <?php echo htmlspecialchars($vaga['area']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($vaga['localizacao']); ?></td>
                                    <td>
                                        <span style="color: var(--text); font-weight: 500;">
                                            <?php echo $vaga['total_candidaturas']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatarData($vaga['data_publicacao']); ?></td>
                                    <td>
                                        <span style="<?php echo strtotime($vaga['data_expiracao']) - time() < 7*24*60*60 && strtotime($vaga['data_expiracao']) > time() ? 'color: var(--warning); font-weight: 600;' : ''; ?>">
                                            <?php echo tempoExpiracao($vaga['data_expiracao']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status['class']; ?>">
                                            <?php echo $status['label']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <?php if ($vaga['ativa'] && strtotime($vaga['data_expiracao']) >= time()): ?>
                                                <form method="POST" style="display: inline;" 
                                                      class="form-desativar-vaga">
                                                    <input type="hidden" name="acao" value="desativar_vaga">
                                                    <input type="hidden" name="vaga_id" value="<?php echo $vaga['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning" title="Desativar">
                                                        <i data-lucide="pause" style="width: 14px; height: 14px;"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;" 
                                                      class="form-ativar-vaga">
                                                    <input type="hidden" name="acao" value="ativar_vaga">
                                                    <input type="hidden" name="vaga_id" value="<?php echo $vaga['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Ativar">
                                                        <i data-lucide="play" style="width: 14px; height: 14px;"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" style="display: inline;" 
                                                  class="form-excluir-vaga"
                                                  data-vaga="<?php echo htmlspecialchars($vaga['titulo']); ?>">
                                                <input type="hidden" name="acao" value="excluir_vaga">
                                                <input type="hidden" name="vaga_id" value="<?php echo $vaga['id']; ?>">
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
                        Exibindo <?php echo min($offset + 1, $total_registros); ?>-<?php echo min($offset + $vagas_por_pagina, $total_registros); ?> 
                        de <?php echo number_format($total_registros); ?> vagas
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

        // Reinitialize icons after page load
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
        });

        // Auto-scroll para a seção de filtros quando aplicar filtros ou mudar página
        const urlParams = new URLSearchParams(window.location.search);
        const hasFilters = urlParams.has('status') || 
                          urlParams.has('area') || 
                          urlParams.has('empresa') || 
                          urlParams.has('busca') || 
                          urlParams.has('data_inicio') ||
                          urlParams.has('data_fim') ||
                          urlParams.has('pagina');
        
        if (hasFilters) {
            setTimeout(() => {
                const filtrosSection = document.getElementById('filtros-vagas');
                if (filtrosSection) {
                    filtrosSection.scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }, 100);
        }

        // Toggle all checkboxes
        function toggleAllCheckboxes() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.vaga-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        // Confirmar bulk actions
        function confirmarBulkAction() {
            const checkboxes = document.querySelectorAll('.vaga-checkbox:checked');
            const action = document.querySelector('select[name="bulk_acao"]').value;
            
            if (checkboxes.length === 0) {
                alert('Selecione pelo menos uma vaga.');
                return false;
            }
            
            if (!action) {
                alert('Selecione uma ação.');
                return false;
            }
            
            const actionText = action === 'ativar' ? 'ativar' : 'desativar';
            const actionTextCapitalized = actionText.charAt(0).toUpperCase() + actionText.slice(1);
            
            confirmModal({
                type: 'bulk',
                title: 'Ação em Massa',
                subtitle: `${checkboxes.length} vaga(s) selecionada(s)`,
                message: `Tem certeza que deseja ${actionText} as ${checkboxes.length} vagas selecionadas?`,
                confirmText: `Sim, ${actionTextCapitalized}`,
                confirmIcon: 'layers',
                onConfirm: () => {
                    document.getElementById('bulk-form').submit();
                }
            });
            return false;
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
                // Formulários de desativar vaga
                document.querySelectorAll('.form-desativar-vaga').forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formElement = this;
                        
                        if (typeof window.confirmModal === 'function') {
                            window.confirmModal({
                                type: 'warning',
                                title: 'Desativar Vaga',
                                message: 'A vaga não aparecerá mais nas buscas públicas. Você pode reativá-la a qualquer momento.',
                                confirmText: 'Sim, Desativar',
                                confirmIcon: 'power-off',
                                onConfirm: function() { 
                                    formElement.submit(); 
                                }
                            });
                        } else {
                            if (confirm('Desativar esta vaga?')) {
                                formElement.submit();
                            }
                        }
                    });
                });

                // Formulários de ativar vaga
                document.querySelectorAll('.form-ativar-vaga').forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formElement = this;
                        
                        if (typeof window.confirmModal === 'function') {
                            window.confirmModal({
                                type: 'warning',
                                title: 'Ativar Vaga',
                                message: 'A vaga voltará a aparecer nas buscas públicas.',
                                confirmText: 'Sim, Ativar',
                                confirmIcon: 'check-circle',
                                onConfirm: function() { 
                                    formElement.submit(); 
                                }
                            });
                        } else {
                            if (confirm('Ativar esta vaga?')) {
                                formElement.submit();
                            }
                        }
                    });
                });

                // Formulários de excluir vaga
                document.querySelectorAll('.form-excluir-vaga').forEach(function(form) {
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
    
    <!-- 🔒 Proteção de Sessão - Encerra ao fechar aba -->
    <script src="../assets/js/session-guard.js"></script>

</body>
</html>