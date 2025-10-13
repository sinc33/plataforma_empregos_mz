<?php
session_start();
require_once 'config/db.php';

 $pdo = getPDO();

// Verificar se o ID da vaga foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: vagas.php");
    exit;
}

 $vaga_id = (int)$_GET['id'];

// Buscar informações completas da vaga
 $sql_vaga = "SELECT v.*, e.nome_empresa, e.logotipo, e.descricao as descricao_empresa, 
                    e.website, e.localizacao as localizacao_empresa,
                    (SELECT COUNT(*) FROM candidatura c WHERE c.vaga_id = v.id) as total_candidaturas
             FROM vaga v 
             JOIN empresa e ON v.empresa_id = e.id 
             WHERE v.id = ? AND v.ativa = TRUE AND v.data_expiracao >= CURDATE()";

 $stmt = $pdo->prepare($sql_vaga);
 $stmt->execute([$vaga_id]);
 $vaga = $stmt->fetch();

// Se vaga não existe ou está inativa
if (!$vaga) {
    header("Location: vagas.php");
    exit;
}

// Verificar se o usuário atual já se candidatou a esta vaga
 $ja_candidatado = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'candidato') {
    $sql_candidatura = "SELECT id FROM candidatura WHERE vaga_id = ? AND candidato_id = ?";
    $stmt = $pdo->prepare($sql_candidatura);
    $stmt->execute([$vaga_id, $_SESSION['user_id']]);
    $ja_candidatado = (bool)$stmt->fetch();
}

// Buscar vagas similares (mesma área ou localização)
 $sql_similares = "SELECT v.*, e.nome_empresa 
                  FROM vaga v 
                  JOIN empresa e ON v.empresa_id = e.id 
                  WHERE v.id != ? AND v.ativa = TRUE AND v.data_expiracao >= CURDATE()
                  AND (v.area = ? OR v.localizacao = ?)
                  ORDER BY v.data_publicacao DESC 
                  LIMIT 4";

 $stmt = $pdo->prepare($sql_similares);
 $stmt->execute([$vaga_id, $vaga['area'], $vaga['localizacao']]);
 $vagas_similares = $stmt->fetchAll();

