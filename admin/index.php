<?php
session_start();
require_once '../config/db.php';
require_once '../config/admin_functions.php';

// Verificar se o admin está logado
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/admin_login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// ==========================================
// 📊 ESTATÍSTICAS PRINCIPAIS
// ==========================================

try {
    // Total de Usuários (Candidatos)
    $stmt = $pdo->query("SELECT COUNT(*) FROM candidato");
    $total_candidatos = $stmt->fetchColumn();
} catch (PDOException $e) {
    $total_candidatos = 0;
    error_log("Erro ao buscar total de candidatos em admin/index.php: " . $e->getMessage());
}

// Total de Empresas
$stmt = $pdo->query("SELECT COUNT(*) FROM empresa");
$total_empresas = $stmt->fetchColumn();

// Total de Vagas
$stmt = $pdo->query("SELECT COUNT(*) FROM vaga");
$total_vagas = $stmt->fetchColumn();

// Vagas Ativas
$stmt = $pdo->query("SELECT COUNT(*) FROM vaga WHERE ativa = TRUE AND data_expiracao >= CURDATE()");
$vagas_ativas = $stmt->fetchColumn();

// Total de Candidaturas
$stmt = $pdo->query("SELECT COUNT(*) FROM candidatura");
$total_candidaturas = $stmt->fetchColumn();

// Candidaturas este mês
$stmt = $pdo->query("SELECT COUNT(*) FROM candidatura WHERE MONTH(data_candidatura) = MONTH(CURDATE()) AND YEAR(data_candidatura) = YEAR(CURDATE())");
$candidaturas_mes = $stmt->fetchColumn();

// Novos usuários este mês (candidatos)
$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM candidato c
    JOIN utilizador u ON c.id = u.id
    WHERE MONTH(u.data_registo) = MONTH(CURDATE()) 
    AND YEAR(u.data_registo) = YEAR(CURDATE())
");
$novos_candidatos_mes = $stmt->fetchColumn();

// Novas empresas este mês
$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM empresa e
    JOIN utilizador u ON e.id = u.id
    WHERE MONTH(u.data_registo) = MONTH(CURDATE()) 
    AND YEAR(u.data_registo) = YEAR(CURDATE())
");
$novas_empresas_mes = $stmt->fetchColumn();

// ==========================================
// 📈 CRESCIMENTO MENSAL
// ==========================================

// Candidaturas mês anterior
$stmt = $pdo->query("SELECT COUNT(*) FROM candidatura WHERE MONTH(data_candidatura) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(data_candidatura) = YEAR(CURDATE() - INTERVAL 1 MONTH)");
$candidaturas_mes_anterior = $stmt->fetchColumn();

// Calcular crescimento percentual
$crescimento_candidaturas = 0;
if ($candidaturas_mes_anterior > 0) {
    $crescimento_candidaturas = (($candidaturas_mes - $candidaturas_mes_anterior) / $candidaturas_mes_anterior) * 100;
}

// ==========================================
// 📊 VAGAS POR ÁREA
// ==========================================

