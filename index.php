<?php
session_start();
require_once 'config/db.php';

// ==========================================
// 📊 ESTATÍSTICAS REAIS DO BANCO DE DADOS
// ==========================================

// Total de vagas ativas
$sql_vagas = "SELECT COUNT(*) as total FROM vaga WHERE ativa = TRUE AND data_expiracao >= CURDATE()";
$stmt = $pdo->prepare($sql_vagas);
$stmt->execute();
$total_vagas = $stmt->fetchColumn();

// Total de empresas
$sql_empresas = "SELECT COUNT(*) as total FROM empresa";
$stmt = $pdo->prepare($sql_empresas);
$stmt->execute();
$total_empresas = $stmt->fetchColumn();

// Total de candidatos
$sql_candidatos = "SELECT COUNT(*) as total FROM candidato";
$stmt = $pdo->prepare($sql_candidatos);
$stmt->execute();
$total_candidatos = $stmt->fetchColumn();

// Vagas publicadas nos últimos 7 dias
$sql_vagas_semana = "SELECT COUNT(*) as total FROM vaga 
                     WHERE ativa = TRUE AND data_publicacao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
$stmt = $pdo->prepare($sql_vagas_semana);
$stmt->execute();
$vagas_ultimos_7_dias = $stmt->fetchColumn();

// Buscar vagas recentes para exibir
$sql_vagas_recentes = "SELECT v.*, e.nome_empresa, e.logotipo,
                       DATEDIFF(v.data_expiracao, CURDATE()) as dias_restantes,
                       DATEDIFF(CURDATE(), v.data_publicacao) as dias_publicada
                       FROM vaga v 
                       JOIN empresa e ON v.empresa_id = e.id 
                       WHERE v.ativa = TRUE AND data_expiracao >= CURDATE()
                       ORDER BY v.data_publicacao DESC 
                       LIMIT 6";

$stmt = $pdo->prepare($sql_vagas_recentes);
$stmt->execute();
$vagas_recentes = $stmt->fetchAll();

// Buscar vagas guardadas do usuário (se logado como candidato)
$vagas_guardadas_ids = [];
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'candidato') {
    $sql_guardadas = "SELECT vaga_id FROM vaga_guardada WHERE candidato_id = ?";
    $stmt_guardadas = $pdo->prepare($sql_guardadas);
    $stmt_guardadas->execute([$_SESSION['user_id']]);
    $vagas_guardadas_ids = $stmt_guardadas->fetchAll(PDO::FETCH_COLUMN);
}

// Contagem de vagas por categoria
$sql_vagas_por_area = "SELECT area, COUNT(*) as total FROM vaga 
                       WHERE ativa = TRUE AND data_expiracao >= CURDATE()
                       GROUP BY area";
$stmt = $pdo->prepare($sql_vagas_por_area);
$stmt->execute();
$vagas_por_area_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Áreas principais com mapeamento para as áreas reais do banco
$areas_principais = [
    [
        'nome' => 'Comercial/Vendas', 
        'icone' => 'shopping-bag', 
        'imagem' => 'comercial.jpg',
        'area_db' => 'Comercial e Vendas' // Mapeamento para o banco
    ],
    [
        'nome' => 'Administrativa', 
        'icone' => 'briefcase', 
        'imagem' => 'administrativa.jpg',
        'area_db' => 'Administrativa'
    ],
    [
        'nome' => 'Serviços Gerais', 
        'icone' => 'tool', 
        'imagem' => 'servicos-gerais.jpg',
        'area_db' => 'Operações e Logística'
    ],
    [
        'nome' => 'Logística', 
        'icone' => 'truck', 
        'imagem' => 'logistica.jpg',
        'area_db' => 'Operações e Logística'
    ],
    [
        'nome' => 'Informática/TI', 
        'icone' => 'laptop', 
        'imagem' => 'informatica.jpg',
        'area_db' => 'Tecnologia da Informação'
    ],
    [
        'nome' => 'Saúde', 
        'icone' => 'heart-pulse', 
        'imagem' => 'saude.jpg',
        'area_db' => 'Saúde' // Área sem vagas atualmente
    ],
    [
        'nome' => 'Finanças', 
        'icone' => 'wallet', 
        'imagem' => 'financas.jpg',
        'area_db' => 'Financeira'
    ],
    [
        'nome' => 'Industrial', 
        'icone' => 'factory', 
        'imagem' => 'industrial.jpg',
        'area_db' => 'Engenharia'
    ],
    [
        'nome' => 'Construção Civil', 
        'icone' => 'hard-hat', 
        'imagem' => 'construcao.jpg',
        'area_db' => 'Engenharia'
    ],
    [
        'nome' => 'Gastronomia', 
        'icone' => 'utensils', 
        'imagem' => 'gastronomia.jpg',
        'area_db' => 'Gastronomia' // Área sem vagas atualmente
    ]
];

