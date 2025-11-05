<?php
session_start();
require_once '../config/db.php';

// Verificar autenticação
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'empresa') {
    header("Location: ../auth/login.php");
    exit;
}

$empresa_id = $_SESSION['user_id'];

// Verificar vaga_id
if (isset($_GET['vaga_id'])) {
    $vaga_id = (int)$_GET['vaga_id'];
    
    // Verificar se a vaga pertence à empresa
    $stmt_vaga = $pdo->prepare("SELECT v.*, e.nome_empresa FROM vaga v JOIN empresa e ON v.empresa_id = e.id WHERE v.id = ? AND v.empresa_id = ?");
    $stmt_vaga->execute([$vaga_id, $empresa_id]);
    $vaga = $stmt_vaga->fetch();
    
    if (!$vaga) {
        header("Location: dashboard.php");
        exit;
    }
} else {
    header("Location: dashboard.php");
    exit;
}

// Processar ações nas candidaturas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidatura_id = (int)$_POST['candidatura_id'];
    $acao = $_POST['acao'];
    
    // Verificar se a candidatura pertence a uma vaga da empresa
    $stmt_verificar = $pdo->prepare("SELECT c.id FROM candidatura c JOIN vaga v ON c.vaga_id = v.id WHERE c.id = ? AND v.empresa_id = ?");
    $stmt_verificar->execute([$candidatura_id, $empresa_id]);
    
    if ($stmt_verificar->fetch()) {
        try {
            switch ($acao) {
                case 'mudar_estado':
                    $novo_estado = $_POST['estado'];
                    $estados_validos = ['submetida', 'em_analise', 'entrevista', 'rejeitada', 'contratado'];
                    
                    if (in_array($novo_estado, $estados_validos)) {
                        $stmt = $pdo->prepare("UPDATE candidatura SET estado = ? WHERE id = ?");
                        $stmt->execute([$novo_estado, $candidatura_id]);
                        $_SESSION['mensagem_sucesso'] = "Estado da candidatura atualizado com sucesso!";
                        header("Location: candidaturas.php?vaga_id=" . $vaga_id);
                        exit;
                    }
                    break;
                
                case 'adicionar_nota':
                    $nota = trim($_POST['nota']);
                    if (!empty($nota)) {
                        $stmt = $pdo->prepare("UPDATE candidatura SET nota_interna = ? WHERE id = ?");
                        $stmt->execute([$nota, $candidatura_id]);
                        $_SESSION['mensagem_sucesso'] = "Nota adicionada com sucesso!";
                        header("Location: candidaturas.php?vaga_id=" . $vaga_id);
                        exit;
                    }
                    break;
            }
        } catch (PDOException $e) {
            $_SESSION['mensagem_erro'] = "Erro ao processar ação. Por favor, tente novamente.";
            error_log("Erro ao processar ação em candidaturas: " . $e->getMessage());
            header("Location: candidaturas.php?vaga_id=" . $vaga_id);
            exit;
        }
    } else {
        $_SESSION['mensagem_erro'] = "Candidatura não encontrada.";
        header("Location: candidaturas.php?vaga_id=" . $vaga_id);
        exit;
    }
}

// Filtro por estado
$filtro_estado = $_GET['estado'] ?? '';

// Buscar candidaturas da vaga
$sql_candidaturas = "
    SELECT c.*, 
        cand.nome_completo, 
        cand.telefone, 
        cand.localizacao, 
        cand.competencias, 
        cand.foto_perfil, 
        cand.cv_pdf
    FROM candidatura c
    JOIN candidato cand ON c.candidato_id = cand.id
    WHERE c.vaga_id = ?
";

$params = [$vaga_id];

if (!empty($filtro_estado)) {
    $sql_candidaturas .= " AND c.estado = ?";
    $params[] = $filtro_estado;
}

$sql_candidaturas .= " ORDER BY c.data_candidatura DESC";

$stmt_candidaturas = $pdo->prepare($sql_candidaturas);
$stmt_candidaturas->execute($params);
$candidaturas = $stmt_candidaturas->fetchAll();

// Contar candidaturas por estado
$sql_contadores = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'submetida' THEN 1 ELSE 0 END) as submetidas,
        SUM(CASE WHEN estado = 'em_analise' THEN 1 ELSE 0 END) as em_analise,
        SUM(CASE WHEN estado = 'entrevista' THEN 1 ELSE 0 END) as entrevistas,
        SUM(CASE WHEN estado = 'rejeitada' THEN 1 ELSE 0 END) as rejeitadas,
        SUM(CASE WHEN estado = 'contratado' THEN 1 ELSE 0 END) as contratados
    FROM candidatura
    WHERE vaga_id = ?
