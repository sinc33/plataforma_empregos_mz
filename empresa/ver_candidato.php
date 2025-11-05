<?php
session_start();
require_once '../config/db.php';

// Verificar autenticação
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'empresa') {
    header("Location: ../auth/login.php");
    exit;
}

$empresa_id = $_SESSION['user_id'];
$candidato_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$candidatura_id = isset($_GET['candidatura_id']) ? (int)$_GET['candidatura_id'] : 0;

if ($candidato_id === 0) {
    header("Location: dashboard.php");
    exit;
}

// Buscar informações do candidato
$sql_candidato = "SELECT c.*, u.email FROM candidato c JOIN utilizador u ON c.id = u.id WHERE c.id = ?";
$stmt_candidato = $pdo->prepare($sql_candidato);
$stmt_candidato->execute([$candidato_id]);
$candidato = $stmt_candidato->fetch();

if (!$candidato) {
    header("Location: dashboard.php");
    exit;
}

// Buscar experiências profissionais
$sql_experiencias = "SELECT * FROM experiencia WHERE candidato_id = ? ORDER BY data_inicio DESC";
$stmt_experiencias = $pdo->prepare($sql_experiencias);
$stmt_experiencias->execute([$candidato_id]);
$experiencias = $stmt_experiencias->fetchAll();

// Buscar formações acadêmicas
$sql_formacoes = "SELECT * FROM formacao WHERE candidato_id = ? ORDER BY data_inicio DESC";
$stmt_formacoes = $pdo->prepare($sql_formacoes);
$stmt_formacoes->execute([$candidato_id]);
$formacoes = $stmt_formacoes->fetchAll();

// Buscar candidaturas deste candidato às vagas da empresa
$sql_candidaturas = "
    SELECT c.*, v.titulo, v.area, v.localizacao
    FROM candidatura c
    JOIN vaga v ON c.vaga_id = v.id
    WHERE c.candidato_id = ? AND v.empresa_id = ?
    ORDER BY c.data_candidatura DESC
";
$stmt_candidaturas = $pdo->prepare($sql_candidaturas);
$stmt_candidaturas->execute([$candidato_id, $empresa_id]);
$candidaturas_empresa = $stmt_candidaturas->fetchAll();

// Buscar candidatura específica se fornecida
$candidatura_atual = null;
if ($candidatura_id > 0) {
    $sql_candidatura_atual = "
        SELECT c.*, v.titulo, v.id as vaga_id FROM candidatura c 
        JOIN vaga v ON c.vaga_id = v.id 
        WHERE c.id = ? AND c.candidato_id = ?
    ";
    $stmt_candidatura_atual = $pdo->prepare($sql_candidatura_atual);
    $stmt_candidatura_atual->execute([$candidatura_id, $candidato_id]);
    $candidatura_atual = $stmt_candidatura_atual->fetch();
}

// Função helper para traduzir estados
function traduzirEstado($estado) {
    $estados = [
        'submetida' => 'Submetida',
        'em_analise' => 'Em Análise',
        'entrevista' => 'Entrevista',
        'rejeitada' => 'Rejeitada',
        'contratado' => 'Contratado'
    ];
    return $estados[$estado] ?? $estado;
}