// Processar candidatura se o formulário foi submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidatar'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidato') {
        $_SESSION['redirect_after_login'] = "vaga_detalhe.php?id=$vaga_id";
        header("Location: auth/login.php");
        exit;
    }
    
    if ($ja_candidatado) {
        $erro = "Você já se candidatou a esta vaga.";
    } else {
        $carta_apresentacao = trim($_POST['carta_apresentacao'] ?? '');
        
        try {
            $sql_inserir = "INSERT INTO candidatura (vaga_id, candidato_id, carta_apresentacao) 
                           VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql_inserir);
            $stmt->execute([$vaga_id, $_SESSION['user_id'], $carta_apresentacao]);
            
            $sucesso = "Candidatura enviada com sucesso!";
            $ja_candidatado = true;
            
        } catch (PDOException $e) {
            $erro = "Erro ao enviar candidatura: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($vaga['titulo']); ?> - Emprego MZ</title>
    
    <!-- Google Fonts - Ubuntu Moderno -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
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
           🌅 HERO SECTION - Pôr do Sol de Maputo
        ============================================ */
        .vaga-header {
            background: var(--gradient-oceano);
            color: var(--branco-puro);
            padding: var(--space-xl) var(--space-md);
            position: relative;
            overflow: hidden;
            margin-top: 0;
        }

        /* Padrão Capulana Sutil no Background */
        .vaga-header::before {
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

        .vaga-container {
            position: relative;
            z-index: 2;
            max-width: 1000px;
            margin: 0 auto;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            margin-bottom: var(--space-md);
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .breadcrumb a {
            color: var(--branco-puro);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            transition: opacity 0.3s;
        }

        .breadcrumb a:hover {
            opacity: 0.8;
        }

        .vaga-title {
            font-family: var(--font-heading);
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 700;
            margin-bottom: var(--space-sm);
        }

        .vaga-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-md);
            font-size: 1.1rem;
            margin-bottom: var(--space-md);
        }

        .vaga-meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        /* ============================================
           🌊 OCEAN DIVIDER - Elemento Assinatura
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
        .vaga-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 var(--space-md);
        }

        .vaga-content {
            padding: var(--space-xl) 0;
        }

        /* ============================================
           📋 VAGA INFO GRID
        ============================================ */
        .vaga-info-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        /* ============================================
           🎴 XIMA CARD - Conteúdo Principal
        ============================================ */
        .vaga-detalhes {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-lg);
            box-shadow: var(--shadow-medium);
            position: relative;
            overflow: hidden;
        }

        /* Padrão Capulana muito sutil no card */
        .vaga-detalhes::before {
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

        .section-title {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--carvao);
            margin-bottom: var(--space-md);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .vaga-descricao {
            line-height: 1.7;
            white-space: pre-wrap;
            margin-bottom: var(--space-lg);
        }

        /* ============================================
           🎛️ SIDEBAR - Informações da Vaga
        ============================================ */
        .vaga-sidebar {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-lg);
            box-shadow: var(--shadow-medium);
            position: sticky;
            top: var(--space-md);
            height: fit-content;
        }

        .empresa-info {
            text-align: center;
            margin-bottom: var(--space-lg);
            padding-bottom: var(--space-md);
            border-bottom: 2px solid var(--areia-quente);
        }

        .empresa-logo {
            width: 100px;
            height: 100px;
            border-radius: 16px;
            background: var(--gradient-terra);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-md);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .empresa-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 16px;
        }

        .empresa-nome {
            font-family: var(--font-heading);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--azul-indico);
        }

        .info-item {
            margin-bottom: var(--space-md);
            padding-bottom: var(--space-md);
            border-bottom: 1px solid var(--areia-quente);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--carvao);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            margin-bottom: var(--space-xs);
            font-size: 0.95rem;
        }

        .info-value {
            color: var(--cinza-baobab);
            font-size: 1rem;
        }

        .info-value.highlight {
            font-weight: 700;
            color: var(--verde-esperanca);
            font-size: 1.1rem;
        }

        /* ============================================
           🔘 BUTTONS - Sistema de Botões
        ============================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-xs);
            padding: var(--space-md) var(--space-lg);
            border-radius: 50px;
            font-size: 1.05rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-soft);
            width: 100%;
        }

        .btn-primary {
            background: var(--gradient-oceano);
            color: var(--branco-puro);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-primary:disabled {
            background: var(--cinza-baobab);
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: var(--branco-puro);
            color: var(--azul-indico);
            border: 2px solid var(--azul-indico);
        }

        .btn-secondary:hover {
            background: var(--azul-indico);
            color: var(--branco-puro);
            transform: translateY(-2px);
        }

        .btn-group {
            display: flex;
            gap: var(--space-sm);
            margin-top: var(--space-md);
        }

        /* ============================================
           📝 FORMULÁRIOS
        ============================================ */
        .form-group {
            margin-bottom: var(--space-md);
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--carvao);
            margin-bottom: var(--space-xs);
        }

        .form-control {
            width: 100%;
            padding: var(--space-sm);
            border: 2px solid var(--areia-quente);
            border-radius: 12px;
            font-size: 1rem;
            font-family: var(--font-body);
            transition: all 0.3s;
            background: var(--branco-marfim);
            resize: vertical;
            min-height: 120px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--verde-esperanca);
            background: var(--branco-puro);
        }

        /* ============================================
           🎭 ALERTAS
        ============================================ */
        .alert {
            padding: var(--space-md);
            border-radius: 12px;
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .alert-success {
            background: rgba(43, 122, 75, 0.1);
            color: var(--verde-esperanca);
            border-left: 4px solid var(--verde-esperanca);
        }

        .alert-danger {
            background: rgba(255, 107, 107, 0.1);
            color: var(--coral-vivo);
            border-left: 4px solid var(--coral-vivo);
        }

        .alert-info {
            background: rgba(30, 58, 95, 0.1);
            color: var(--azul-indico);
            border-left: 4px solid var(--azul-indico);
        }

        /* ============================================
           🏷️ BADGES
        ============================================ */
        .badge-container {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-xs);
            margin: var(--space-md) 0;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-primary {
            background: linear-gradient(135deg, rgba(30, 58, 95, 0.15), rgba(30, 58, 95, 0.08));
            color: var(--azul-indico);
        }

        .badge-success {
            background: linear-gradient(135deg, rgba(43, 122, 75, 0.15), rgba(43, 122, 75, 0.08));
            color: var(--verde-esperanca);
        }

        .badge-warning {
            background: linear-gradient(135deg, rgba(255, 176, 59, 0.15), rgba(255, 176, 59, 0.08));
            color: #cc8a2e;
        }

        /* ============================================
           🔍 VAGAS SIMILARES
        ============================================ */
        .vagas-similares {
            margin-top: var(--space-xl);
        }

        .vaga-similar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-lg);
            margin-top: var(--space-lg);
        }

        .vaga-similar-card {
            background: var(--branco-puro);
            border-radius: 16px;
            padding: var(--space-md);
            box-shadow: var(--shadow-soft);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
        }

        .vaga-similar-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: var(--dourado-sol);
        }

        .vaga-similar-title {
            font-family: var(--font-heading);
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: var(--space-xs);
            line-height: 1.3;
        }

        .vaga-similar-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s;
        }

        .vaga-similar-title a:hover {
            color: var(--verde-esperanca);
        }

        .vaga-similar-meta {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
            margin-bottom: var(--space-sm);
            font-size: 0.9rem;
            color: var(--cinza-baobab);
        }

        .vaga-similar-meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .vaga-similar-salary {
            font-weight: 700;
            color: var(--verde-esperanca);
            margin-top: var(--space-sm);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        /* ============================================
           📱 NAVIGATION
        ============================================ */
        .page-navigation {
            text-align: center;
            margin: var(--space-xl) 0;
            padding-top: var(--space-lg);
            border-top: 1px solid var(--areia-quente);
        }

        .nav-links {
            display: flex;
            justify-content: center;
            gap: var(--space-lg);
            flex-wrap: wrap;
        }

        .nav-link {
            color: var(--azul-indico);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            transition: color 0.3s;
        }

        .nav-link:hover {
            color: var(--verde-esperanca);
        }

        /* ============================================
           📱 RESPONSIVE DESIGN
        ============================================ */
        @media (max-width: 768px) {
            .vaga-header {
                padding: var(--space-lg) var(--space-md);
            }

            .vaga-info-grid {
                grid-template-columns: 1fr;
            }

            .vaga-sidebar {
                position: static;
            }

            .vaga-meta {
                flex-direction: column;
                gap: var(--space-sm);
            }

            .btn-group {
                flex-direction: column;
            }

            .vaga-similar-grid {
                grid-template-columns: 1fr;
            }

            .nav-links {
                flex-direction: column;
                gap: var(--space-md);
            }
        }

        /* ============================================
           ✨ MICRO-ANIMATIONS
        ============================================ */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .floating {
            animation: float 3s ease-in-out infinite;
        }

        /* Smooth scroll */
        html {
            scroll-behavior: smooth;
        }

        /* Selection colors */
        ::selection {
            background: var(--dourado-sol);
            color: var(--carvao);
        }

        /* Foco melhorado para acessibilidade */
        .btn:focus, .form-control:focus, .nav-link:focus {
            outline: 3px solid var(--dourado-sol);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <!-- INCLUDE DO HEADER -->
    <?php include 'includes/header.php'; ?>

    <!-- 🌅 VAGA HEADER -->
    <div class="vaga-header">
        <div class="vaga-container">
            <nav class="breadcrumb">
                <a href="index.php">
                    <i data-lucide="home" style="width: 16px; height: 16px;"></i>
                    Início
                </a>
                <span>/</span>
                <a href="vagas.php">
                    <i data-lucide="briefcase" style="width: 16px; height: 16px;"></i>
                    Vagas
                </a>
                <span>/</span>
                <span><?php echo htmlspecialchars($vaga['titulo']); ?></span>
            </nav>
            
            <h1 class="vaga-title"><?php echo htmlspecialchars($vaga['titulo']); ?></h1>
            
            <div class="vaga-meta">
                <div class="vaga-meta-item">
                    <i data-lucide="building" style="width: 20px; height: 20px;"></i>
                    <?php echo htmlspecialchars($vaga['nome_empresa']); ?>
                </div>
                <div class="vaga-meta-item">
                    <i data-lucide="map-pin" style="width: 20px; height: 20px;"></i>
                    <?php echo htmlspecialchars($vaga['localizacao']); ?>
                </div>
                <div class="vaga-meta-item">
                    <i data-lucide="tag" style="width: 20px; height: 20px;"></i>
                    <?php echo htmlspecialchars($vaga['area']); ?>
                </div>
                <div class="vaga-meta-item">
                    <i data-lucide="users" style="width: 20px; height: 20px;"></i>
                    <?php echo $vaga['total_candidaturas']; ?> candidatura(s)
                </div>
            </div>
        </div>
    </div>

    <!-- 🌊 OCEAN DIVIDER -->
    <div class="ocean-divider">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                  style="fill: url(#gradient-oceano); opacity: 0.8;"></path>
            <defs>
                <linearGradient id="gradient-oceano" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" style="stop-color:#1E3A5F;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#2B7A4B;stop-opacity:1" />
                </linearGradient>
            </defs>
        </svg>
    </div>

    <div class="vaga-container">
        <div class="vaga-content">
            <!-- Mensagens de Sucesso/Erro -->
            <?php if (isset($sucesso)): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
                    <?php echo htmlspecialchars($sucesso); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($erro)): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
                    <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>

            <div class="vaga-info-grid">
                <!-- Conteúdo Principal -->
                <div class="vaga-detalhes">
                    <h2 class="section-title">
                        <i data-lucide="file-text" style="width: 24px; height: 24px;"></i>
                        Descrição da Vaga
                    </h2>
                    <div class="vaga-descricao"><?php echo htmlspecialchars($vaga['descricao']); ?></div>

                    <?php if ($vaga['nivel_experiencia']): ?>
                        <h3 class="section-title">
                            <i data-lucide="award" style="width: 24px; height: 24px;"></i>
                            Requisitos e Qualificações
                        </h3>
                        <div class="info-item">
                            <span class="info-label">
                                <i data-lucide="trending-up" style="width: 16px; height: 16px;"></i>
                                Nível de Experiência
                            </span>
                            <span class="info-value"><?php echo htmlspecialchars($vaga['nivel_experiencia']); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Informações da Empresa -->
                    <h3 class="section-title">
                        <i data-lucide="building" style="width: 24px; height: 24px;"></i>
                        Sobre a Empresa
                    </h3>
                    <div class="vaga-descricao">
                        <?php echo htmlspecialchars($vaga['descricao_empresa'] ?? 'Informação da empresa não disponível.'); ?>
                    </div>
                    
                    <?php if ($vaga['website']): ?>
                        <div style="margin-top: var(--space-md);">
                            <div class="info-label">
                                <i data-lucide="globe" style="width: 16px; height: 16px;"></i>
                                Website
                            </div>
                            <a href="<?php echo htmlspecialchars($vaga['website']); ?>" target="_blank" 
                               style="color: var(--azul-indico); text-decoration: none; display: flex; align-items: center; gap: var(--space-xs);">
                                <?php echo htmlspecialchars($vaga['website']); ?>
                                <i data-lucide="external-link" style="width: 14px; height: 14px;"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="vaga-sidebar">
                    <!-- Informações da Empresa -->
                    <div class="empresa-info">
                        <div class="empresa-logo">
                            <?php if ($vaga['logotipo']): ?>
                                <img src="<?php echo htmlspecialchars($vaga['logotipo']); ?>" 
                                     alt="<?php echo htmlspecialchars($vaga['nome_empresa']); ?>">
                            <?php else: ?>
                                <i data-lucide="building" style="width: 48px; height: 48px; color: var(--azul-indico);"></i>
                            <?php endif; ?>
                        </div>
                        <h3 class="empresa-nome"><?php echo htmlspecialchars($vaga['nome_empresa']); ?></h3>
                    </div>

                    <!-- Detalhes da Vaga -->
                    <div class="info-item">
                        <span class="info-label">
                            <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i>
                            Localização
                        </span>
                        <span class="info-value"><?php echo htmlspecialchars($vaga['localizacao']); ?></span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">
                            <i data-lucide="tag" style="width: 16px; height: 16px;"></i>
                            Área
                        </span>
                        <span class="info-value"><?php echo htmlspecialchars($vaga['area']); ?></span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">
                            <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                            Tipo de Contrato
                        </span>
                        <span class="info-value">
                            <?php 
                                echo [
                                    'tempo_inteiro' => 'Tempo Inteiro',
                                    'tempo_parcial' => 'Tempo Parcial', 
                                    'estagio' => 'Estágio',
                                    'freelance' => 'Freelance'
                                ][$vaga['tipo_contrato']] ?? $vaga['tipo_contrato'];
                            ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">
                            <i data-lucide="monitor" style="width: 16px; height: 16px;"></i>
                            Modalidade
                        </span>
                        <span class="info-value">
                            <?php 
                                echo [
                                    'presencial' => 'Presencial',
                                    'hibrido' => 'Híbrido',
                                    'remoto' => 'Remoto'
                                ][$vaga['modalidade']] ?? $vaga['modalidade'];
                            ?>
                        </span>
                    </div>

                    <?php if ($vaga['salario_estimado']): ?>
                        <div class="info-item">
                            <span class="info-label">
                                <i data-lucide="dollar-sign" style="width: 16px; height: 16px;"></i>
                                Salário Estimado
                            </span>
                            <span class="info-value highlight">
                                <?php echo number_format($vaga['salario_estimado'], 0, ',', ' '); ?> MT
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="info-item">
                        <span class="info-label">
                            <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                            Publicada em
                        </span>
                        <span class="info-value"><?php echo date('d/m/Y', strtotime($vaga['data_publicacao'])); ?></span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">
                            <i data-lucide="hourglass" style="width: 16px; height: 16px;"></i>
                            Expira em
                        </span>
                        <span class="info-value"><?php echo date('d/m/Y', strtotime($vaga['data_expiracao'])); ?></span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">
                            <i data-lucide="users" style="width: 16px; height: 16px;"></i>
                            Candidaturas
                        </span>
                        <span class="info-value"><?php echo $vaga['total_candidaturas']; ?> pessoa(s)</span>
                    </div>

                    <!-- Botão de Candidatura -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['user_type'] === 'candidato'): ?>
                            <?php if (!$ja_candidatado): ?>
                                <form method="POST">
                                    <div class="form-group">
                                        <label for="carta_apresentacao" class="form-label">
                                            <i data-lucide="mail" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"></i>
                                            Carta de Apresentação (Opcional)
                                        </label>
                                        <textarea name="carta_apresentacao" id="carta_apresentacao" 
                                                  class="form-control" 
                                                  placeholder="Escreva uma carta de apresentação para esta vaga..."></textarea>
                                    </div>
                                    <button type="submit" name="candidatar" class="btn btn-primary">
                                        <i data-lucide="send" style="width: 18px; height: 18px;"></i>
                                        Candidatar-se a Esta Vaga
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-primary" disabled>
                                    <i data-lucide="check-circle" style="width: 18px; height: 18px;"></i>
                                    Já Candidatado
                                </button>
                                <p style="text-align: center; color: var(--cinza-baobab); margin-top: var(--space-sm);">
                                    Você já se candidatou a esta vaga.
                                </p>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i data-lucide="bar-chart" style="width: 20px; height: 20px;"></i>
                                <div>
                                    <strong>Área da Empresa</strong><br>
                                    Acesse o dashboard para gerir candidaturas.
                                </div>
                            </div>
                            <a href="empresa/dashboard.php" class="btn btn-secondary">
                                <i data-lucide="bar-chart" style="width: 18px; height: 18px;"></i>
                                Ir para Dashboard
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i data-lucide="user" style="width: 20px; height: 20px;"></i>
                            <div>
                                Faça login como candidato para se candidatar a esta vaga.
                            </div>
                        </div>
                        <div class="btn-group">
                            <a href="auth/login.php" class="btn btn-secondary">
                                <i data-lucide="log-in" style="width: 18px; height: 18px;"></i>
                                Login
                            </a>
                            <a href="auth/register.php" class="btn btn-primary">
                                <i data-lucide="user-plus" style="width: 18px; height: 18px;"></i>
                                Registrar
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vagas Similares -->
            <?php if (!empty($vagas_similares)): ?>
                <div class="vagas-similares">
                    <h2 class="section-title">
                        <i data-lucide="search" style="width: 24px; height: 24px;"></i>
                        Vagas Similares
                    </h2>
                    <div class="vaga-similar-grid">
                        <?php foreach ($vagas_similares as $vaga_similar): ?>
                            <div class="vaga-similar-card">
                                <h4 class="vaga-similar-title">
                                    <a href="vaga_detalhe.php?id=<?php echo $vaga_similar['id']; ?>">
                                        <?php echo htmlspecialchars($vaga_similar['titulo']); ?>
                                    </a>
                                </h4>
                                <div class="vaga-similar-meta">
                                    <div class="vaga-similar-meta-item">
                                        <i data-lucide="building" style="width: 14px; height: 14px;"></i>
                                        <?php echo htmlspecialchars($vaga_similar['nome_empresa']); ?>
                                    </div>
                                    <div class="vaga-similar-meta-item">
                                        <i data-lucide="map-pin" style="width: 14px; height: 14px;"></i>
                                        <?php echo htmlspecialchars($vaga_similar['localizacao']); ?>
                                    </div>
                                    <div class="vaga-similar-meta-item">
                                        <i data-lucide="tag" style="width: 14px; height: 14px;"></i>
                                        <?php echo htmlspecialchars($vaga_similar['area']); ?>
                                    </div>
                                </div>
                                <?php if ($vaga_similar['salario_estimado']): ?>
                                    <div class="vaga-similar-salary">
                                        <i data-lucide="dollar-sign" style="width: 16px; height: 16px;"></i>
                                        <?php echo number_format($vaga_similar['salario_estimado'], 0, ',', ' '); ?> MT
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Navegação -->
            <div class="page-navigation">
                <div class="nav-links">
                    <a href="vagas.php" class="nav-link">
                        <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
                        Voltar para Todas as Vagas
                    </a>
                    <a href="index.php" class="nav-link">
                        <i data-lucide="home" style="width: 16px; height: 16px;"></i>
                        Página Inicial
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 🌊 OCEAN DIVIDER -->
    <div class="ocean-divider">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none" transform="rotate(180)">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                  style="fill: url(#gradient-oceano); opacity: 0.8;"></path>
        </svg>
    </div>

    <!-- INCLUDE DO FOOTER -->
    <?php include 'includes/footer.php'; ?>

    <!-- ✨ MICRO-INTERACTION SCRIPT -->
    <script>
        // Inicializar ícones Lucide
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            
            // Animação de entrada dos elementos
            const animateElements = document.querySelectorAll('.vaga-detalhes, .vaga-sidebar, .vaga-similar-card');
            
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

            // Efeito parallax suave no header
            window.addEventListener('scroll', () => {
                const header = document.querySelector('.vaga-header');
                const scrolled = window.pageYOffset;
                if (header && scrolled < 500) {
                    header.style.transform = `translateY(${scrolled * 0.3}px)`;
                    header.style.opacity = 1 - (scrolled / 500);
                }
            });

            // Adicionar efeito de onda ao clicar nos botões
            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;

                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.style.position = 'absolute';
                    ripple.style.borderRadius = '50%';
                    ripple.style.background = 'rgba(255, 255, 255, 0.5)';
                    ripple.style.transform = 'scale(0)';
                    ripple.style.animation = 'ripple 0.6s ease-out';
                    ripple.style.pointerEvents = 'none';

                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);

                    setTimeout(() => ripple.remove(), 600);
                });
            });

            // Animação CSS para ripple
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>