$stmt = $pdo->query("
    SELECT area, COUNT(*) as total 
    FROM vaga 
    WHERE ativa = TRUE AND data_expiracao >= CURDATE()
    GROUP BY area 
    ORDER BY total DESC 
    LIMIT 5
");
$vagas_por_area = $stmt->fetchAll();

// ==========================================
// 🕒 ATIVIDADE RECENTE
// ==========================================

// Candidaturas recentes
$stmt = $pdo->query("
    SELECT c.*, ca.nome_completo as candidato_nome, v.titulo as vaga_titulo, e.nome_empresa
    FROM candidatura c
    JOIN candidato ca ON c.candidato_id = ca.id
    JOIN vaga v ON c.vaga_id = v.id
    JOIN empresa e ON v.empresa_id = e.id
    ORDER BY c.data_candidatura DESC
    LIMIT 10
");
$candidaturas_recentes = $stmt->fetchAll();

// Empresas recentes
$stmt = $pdo->query("
    SELECT e.nome_empresa, u.email, u.data_registo, e.logotipo
    FROM empresa e
    JOIN utilizador u ON e.id = u.id
    ORDER BY u.data_registo DESC
    LIMIT 5
");
$empresas_recentes = $stmt->fetchAll();

// Candidatos recentes
$stmt = $pdo->query("
    SELECT c.nome_completo, u.email, u.data_registo, c.foto_perfil as foto
    FROM candidato c
    JOIN utilizador u ON c.id = u.id
    ORDER BY u.data_registo DESC
    LIMIT 5
");
$candidatos_recentes = $stmt->fetchAll();

// Vagas recentes para gestão administrativa
$stmt = $pdo->query("
    SELECT v.*, e.nome_empresa, e.logotipo,
           (SELECT COUNT(*) FROM candidatura WHERE vaga_id = v.id) as total_candidaturas
    FROM vaga v
    JOIN empresa e ON v.empresa_id = e.id
    ORDER BY v.data_publicacao DESC
    LIMIT 8
");
$vagas_recentes = $stmt->fetchAll();

// ==========================================
// 📊 MÉTRICAS ADICIONAIS
// ==========================================

// Taxa de conversão (candidaturas/vagas)
$taxa_conversao = $vagas_ativas > 0 ? round(($total_candidaturas / $total_vagas) * 100, 1) : 0;

// Média de candidaturas por vaga
$media_candidaturas = $total_vagas > 0 ? round($total_candidaturas / $total_vagas, 1) : 0;

// Total de usuários
$total_usuarios = $total_candidatos + $total_empresas;

// Função auxiliar para formatar datas
function formatarDataHora($data) {
    $dt = new DateTime($data);
    $agora = new DateTime();
    $diff = $agora->diff($dt);
    
    if ($diff->days == 0) {
        if ($diff->h == 0) {
            return $diff->i == 0 ? 'Agora' : $diff->i . ' min atrás';
        }
        return $diff->h . 'h atrás';
    } elseif ($diff->days == 1) {
        return 'Ontem';
    } elseif ($diff->days < 7) {
        return $diff->days . ' dias atrás';
    } else {
        return $dt->format('d/m/Y H:i');
    }
}

// Função para traduzir estado da candidatura
function traduzirEstado($estado) {
    $traducoes = [
        'submetida' => 'Submetida',
        'em_analise' => 'Em Análise',
        'entrevista' => 'Entrevista',
        'aprovada' => 'Aprovada',
        'rejeitada' => 'Rejeitada'
    ];
    return $traducoes[$estado] ?? $estado;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo | Emprego MZ</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        /* ==========================================
           🎨 CSS VARIABLES - EXECUTIVE ADMIN THEME
        ========================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            /* 🎨 CORES PRINCIPAIS ADMIN - Consistentes com o Site Público */
            --cor-primaria-admin: #14213d;        /* Oxford Blue - IGUAL ao site público */
            --cor-primaria-admin-escura: #0f1a2e; /* Oxford Blue Dark - hover */
            --cor-primaria-admin-clara: #1e2c47;  /* Oxford Blue Light - Elementos secundários */
            --cor-primaria-alpha: rgba(20, 33, 61, 0.08);
            
            --cor-secundaria-admin: #fca311;      /* Orange Web - IGUAL ao site público */
            --cor-secundaria-admin-hover: #e3940f; /* Orange Hover */
            --cor-secundaria-alpha: rgba(252, 163, 17, 0.08);
            
            /* Aliases ADMIN */
            --primary: var(--cor-primaria-admin);
            --primary-dark: var(--cor-primaria-admin-escura);
            --primary-light: var(--cor-primaria-alpha);
            --secondary: var(--cor-secundaria-admin);
            --text: #0f172a;                      /* Texto principal super escuro */
            --text-medium: #475569;               /* Texto secundário */
            --text-light: #64748B;
            --border: #cbd5e1;                    /* Border admin */
            --border-light: #e2e8f0;
            --background: #f1f5f9;                /* Lighter Gray - Fundo admin */
            --cor-fundo-admin: #f1f5f9;
            --cor-cards-admin: #ffffff;
            --cor-acento-admin: #e2e8f0;
            --white: #FFFFFF;
            --success: #059669;                   /* Verde mais escuro admin */
            --success-light: rgba(5, 150, 105, 0.08);
            --warning: var(--cor-secundaria-admin);
            --warning-light: var(--cor-secundaria-alpha);
            --error: #dc2626;                     /* Vermelho admin */
            --error-light: rgba(220, 38, 38, 0.08);
            --info: var(--cor-primaria-admin);
            --info-light: var(--cor-primaria-alpha);
            
            /* Admin Executive Colors - Dark Navy */
            --admin-primary: var(--cor-primaria-admin);
            --admin-primary-light: var(--cor-primaria-alpha);
            --admin-dark: var(--cor-primaria-admin-clara);
            --admin-darker: var(--cor-primaria-admin-escura);
            
            /* === GRADIENTES ADMIN === */
            --gradiente-admin-primario: linear-gradient(135deg, var(--cor-primaria-admin), var(--cor-primaria-admin-clara));
            --gradiente-admin-secundario: linear-gradient(135deg, var(--cor-secundaria-admin), var(--cor-secundaria-admin-hover));
            --gradiente-admin-fundo: linear-gradient(135deg, rgba(20, 33, 61, 0.02), rgba(20, 33, 61, 0.01));
            
            /* === SIDEBAR E NAVEGAÇÃO === */
            --cor-sidebar-admin: var(--cor-primaria-admin);
            --cor-sidebar-hover: rgba(252, 163, 17, 0.1);
            --cor-sidebar-ativo: var(--cor-secundaria-admin);
            --cor-topbar-admin: var(--cor-cards-admin);
            
            /* === MÉTRICAS E KPIS === */
            --cor-kpi-vagas: #059669;
            --cor-kpi-vagas-light: #ecfdf5;
            --cor-kpi-empresas: #7c3aed;
            --cor-kpi-empresas-light: #f3f4f6;
            --cor-kpi-candidatos: #0369a1;
            --cor-kpi-candidatos-light: #eff6ff;
            --cor-kpi-candidaturas: #d97706;
            --cor-kpi-candidaturas-light: #fffbeb;
            
            /* === STATUS ADMINISTRATIVO === */
            --cor-admin-critico: #dc2626;
            --cor-admin-critico-light: #fef2f2;
            --cor-admin-sucesso: #059669;
            --cor-admin-sucesso-light: #ecfdf5;
            --cor-admin-aviso: #d97706;
            --cor-admin-aviso-light: #fffbeb;
            --cor-admin-info: #0369a1;
            --cor-admin-info-light: #eff6ff;
            
            /* === BORDAS E SOMBRAS ADMIN === */
            --cor-borda-admin: var(--cor-acento-admin-escuro);
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
            background: var(--cor-fundo-admin);
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
        
        /* Typography Hierarchy */
        h1, h2, h3, h4, h5, h6 {
            font-weight: var(--font-weight-bold);
            line-height: var(--line-height-tight);
            color: var(--text);
            margin: 0;
        }
        
        h1 { font-size: var(--font-size-3xl); letter-spacing: -0.02em; }
        h2 { font-size: var(--font-size-2xl); letter-spacing: -0.01em; }
        h3 { font-size: var(--font-size-xl); }
        h4 { font-size: var(--font-size-lg); }
        h5 { font-size: var(--font-size-md); }
        h6 { font-size: var(--font-size-base); }
        
        p {
            margin: 0;
            line-height: var(--line-height-relaxed);
        }
        
        strong {
            font-weight: var(--font-weight-semibold);
            color: var(--text);
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
            position: relative;
            z-index: 1;
        }
        
        .nav-item span {
            position: relative;
            z-index: 1;
        }
        
        .nav-item svg {
            width: 18px;
            height: 18px;
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
        
        /* ==========================================
           📊 MAIN CONTENT - Organized Layout
        ========================================== */
        .main-content {
            flex: 1;
            max-width: 1440px;
            margin: 0 auto;
            padding: var(--space-10) var(--space-8) var(--space-12);
            width: 100%;
        }
        
        /* Page Header - Clear Hierarchy */
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
        
        .page-subtitle {
            font-size: var(--font-size-md);
            color: var(--text-medium);
            font-weight: var(--font-weight-normal);
            line-height: var(--line-height-relaxed);
        }
        
        /* ==========================================
           📈 KPI CARDS - Organized Statistics
        ========================================== */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-6);
            margin-bottom: var(--space-12);
        }
        
        .kpi-card {
            background: var(--cor-cards-admin);
            border-radius: var(--radius-2xl);
            padding: var(--space-6);
            box-shadow: var(--sombra-admin-card);
            border: 1px solid var(--cor-borda-admin);
            transition: var(--transicao-admin-elevacao);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }
        
        .kpi-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--sombra-admin-elevada);
            border-color: var(--cor-acento-admin-escuro);
        }
        
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--cor-primaria-admin), var(--cor-secundaria-admin));
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
            transition: var(--transicao-admin-suave);
        }
        
        .kpi-card.purple::before,
        .kpi-card.vagas::before {
            background: linear-gradient(90deg, var(--cor-kpi-vagas), #047857);
        }
        
        .kpi-card.green::before,
        .kpi-card.empresas::before {
            background: linear-gradient(90deg, var(--cor-kpi-empresas), #6d28d9);
        }
        
        .kpi-card.orange::before,
        .kpi-card.candidaturas::before {
            background: linear-gradient(90deg, var(--cor-kpi-candidaturas), #c2410c);
        }
        
        .kpi-card.blue::before,
        .kpi-card.candidatos::before {
            background: linear-gradient(90deg, var(--cor-kpi-candidatos), #1e40af);
        }
        
        .kpi-card:hover::before {
            height: 6px;
        }
        
        .kpi-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: var(--space-5);
            gap: var(--space-3);
        }
        
        .kpi-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 24px;
            color: white;
            box-shadow: var(--sombra-admin-card);
            transition: var(--transicao-admin-elevacao);
        }
        
        .kpi-card:hover .kpi-icon {
            transform: scale(1.05);
        }
        
        .kpi-card.purple .kpi-icon,
        .kpi-card.vagas .kpi-icon {
            background: linear-gradient(135deg, var(--cor-kpi-vagas), #047857);
        }
        
        .kpi-card.green .kpi-icon,
        .kpi-card.empresas .kpi-icon {
            background: linear-gradient(135deg, var(--cor-kpi-empresas), #6d28d9);
        }
        
        .kpi-card.orange .kpi-icon,
        .kpi-card.candidaturas .kpi-icon {
            background: linear-gradient(135deg, var(--cor-kpi-candidaturas), #c2410c);
        }
        
        .kpi-card.blue .kpi-icon,
        .kpi-card.candidatos .kpi-icon {
            background: linear-gradient(135deg, var(--cor-kpi-candidatos), #1e40af);
        }
        
        .kpi-icon.purple {
            background: var(--admin-primary-light);
            color: var(--admin-primary);
        }
        
        .kpi-icon.green {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .kpi-icon.orange {
            background: rgba(245, 158, 11, 0.1);
            color: var(--secondary);
        }
        
        .kpi-icon.blue {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }
        
        .kpi-icon svg {
            width: 24px;
            height: 24px;
        }
        
        .kpi-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 10px;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
            line-height: 1;
            white-space: nowrap;
        }
        
        .kpi-badge.success {
            background: var(--success-light);
            color: var(--success);
        }
        
        .kpi-badge.warning {
            background: var(--warning-light);
            color: var(--warning);
        }
        
        .kpi-badge svg {
            width: 12px;
            height: 12px;
        }
        
        .kpi-value {
            font-size: var(--font-size-4xl);
            font-weight: var(--font-weight-extrabold);
            background: var(--gradiente-admin-primario);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: var(--line-height-tight);
            margin-bottom: var(--space-2);
            letter-spacing: -0.02em;
            animation: countUp 1.5s ease-out;
        }
        
        @keyframes countUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .kpi-label {
            font-size: var(--font-size-sm);
            color: var(--text-medium);
            font-weight: var(--font-weight-medium);
            line-height: var(--line-height-normal);
        }
        
        .kpi-footer {
            margin-top: auto;
            padding-top: var(--space-5);
            border-top: 1px solid var(--border-light);
            font-size: var(--font-size-sm);
            color: var(--text-light);
            line-height: var(--line-height-relaxed);
        }
        
        .kpi-footer strong {
            color: var(--text);
            font-weight: var(--font-weight-semibold);
        }
        
        /* ==========================================
           📊 CONTENT GRID - Structured Sections
        ========================================== */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--space-6);
            margin-bottom: var(--space-10);
        }
        
        .content-card {
            background: var(--cor-cards-admin);
            border-radius: var(--radius-2xl);
            padding: var(--space-8);
            box-shadow: var(--sombra-admin-card);
            border: 1px solid var(--cor-borda-admin);
            transition: var(--transicao-admin-suave);
        }
        
        .content-card:hover {
            box-shadow: var(--sombra-admin-elevada);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--space-6);
            padding-bottom: var(--space-5);
            border-bottom: 2px solid var(--border-light);
            gap: var(--space-4);
        }
        
        .card-title {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-bold);
            color: var(--text);
            line-height: var(--line-height-tight);
        }
        
        .card-title svg {
            width: 20px;
            height: 20px;
            color: var(--primary);
        }
        
        .card-action {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: 8px var(--space-4);
            background: var(--cor-acento-admin);
            color: var(--cor-primaria-admin);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-semibold);
            text-decoration: none;
            transition: var(--transicao-admin-suave);
            border: 1px solid transparent;
            white-space: nowrap;
        }
        
        .card-action:hover {
            background: var(--cor-secundaria-admin);
            color: var(--white);
            border-color: var(--cor-secundaria-admin);
            transform: translateY(-2px);
            box-shadow: var(--sombra-admin-button);
        }
        
        .card-action svg {
            width: 14px;
            height: 14px;
        }
        
        /* ==========================================
           📋 ACTIVITY LIST - Organized Timeline
        ========================================== */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }
        
        .activity-item {
            display: flex;
            gap: var(--space-4);
            padding: var(--space-4);
            background: var(--background);
            border-radius: var(--radius-lg);
            transition: var(--transition-fast);
            border: 1px solid transparent;
        }
        
        .activity-item:hover {
            background: var(--border-light);
            border-color: var(--border);
            transform: translateX(2px);
        }
        
        .activity-avatar {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-full);
            background: var(--primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: var(--font-weight-semibold);
            font-size: var(--font-size-base);
            flex-shrink: 0;
            box-shadow: var(--shadow-xs);
        }
        
        .activity-content {
            flex: 1;
            min-width: 0;
        }
        
        .activity-title {
            font-size: var(--font-size-base);
            font-weight: var(--font-weight-semibold);
            color: var(--text);
            margin-bottom: 4px;
            line-height: var(--line-height-normal);
        }
        
        .activity-description {
            font-size: var(--font-size-sm);
            color: var(--text-medium);
            margin-bottom: var(--space-3);
            line-height: var(--line-height-relaxed);
        }
        
        .activity-meta {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-size: var(--font-size-xs);
            color: var(--text-light);
            font-weight: var(--font-weight-medium);
        }
        
        .activity-meta svg {
            width: 14px;
            height: 14px;
        }
        
        .activity-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            line-height: 1;
        }
        
        .activity-badge.submetida {
            background: var(--cor-admin-info-light);
            color: var(--cor-admin-info);
        }
        
        .activity-badge.em_analise {
            background: var(--cor-admin-aviso-light);
            color: var(--cor-admin-aviso);
        }
        
        .activity-badge.entrevista {
            background: rgba(20, 33, 61, 0.1);
            color: var(--cor-primaria-admin);
            font-weight: var(--font-weight-bold);
        }
        
        .activity-badge.aprovada {
            background: var(--cor-admin-sucesso-light);
            color: var(--cor-admin-sucesso);
        }
        
        .activity-badge.rejeitada {
            background: var(--cor-admin-critico-light);
            color: var(--cor-admin-critico);
        }
        
        /* ==========================================
           📊 STATS TABLE - Organized Data Display
        ========================================== */
        .stats-table {
            width: 100%;
        }
        
        .stats-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-4) 0;
            border-bottom: 1px solid var(--border-light);
            transition: var(--transition-fast);
            gap: var(--space-4);
        }
        
        .stats-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .stats-row:hover {
            background: var(--background);
            padding-left: var(--space-3);
            padding-right: var(--space-3);
            margin: 0 calc(var(--space-3) * -1);
            border-radius: var(--radius-lg);
        }
        
        .stats-label {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
            color: var(--text);
            line-height: var(--line-height-normal);
        }
        
        .stats-label svg {
            width: 18px;
            height: 18px;
            color: var(--primary);
            flex-shrink: 0;
        }
        
        .stats-value {
            font-size: var(--font-size-xl);
            font-weight: var(--font-weight-bold);
            color: var(--text);
            line-height: var(--line-height-tight);
        }
        
        .stats-bar {
            height: 6px;
            background: var(--border-light);
            border-radius: var(--radius-full);
            overflow: hidden;
            margin-top: var(--space-2);
        }
        
        .stats-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: var(--radius-full);
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* ==========================================
           👥 USER LIST - Organized User Cards
        ========================================== */
        .user-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }
        
        .user-item {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            padding: var(--space-4);
            background: var(--background);
            border-radius: var(--radius-lg);
            transition: var(--transition-fast);
            border: 1px solid transparent;
        }
        
        .user-item:hover {
            background: var(--border-light);
            border-color: var(--border);
            transform: translateX(2px);
        }
        
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            object-fit: cover;
            border: 2px solid var(--white);
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
        }
        
        .user-avatar-placeholder {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            background: var(--primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: var(--font-weight-semibold);
            font-size: var(--font-size-md);
            border: 2px solid var(--white);
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
        }
        
        .user-info {
            flex: 1;
            min-width: 0;
        }
        
        .user-name {
            font-size: var(--font-size-base);
            font-weight: var(--font-weight-semibold);
            color: var(--text);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: var(--line-height-normal);
        }
        
        .user-email {
            font-size: var(--font-size-sm);
            color: var(--text-light);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: var(--line-height-normal);
        }
        
        .user-time {
            font-size: var(--font-size-xs);
            color: var(--text-light);
            font-weight: var(--font-weight-medium);
            flex-shrink: 0;
            white-space: nowrap;
        }
        
        /* ==========================================
           💼 VAGAS TABLE - Organized Data Grid
        ========================================== */
        .vagas-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .vagas-table thead {
            background: var(--background);
        }
        
        .vagas-table th {
            padding: var(--space-4) var(--space-4);
            text-align: left;
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-bold);
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-bottom: 2px solid var(--border);
            background: var(--background);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .vagas-table th:first-child {
            border-top-left-radius: var(--radius-lg);
        }
        
        .vagas-table th:last-child {
            border-top-right-radius: var(--radius-lg);
        }
        
        .vagas-table td {
            padding: var(--space-4);
            font-size: var(--font-size-sm);
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }
        
        .vagas-table tbody tr {
            transition: var(--transition-fast);
        }
        
        .vagas-table tbody tr:hover {
            background: var(--background);
        }
        
        .vagas-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .vaga-info {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            min-width: 240px;
        }
        
        .vaga-logo {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-lg);
            object-fit: cover;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: var(--font-weight-semibold);
            font-size: var(--font-size-base);
            flex-shrink: 0;
            border: 2px solid var(--border-light);
            box-shadow: var(--shadow-xs);
        }
        
        .vaga-details {
            flex: 1;
            min-width: 0;
        }
        
        .vaga-title {
            font-weight: var(--font-weight-semibold);
            font-size: var(--font-size-base);
            color: var(--text);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: var(--line-height-normal);
        }
        
        .vaga-empresa {
            font-size: var(--font-size-sm);
            color: var(--text-light);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: var(--line-height-normal);
        }
        
        .vaga-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 10px;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            line-height: 1;
            white-space: nowrap;
        }
        
        .vaga-badge svg {
            width: 12px;
            height: 12px;
        }
        
        .vaga-badge.ativa {
            background: var(--success-light);
            color: var(--success);
        }
        
        .vaga-badge.inativa {
            background: var(--error-light);
            color: var(--error);
        }
        
        .vaga-count {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            font-weight: var(--font-weight-semibold);
            font-size: var(--font-size-base);
            color: var(--primary);
            padding: 4px 8px;
            background: var(--primary-light);
            border-radius: var(--radius);
        }
        
        .vaga-count svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }
        
        .btn-view-vaga {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: 8px var(--space-4);
            background: var(--primary);
            color: var(--white);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-semibold);
            text-decoration: none;
            transition: var(--transition-fast);
            white-space: nowrap;
            border: 1px solid var(--primary);
        }
        
        .btn-view-vaga:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .btn-view-vaga svg {
            width: 14px;
            height: 14px;
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
        
        .footer-column h3 {
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
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Tablet */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-8);
            }
            
            .main-content {
                padding: var(--space-8) var(--space-6) var(--space-10);
            }
        }
        
        /* Mobile */
        @media (max-width: 768px) {
            :root {
                --font-size-3xl: 28px;
                --font-size-2xl: 22px;
            }
            
            .header-container {
                padding: 0 var(--space-4);
                height: 64px;
                gap: var(--space-4);
            }
            
            .breadcrumb-section {
                padding: var(--space-3) var(--space-4);
            }
            
            .main-content {
                padding: var(--space-6) var(--space-4) var(--space-8);
            }
            
            .page-header {
                margin-bottom: var(--space-8);
            }
            
            .kpi-grid {
                grid-template-columns: 1fr;
                gap: var(--space-4);
                margin-bottom: var(--space-8);
            }
            
            .kpi-card {
                padding: var(--space-5);
            }
            
            .kpi-value {
                font-size: var(--font-size-3xl);
            }
            
            .content-card {
                padding: var(--space-5);
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-3);
            }
            
            .admin-user-info {
                display: none;
            }
            
            .header-nav {
                display: none;
            }
            
            .footer {
                padding: var(--space-8) var(--space-4) var(--space-6);
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
                gap: var(--space-6);
            }
            
            .vagas-table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .vagas-table tbody {
                display: block;
            }
            
            .vagas-table thead {
                display: block;
            }
            
            .vagas-table tr {
                display: block;
            }
            
            .vagas-table td,
            .vagas-table th {
                display: block;
                text-align: left;
            }
        }
        
        /* ==========================================
           ✨ ANIMATIONS - Smooth & Organized
        ========================================== */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .kpi-card {
            animation: fadeInScale 0.4s cubic-bezier(0.4, 0, 0.2, 1) backwards;
        }
        
        .kpi-card:nth-child(1) { animation-delay: 0.05s; }
        .kpi-card:nth-child(2) { animation-delay: 0.1s; }
        .kpi-card:nth-child(3) { animation-delay: 0.15s; }
        .kpi-card:nth-child(4) { animation-delay: 0.2s; }
        
        .content-card {
            animation: fadeIn 0.5s cubic-bezier(0.4, 0, 0.2, 1) backwards;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.9;
                transform: scale(1.02);
            }
        }
        
        .kpi-badge.success {
            animation: pulse 2.5s ease-in-out infinite;
        }
        
        /* Smooth scroll */
        html {
            scroll-behavior: smooth;
        }
        
        /* Loading state for stats bar */
        @keyframes progressGrow {
            from {
                width: 0;
            }
        }
        
        .stats-bar-fill {
            animation: progressGrow 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
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
                    <a href="index.php" class="nav-item active">
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
                <span>Dashboard</span>
            </div>
        </div>
    </div>

    <!-- ==========================================
         📊 MAIN CONTENT
    ========================================== -->
    <main class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Dashboard Executivo</h1>
            <p class="page-subtitle">Visão geral da plataforma e métricas principais</p>
        </div>

        <!-- ==========================================
             📈 KPI CARDS
        ========================================== -->
        <div class="kpi-grid">
            <!-- Total Usuários -->
            <div class="kpi-card purple">
                <div class="kpi-header">
                    <div class="kpi-icon purple">
                        <i data-lucide="users"></i>
                    </div>
                    <div class="kpi-badge success">
                        <i data-lucide="trending-up"></i>
                        +<?php echo $novos_candidatos_mes + $novas_empresas_mes; ?> este mês
                    </div>
                </div>
                <div class="kpi-value"><?php echo number_format($total_usuarios, 0, ',', '.'); ?></div>
                <div class="kpi-label">Total de Usuários</div>
                <div class="kpi-footer">
                    <strong><?php echo number_format($total_candidatos, 0, ',', '.'); ?></strong> candidatos • 
                    <strong><?php echo number_format($total_empresas, 0, ',', '.'); ?></strong> empresas
                </div>
            </div>

            <!-- Total Vagas -->
            <div class="kpi-card green">
                <div class="kpi-header">
                    <div class="kpi-icon green">
                        <i data-lucide="briefcase"></i>
                    </div>
                    <div class="kpi-badge success">
                        <i data-lucide="check-circle"></i>
                        <?php echo $vagas_ativas; ?> ativas
                    </div>
                </div>
                <div class="kpi-value"><?php echo number_format($total_vagas, 0, ',', '.'); ?></div>
                <div class="kpi-label">Total de Vagas</div>
                <div class="kpi-footer">
                    <strong><?php echo round(($vagas_ativas / max($total_vagas, 1)) * 100, 1); ?>%</strong> taxa de ativação
                </div>
            </div>

            <!-- Total Candidaturas -->
            <div class="kpi-card orange">
                <div class="kpi-header">
                    <div class="kpi-icon orange">
                        <i data-lucide="file-text"></i>
                    </div>
                    <?php if ($crescimento_candidaturas >= 0): ?>
                        <div class="kpi-badge success">
                            <i data-lucide="trending-up"></i>
                            +<?php echo round($crescimento_candidaturas, 1); ?>%
                        </div>
                    <?php else: ?>
                        <div class="kpi-badge warning">
                            <i data-lucide="trending-down"></i>
                            <?php echo round($crescimento_candidaturas, 1); ?>%
                        </div>
                    <?php endif; ?>
                </div>
                <div class="kpi-value"><?php echo number_format($total_candidaturas, 0, ',', '.'); ?></div>
                <div class="kpi-label">Total de Candidaturas</div>
                <div class="kpi-footer">
                    <strong><?php echo number_format($candidaturas_mes, 0, ',', '.'); ?></strong> este mês
                </div>
            </div>

            <!-- Taxa de Conversão -->
            <div class="kpi-card blue">
                <div class="kpi-header">
                    <div class="kpi-icon blue">
                        <i data-lucide="trending-up"></i>
                    </div>
                    <div class="kpi-badge success">
                        <i data-lucide="activity"></i>
                        Boa taxa
                    </div>
                </div>
                <div class="kpi-value"><?php echo $media_candidaturas; ?></div>
                <div class="kpi-label">Média Candidaturas/Vaga</div>
                <div class="kpi-footer">
                    Taxa de conversão: <strong><?php echo $taxa_conversao; ?>%</strong>
                </div>
            </div>
        </div>

        <!-- ==========================================
             📊 CONTENT GRID
        ========================================== -->
        <div class="content-grid">
            
            <!-- Candidaturas Recentes -->
            <div class="content-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i data-lucide="activity"></i>
                        Candidaturas Recentes
                    </h2>
                    <a href="candidaturas.php" class="card-action">
                        Ver todas
                        <i data-lucide="arrow-right"></i>
                    </a>
                </div>
                
                <div class="activity-list">
                    <?php if (empty($candidaturas_recentes)): ?>
                        <p style="text-align: center; color: var(--text-light); padding: var(--space-8);">
                            Nenhuma candidatura registrada ainda.
                        </p>
                    <?php else: ?>
                        <?php foreach (array_slice($candidaturas_recentes, 0, 5) as $candidatura): ?>
                            <div class="activity-item">
                                <div class="activity-avatar">
                                    <?php echo strtoupper(substr($candidatura['candidato_nome'], 0, 1)); ?>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($candidatura['candidato_nome']); ?></div>
                                    <div class="activity-description">
                                        Candidatou-se para <strong><?php echo htmlspecialchars($candidatura['vaga_titulo']); ?></strong> 
                                        em <strong><?php echo htmlspecialchars($candidatura['nome_empresa']); ?></strong>
                                    </div>
                                    <div class="activity-meta">
                                        <span>
                                            <i data-lucide="clock"></i>
                                            <?php echo formatarDataHora($candidatura['data_candidatura']); ?>
                                        </span>
                                        <span class="activity-badge <?php echo $candidatura['estado']; ?>">
                                            <?php echo traduzirEstado($candidatura['estado']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vagas por Área -->
            <div class="content-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i data-lucide="bar-chart-3"></i>
                        Vagas por Área
                    </h2>
                </div>
                
                <div class="stats-table">
                    <?php if (empty($vagas_por_area)): ?>
                        <p style="text-align: center; color: var(--text-light); padding: var(--space-6);">
                            Nenhuma vaga ativa no momento.
                        </p>
                    <?php else: ?>
                        <?php 
                        $max_vagas = max(array_column($vagas_por_area, 'total'));
                        foreach ($vagas_por_area as $area): 
                            $percentual = ($area['total'] / $max_vagas) * 100;
                        ?>
                            <div class="stats-row">
                                <div style="flex: 1;">
                                    <div class="stats-label">
                                        <i data-lucide="briefcase"></i>
                                        <?php echo htmlspecialchars($area['area']); ?>
                                    </div>
                                    <div class="stats-bar">
                                        <div class="stats-bar-fill" style="width: <?php echo $percentual; ?>%;"></div>
                                    </div>
                                </div>
                                <div class="stats-value"><?php echo $area['total']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ==========================================
             👥 RECENT USERS
        ========================================== -->
        <div class="content-grid">
            
            <!-- Empresas Recentes -->
            <div class="content-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i data-lucide="building-2"></i>
                        Empresas Recentes
                    </h2>
                    <a href="usuarios.php?tab=empresas" class="card-action">
                        Ver todas
                        <i data-lucide="arrow-right"></i>
                    </a>
                </div>
                
                <div class="user-list">
                    <?php if (empty($empresas_recentes)): ?>
                        <p style="text-align: center; color: var(--text-light); padding: var(--space-6);">
                            Nenhuma empresa cadastrada ainda.
                        </p>
                    <?php else: ?>
                        <?php foreach ($empresas_recentes as $empresa): ?>
                            <div class="user-item">
                                <?php if (!empty($empresa['logotipo'])): ?>
                                    <img src="../uploads/<?php echo htmlspecialchars($empresa['logotipo']); ?>" 
                                         alt="<?php echo htmlspecialchars($empresa['nome_empresa']); ?>" 
                                         class="user-avatar">
                                <?php else: ?>
                                    <div class="user-avatar-placeholder">
                                        <?php echo strtoupper(substr($empresa['nome_empresa'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="user-info">
                                    <div class="user-name"><?php echo htmlspecialchars($empresa['nome_empresa']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($empresa['email']); ?></div>
                                </div>
                                <div class="user-time"><?php echo formatarDataHora($empresa['data_registo']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Candidatos Recentes -->
            <div class="content-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i data-lucide="user-check"></i>
                        Candidatos Recentes
                    </h2>
                    <a href="usuarios.php?tab=candidatos" class="card-action">
                        Ver todos
                        <i data-lucide="arrow-right"></i>
                    </a>
                </div>
                
                <div class="user-list">
                    <?php if (empty($candidatos_recentes)): ?>
                        <p style="text-align: center; color: var(--text-light); padding: var(--space-6);">
                            Nenhum candidato cadastrado ainda.
                        </p>
                    <?php else: ?>
                        <?php foreach ($candidatos_recentes as $candidato): ?>
                            <div class="user-item">
                                <?php if (!empty($candidato['foto'])): ?>
                                    <img src="../uploads/fotos/<?php echo htmlspecialchars($candidato['foto']); ?>" 
                                         alt="<?php echo htmlspecialchars($candidato['nome_completo']); ?>" 
                                         class="user-avatar">
                                <?php else: ?>
                                    <div class="user-avatar-placeholder">
                                        <?php echo strtoupper(substr($candidato['nome_completo'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="user-info">
                                    <div class="user-name"><?php echo htmlspecialchars($candidato['nome_completo']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($candidato['email']); ?></div>
                                </div>
                                <div class="user-time"><?php echo formatarDataHora($candidato['data_registo']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ==========================================
             💼 GESTÃO DE VAGAS
        ========================================== -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i data-lucide="briefcase"></i>
                    Gestão de Vagas (<?php echo count($vagas_recentes); ?>)
                </h2>
                <a href="vagas.php" class="card-action">
                    Ver todas
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($vagas_recentes)): ?>
                <div style="text-align: center; padding: var(--space-10); color: var(--text-light);">
                    <i data-lucide="inbox" style="width: 64px; height: 64px; margin-bottom: var(--space-4); opacity: 0.3;"></i>
                    <p style="font-size: 16px; font-weight: 600; margin-bottom: var(--space-2);">Nenhuma vaga cadastrada</p>
                    <p style="font-size: 14px;">Nenhuma vaga foi publicada ainda.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="vagas-table">
                        <thead>
                            <tr>
                                <th>Vaga</th>
                                <th>Área</th>
                                <th>Localização</th>
                                <th>Candidaturas</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vagas_recentes as $vaga): ?>
                                <tr>
                                    <td>
                                        <div class="vaga-info">
                                            <?php if (!empty($vaga['logotipo'])): ?>
                                                <img src="../<?php echo htmlspecialchars($vaga['logotipo']); ?>" 
                                                     alt="<?php echo htmlspecialchars($vaga['nome_empresa']); ?>" 
                                                     class="vaga-logo">
                                            <?php else: ?>
                                                <div class="vaga-logo">
                                                    <?php echo strtoupper(substr($vaga['nome_empresa'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="vaga-details">
                                                <div class="vaga-title"><?php echo htmlspecialchars($vaga['titulo']); ?></div>
                                                <div class="vaga-empresa"><?php echo htmlspecialchars($vaga['nome_empresa']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($vaga['area']); ?></td>
                                    <td><?php echo htmlspecialchars($vaga['localizacao']); ?></td>
                                    <td>
                                        <span class="vaga-count">
                                            <i data-lucide="users"></i>
                                            <?php echo $vaga['total_candidaturas']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="vaga-badge <?php echo $vaga['ativa'] ? 'ativa' : 'inativa'; ?>">
                                            <i data-lucide="<?php echo $vaga['ativa'] ? 'check-circle' : 'x-circle'; ?>"></i>
                                            <?php echo $vaga['ativa'] ? 'Ativa' : 'Inativa'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
    </script>
    
    <!-- 🔒 Proteção de Sessão - Encerra ao fechar aba -->
    <script src="../assets/js/session-guard.js"></script>

</body>
</html>