// Calcular anos de experiência total
function calcularAnosExperiencia($experiencias) {
    $total_meses = 0;
    foreach ($experiencias as $exp) {
        $inicio = new DateTime($exp['data_inicio']);
        $fim = !empty($exp['data_fim']) ? new DateTime($exp['data_fim']) : new DateTime();
        $diff = $inicio->diff($fim);
        $total_meses += ($diff->y * 12) + $diff->m;
    }
    $anos = floor($total_meses / 12);
    $meses = $total_meses % 12;
    
    if ($anos > 0 && $meses > 0) {
        return "$anos anos e $meses meses";
    } elseif ($anos > 0) {
        return "$anos anos";
    } else {
        return "$meses meses";
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de <?php echo htmlspecialchars($candidato['nome_completo']); ?> | Emprego MZ</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- html2pdf.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
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
            /* 🎨 CORES PRINCIPAIS - Nova Identidade Visual Profissional */
            --cor-primaria: #14213d;              /* Oxford Blue - Confiança e Estabilidade */
            --cor-primaria-escura: #0f1a2e;       /* Oxford Blue Dark */
            --cor-primaria-clara: #1e2c47;        /* Oxford Blue Light */
            --cor-primaria-alpha: rgba(20, 33, 61, 0.1);
            
            --cor-secundaria: #fca311;            /* Orange Web - Energia e Ação */
            --cor-secundaria-hover: #e3940f;      /* Orange Hover */
            --cor-secundaria-clara: #fdb541;      /* Orange Light */
            --cor-secundaria-muito-clara: #fec871; /* Orange Very Light */
            --cor-secundaria-alpha: rgba(252, 163, 17, 0.1);
            
            /* 🌫️ HIERARQUIA VISUAL - Soft Gray System */
            --cor-fundo: #f8fafc;                 /* Soft Gray - FUNDO PRINCIPAL */
            --cor-cards: #ffffff;                 /* White - Cards/Containers */
            --cor-acento: #f1f5ff;                /* Light Blue - Detalhes e acentos */
            --cor-texto: #000000;                 /* Black - Textos principais */
            --cor-texto-claro: #666666;           /* Gray - Textos secundários */
            --cor-borda: #e5e7eb;                 /* Light Border */
            
            /* 🎨 ESTADOS */
            --cor-sucesso: #10B981;
            --cor-erro: #EF4444;
            --cor-aviso: #fca311;
            --cor-info: #14213d;
            
            /* Aliases compatibilidade */
            --primary: var(--cor-primaria);
            --primary-dark: var(--cor-primaria-escura);
            --secondary: var(--cor-secundaria);
            --accent: var(--cor-sucesso);
            --text: var(--cor-texto);
            --text-light: var(--cor-texto-claro);
            --text-lighter: #999999;
            --border: var(--cor-borda);
            --gray-50: #fafafa;
            --gray-100: #f5f5f5;
            --gray-200: #ebebeb;
            --white: var(--cor-fundo);
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
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            
            /* Transitions */
            --transition: all 0.2s ease;
        }
        
        body {
            font-family: var(--font-primary);
            color: var(--text);
            background: var(--gray-50);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* ==========================================
           📱 HEADER - CONSISTENT WITH INDEX.PHP (Oxford Blue)
        ========================================== */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: var(--cor-primaria);     /* #14213d - Oxford Blue */
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
            flex-shrink: 0;
        }

        .logo-img {
            height: 36px;
            width: auto;
        }

        .logo:hover .logo-img {
            transform: scale(1.05);
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
            top: calc(100% + 12px);
            left: 0;
            background: var(--cor-cards);           /* #ffffff - White card */
            border: 1px solid var(--cor-borda);     /* #e2e8f0 - Borda refinada */
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 24px rgba(20, 33, 61, 0.15);
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
            cursor: pointer;
            border: none;
        }

        .btn svg {
            width: 18px;
            height: 18px;
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

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: var(--space-2);
            color: var(--white);
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
            margin-top: 70px;
        }

        .breadcrumb-container {
            max-width: 1200px;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--space-8) var(--space-6);
        }

        /* ==========================================
           👤 PROFILE HEADER EXECUTIVE
        ========================================== */
        .profile-header {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-8);
            box-shadow: var(--shadow);
            margin-bottom: var(--space-8);
        }

        .profile-main {
            display: flex;
            gap: var(--space-8);
            margin-bottom: var(--space-6);
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--gray-200);
            flex-shrink: 0;
            border: 4px solid var(--white);
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            color: var(--text-light);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-details {
            flex: 1;
            min-width: 0;
        }

        .profile-name {
            font-size: 32px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: var(--space-3);
        }

        .profile-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-4);
            margin-bottom: var(--space-5);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--text-light);
            font-size: 15px;
        }

        .meta-item svg {
            width: 18px;
            height: 18px;
            color: var(--primary);
            flex-shrink: 0;
        }

        .profile-actions {
            display: flex;
            gap: var(--space-3);
            flex-wrap: wrap;
        }

        .btn-action {
            padding: var(--space-3) var(--space-5);
            font-size: 15px;
        }

        .btn-action svg {
            width: 20px;
            height: 20px;
        }

        /* ==========================================
           📊 TWO COLUMN LAYOUT
        ========================================== */
        .content-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: var(--space-8);
        }

        .main-content {
            display: flex;
            flex-direction: column;
            gap: var(--space-6);
        }

        /* ==========================================
           📝 SECTION CARDS
        ========================================== */
        .section-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-bottom: var(--space-6);
            padding-bottom: var(--space-4);
            border-bottom: 2px solid var(--gray-100);
        }

        .section-icon {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 136, 204, 0.1);
            border-radius: var(--radius);
            flex-shrink: 0;
        }

        .section-icon svg {
            width: 24px;
            height: 24px;
            color: var(--primary);
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
        }

        /* Skills */
        .skills-grid {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .skill-tag {
            padding: var(--space-2) var(--space-4);
            background: rgba(0, 136, 204, 0.1);
            color: var(--primary);
            border-radius: var(--radius-full);
            font-size: 14px;
            font-weight: 600;
            border: 1px solid rgba(0, 136, 204, 0.2);
        }

        /* Timeline */
        .timeline-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-6);
        }

        .timeline-item {
            position: relative;
            padding-left: var(--space-6);
            border-left: 2px solid var(--border);
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 4px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
            border: 2px solid var(--white);
            box-shadow: 0 0 0 2px var(--primary);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--space-4);
            margin-bottom: var(--space-2);
        }

        .timeline-content {
            flex: 1;
            min-width: 0;
        }

        .timeline-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: var(--space-1);
        }

        .timeline-subtitle {
            font-size: 15px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: var(--space-2);
        }

        .timeline-date {
            font-size: 14px;
            color: var(--text-light);
            font-weight: 600;
            white-space: nowrap;
        }

        .timeline-description {
            font-size: 14px;
            color: var(--text-light);
            line-height: 1.7;
            margin-top: var(--space-2);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: var(--space-12);
            color: var(--text-lighter);
        }

        .empty-state-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto var(--space-4);
            opacity: 0.4;
        }

        .empty-state-text {
            font-size: 15px;
            color: var(--text-light);
        }

        /* ==========================================
           📌 SIDEBAR
        ========================================== */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: var(--space-6);
        }

        .sidebar-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            box-shadow: var(--shadow);
        }

        .sidebar-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: var(--space-4);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .sidebar-title svg {
            width: 20px;
            height: 20px;
            color: var(--primary);
        }

        /* Info Items */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-4);
        }

        .info-item {
            padding: var(--space-3);
            background: var(--gray-50);
            border-radius: var(--radius);
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: var(--space-1);
        }

        .info-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }

        /* Application Mini Cards */
        .application-mini {
            padding: var(--space-4);
            background: var(--gray-50);
            border-radius: var(--radius);
            margin-bottom: var(--space-3);
            border: 1px solid var(--border);
        }

        .application-mini:last-child {
            margin-bottom: 0;
        }

        .application-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: var(--space-2);
        }

        .application-meta {
            display: flex;
            flex-direction: column;
            gap: var(--space-1);
            font-size: 13px;
            color: var(--text-light);
            margin-bottom: var(--space-2);
        }

        .application-meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-1);
        }

        .application-meta-item svg {
            width: 14px;
            height: 14px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-1);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge svg {
            width: 12px;
            height: 12px;
        }

        .badge-submetida {
            background: rgba(0, 136, 204, 0.1);
            color: var(--primary);
            border: 1px solid rgba(0, 136, 204, 0.3);
        }

        .badge-em_analise {
            background: rgba(255, 140, 0, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(255, 140, 0, 0.3);
        }

        .badge-entrevista {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-rejeitada {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .badge-contratado {
            background: rgba(111, 66, 193, 0.1);
            color: #6F42C1;
            border: 1px solid rgba(111, 66, 193, 0.3);
        }

        /* ==========================================
           🦶 FOOTER - CONSISTENT WITH INDEX.PHP
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
            .nav-menu {
                display: none;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .content-layout {
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

            .profile-header {
                padding: var(--space-5);
            }

            .profile-main {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .profile-name {
                font-size: 24px;
            }

            .profile-meta-grid {
                grid-template-columns: 1fr;
            }

            .profile-actions {
                flex-direction: column;
                width: 100%;
            }

            .btn-action {
                width: 100%;
                justify-content: center;
            }

            .section-card {
                padding: var(--space-5);
            }

            .timeline-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
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

        .profile-header,
        .section-card,
        .sidebar-card {
            animation: fadeIn 0.3s ease-out;
        }

        /* PDF Generation Styles */
        .pdf-hide {
            /* Elements with this class will be hidden in PDF */
        }

        .pdf-content {
            /* Wrapper for PDF content */
        }
    </style>
</head>
<body>

    <!-- ==========================================
         📱 HEADER - CONSISTENT WITH OTHER PAGES
    ========================================== -->
    <header class="header pdf-hide">
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
                    <a href="#" class="nav-link has-dropdown active">
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
                        <?php if ($candidatura_atual): ?>
                            <a href="candidaturas.php?vaga_id=<?php echo $candidatura_atual['vaga_id']; ?>" class="btn btn-outline">
                                <i data-lucide="arrow-left"></i>
                                Voltar
                            </a>
                        <?php endif; ?>
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
    <div class="breadcrumb pdf-hide">
        <div class="breadcrumb-container">
            <a href="../index.php" class="breadcrumb-link">Início</a>
            <i data-lucide="chevron-right" class="breadcrumb-separator"></i>
            <a href="dashboard.php" class="breadcrumb-link">Dashboard</a>
            <i data-lucide="chevron-right" class="breadcrumb-separator"></i>
            <?php if ($candidatura_atual): ?>
                <a href="candidaturas.php?vaga_id=<?php echo $candidatura_atual['vaga_id']; ?>" class="breadcrumb-link">
                    <?php echo htmlspecialchars($candidatura_atual['titulo']); ?>
                </a>
                <i data-lucide="chevron-right" class="breadcrumb-separator"></i>
            <?php endif; ?>
            <span><?php echo htmlspecialchars($candidato['nome_completo']); ?></span>
        </div>
    </div>

    <!-- ==========================================
         📋 MAIN CONTENT
    ========================================== -->
    <div class="main-container">

        <!-- Profile Header Executive -->
        <div class="profile-header">
            <div class="profile-main">
                <div class="profile-avatar">
                    <?php if (!empty($candidato['foto_perfil']) && file_exists('../uploads/fotos/' . $candidato['foto_perfil'])): ?>
                        <img src="../uploads/fotos/<?php echo htmlspecialchars($candidato['foto_perfil']); ?>" 
                             alt="<?php echo htmlspecialchars($candidato['nome_completo']); ?>">
                    <?php else: ?>
                        <i data-lucide="user"></i>
                    <?php endif; ?>
                </div>

                <div class="profile-details">
                    <h1 class="profile-name"><?php echo htmlspecialchars($candidato['nome_completo']); ?></h1>
                    
                    <div class="profile-meta-grid">
                        <?php if (!empty($candidato['localizacao'])): ?>
                            <div class="meta-item">
                                <i data-lucide="map-pin"></i>
                                <?php echo htmlspecialchars($candidato['localizacao']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="meta-item">
                            <i data-lucide="mail"></i>
                            <?php echo htmlspecialchars($candidato['email']); ?>
                        </div>
                        
                        <?php if (!empty($candidato['telefone'])): ?>
                            <div class="meta-item">
                                <i data-lucide="phone"></i>
                                <?php echo htmlspecialchars($candidato['telefone']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($experiencias)): ?>
                            <div class="meta-item">
                                <i data-lucide="briefcase"></i>
                                <?php echo calcularAnosExperiencia($experiencias); ?> de experiência
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="profile-actions pdf-hide">
                        <?php if (!empty($candidato['cv_pdf'])): ?>
                            <a href="../uploads/cv/<?php echo htmlspecialchars($candidato['cv_pdf']); ?>" 
                               target="_blank" class="btn btn-primary btn-action">
                                <i data-lucide="download"></i>
                                Baixar CV
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Layout -->
        <div class="content-layout">
            
            <!-- Main Content -->
            <div class="main-content">

                <!-- Skills Section -->
                <?php if (!empty($candidato['competencias'])): ?>
                    <div class="section-card">
                        <div class="section-header">
                            <div class="section-icon">
                                <i data-lucide="award"></i>
                            </div>
                            <h2 class="section-title">Competências</h2>
                        </div>
                        <div class="skills-grid">
                            <?php
                            $competencias = explode(',', $candidato['competencias']);
                            foreach ($competencias as $comp):
                                $comp = trim($comp);
                                if (!empty($comp)):
                            ?>
                                <span class="skill-tag"><?php echo htmlspecialchars($comp); ?></span>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Experience Section -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-icon">
                            <i data-lucide="briefcase"></i>
                        </div>
                        <h2 class="section-title">Experiência Profissional</h2>
                    </div>
                    
                    <?php if (empty($experiencias)): ?>
                        <div class="empty-state">
                            <i data-lucide="inbox" class="empty-state-icon"></i>
                            <div class="empty-state-text">Nenhuma experiência profissional cadastrada</div>
                        </div>
                    <?php else: ?>
                        <div class="timeline-list">
                            <?php foreach ($experiencias as $exp): ?>
                                <div class="timeline-item">
                                    <div class="timeline-header">
                                        <div class="timeline-content">
                                            <div class="timeline-title"><?php echo htmlspecialchars($exp['cargo']); ?></div>
                                            <div class="timeline-subtitle"><?php echo htmlspecialchars($exp['empresa']); ?></div>
                                        </div>
                                        <div class="timeline-date">
                                            <?php echo $exp['data_inicio'] ? date('m/Y', strtotime($exp['data_inicio'])) : 'N/A'; ?> - 
                                            <?php echo (empty($exp['data_fim']) ? 'Atual' : date('m/Y', strtotime($exp['data_fim']))); ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($exp['descricao'])): ?>
                                        <div class="timeline-description"><?php echo nl2br(htmlspecialchars($exp['descricao'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Education Section -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-icon">
                            <i data-lucide="graduation-cap"></i>
                        </div>
                        <h2 class="section-title">Formação Académica</h2>
                    </div>
                    
                    <?php if (empty($formacoes)): ?>
                        <div class="empty-state">
                            <i data-lucide="inbox" class="empty-state-icon"></i>
                            <div class="empty-state-text">Nenhuma formação académica cadastrada</div>
                        </div>
                    <?php else: ?>
                        <div class="timeline-list">
                            <?php foreach ($formacoes as $formacao): ?>
                                <div class="timeline-item">
                                    <div class="timeline-header">
                                        <div class="timeline-content">
                                            <div class="timeline-title"><?php echo htmlspecialchars($formacao['curso']); ?></div>
                                            <div class="timeline-subtitle"><?php echo htmlspecialchars($formacao['instituicao']); ?></div>
                                        </div>
                                        <div class="timeline-date">
                                            <?php echo $formacao['data_inicio'] ? date('Y', strtotime($formacao['data_inicio'])) : 'N/A'; ?> - 
                                            <?php echo (empty($formacao['data_fim']) ? 'Em andamento' : date('Y', strtotime($formacao['data_fim']))); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Cover Letter (if available) -->
                <?php if ($candidatura_atual && !empty($candidatura_atual['carta_apresentacao'])): ?>
                    <div class="section-card">
                        <div class="section-header">
                            <div class="section-icon">
                                <i data-lucide="mail"></i>
                            </div>
                            <h2 class="section-title">Carta de Apresentação</h2>
                        </div>
                        <div style="font-size: 15px; color: var(--text-light); line-height: 1.7;">
                            <?php echo nl2br(htmlspecialchars($candidatura_atual['carta_apresentacao'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Sidebar -->
            <div class="sidebar">

                <!-- Quick Stats -->
                <div class="sidebar-card">
                    <h3 class="sidebar-title">
                        <i data-lucide="bar-chart"></i>
                        Resumo Executivo
                    </h3>
                    <div class="info-list">
                        <div class="info-item">
                            <div class="info-label">Experiências</div>
                            <div class="info-value"><?php echo count($experiencias); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Formações</div>
                            <div class="info-value"><?php echo count($formacoes); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Candidaturas</div>
                            <div class="info-value"><?php echo count($candidaturas_empresa); ?></div>
                        </div>
                        <?php if (!empty($candidato['cv_pdf'])): ?>
                            <div class="info-item">
                                <div class="info-label">CV Disponível</div>
                                <div class="info-value">
                                    <i data-lucide="check-circle" style="width: 20px; height: 20px; color: var(--success);"></i>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Applications to Company -->
                <div class="sidebar-card">
                    <h3 class="sidebar-title">
                        <i data-lucide="file-text"></i>
                        Candidaturas à Empresa
                    </h3>
                    
                    <?php if (empty($candidaturas_empresa)): ?>
                        <div class="empty-state" style="padding: var(--space-8);">
                            <i data-lucide="inbox" class="empty-state-icon" style="width: 48px; height: 48px;"></i>
                            <div class="empty-state-text">Nenhuma candidatura</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($candidaturas_empresa as $cand): ?>
                            <div class="application-mini">
                                <div class="application-title"><?php echo htmlspecialchars($cand['titulo']); ?></div>
                                <div class="application-meta">
                                    <div class="application-meta-item">
                                        <i data-lucide="map-pin"></i>
                                        <?php echo htmlspecialchars($cand['localizacao']); ?>
                                    </div>
                                    <div class="application-meta-item">
                                        <i data-lucide="calendar"></i>
                                        <?php echo date('d/m/Y', strtotime($cand['data_candidatura'])); ?>
                                    </div>
                                </div>
                                <span class="status-badge badge-<?php echo $cand['estado']; ?>">
                                    <i data-lucide="<?php 
                                        echo $cand['estado'] === 'submetida' ? 'send' : 
                                            ($cand['estado'] === 'em_analise' ? 'eye' : 
                                            ($cand['estado'] === 'entrevista' ? 'calendar' : 
                                            ($cand['estado'] === 'rejeitada' ? 'x-circle' : 'check-circle'))); 
                                    ?>"></i>
                                    <?php echo traduzirEstado($cand['estado']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </div>

    <!-- ==========================================
         🦶 FOOTER
    ========================================== -->
    <footer class="footer pdf-hide">
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

        // Download PDF function
        function downloadPDF() {
            // Get candidate name for filename
            const candidateName = '<?php echo addslashes($candidato['nome_completo']); ?>';
            const filename = `Perfil_${candidateName.replace(/\s+/g, '_')}.pdf`;

            // Store original display values
            const elementsToHide = document.querySelectorAll('.pdf-hide');
            const originalDisplays = Array.from(elementsToHide).map(el => el.style.display);

            // Hide elements
            elementsToHide.forEach(el => el.style.display = 'none');

            // Get the main container
            const element = document.querySelector('.main-container');

            // PDF options
            const opt = {
                margin: [10, 10, 10, 10],
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    logging: false
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait' 
                }
            };

            // Generate PDF
            html2pdf().set(opt).from(element).save().then(() => {
                // Restore original display values
                elementsToHide.forEach((el, index) => {
                    el.style.display = originalDisplays[index];
                });
            });
        }
    </script>
    
    <!-- 🔒 Proteção de Sessão - Encerra ao fechar aba -->
    <script src="../assets/js/session-guard.js"></script>

</body>
</html>