// Províncias de Moçambique
$provincias = [
    ['nome' => 'Maputo', 'slug' => 'maputo'],
    ['nome' => 'Gaza', 'slug' => 'gaza'],
    ['nome' => 'Inhambane', 'slug' => 'inhambane'],
    ['nome' => 'Sofala', 'slug' => 'sofala'],
    ['nome' => 'Manica', 'slug' => 'manica'],
    ['nome' => 'Tete', 'slug' => 'tete'],
    ['nome' => 'Zambézia', 'slug' => 'zambezia'],
    ['nome' => 'Nampula', 'slug' => 'nampula'],
    ['nome' => 'Cabo Delgado', 'slug' => 'cabo-delgado'],
    ['nome' => 'Niassa', 'slug' => 'niassa'],
    ['nome' => 'Remoto', 'slug' => 'remoto']
];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emprego MZ - Encontre sua vaga ideal em Moçambique</title>
    <meta name="description" content="A maior plataforma de empregos de Moçambique. <?php echo number_format($total_vagas); ?>+ vagas ativas em todas as províncias.">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        /* ==========================================
           🎨 RESET & VARIABLES
        ========================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* === 🎨 PALETA DEFINITIVA PROFISSIONAL === */
            
            /* 🔵 CORES PRINCIPAIS */
            --cor-primaria: #14213d;              /* Oxford Blue - Headers, Navigation, Botões Principais */
            --cor-primaria-escura: #0f1a2e;       /* Oxford Blue Dark - Hover em botões primários */
            --cor-primaria-clara: #1e2c47;        /* Oxford Blue Light - Elementos secundários */
            --cor-primaria-alpha: rgba(20, 33, 61, 0.1);
            
            /* 🟠 CORES SECUNDÁRIAS */
            --cor-secundaria: #fca311;            /* Orange Web - CTAs, Links, Destaques */
            --cor-secundaria-hover: #e3940f;      /* Orange Hover - Hover em botões secundários */
            --cor-secundaria-clara: #fdb541;      /* Orange Light */
            --cor-secundaria-muito-clara: #fec871; /* Orange Very Light */
            --cor-secundaria-alpha: rgba(252, 163, 17, 0.1);
            
            /* 🌫️ HIERARQUIA VISUAL - Soft Gray System */
            --cor-fundo: #f8fafc;                 /* Soft Gray - Fundo geral da página */
            --cor-cards: #ffffff;                 /* White - Cards, containers, formulários */
            --cor-acento: #f1f5ff;                /* Light Blue - Hover states, seções alternadas */
            --cor-acento-escuro: #e1ebff;         /* Light Blue Dark - Bordas e detalhes */
            
            /* 📝 TEXTO - Hierarquia Refinada */
            --cor-texto: #1a202c;                 /* Texto principal */
            --cor-texto-claro: #64748b;           /* Texto secundário */
            --cor-texto-muito-claro: #94a3b8;     /* Texto placeholder */
            
            /* 🎯 BORDAS - Sistema Refinado */
            --cor-borda: #e2e8f0;                 /* Bordas padrão */
            --cor-borda-clara: #f1f5f9;           /* Bordas suaves */
            
            /* ✅ ESTADOS */
            --cor-sucesso: #10B981;               /* Success messages */
            --cor-erro: #EF4444;                  /* Error messages */
            --cor-aviso: #F59E0B;                 /* Warning messages */
            --cor-info: #14213d;                  /* Info messages */
            
            /* 🌑 SOMBRAS - Profissionais com Oxford Blue */
            --sombra-suave: 0 1px 3px rgba(20, 33, 61, 0.08);
            --sombra-media: 0 4px 12px rgba(20, 33, 61, 0.12);
            --sombra-forte: 0 8px 24px rgba(20, 33, 61, 0.15);
            
            /* 🔄 Aliases para compatibilidade */
            --primary: var(--cor-primaria);
            --primary-dark: var(--cor-primaria-escura);
            --primary-light: var(--cor-primaria-clara);
            --secondary: var(--cor-secundaria);
            --secondary-dark: var(--cor-secundaria-hover);
            
            /* Texto */
            --text: var(--cor-texto);
            --text-light: var(--cor-texto-claro);
            --text-lighter: var(--cor-texto-muito-claro);
            --gray-900: var(--cor-texto);
            --gray-800: #333333;
            --gray-700: var(--cor-texto-claro);
            --gray-600: var(--cor-texto-muito-claro);
            --gray-500: #8a8a8a;
            --gray-400: #b8b8b8;
            --gray-300: #cbd5e0;
            --gray-200: var(--cor-borda);
            --gray-100: var(--cor-borda-clara);
            --gray-50: var(--cor-fundo);
            
            /* Backgrounds e Bordas */
            --white: #ffffff;
            --border: var(--cor-borda);
            --border-light: var(--cor-borda-clara);
            
            /* Estados */
            --success: var(--cor-sucesso);
            --error: var(--cor-erro);
            --warning: var(--cor-aviso);
            --info: var(--cor-info);
            
            /* Typography */
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            
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
            --radius: 6px;
            --radius-lg: 8px;
            --radius-xl: 12px;
            --radius-2xl: 16px;
            
            /* Shadows - Usando as novas sombras profissionais */
            --shadow-sm: var(--sombra-suave);
            --shadow: var(--sombra-suave);
            --shadow-md: var(--sombra-media);
            --shadow-lg: var(--sombra-forte);
            --shadow-xl: 0 20px 40px rgba(20, 33, 61, 0.18);
            
            /* Transitions */
            --transition: all 0.2s ease;
            --transition-slow: all 0.3s ease;
        }

        body {
            font-family: var(--font-family);
            color: var(--cor-texto);         /* #1a202c - Texto principal refinado */
            background: var(--cor-fundo);    /* #f8fafc - Soft Gray */
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ==========================================
           📱 HEADER PROFISSIONAL - Oxford Blue
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
           🌅 HERO SECTION - White Card Flutuante
        ========================================== */
        .hero {
            background: var(--cor-fundo);            /* #f8fafc - Soft Gray background */
            padding: calc(70px + var(--space-20)) var(--space-6) var(--space-20);
            position: relative;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="%2314213d" opacity="0.03"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/></svg>');
            opacity: 1;
        }

        .hero-container {
            max-width: 900px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 1;
            background: linear-gradient(135deg, 
                        rgba(20, 33, 61, 0.97) 0%,      /* Oxford Blue com 97% opacidade */
                        rgba(30, 44, 71, 0.95) 50%,     /* Oxford Blue Light */
                        rgba(20, 33, 61, 0.97) 100%);   /* Oxford Blue */
            border-radius: var(--radius-2xl);
            padding: var(--space-16);
            box-shadow: 0 20px 60px rgba(20, 33, 61, 0.4), 
                        0 0 0 1px rgba(252, 163, 17, 0.1);  /* Sombra profunda + borda orange sutil */
            border: 1px solid rgba(252, 163, 17, 0.2);      /* Borda orange translúcida */
            backdrop-filter: blur(10px);                     /* Efeito glassmorphism */
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            background: rgba(252, 163, 17, 0.15);    /* Orange translúcido */
            padding: var(--space-2) var(--space-4);
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            color: var(--cor-secundaria);            /* #fca311 - Orange */
            margin-bottom: var(--space-6);
            border: 1px solid rgba(252, 163, 17, 0.3);
        }

        .hero-badge svg {
            width: 16px;
            height: 16px;
        }

        .hero-title {
            font-size: 56px;
            font-weight: 900;
            line-height: 1.1;
            color: #ffffff;                          /* Branco puro para contraste no fundo escuro */
            margin-bottom: var(--space-6);
            letter-spacing: -0.02em;
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.3);  /* Sombra suave no texto */
        }

        .hero-subtitle {
            font-size: 20px;
            color: rgba(255, 255, 255, 0.85);        /* Branco translúcido */
            margin-bottom: var(--space-8);
            font-weight: 400;
        }

        /* Search Box Sofisticada */
        .search-box {
            background: rgba(255, 255, 255, 0.95);   /* Branco semi-transparente */
            border-radius: var(--radius-xl);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);  /* Sombra mais pronunciada */
            padding: var(--space-2);
            display: flex;
            gap: var(--space-2);
            margin-bottom: var(--space-6);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);              /* Efeito glassmorphism */
        }

        .search-input-group {
            flex: 1;
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: 0 var(--space-4);
            border-right: 1px solid var(--gray-200);
            position: relative;
        }

        .search-input-group:last-of-type {
            border-right: none;
        }

        .search-input-group svg {
            width: 20px;
            height: 20px;
            color: var(--cor-texto-claro);           /* #64748b */
            flex-shrink: 0;
        }

        .search-input {
            flex: 1;
            border: none;
            outline: none;
            padding: var(--space-3) 0;
            font-size: 15px;
            font-family: var(--font-family);
            color: var(--cor-texto);                 /* #1a202c - Texto principal */
            background: transparent;
        }

        .search-input::placeholder {
            color: var(--cor-texto-muito-claro);     /* #94a3b8 - Placeholder */
        }
        
        /* Ícone de validação */
        .search-input-group .check-icon {
            color: var(--cor-sucesso);
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .search-input-group.has-value .check-icon {
            opacity: 1;
        }
        
        /* Autocomplete Dropdown */
        .autocomplete-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: var(--white);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            max-height: 280px;
            overflow-y: auto;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: var(--transition);
        }
        
        .autocomplete-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .autocomplete-item {
            padding: var(--space-3) var(--space-4);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            color: var(--cor-texto);
            border-bottom: 1px solid var(--gray-100);
        }
        
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        
        .autocomplete-item:hover,
        .autocomplete-item.active {
            background: var(--cor-acento);
            color: var(--cor-primaria);
        }
        
        .autocomplete-item svg {
            width: 16px;
            height: 16px;
            color: var(--cor-texto-claro);
        }
        
        .autocomplete-item:hover svg,
        .autocomplete-item.active svg {
            color: var(--cor-secundaria);
        }
        
        .autocomplete-loading {
            padding: var(--space-4);
            text-align: center;
            color: var(--cor-texto-claro);
            font-size: 14px;
        }
        
        .autocomplete-empty {
            padding: var(--space-4);
            text-align: center;
            color: var(--cor-texto-claro);
            font-size: 14px;
        }
        
        /* Select customizado para província */
        .search-select {
            flex: 1;
            border: none;
            outline: none;
            padding: var(--space-3) var(--space-6) var(--space-3) 0;
            font-size: 15px;
            font-family: var(--font-family);
            color: var(--cor-texto);
            background: transparent;
            cursor: pointer;
            appearance: none;
        }
        
        .search-select option {
            padding: var(--space-3);
        }
        
        .search-input-group .select-arrow {
            position: absolute;
            right: var(--space-4);
            pointer-events: none;
            color: var(--cor-texto-claro);
        }

        .btn-search {
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: var(--space-4) var(--space-8);
            border-radius: var(--radius-lg);
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .hero-cta {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            background: transparent;
            color: rgba(255, 255, 255, 0.7);         /* Branco translúcido */
            border: none;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .hero-cta:hover {
            color: var(--cor-secundaria);            /* #fca311 - Orange */
        }

        .hero-cta svg {
            width: 18px;
            height: 18px;
        }
        
        /* Contador de resultados */
        .search-results-count {
            text-align: center;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.85);
            margin-top: var(--space-4);
            opacity: 0;
            transform: translateY(-10px);
            transition: var(--transition);
        }
        
        .search-results-count.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .search-results-count strong {
            color: var(--cor-secundaria);
            font-weight: 700;
        }

        /* ==========================================
           📊 ESTATÍSTICAS REAIS
        ========================================== */
        .stats-section {
            background: var(--white);
            padding: var(--space-16) var(--space-6);
            border-bottom: 1px solid var(--gray-200);
        }

        .stats-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-8);
        }

        .stat-card {
            text-align: center;
            padding: var(--space-6);
            background: var(--cor-cards);            /* #ffffff - White card */
            border-radius: var(--radius-xl);
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            box-shadow: var(--sombra-suave);
            transition: var(--transition);
            opacity: 0;
            transform: translateY(30px);
        }
        
        .stat-card.animate-in {
            animation: slideUpFade 0.6s ease-out forwards;
        }
        
        @keyframes slideUpFade {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--sombra-media);
            border-color: var(--cor-secundaria);     /* #fca311 - Orange */
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-4);
            color: var(--white);
        }

        .stat-icon svg {
            width: 24px;
            height: 24px;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 900;
            color: var(--gray-900);
            display: block;
            margin-bottom: var(--space-2);
            line-height: 1;
            min-height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-number.counting {
            background: linear-gradient(90deg, 
                        var(--cor-primaria) 0%, 
                        var(--cor-secundaria) 50%, 
                        var(--cor-primaria) 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: gradient-shift 2s ease-in-out;
        }
        
        @keyframes gradient-shift {
            0% {
                background-position: 0% center;
            }
            100% {
                background-position: 100% center;
            }
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray-600);
            font-weight: 500;
        }

        /* ==========================================
           🎯 CATEGORIAS COM IMAGENS
        ========================================== */
        .categories-section {
            padding: var(--space-20) var(--space-6);
            background: var(--white);
        }

        .section-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: var(--space-12);
        }

        .section-title {
            font-size: 36px;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: var(--space-4);
            letter-spacing: -0.01em;
        }

        .section-subtitle {
            font-size: 18px;
            color: var(--gray-600);
            max-width: 600px;
            margin: 0 auto;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: var(--space-6);
            margin-bottom: var(--space-10);
        }

        .category-card {
            position: relative;
            border-radius: var(--radius-xl);
            overflow: hidden;
            aspect-ratio: 1;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition-slow);
            box-shadow: var(--sombra-suave);
            border: 1px solid var(--cor-borda-clara);
        }

        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--sombra-forte);
            border-color: var(--cor-secundaria);     /* #fca311 - Orange */
        }

        .category-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .category-card:hover .category-image {
            transform: scale(1.1);
        }

        .category-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
            padding: var(--space-5);
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }

        .category-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--white);
        }

        .category-count {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.9);
        }

        .category-icon {
            width: 32px;
            height: 32px;
            color: var(--white);
            margin-bottom: var(--space-2);
        }

        .btn-view-all {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            background: var(--primary);
            color: var(--white);
            padding: var(--space-4) var(--space-8);
            border-radius: var(--radius);
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            margin: 0 auto;
            transition: var(--transition);
        }

        .btn-view-all:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-view-all svg {
            width: 18px;
            height: 18px;
        }

        /* ==========================================
           💼 VAGAS RECENTES
        ========================================== */
        .jobs-section {
            padding: var(--space-20) var(--space-6);
            background: var(--gray-50);
        }

        .jobs-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-6);
            margin-bottom: var(--space-10);
        }

        .job-card {
            background: var(--cor-cards);            /* #ffffff - White card */
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            box-shadow: var(--sombra-suave);
            position: relative;
            display: block;
        }

        .job-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--sombra-media);
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            border-color: var(--cor-secundaria);     /* #fca311 - Orange */
        }
        
        /* Botão de Salvar Vaga */
        .btn-save-job {
            position: absolute;
            top: var(--space-4);
            right: var(--space-4);
            width: 36px;
            height: 36px;
            background: var(--white);
            border: 1px solid var(--cor-borda);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            z-index: 10;
            box-shadow: var(--shadow-sm);
        }
        
        .btn-save-job:hover {
            background: var(--cor-secundaria);
            border-color: var(--cor-secundaria);
            transform: scale(1.1);
            box-shadow: var(--shadow-md);
        }
        
        .btn-save-job:hover svg {
            color: var(--white);
        }
        
        .btn-save-job svg {
            width: 18px;
            height: 18px;
            color: var(--cor-texto-claro);
            transition: var(--transition);
        }
        
        .btn-save-job.saved {
            background: var(--cor-secundaria);
            border-color: var(--cor-secundaria);
        }
        
        .btn-save-job.saved svg {
            color: var(--white);
            fill: var(--white);
        }
        
        /* Badges de Urgência */
        .job-urgency-badge {
            position: absolute;
            top: var(--space-4);
            left: var(--space-4);
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 5;
        }
        
        .job-urgency-badge.nova {
            background: linear-gradient(135deg, #10B981, #059669);
            color: var(--white);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .job-urgency-badge.urgente {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: var(--white);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        .job-urgency-badge.destaque {
            background: linear-gradient(135deg, var(--cor-secundaria), var(--cor-secundaria-hover));
            color: var(--white);
            box-shadow: 0 2px 8px rgba(252, 163, 17, 0.3);
        }

        .job-header {
            display: flex;
            align-items: flex-start;
            gap: var(--space-4);
            margin-bottom: var(--space-4);
        }

        .job-logo {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            object-fit: cover;
            background: var(--gray-100);
        }

        .job-info {
            flex: 1;
        }

        .job-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: var(--space-1);
        }

        .job-company {
            font-size: 14px;
            color: var(--gray-600);
        }

        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-4);
            margin-bottom: var(--space-4);
        }

        .job-meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 13px;
            color: var(--gray-600);
        }

        .job-meta-item svg {
            width: 16px;
            height: 16px;
        }

        .job-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: var(--space-4);
            border-top: 1px solid var(--gray-200);
        }

        .job-salary {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
        }

        .job-badge {
            padding: var(--space-1) var(--space-3);
            background: var(--gray-100);
            color: var(--gray-700);
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        
        /* Requisitos e Tags */
        .job-tags {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            margin-bottom: var(--space-4);
        }
        
        .job-tag {
            padding: 4px 10px;
            background: var(--cor-acento);
            color: var(--cor-primaria);
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid var(--cor-acento-escuro);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .job-tag svg {
            width: 14px;
            height: 14px;
        }
        
        .job-tag.modalidade {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border-color: rgba(16, 185, 129, 0.2);
        }
        
        .job-tag.nivel {
            background: rgba(252, 163, 17, 0.1);
            color: #92400e;
            border-color: rgba(252, 163, 17, 0.2);
        }

        /* ==========================================
           📱 RESPONSIVE
        ========================================== */
        @media (max-width: 1024px) {
            .categories-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .jobs-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .hero-title {
                font-size: 36px;
            }

            .hero-subtitle {
                font-size: 16px;
            }

            .search-box {
                flex-direction: column;
            }

            .search-input-group {
                border-right: none;
                border-bottom: 1px solid var(--gray-200);
            }

            .search-input-group:last-of-type {
                border-bottom: none;
            }

            .btn-search {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .categories-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .jobs-grid {
                grid-template-columns: 1fr;
            }

            .section-title {
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            .categories-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ==========================================
           🎨 FOOTER RICO
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
    </style>
</head>
<body>

    <!-- ==========================================
         📱 HEADER PROFISSIONAL
    ========================================== -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <img src="assets/images/empregos-logo.svg" alt="Emprego MZ" class="logo-img">
            </a>

            <nav class="nav-menu">
                <a href="index.php" class="nav-link active">Início</a>
                <a href="vagas.php" class="nav-link">Vagas</a>
                
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
         🌅 HERO SECTION
    ========================================== -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-badge">
                <i data-lucide="trending-up"></i>
                <?php echo number_format($vagas_ultimos_7_dias); ?> novas vagas esta semana
            </div>
            
            <h1 class="hero-title">
                Encontre o emprego dos seus sonhos em Moçambique
            </h1>
            
            <p class="hero-subtitle">
                Milhares de oportunidades esperando por você. Simples, rápido e gratuito.
            </p>

            <form action="vagas.php" method="GET" class="search-box" id="searchForm">
                <!-- Campo de Cargo com Autocomplete -->
                <div class="search-input-group" id="cargoGroup">
                    <i data-lucide="search"></i>
                    <input 
                        type="text" 
                        name="q" 
                        id="cargoInput"
                        class="search-input" 
                        placeholder="Cargo ou palavra-chave"
                        autocomplete="off"
                    >
                    <i data-lucide="check-circle" class="check-icon"></i>
                    
                    <!-- Dropdown de Autocomplete -->
                    <div class="autocomplete-dropdown" id="autocompleteDropdown"></div>
                </div>
                
                <!-- Campo de Província com Select -->
                <div class="search-input-group" id="provinciaGroup">
                    <i data-lucide="map-pin"></i>
                    <select 
                        name="local" 
                        id="provinciaSelect"
                        class="search-select"
                    >
                        <option value="">Todas as províncias</option>
                        <?php foreach ($provincias as $provincia): ?>
                            <option value="<?php echo htmlspecialchars($provincia['nome']); ?>">
                                <?php echo htmlspecialchars($provincia['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <i data-lucide="chevron-down" class="select-arrow"></i>
                    <i data-lucide="check-circle" class="check-icon"></i>
                </div>
                
                <button type="submit" class="btn-search">
                    Buscar Vagas
                </button>
            </form>
            
            <!-- Contador de Resultados -->
            <div id="searchResultsCount" class="search-results-count"></div>

            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="auth/register.php" class="hero-cta">
                    Ou crie seu perfil grátis
                    <i data-lucide="arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- ==========================================
         📊 ESTATÍSTICAS REAIS
    ========================================== -->
    <section class="stats-section" id="statsSection">
        <div class="stats-container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="briefcase"></i>
                    </div>
                    <span class="stat-number" data-target="<?php echo $total_vagas; ?>">0</span>
                    <span class="stat-label">Vagas Ativas</span>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="building-2"></i>
                    </div>
                    <span class="stat-number" data-target="<?php echo $total_empresas; ?>">0</span>
                    <span class="stat-label">Empresas Parceiras</span>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="users"></i>
                    </div>
                    <span class="stat-number" data-target="<?php echo $total_candidatos; ?>">0</span>
                    <span class="stat-label">Candidatos Registados</span>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="map-pin"></i>
                    </div>
                    <span class="stat-number" data-target="11">0</span>
                    <span class="stat-label">Províncias</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         🎯 CATEGORIAS COM IMAGENS
    ========================================== -->
    <section class="categories-section">
        <div class="section-container">
            <div class="section-header">
                <h2 class="section-title">Explore vagas por categoria</h2>
                <p class="section-subtitle">Encontre oportunidades na sua área de atuação em todo Moçambique</p>
            </div>

            <div class="categories-grid">
                <?php foreach ($areas_principais as $area): 
                    // Buscar número de vagas usando o mapeamento area_db
                    $vagas_count = $vagas_por_area_data[$area['area_db']] ?? 0;
                ?>
                    <a href="vagas.php?area=<?php echo urlencode($area['area_db']); ?>" class="category-card">
                        <img src="assets/images/<?php echo $area['imagem']; ?>" alt="<?php echo htmlspecialchars($area['nome']); ?>" class="category-image">
                        <div class="category-overlay">
                            <i data-lucide="<?php echo $area['icone']; ?>" class="category-icon"></i>
                            <div class="category-name"><?php echo htmlspecialchars($area['nome']); ?></div>
                            <div class="category-count"><?php echo $vagas_count; ?> <?php echo $vagas_count == 1 ? 'vaga' : 'vagas'; ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <center>
                <a href="vagas.php" class="btn-view-all">
                    Ver todas as vagas
                    <i data-lucide="arrow-right"></i>
                </a>
            </center>
        </div>
    </section>

    <!-- ==========================================
         💼 VAGAS RECENTES
    ========================================== -->
    <?php if (count($vagas_recentes) > 0): ?>
    <section class="jobs-section">
        <div class="section-container">
            <div class="section-header">
                <h2 class="section-title">Vagas Publicadas Recentemente</h2>
                <p class="section-subtitle">Oportunidades adicionadas nas últimas horas</p>
            </div>

            <div class="jobs-grid">
                <?php foreach ($vagas_recentes as $vaga): 
                    // Determinar badge de urgência
                    $badge_urgencia = '';
                    $badge_class = '';
                    
                    if ($vaga['dias_publicada'] <= 2) {
                        $badge_urgencia = 'Nova';
                        $badge_class = 'nova';
                    } elseif ($vaga['dias_restantes'] <= 7) {
                        $badge_urgencia = 'Preenche Rápido';
                        $badge_class = 'urgente';
                    }
                    
                    // Verificar se vaga está guardada
                    $is_saved = in_array($vaga['id'], $vagas_guardadas_ids);
                    
                    // Traduzir modalidade
                    $modalidade_texto = [
                        'presencial' => 'Presencial',
                        'remoto' => 'Remoto',
                        'hibrido' => 'Híbrido'
                    ];
                    
                    // Traduzir tipo de contrato
                    $tipo_contrato_texto = [
                        'tempo_inteiro' => 'Tempo Inteiro',
                        'tempo_parcial' => 'Tempo Parcial',
                        'estagio' => 'Estágio',
                        'freelance' => 'Freelance'
                    ];
                ?>
                    <div class="job-card">
                        <!-- Badge de Urgência -->
                        <?php if ($badge_urgencia): ?>
                            <div class="job-urgency-badge <?php echo $badge_class; ?>">
                                <?php echo $badge_urgencia; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Botão Salvar -->
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'candidato'): ?>
                            <button 
                                class="btn-save-job <?php echo $is_saved ? 'saved' : ''; ?>" 
                                data-vaga-id="<?php echo $vaga['id']; ?>"
                                onclick="toggleSaveJob(event, <?php echo $vaga['id']; ?>)"
                                title="<?php echo $is_saved ? 'Remover dos guardados' : 'Guardar vaga'; ?>"
                            >
                                <i data-lucide="bookmark"></i>
                            </button>
                        <?php endif; ?>
                        
                        <a href="vaga_detalhe.php?id=<?php echo $vaga['id']; ?>" style="text-decoration: none; color: inherit;">
                            <div class="job-header">
                                <?php if (!empty($vaga['logotipo'])): ?>
                                    <img src="<?php echo htmlspecialchars($vaga['logotipo']); ?>" alt="<?php echo htmlspecialchars($vaga['nome_empresa']); ?>" class="job-logo">
                                <?php else: ?>
                                    <div class="job-logo" style="display: flex; align-items: center; justify-content: center; background: var(--primary); color: white; font-weight: 700;">
                                        <?php echo strtoupper(substr($vaga['nome_empresa'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="job-info">
                                    <h3 class="job-title"><?php echo htmlspecialchars($vaga['titulo']); ?></h3>
                                    <div class="job-company"><?php echo htmlspecialchars($vaga['nome_empresa']); ?></div>
                                </div>
                            </div>

                            <!-- Tags de Requisitos -->
                            <div class="job-tags">
                                <?php if (!empty($vaga['modalidade'])): ?>
                                    <span class="job-tag modalidade">
                                        <i data-lucide="<?php echo $vaga['modalidade'] === 'remoto' ? 'home' : ($vaga['modalidade'] === 'hibrido' ? 'laptop' : 'building-2'); ?>"></i>
                                        <?php echo $modalidade_texto[$vaga['modalidade']] ?? ucfirst($vaga['modalidade']); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($vaga['nivel_experiencia'])): ?>
                                    <span class="job-tag nivel">
                                        <i data-lucide="award"></i>
                                        <?php echo htmlspecialchars($vaga['nivel_experiencia']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="job-meta">
                                <div class="job-meta-item">
                                    <i data-lucide="map-pin"></i>
                                    <?php echo htmlspecialchars($vaga['localizacao']); ?>
                                </div>
                                <div class="job-meta-item">
                                    <i data-lucide="clock"></i>
                                    <?php echo $tipo_contrato_texto[$vaga['tipo_contrato']] ?? ucfirst($vaga['tipo_contrato']); ?>
                                </div>
                            </div>

                            <div class="job-footer">
                                <?php if (!empty($vaga['salario_min']) && !empty($vaga['salario_max'])): ?>
                                    <div class="job-salary">
                                        <?php echo number_format($vaga['salario_min']); ?> - <?php echo number_format($vaga['salario_max']); ?> MT
                                    </div>
                                <?php elseif (!empty($vaga['salario_estimado'])): ?>
                                    <div class="job-salary">
                                        <?php echo number_format($vaga['salario_estimado']); ?> MT
                                    </div>
                                <?php else: ?>
                                    <div class="job-salary">A combinar</div>
                                <?php endif; ?>
                                
                                <div class="job-badge">
                                    <?php 
                                    if ($vaga['dias_publicada'] < 1) {
                                        echo 'Hoje';
                                    } elseif ($vaga['dias_publicada'] < 2) {
                                        echo 'Ontem';
                                    } else {
                                        echo floor($vaga['dias_publicada']) . ' dias atrás';
                                    }
                                    ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <center>
                <a href="vagas.php" class="btn-view-all">
                    Ver todas as vagas
                    <i data-lucide="arrow-right"></i>
                </a>
            </center>
        </div>
    </section>
    <?php endif; ?>

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

    <script>
        // Inicializar ícones Lucide
        lucide.createIcons();

        // Mobile Menu Toggle
        const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        const navMenu = document.querySelector('.nav-menu');

        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', () => {
                navMenu.style.display = navMenu.style.display === 'flex' ? 'none' : 'flex';
            });
        }

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Add scroll effect to header
        window.addEventListener('scroll', () => {
            const header = document.querySelector('.header');
            if (window.scrollY > 50) {
                header.style.boxShadow = 'var(--shadow-md)';
            } else {
                header.style.boxShadow = 'var(--shadow-sm)';
            }
        });

        // ==========================================
        // 📊 ANIMAÇÃO DE CONTAGEM NAS ESTATÍSTICAS
        // ==========================================
        
        // Função de easing para animação suave
        function easeOutQuad(t) {
            return t * (2 - t);
        }
        
        // Função para animar contador
        function animateCounter(element, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const easedProgress = easeOutQuad(progress);
                const current = Math.floor(easedProgress * (end - start) + start);
                
                // Formatar número com separador de milhares
                element.textContent = current.toLocaleString('pt-PT');
                
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    // Garantir valor final exato
                    element.textContent = end.toLocaleString('pt-PT');
                    // Remover classe de animação
                    element.classList.remove('counting');
                }
            };
            window.requestAnimationFrame(step);
        }
        
        // Intersection Observer para detectar quando estatísticas aparecem
        const statsSection = document.getElementById('statsSection');
        let statsAnimated = false;
        
        if (statsSection) {
            const statsObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !statsAnimated) {
                        statsAnimated = true;
                        
                        // Animar cards primeiro
                        const statCards = document.querySelectorAll('.stat-card');
                        statCards.forEach((card, index) => {
                            setTimeout(() => {
                                card.classList.add('animate-in');
                                // Definir delay da animação via CSS inline
                                card.style.animationDelay = `${index * 0.1}s`;
                            }, 0);
                        });
                        
                        // Animar contadores após cards aparecerem
                        const statNumbers = document.querySelectorAll('.stat-number');
                        
                        statNumbers.forEach((stat, index) => {
                            const target = parseInt(stat.getAttribute('data-target'));
                            
                            // Adicionar classe de animação de gradiente
                            stat.classList.add('counting');
                            
                            // Delay escalonado: 400ms inicial + 150ms entre cada
                            setTimeout(() => {
                                animateCounter(stat, 0, target, 2000);
                            }, 400 + (index * 150));
                        });
                        
                        // Parar de observar após animar
                        statsObserver.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.3  // Ativar quando 30% da seção estiver visível
            });
            
            statsObserver.observe(statsSection);
        }

        // ==========================================
        // 🔍 AUTOCOMPLETE INTELIGENTE NA BUSCA
        // ==========================================
        const cargoInput = document.getElementById('cargoInput');
        const cargoGroup = document.getElementById('cargoGroup');
        const autocompleteDropdown = document.getElementById('autocompleteDropdown');
        const provinciaSelect = document.getElementById('provinciaSelect');
        const provinciaGroup = document.getElementById('provinciaGroup');
        
        let debounceTimer;
        let selectedIndex = -1;
        let suggestions = [];
        
        // Autocomplete de cargo
        if (cargoInput) {
            cargoInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Mostrar check icon se tiver valor
                if (query.length > 0) {
                    cargoGroup.classList.add('has-value');
                } else {
                    cargoGroup.classList.remove('has-value');
                }
                
                // Limpar timer anterior
                clearTimeout(debounceTimer);
                
                // Se query muito curta, esconder dropdown
                if (query.length < 2) {
                    hideAutocomplete();
                    return;
                }
                
                // Mostrar loading
                autocompleteDropdown.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
                autocompleteDropdown.classList.add('show');
                
                // Debounce - aguardar 300ms após parar de digitar
                debounceTimer = setTimeout(() => {
                    fetchSuggestions(query);
                }, 300);
            });
            
            // Navegação por teclado
            cargoInput.addEventListener('keydown', function(e) {
                const items = autocompleteDropdown.querySelectorAll('.autocomplete-item');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                    updateSelectedItem(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, -1);
                    updateSelectedItem(items);
                } else if (e.key === 'Enter' && selectedIndex >= 0) {
                    e.preventDefault();
                    items[selectedIndex].click();
                } else if (e.key === 'Escape') {
                    hideAutocomplete();
                }
            });
            
            // Fechar ao clicar fora
            document.addEventListener('click', function(e) {
                if (!cargoGroup.contains(e.target)) {
                    hideAutocomplete();
                }
            });
        }
        
        // Validação visual do select de província
        if (provinciaSelect) {
            provinciaSelect.addEventListener('change', function() {
                if (this.value) {
                    provinciaGroup.classList.add('has-value');
                } else {
                    provinciaGroup.classList.remove('has-value');
                }
                
                // Atualizar contador ao mudar província
                updateResultsCount();
            });
        }
        
        // Atualizar contador de resultados
        let countDebounceTimer;
        
        function updateResultsCount() {
            const cargo = cargoInput ? cargoInput.value.trim() : '';
            const provincia = provinciaSelect ? provinciaSelect.value : '';
            const resultsCountEl = document.getElementById('searchResultsCount');
            
            // Se ambos vazios, esconder contador
            if (!cargo && !provincia) {
                resultsCountEl.classList.remove('show');
                return;
            }
            
            clearTimeout(countDebounceTimer);
            
            countDebounceTimer = setTimeout(() => {
                fetch(`api/contar_vagas.php?cargo=${encodeURIComponent(cargo)}&provincia=${encodeURIComponent(provincia)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const total = data.total;
                            
                            if (total > 0) {
                                resultsCountEl.innerHTML = `<strong>${total}</strong> ${total === 1 ? 'vaga encontrada' : 'vagas encontradas'}`;
                                resultsCountEl.classList.add('show');
                            } else {
                                resultsCountEl.innerHTML = 'Nenhuma vaga encontrada com esses critérios';
                                resultsCountEl.classList.add('show');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao contar vagas:', error);
                    });
            }, 500);
        }
        
        // Atualizar contador ao digitar no campo de cargo
        if (cargoInput) {
            const originalInputHandler = cargoInput.oninput;
            cargoInput.addEventListener('input', function() {
                updateResultsCount();
            });
        }
        
        // Buscar sugestões da API
        function fetchSuggestions(query) {
            fetch(`api/buscar_sugestoes.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    suggestions = data;
                    displaySuggestions(data, query);
                })
                .catch(error => {
                    console.error('Erro ao buscar sugestões:', error);
                    autocompleteDropdown.innerHTML = '<div class="autocomplete-empty">Erro ao buscar sugestões</div>';
                });
        }
        
        // Exibir sugestões
        function displaySuggestions(data, query) {
            selectedIndex = -1;
            
            if (data.length === 0) {
                autocompleteDropdown.innerHTML = '<div class="autocomplete-empty">Nenhuma sugestão encontrada</div>';
                return;
            }
            
            let html = '';
            data.forEach((item, index) => {
                // Destacar termo de busca
                const highlighted = item.replace(
                    new RegExp(query, 'gi'),
                    match => `<strong style="color: var(--cor-secundaria)">${match}</strong>`
                );
                
                html += `
                    <div class="autocomplete-item" data-index="${index}" data-value="${escapeHtml(item)}">
                        <i data-lucide="briefcase"></i>
                        <span>${highlighted}</span>
                    </div>
                `;
            });
            
            autocompleteDropdown.innerHTML = html;
            lucide.createIcons();
            
            // Adicionar event listeners
            const items = autocompleteDropdown.querySelectorAll('.autocomplete-item');
            items.forEach(item => {
                item.addEventListener('click', function() {
                    cargoInput.value = this.getAttribute('data-value');
                    cargoGroup.classList.add('has-value');
                    hideAutocomplete();
                });
            });
        }
        
        // Atualizar item selecionado
        function updateSelectedItem(items) {
            items.forEach((item, index) => {
                if (index === selectedIndex) {
                    item.classList.add('active');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('active');
                }
            });
        }
        
        // Esconder autocomplete
        function hideAutocomplete() {
            autocompleteDropdown.classList.remove('show');
            selectedIndex = -1;
        }
        
        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ==========================================
        // 🔖 FUNCIONALIDADE DE SALVAR/GUARDAR VAGAS
        // ==========================================
        function toggleSaveJob(event, vagaId) {
            event.preventDefault();
            event.stopPropagation();
            
            const button = event.currentTarget;
            const isSaved = button.classList.contains('saved');
            
            // Fazer requisição AJAX
            fetch('api/guardar_vaga.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    vaga_id: vagaId,
                    action: isSaved ? 'remove' : 'add'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Toggle visual
                    button.classList.toggle('saved');
                    
                    // Atualizar título
                    button.title = isSaved ? 'Guardar vaga' : 'Remover dos guardados';
                    
                    // Reinicializar ícones
                    lucide.createIcons();
                    
                    // Mostrar feedback visual (opcional)
                    showToast(isSaved ? 'Vaga removida dos guardados' : 'Vaga guardada com sucesso!');
                } else {
                    // Mostrar erro
                    showToast(data.message || 'Erro ao processar ação', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro ao processar ação', 'error');
            });
        }

        // Toast notification (feedback visual)
        function showToast(message, type = 'success') {
            // Criar toast se não existir
            let toast = document.getElementById('toast-notification');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'toast-notification';
                toast.style.cssText = `
                    position: fixed;
                    bottom: 24px;
                    right: 24px;
                    padding: 16px 24px;
                    background: ${type === 'success' ? '#10B981' : '#EF4444'};
                    color: white;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
                    z-index: 10000;
                    transform: translateY(100px);
                    opacity: 0;
                    transition: all 0.3s ease;
                `;
                document.body.appendChild(toast);
            }
            
            // Atualizar mensagem e cor
            toast.textContent = message;
            toast.style.background = type === 'success' ? '#10B981' : '#EF4444';
            
            // Animar entrada
            setTimeout(() => {
                toast.style.transform = 'translateY(0)';
                toast.style.opacity = '1';
            }, 10);
            
            // Remover após 3 segundos
            setTimeout(() => {
                toast.style.transform = 'translateY(100px)';
                toast.style.opacity = '0';
            }, 3000);
        }
    </script>
</body>
</html>