";
$stmt_contadores = $pdo->prepare($sql_contadores);
$stmt_contadores->execute([$vaga_id]);
$contadores = $stmt_contadores->fetch();

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
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATS - <?php echo htmlspecialchars($vaga['titulo']); ?> | Emprego MZ</title>
    
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
           🔔 ALERTS & NOTIFICATIONS
        ========================================== */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            border-radius: var(--radius-lg);
            font-size: 14px;
            font-weight: 500;
            line-height: 1.6;
            animation: slideDown 0.3s ease-out;
        }
        
        .alert svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-left: 4px solid var(--cor-sucesso);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #991B1B;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-left: 4px solid var(--cor-erro);
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
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-4) var(--space-6);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-8);
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            flex-shrink: 0;
        }

        .logo-img {
            height: 40px;
            width: auto;
            transition: var(--transition);
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

        .dropdown-divider {
            height: 1px;
            background: var(--cor-borda);
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

        .vaga-title-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--space-6);
            margin-bottom: var(--space-4);
        }

        .vaga-title-content {
            flex: 1;
        }

        .vaga-title {
            font-size: 28px;
            font-weight: 800;
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
           📊 STATISTICS CARDS (ATS FUNNEL)
        ========================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: var(--space-4);
            margin-bottom: var(--space-8);
        }

        .stat-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            box-shadow: var(--shadow);
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
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

        .stat-card.active::before {
            opacity: 1;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
            margin-bottom: var(--space-2);
        }

        .stat-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ==========================================
           💼 CANDIDATE CARDS (ATS PREMIUM)
        ========================================== */
        .candidates-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-6);
        }

        .candidate-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .candidate-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
            border-color: var(--primary);
        }

        .candidate-header {
            display: flex;
            gap: var(--space-5);
            margin-bottom: var(--space-5);
        }

        .candidate-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--gray-200);
            flex-shrink: 0;
            border: 3px solid var(--white);
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: var(--text-light);
        }

        .candidate-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .candidate-main {
            flex: 1;
            min-width: 0;
        }

        .candidate-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--space-4);
            margin-bottom: var(--space-3);
        }

        .candidate-name-area {
            flex: 1;
            min-width: 0;
        }

        .candidate-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: var(--space-1);
        }

        .candidate-date {
            font-size: 13px;
            color: var(--text-lighter);
            display: flex;
            align-items: center;
            gap: var(--space-1);
        }

        .candidate-date svg {
            width: 14px;
            height: 14px;
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

        .candidate-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-5);
            padding: var(--space-4) 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            margin-bottom: var(--space-5);
        }

        .candidate-skills {
            margin-bottom: var(--space-5);
        }

        .skills-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: var(--space-3);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .skills-title svg {
            width: 16px;
            height: 16px;
            color: var(--primary);
        }

        .skills-tags {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .skill-tag {
            padding: var(--space-1) var(--space-3);
            background: var(--gray-100);
            border-radius: var(--radius-full);
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .candidate-letter {
            background: var(--gray-50);
            border-left: 3px solid var(--primary);
            padding: var(--space-4);
            border-radius: var(--radius);
            margin-bottom: var(--space-5);
        }

        .letter-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: var(--space-2);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .letter-title svg {
            width: 16px;
            height: 16px;
            color: var(--primary);
        }

        .letter-text {
            font-size: 14px;
            color: var(--text-light);
            line-height: 1.7;
        }

        .candidate-actions {
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
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--text);
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-action:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-action svg {
            width: 16px;
            height: 16px;
        }

        .btn-action-primary {
            background: var(--primary);
            border-color: var(--primary);
            color: var(--white);
        }

        .btn-action-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .status-selector {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .status-select {
            padding: var(--space-2) var(--space-3);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 14px;
            font-family: var(--font-primary);
            font-weight: 500;
            color: var(--text);
            cursor: pointer;
            transition: var(--transition);
        }

        .status-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 136, 204, 0.1);
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
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ==========================================
           📋 MODAL
        ========================================== */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
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
            background: var(--white);
            border-radius: var(--radius-lg);
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            animation: slideUp 0.3s ease-out;
        }

        .modal-header {
            padding: var(--space-6);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .modal-title svg {
            width: 24px;
            height: 24px;
            color: var(--primary);
        }

        .modal-close {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            border-radius: var(--radius);
            transition: var(--transition);
            flex-shrink: 0;
        }

        .modal-close:hover {
            background: var(--gray-100);
            color: var(--text);
        }

        .modal-close svg {
            width: 24px;
            height: 24px;
        }

        .modal-body {
            padding: var(--space-6);
        }

        .form-group {
            margin-bottom: var(--space-5);
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: var(--space-2);
        }

        .form-textarea {
            width: 100%;
            padding: var(--space-3);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 14px;
            font-family: var(--font-primary);
            color: var(--text);
            resize: vertical;
            min-height: 120px;
            transition: var(--transition);
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 136, 204, 0.1);
        }

        .modal-actions {
            display: flex;
            gap: var(--space-3);
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

            .vaga-title {
                font-size: 22px;
            }

            .vaga-title-section {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .candidate-header {
                flex-direction: row;
            }

            .candidate-avatar {
                width: 64px;
                height: 64px;
            }

            .candidate-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .candidate-meta {
                flex-direction: column;
                gap: var(--space-3);
            }

            .candidate-actions {
                flex-direction: column;
            }

            .btn-action,
            .status-selector {
                width: 100%;
            }

            .status-selector {
                flex-direction: column;
                align-items: stretch;
            }

            .status-select {
                width: 100%;
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

        .candidate-card {
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
            <a href="dashboard.php" class="breadcrumb-link">Dashboard</a>
            <i data-lucide="chevron-right" class="breadcrumb-separator"></i>
            <span><?php echo htmlspecialchars($vaga['titulo']); ?></span>
            <i data-lucide="chevron-right" class="breadcrumb-separator"></i>
            <span>Candidaturas</span>
        </div>
    </div>

    <!-- ==========================================
         📋 MAIN CONTENT
    ========================================== -->
    <div class="main-container">

        <!-- Mensagens de Feedback -->
        <?php if (isset($_SESSION['mensagem_sucesso'])): ?>
            <div class="alert alert-success" style="margin-bottom: var(--space-6);">
                <i data-lucide="check-circle"></i>
                <div><?php echo htmlspecialchars($_SESSION['mensagem_sucesso']); ?></div>
            </div>
            <?php unset($_SESSION['mensagem_sucesso']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['mensagem_erro'])): ?>
            <div class="alert alert-error" style="margin-bottom: var(--space-6);">
                <i data-lucide="alert-circle"></i>
                <div><?php echo htmlspecialchars($_SESSION['mensagem_erro']); ?></div>
            </div>
            <?php unset($_SESSION['mensagem_erro']); ?>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div class="vaga-title-section">
                <div class="vaga-title-content">
                    <h1 class="vaga-title"><?php echo htmlspecialchars($vaga['titulo']); ?></h1>
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
                            Publicada em <?php echo date('d/m/Y', strtotime($vaga['data_publicacao'])); ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Statistics Cards (ATS Funnel) -->
        <div class="stats-grid">
            <a href="?vaga_id=<?php echo $vaga_id; ?>" class="stat-card <?php echo empty($filtro_estado) ? 'active' : ''; ?>">
                <div class="stat-number"><?php echo $contadores['total']; ?></div>
                <div class="stat-label">Total</div>
            </a>

            <a href="?vaga_id=<?php echo $vaga_id; ?>&estado=submetida" class="stat-card <?php echo $filtro_estado === 'submetida' ? 'active' : ''; ?>">
                <div class="stat-number"><?php echo $contadores['submetidas']; ?></div>
                <div class="stat-label">Submetidas</div>
            </a>

            <a href="?vaga_id=<?php echo $vaga_id; ?>&estado=em_analise" class="stat-card <?php echo $filtro_estado === 'em_analise' ? 'active' : ''; ?>">
                <div class="stat-number"><?php echo $contadores['em_analise']; ?></div>
                <div class="stat-label">Em Análise</div>
            </a>

            <a href="?vaga_id=<?php echo $vaga_id; ?>&estado=entrevista" class="stat-card <?php echo $filtro_estado === 'entrevista' ? 'active' : ''; ?>">
                <div class="stat-number"><?php echo $contadores['entrevistas']; ?></div>
                <div class="stat-label">Entrevistas</div>
            </a>

            <a href="?vaga_id=<?php echo $vaga_id; ?>&estado=rejeitada" class="stat-card <?php echo $filtro_estado === 'rejeitada' ? 'active' : ''; ?>">
                <div class="stat-number"><?php echo $contadores['rejeitadas']; ?></div>
                <div class="stat-label">Rejeitadas</div>
            </a>

            <a href="?vaga_id=<?php echo $vaga_id; ?>&estado=contratado" class="stat-card <?php echo $filtro_estado === 'contratado' ? 'active' : ''; ?>">
                <div class="stat-number"><?php echo $contadores['contratados']; ?></div>
                <div class="stat-label">Contratados</div>
            </a>
        </div>

        <!-- Candidates List -->
        <?php if (empty($candidaturas)): ?>
            <div class="empty-state">
                <i data-lucide="inbox" class="empty-state-icon"></i>
                <h3 class="empty-state-title">Nenhuma candidatura encontrada</h3>
                <p class="empty-state-text">
                    <?php if (!empty($filtro_estado)): ?>
                        Não há candidaturas com o estado "<?php echo traduzirEstado($filtro_estado); ?>".
                    <?php else: ?>
                        Esta vaga ainda não recebeu candidaturas. Aguarde os primeiros candidatos.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="candidates-list">
                <?php foreach ($candidaturas as $candidatura): ?>
                    <div class="candidate-card">
                        <div class="candidate-header">
                            <div class="candidate-avatar">
                                <?php if (!empty($candidatura['foto_perfil']) && file_exists('../uploads/fotos/' . $candidatura['foto_perfil'])): ?>
                                    <img src="../uploads/fotos/<?php echo htmlspecialchars($candidatura['foto_perfil']); ?>" 
                                         alt="<?php echo htmlspecialchars($candidatura['nome_completo']); ?>">
                                <?php else: ?>
                                    <i data-lucide="user"></i>
                                <?php endif; ?>
                            </div>

                            <div class="candidate-main">
                                <div class="candidate-top">
                                    <div class="candidate-name-area">
                                        <h3 class="candidate-name"><?php echo htmlspecialchars($candidatura['nome_completo']); ?></h3>
                                        <div class="candidate-date">
                                            <i data-lucide="calendar"></i>
                                            Candidatura enviada em <?php echo date('d/m/Y', strtotime($candidatura['data_candidatura'])); ?>
                                        </div>
                                    </div>
                                    <span class="status-badge badge-<?php echo $candidatura['estado']; ?>">
                                        <i data-lucide="<?php 
                                            echo $candidatura['estado'] === 'submetida' ? 'send' : 
                                                ($candidatura['estado'] === 'em_analise' ? 'eye' : 
                                                ($candidatura['estado'] === 'entrevista' ? 'calendar' : 
                                                ($candidatura['estado'] === 'rejeitada' ? 'x-circle' : 'check-circle'))); 
                                        ?>"></i>
                                        <?php echo traduzirEstado($candidatura['estado']); ?>
                                    </span>
                                </div>

                                <div class="candidate-meta">
                                    <?php if (!empty($candidatura['localizacao'])): ?>
                                        <div class="meta-item">
                                            <i data-lucide="map-pin"></i>
                                            <?php echo htmlspecialchars($candidatura['localizacao']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($candidatura['telefone'])): ?>
                                        <div class="meta-item">
                                            <i data-lucide="phone"></i>
                                            <?php echo htmlspecialchars($candidatura['telefone']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($candidatura['cv_pdf'])): ?>
                                        <div class="meta-item">
                                            <i data-lucide="file-text"></i>
                                            CV Anexado
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($candidatura['competencias'])): ?>
                            <div class="candidate-skills">
                                <div class="skills-title">
                                    <i data-lucide="award"></i>
                                    Competências
                                </div>
                                <div class="skills-tags">
                                    <?php
                                    $competencias = explode(',', $candidatura['competencias']);
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

                        <?php if (!empty($candidatura['carta_apresentacao'])): ?>
                            <div class="candidate-letter">
                                <div class="letter-title">
                                    <i data-lucide="mail"></i>
                                    Carta de Apresentação
                                </div>
                                <div class="letter-text"><?php echo nl2br(htmlspecialchars($candidatura['carta_apresentacao'])); ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="candidate-actions">
                            <a href="ver_candidato.php?id=<?php echo $candidatura['candidato_id']; ?>&candidatura_id=<?php echo $candidatura['id']; ?>" 
                               class="btn-action">
                                <i data-lucide="user"></i>
                                Ver Perfil Completo
                            </a>

                            <?php if (!empty($candidatura['cv_pdf'])): ?>
                                <a href="../uploads/cv/<?php echo htmlspecialchars($candidatura['cv_pdf']); ?>" 
                                   target="_blank" class="btn-action">
                                    <i data-lucide="download"></i>
                                    Baixar CV
                                </a>
                            <?php endif; ?>

                            <form method="POST" class="status-selector form-mudar-estado"
                                  data-candidato="<?php echo htmlspecialchars($candidatura['nome_completo']); ?>">
                                <input type="hidden" name="candidatura_id" value="<?php echo $candidatura['id']; ?>">
                                <input type="hidden" name="acao" value="mudar_estado">
                                <select name="estado" class="status-select">
                                    <option value="submetida" <?php echo $candidatura['estado'] === 'submetida' ? 'selected' : ''; ?>>Submetida</option>
                                    <option value="em_analise" <?php echo $candidatura['estado'] === 'em_analise' ? 'selected' : ''; ?>>Em Análise</option>
                                    <option value="entrevista" <?php echo $candidatura['estado'] === 'entrevista' ? 'selected' : ''; ?>>Entrevista</option>
                                    <option value="rejeitada" <?php echo $candidatura['estado'] === 'rejeitada' ? 'selected' : ''; ?>>Rejeitada</option>
                                    <option value="contratado" <?php echo $candidatura['estado'] === 'contratado' ? 'selected' : ''; ?>>Contratado</option>
                                </select>
                                <button type="submit" class="btn-action" style="background: var(--success); color: var(--white); border-color: var(--success);">
                                    <i data-lucide="check"></i>
                                    Atualizar
                                </button>
                            </form>

                            <button class="btn-action" onclick="openNotaModal(<?php echo $candidatura['id']; ?>, '<?php echo htmlspecialchars($candidatura['nota_interna'] ?? '', ENT_QUOTES); ?>')">
                                <i data-lucide="edit"></i>
                                <?php echo !empty($candidatura['nota_interna']) ? 'Editar Nota' : 'Adicionar Nota'; ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- ==========================================
         🦶 FOOTER - CONSISTENT WITH INDEX.PHP
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
         📋 MODAL NOTA
    ========================================== -->
    <div id="notaModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i data-lucide="edit"></i>
                    Adicionar/Editar Nota
                </h3>
                <button class="modal-close" onclick="closeNotaModal()">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="candidatura_id" id="nota_candidatura_id">
                    <input type="hidden" name="acao" value="adicionar_nota">
                    <div class="form-group">
                        <label class="form-label">Nota interna sobre o candidato:</label>
                        <textarea name="nota" id="nota_textarea" class="form-textarea" 
                                  placeholder="Adicione observações internas sobre este candidato..."></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-primary" style="padding: 12px 24px;">
                            <i data-lucide="save"></i>
                            Salvar Nota
                        </button>
                        <button type="button" class="btn-outline" style="padding: 12px 24px;" onclick="closeNotaModal()">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
        function openNotaModal(candidaturaId, notaAtual) {
            document.getElementById('nota_candidatura_id').value = candidaturaId;
            document.getElementById('nota_textarea').value = notaAtual;
            document.getElementById('notaModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Reinitialize icons in modal
            setTimeout(() => {
                lucide.createIcons();
            }, 100);
        }

        function closeNotaModal() {
            document.getElementById('notaModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close modal when clicking outside
        document.getElementById('notaModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNotaModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('notaModal');
                if (modal.classList.contains('active')) {
                    closeNotaModal();
                }
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
                // Formulários de mudar estado da candidatura
                document.querySelectorAll('.form-mudar-estado').forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const select = this.querySelector('select[name=estado]');
                        const newState = select.options[select.selectedIndex].text;
                        const candidato = this.getAttribute('data-candidato');
                        const formElement = this;
                        
                        if (typeof window.confirmModal === 'function') {
                            window.confirmModal({
                                type: 'info',
                                title: 'Alterar Estado da Candidatura',
                                message: 'Tem certeza que deseja alterar o estado desta candidatura?',
                                highlightTitle: 'Candidato: ' + candidato,
                                highlightText: 'Novo estado: ' + newState,
                                confirmText: 'Sim, Alterar Estado',
                                confirmIcon: 'check-circle',
                                onConfirm: function() { 
                                    formElement.submit(); 
                                }
                            });
                        } else {
                            if (confirm('Alterar estado da candidatura de ' + candidato + '?')) {
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
