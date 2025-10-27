<?php
session_start();
require_once 'config/db.php';

 $pdo = getPDO();

// Buscar apenas 3 vagas recentes para a página inicial
 $sql_vagas_recentes = "SELECT v.*, e.nome_empresa, e.logotipo 
                       FROM vaga v 
                       JOIN empresa e ON v.empresa_id = e.id 
                       WHERE v.ativa = TRUE AND v.data_expiracao >= CURDATE()
                       ORDER BY v.data_publicacao DESC 
                       LIMIT 3";

 $stmt = $pdo->prepare($sql_vagas_recentes);
 $stmt->execute();
 $vagas_recentes = $stmt->fetchAll();

// Contar total de vagas ativas
 $sql_total_vagas = "SELECT COUNT(*) as total FROM vaga WHERE ativa = TRUE AND data_expiracao >= CURDATE()";
 $total_vagas = $pdo->query($sql_total_vagas)->fetch()['total'];

// Contar total de empresas
 $sql_total_empresas = "SELECT COUNT(*) as total FROM empresa";
 $total_empresas = $pdo->query($sql_total_empresas)->fetch()['total'];

// Áreas mais populares para quick links
 $areas_populares = [
    'TI e Tecnologia',
    'Agricultura e Pecuária',
    'Construção Civil', 
    'Educação e Formação',
    'Saúde e Medicina',
    'Comércio e Vendas',
    'Hotelaria e Turismo',
    'Administração e Secretariado',
    'Finanças e Contabilidade',
    'Recursos Humanos',
    'Marketing e Publicidade',
    'Logística e Transportes',
    'Mineração e Recursos Naturais',
    'Pesca e Aquicultura'
];

// Todas as províncias de Moçambique + Remoto
 $provincias_mocambique = [
    'Maputo Cidade',
    'Maputo Província',
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

// Principais cidades para quick links (mais procuradas)
 $cidades_principais = [
    'Maputo Cidade',
    'Matola',
    'Beira',
    'Nampula',
    'Chimoio',
    'Quelimane',
    'Tete',
    'Xai-Xai',
    'Inhambane',
    'Lichinga',
    'Pemba',
    'Remoto'
];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emprego MZ - Encontre Emprego em Moçambique</title>
    
    <!-- Google Fonts - Ubuntu Moderno -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
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
            overflow-x: hidden;
        }

        main {
            padding-top: 80px; /* Espaço para o header fixo */
        }
        
        /* ============================================
           📱 HEADER - Navegação Principal
        ============================================ */
        .main-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: var(--branco-puro);
            box-shadow: var(--shadow-medium);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 80px;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            font-family: var(--font-heading);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--azul-indigo);
            text-decoration: none;
        }

        .header-logo:hover {
            color: var(--verde-esperanca);
        }

        .header-nav {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .header-nav a {
            color: var(--carvao);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .header-nav a:hover {
            color: var(--verde-esperanca);
        }

        .header-welcome {
            color: var(--azul-indigo);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }
        
        /* ============================================
           🌅 HERO SECTION - Pôr do Sol de Maputo
        ============================================ */
        .hero {
            background: var(--gradient-por-do-sol);
            color: var(--branco-puro);
            padding: var(--space-xl) var(--space-md);
            text-align: center;
            position: relative;
            overflow: hidden;
            min-height: 500px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        /* Padrão Capulana Sutil no Background */
        .hero::before {
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
        
        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 900px;
        }
        
        .hero h1 {
            font-family: var(--font-heading);
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 800;
            margin-bottom: var(--space-md);
            text-shadow: 0 2px 12px rgba(0,0,0,0.15);
            animation: slideDown 0.8s ease-out;
        }
        
        .hero p {
            font-size: clamp(1rem, 2vw, 1.3rem);
            margin-bottom: var(--space-lg);
            opacity: 0.95;
            animation: fadeIn 1s ease-out 0.3s both;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* ============================================
           🔍 SEARCH BOX - Forma Orgânica
        ============================================ */
        .search-box {
            max-width: 700px;
            width: 100%;
            animation: fadeIn 1s ease-out 0.5s both;
        }
        
        .search-form {
            display: flex;
            gap: var(--space-sm);
            background: var(--branco-puro);
            padding: var(--space-xs);
            border-radius: 60px;
            box-shadow: var(--shadow-strong);
        }
        
        .search-input {
            flex: 1;
            padding: var(--space-md) var(--space-lg);
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            background: transparent;
            color: var(--carvao);
            outline: none;
        }
        
        .search-input::placeholder {
            color: var(--cinza-baobab);
        }
        
        .btn-search {
            background: var(--gradient-oceano);
            color: var(--branco-puro);
            border: none;
            padding: var(--space-md) var(--space-lg);
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }
        
        .btn-search:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-hover);
        }
        
        .btn-search:active {
            transform: scale(0.98);
        }
        
        /* ============================================
           📊 STATS - Animação Índico Waves
        ============================================ */
        .stats {
            display: flex;
            justify-content: center;
            gap: var(--space-xl);
            padding: var(--space-xl) var(--space-md);
            flex-wrap: wrap;
            background: var(--branco-puro);
            margin: calc(var(--space-lg) * -1) auto var(--space-xl);
            max-width: 900px;
            border-radius: 24px;
            box-shadow: var(--shadow-medium);
            position: relative;
            z-index: 3;
        }
        
        .stat-item {
            text-align: center;
            padding: var(--space-md);
            min-width: 150px;
            animation: popIn 0.6s ease-out both;
        }
        
        .stat-item:nth-child(1) { animation-delay: 0.7s; }
        .stat-item:nth-child(2) { animation-delay: 0.8s; }
        .stat-item:nth-child(3) { animation-delay: 0.9s; }
        
        @keyframes popIn {
            from {
                opacity: 0;
                transform: scale(0.8) translateY(20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .stat-number {
            display: block;
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            font-weight: 800;
            background: var(--gradient-por-do-sol);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }
        
        .stat-label {
            display: block;
            font-size: 1rem;
            color: var(--cinza-baobab);
            margin-top: var(--space-xs);
            font-weight: 500;
        }
        
        /* ============================================
           🌊 OCEAN DIVIDER - Elemento Assinatura
        ============================================ */
        .ocean-divider {
            position: relative;
            width: 100%;
            overflow: hidden;
            line-height: 0;
            margin: var(--space-xl) 0;
        }
        
        .ocean-divider svg {
            position: relative;
            display: block;
            width: calc(100% + 1.3px);
            height: 80px;
        }
        
        /* ============================================
           📦 CONTAINER SYSTEM
        ============================================ */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-md);
        }
        
        .section {
            padding: var(--space-xl) 0;
        }
        
        .section-title {
            font-family: var(--font-heading);
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 700;
            text-align: center;
            margin-bottom: var(--space-lg);
            color: var(--carvao);
        }
        
        .section-subtitle {
            text-align: center;
            color: var(--cinza-baobab);
            font-size: 1.1rem;
            margin-top: calc(var(--space-sm) * -1);
            margin-bottom: var(--space-lg);
        }
        
        /* ============================================
           🏷️ QUICK LINKS - Tags Orgânicas
        ============================================ */
        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-sm);
            justify-content: center;
            padding: var(--space-md) 0;
        }
        
        .quick-link {
            background: var(--branco-puro);
            color: var(--carvao);
            padding: var(--space-sm) var(--space-md);
            border-radius: 24px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            border: 2px solid transparent;
            box-shadow: var(--shadow-soft);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }
        
        .quick-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--gradient-oceano);
            transition: left 0.3s ease;
            z-index: -1;
        }
        
        .quick-link:hover {
            color: var(--branco-puro);
            border-color: var(--verde-esperanca);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .quick-link:hover::before {
            left: 0;
        }
        
        /* ============================================
           🎴 XIMA CARDS - Cards com Textura
        ============================================ */
        .vaga-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: var(--space-lg);
            margin: var(--space-lg) 0;
        }
        
        .xima-card {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-lg);
            box-shadow: var(--shadow-soft);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }
        
        /* Padrão Capulana muito sutil no card */
        .xima-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: 
                repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(255, 176, 59, 0.05) 10px, rgba(255, 176, 59, 0.05) 20px);
            opacity: 0;
            transition: opacity 0.4s ease, transform 0.4s ease;
            transform: scale(1.1);
            pointer-events: none;
        }
        
        .xima-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
            border-color: var(--dourado-sol);
        }
        
        .xima-card:hover::before {
            opacity: 1;
            transform: scale(1.1);
        }
        
        .card-header {
            display: flex;
            align-items: flex-start;
            gap: var(--space-md);
            margin-bottom: var(--space-md);
            padding-bottom: var(--space-md);
            border-bottom: 2px solid var(--areia-quente);
        }
        
        .card-logo {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: var(--gradient-terra);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }
        
        .card-title-section {
            flex: 1;
            min-width: 0;
        }
        
        .card-title {
            font-family: var(--font-heading);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--carvao);
            margin-bottom: var(--space-xs);
            line-height: 1.3;
        }
        
        .card-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .card-title a:hover {
            color: var(--verde-esperanca);
        }
        
        .card-empresa {
            color: var(--azul-indico);
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }
        
        .card-meta-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: var(--space-sm);
            margin-bottom: var(--space-md);
        }
        
        .card-meta {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            color: var(--cinza-baobab);
            font-size: 0.9rem;
        }
        
        .card-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-xs) var(--space-sm);
            background: var(--areia-quente);
            color: var(--carvao);
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .card-salary {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--verde-esperanca);
            margin: var(--space-md) 0;
            padding: var(--space-sm);
            background: linear-gradient(135deg, rgba(43, 122, 75, 0.08), rgba(255, 176, 59, 0.08));
            border-radius: 12px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-xs);
        }
        
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: var(--space-md);
            border-top: 1px solid var(--areia-quente);
            font-size: 0.85rem;
            color: var(--cinza-baobab);
        }
        
        /* Card Urgente - Estado Especial */
        .xima-card--urgent {
            border-left: 4px solid var(--coral-vivo);
        }
        
        .card-urgent-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            background: var(--gradient-por-do-sol);
            color: var(--branco-puro);
            padding: var(--space-xs) var(--space-sm);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            position: absolute;
            top: var(--space-md);
            right: var(--space-md);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        /* ============================================
           🎯 CTA SECTION - Call to Action
        ============================================ */
        .cta-section {
            background: var(--gradient-oceano);
            color: var(--branco-puro);
            padding: var(--space-xl) var(--space-md);
            text-align: center;
            margin-top: var(--space-xl);
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                repeating-linear-gradient(45deg, transparent, transparent 50px, rgba(255,255,255,0.03) 50px, rgba(255,255,255,0.03) 100px);
            opacity: 0.5;
        }
        
        .cta-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .cta-section h2 {
            font-family: var(--font-heading);
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            margin-bottom: var(--space-md);
            font-weight: 700;
        }
        
        .cta-section p {
            font-size: 1.1rem;
            margin-bottom: var(--space-lg);
            opacity: 0.95;
        }
        
        .cta-buttons {
            display: flex;
            gap: var(--space-md);
            justify-content: center;
            flex-wrap: wrap;
        }
        
        /* ============================================
           🔘 BUTTONS - Sistema de Botões
        ============================================ */
        .btn {
            display: inline-flex;
            align-items: center;
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
        }
        
        .btn-primary {
            background: var(--branco-puro);
            color: var(--verde-esperanca);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: var(--branco-puro);
            border: 2px solid var(--branco-puro);
        }
        
        .btn-secondary:hover {
            background: var(--branco-puro);
            color: var(--verde-esperanca);
            transform: translateY(-2px);
        }
        
        /* ============================================
           🎭 EMPTY STATE
        ============================================ */
        .empty-state {
            text-align: center;
            padding: var(--space-xl);
            background: var(--branco-puro);
            border-radius: 20px;
            box-shadow: var(--shadow-soft);
        }
        
        .empty-state h3 {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            color: var(--carvao);
            margin-bottom: var(--space-sm);
        }
        
        .empty-state p {
            color: var(--cinza-baobab);
            font-size: 1.1rem;
        }
        
        /* ============================================
           📱 FOOTER - Rodapé Completo
        ============================================ */
        .main-footer {
            background: var(--carvao);
            color: var(--branco-puro);
            padding: var(--space-xl) 0 var(--space-md);
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-md);
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
        }

        .footer-column h3 {
            font-family: var(--font-heading);
            font-size: 1.2rem;
            margin-bottom: var(--space-md);
            color: var(--dourado-sol);
        }

        .footer-column p {
            margin-bottom: var(--space-sm);
            opacity: 0.9;
            line-height: 1.7;
        }

        .footer-column ul {
            list-style: none;
        }

        .footer-column ul li {
            margin-bottom: var(--space-xs);
        }

        .footer-column a {
            color: var(--branco-puro);
            text-decoration: none;
            opacity: 0.8;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .footer-column a:hover {
            opacity: 1;
            color: var(--dourado-sol);
            transform: translateX(3px);
        }

        .social-links {
            display: flex;
            gap: var(--space-sm);
            margin-top: var(--space-md);
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--dourado-sol);
            transform: translateY(-3px);
        }

        .footer-bottom {
            text-align: center;
            padding-top: var(--space-md);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        /* ============================================
           📱 RESPONSIVE DESIGN
        ============================================ */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                height: auto;
                padding: var(--space-sm) 0;
            }
            main {
                padding-top: 120px; /* Ajuste para header mobile */
            }
            .header-nav {
                flex-wrap: wrap;
                justify-content: center;
                margin-top: var(--space-sm);
            }
            .hero {
                padding: var(--space-lg) var(--space-md);
                min-height: 400px;
            }
            
            .stats {
                gap: var(--space-md);
                margin: calc(var(--space-md) * -1) var(--space-md) var(--space-lg);
            }
            
            .stat-item {
                min-width: 120px;
                padding: var(--space-sm);
            }
            
            .search-form {
                flex-direction: column;
                border-radius: 20px;
                gap: var(--space-xs);
            }
            
            .btn-search {
                width: 100%;
            }
            
            .vaga-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-links {
                gap: var(--space-xs);
            }
            
            .quick-link {
                font-size: 0.9rem;
                padding: var(--space-xs) var(--space-sm);
            }
            
            .cta-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
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
        .btn:focus, .quick-link:focus, .header-nav a:focus {
            outline: 3px solid var(--dourado-sol);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <!-- 📱 HEADER - Navegação Principal -->
    <header class="main-header">
        <div class="header-container">
            <a href="index.php" class="header-logo">
                <i data-lucide="briefcase" style="width: 32px; height: 32px;"></i>
                Emprego MZ
            </a>
            <nav class="header-nav">
                <a href="index.php">
                    <i data-lucide="home" style="width: 18px; height: 18px;"></i>
                    Início
                </a>
                <a href="vagas.php">
                    <i data-lucide="briefcase" style="width: 18px; height: 18px;"></i>
                    Todas as Vagas
                </a>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['user_type'] === 'empresa'): ?>
                        <a href="empresa/dashboard.php">
                            <i data-lucide="bar-chart" style="width: 18px; height: 18px;"></i>
                            Dashboard
                        </a>
                        <a href="empresa/publicar_vaga.php">
                            <i data-lucide="plus-circle" style="width: 18px; height: 18px;"></i>
                            Publicar Vaga
                        </a>
                    <?php else: ?>
                        <a href="candidato/perfil.php">
                            <i data-lucide="user" style="width: 18px; height: 18px;"></i>
                            Meu Perfil
                        </a>
                        <a href="candidato/candidaturas.php">
                            <i data-lucide="file-text" style="width: 18px; height: 18px;"></i>
                            Minhas Candidaturas
                        </a>
                    <?php endif; ?>
                    
                    <span class="header-welcome">
                        <i data-lucide="smile" style="width: 18px; height: 18px;"></i>
                        Olá, 
                        <?php 
                        if ($_SESSION['user_type'] === 'empresa') {
                            $stmt_empresa = $pdo->prepare("SELECT nome_empresa FROM empresa WHERE id = ?");
                            $stmt_empresa->execute([$_SESSION['user_id']]);
                            $empresa = $stmt_empresa->fetch();
                            echo htmlspecialchars($empresa['nome_empresa'] ?? 'Empresa');
                        } else {
                            $stmt_candidato = $pdo->prepare("SELECT nome_completo FROM candidato WHERE id = ?");
                            $stmt_candidato->execute([$_SESSION['user_id']]);
                            $candidato = $stmt_candidato->fetch();
                            echo htmlspecialchars($candidato['nome_completo'] ?? 'Candidato');
                        }
                        ?>
                    </span>
                    <a href="auth/logout.php">
                        <i data-lucide="log-out" style="width: 18px; height: 18px;"></i>
                        Sair
                    </a>
                <?php else: ?>
                    <a href="auth/login.php">
                        <i data-lucide="log-in" style="width: 18px; height: 18px;"></i>
                        Login
                    </a>
                    <a href="auth/register.php">
                        <i data-lucide="user-plus" style="width: 18px; height: 18px;"></i>
                        Registar
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <!-- 🌅 HERO SECTION -->
        <div class="hero">
            <div class="hero-content">
                <h1>Encontre o Emprego dos Seus Sonhos em Moçambique</h1>
                <p>Conectamos talentos moçambicanos com as melhores oportunidades em todas as províncias</p>
                
                <!-- 🔍 BUSCA RÁPIDA -->
                <div class="search-box">
                    <form action="vagas.php" method="GET" class="search-form">
                        <input type="text" name="pesquisa" class="search-input"
                               placeholder="Cargo, palavra-chave ou empresa..." />
                        <button type="submit" class="btn-search">
                            <i data-lucide="search"></i>
                            Buscar Vagas
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- 📊 ESTATÍSTICAS -->
        <div class="stats">
            <div class="stat-item">
                <span class="stat-number"><?php echo $total_vagas; ?></span>
                <span class="stat-label">Vagas Ativas</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo $total_empresas; ?></span>
                <span class="stat-label">Empresas Parceiras</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo count($provincias_mocambique); ?></span>
                <span class="stat-label">Províncias</span>
            </div>
        </div>

        <!-- 🌊 OCEAN DIVIDER -->
        <div class="ocean-divider">
            <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                      style="fill: var(--gradient-oceano); opacity: 0.8;"></path>
                <defs>
                    <linearGradient id="gradient-oceano" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:#1E3A5F;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#2B7A4B;stop-opacity:1" />
                    </linearGradient>
                </defs>
            </svg>
        </div>

        <!-- 🏷️ BUSCAR POR ÁREA -->
        <section class="section container">
            <h2 class="section-title">Buscar por Área</h2>
            <p class="section-subtitle">Encontre oportunidades no seu sector de actuação</p>
            <div class="quick-links">
                <?php foreach ($areas_populares as $area): ?>
                    <a href="vagas.php?area=<?php echo urlencode($area); ?>" class="quick-link">
                        <i data-lucide="briefcase"></i>
                        <?php echo $area; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 📍 CIDADES PRINCIPAIS -->
        <section class="section container">
            <h2 class="section-title">Cidades Mais Procuradas</h2>
            <p class="section-subtitle">Vagas nas principais cidades de Moçambique</p>
            <div class="quick-links">
                <?php foreach ($cidades_principais as $cidade): ?>
                    <a href="vagas.php?localizacao=<?php echo urlencode($cidade); ?>" class="quick-link">
                        <i data-lucide="map-pin"></i>
                        <?php echo $cidade; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 🗺️ TODAS AS PROVÍNCIAS -->
        <section class="section container">
            <h2 class="section-title">Todas as Províncias de Moçambique</h2>
            <p class="section-subtitle">De Maputo ao Rovuma - oportunidades em todo o país</p>
            <div class="quick-links">
                <?php foreach ($provincias_mocambique as $provincia): ?>
                    <a href="vagas.php?localizacao=<?php echo urlencode($provincia); ?>" class="quick-link">
                        <i data-lucide="<?php echo ($provincia === 'Remoto') ? 'globe' : 'map-pin'; ?>"></i>
                        <?php echo $provincia; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 🌊 OCEAN DIVIDER -->
        <div class="ocean-divider">
            <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none" transform="rotate(180)">
                <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                      style="fill: var(--gradient-oceano); opacity: 0.8;"></path>
            </svg>
        </div>

        <!-- 🔥 VAGAS EM DESTAQUE -->
        <section class="section container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg); flex-wrap: wrap; gap: var(--space-md);">
                <div>
                    <h2 class="section-title" style="text-align: left; margin-bottom: var(--space-xs);">Vagas em Destaque</h2>
                    <p style="color: var(--cinza-baobab);">As oportunidades mais recentes para você</p>
                </div>
                <a href="vagas.php" class="btn" style="background: var(--gradient-oceano); color: var(--branco-puro);">
                    <i data-lucide="arrow-right"></i>
                    Ver todas as <?php echo $total_vagas; ?> vagas
                </a>
            </div>

            <?php if (empty($vagas_recentes)): ?>
                <div class="empty-state">
                    <div style="font-size: 4rem; margin-bottom: var(--space-md); color: var(--cinza-baobab);">
                        <i data-lucide="inbox"></i>
                    </div>
                    <h3>Nenhuma vaga disponível no momento</h3>
                    <p>Volte mais tarde para conferir novas oportunidades!</p>
                </div>
            <?php else: ?>
                <div class="vaga-grid">
                    <?php foreach ($vagas_recentes as $index => $vaga): ?>
                        <?php 
                        // Simulação: vamos dizer que a primeira vaga é urgente
                        $is_urgent = ($index === 0);
                        ?>
                        <div class="xima-card <?php echo $is_urgent ? 'xima-card--urgent' : ''; ?>">
                            <?php if ($is_urgent): ?>
                                <div class="card-urgent-badge">
                                    <i data-lucide="zap" style="width: 14px; height: 14px;"></i>
                                    Urgente
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-header">
                                <div class="card-logo">
                                    <?php if ($vaga['logotipo']): ?>
                                        <img src="<?php echo htmlspecialchars($vaga['logotipo']); ?>" 
                                             alt="<?php echo htmlspecialchars($vaga['nome_empresa']); ?>"
                                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">
                                    <?php else: ?>
                                        <i data-lucide="building" style="width: 30px; height: 30px; color: var(--azul-indico);"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="card-title-section">
                                    <div class="card-title">
                                        <a href="vaga_detalhe.php?id=<?php echo $vaga['id']; ?>">
                                            <?php echo htmlspecialchars($vaga['titulo']); ?>
                                        </a>
                                    </div>
                                    <div class="card-empresa">
                                        <i data-lucide="building" style="width: 16px; height: 16px;"></i>
                                        <?php echo htmlspecialchars($vaga['nome_empresa']); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-meta-group">
                                <div class="card-meta">
                                    <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i>
                                    <?php echo htmlspecialchars($vaga['localizacao']); ?>
                                </div>
                                <div class="card-meta">
                                    <i data-lucide="tag" style="width: 16px; height: 16px;"></i>
                                    <?php echo htmlspecialchars($vaga['area']); ?>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: var(--space-xs); flex-wrap: wrap; margin-bottom: var(--space-md);">
                                <span class="card-badge">
                                    <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                    <?php 
                                        echo [
                                            'tempo_inteiro' => 'Tempo Inteiro',
                                            'tempo_parcial' => 'Tempo Parcial', 
                                            'estagio' => 'Estágio',
                                            'freelance' => 'Freelance'
                                        ][$vaga['tipo_contrato']] ?? $vaga['tipo_contrato'];
                                    ?>
                                </span>
                                <span class="card-badge">
                                    <i data-lucide="monitor" style="width: 14px; height: 14px;"></i>
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
                                <div class="card-salary">
                                    <i data-lucide="dollar-sign" style="width: 20px; height: 20px;"></i>
                                    <?php echo number_format($vaga['salario_estimado'], 0, ',', ' '); ?> MT
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-footer">
                                <span style="display: flex; align-items: center; gap: var(--space-xs);">
                                    <i data-lucide="calendar" style="width: 14px; height: 14px;"></i>
                                    <?php echo date('d/m/Y', strtotime($vaga['data_publicacao'])); ?>
                                </span>
                                <a href="vaga_detalhe.php?id=<?php echo $vaga['id']; ?>" 
                                   style="color: var(--verde-esperanca); font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: var(--space-xs);">
                                    Ver detalhes
                                    <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- 🎯 CALL TO ACTION -->
        <div class="cta-section">
            <div class="cta-content">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['user_type'] === 'empresa'): ?>
                        <h2>Precisa de Talentos para Sua Empresa?</h2>
                        <p>Publique suas vagas e encontre os melhores profissionais em todas as províncias de Moçambique</p>
                        <div class="cta-buttons">
                            <a href="empresa/dashboard.php" class="btn btn-primary">
                                <i data-lucide="bar-chart"></i>
                                Acessar Dashboard
                            </a>
                            <a href="empresa/publicar_vaga.php" class="btn btn-secondary">
                                <i data-lucide="plus-circle"></i>
                                Publicar Nova Vaga
                            </a>
                        </div>
                    <?php else: ?>
                        <h2>Procura por Oportunidades?</h2>
                        <p>Complete seu perfil, adicione suas experiências e formações para se destacar para as empresas</p>
                        <div class="cta-buttons">
                            <a href="candidato/perfil.php" class="btn btn-primary">
                                <i data-lucide="user"></i>
                                Completar Perfil
                            </a>
                            <a href="vagas.php" class="btn btn-secondary">
                                <i data-lucide="search"></i>
                                Ver Todas as Vagas
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <h2>Comece Sua Jornada Profissional Hoje</h2>
                    <p>Junte-se a milhares de profissionais e empresas que já encontraram sucesso na nossa plataforma</p>
                    <div class="cta-buttons">
                        <a href="auth/register.php?tipo=candidato" class="btn btn-primary">
                            <i data-lucide="user-plus"></i>
                            Criar Conta Candidato
                        </a>
                        <a href="auth/register.php?tipo=empresa" class="btn btn-secondary">
                            <i data-lucide="building"></i>
                            Criar Conta Empresa
                        </a>
                    </div>
                    <div style="margin-top: var(--space-md);">
                        <a href="auth/login.php" style="color: var(--branco-puro); text-decoration: underline; opacity: 0.9; display: flex; align-items: center; justify-content: center; gap: var(--space-xs);">
                            <i data-lucide="log-in" style="width: 16px; height: 16px;"></i>
                            Já tem conta? Faça login aqui
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- 📱 FOOTER - Rodapé Completo -->
    <footer class="main-footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3>Sobre a Emprego MZ</h3>
                    <p>Somos a principal plataforma de conexão de talentos e oportunidades em Moçambique, dedicada a impulsionar carreiras e fortalecer empresas em todo o país.</p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><i data-lucide="facebook"></i></a>
                        <a href="#" aria-label="LinkedIn"><i data-lucide="linkedin"></i></a>
                        <a href="#" aria-label="Twitter"><i data-lucide="twitter"></i></a>
                        <a href="#" aria-label="Instagram"><i data-lucide="instagram"></i></a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>Links Rápidos</h3>
                    <ul>
                        <li><a href="vagas.php"><i data-lucide="briefcase" style="width: 16px; height: 16px;"></i> Todas as Vagas</a></li>
                        <li><a href="#"><i data-lucide="help-circle" style="width: 16px; height: 16px;"></i> Como Funciona</a></li>
                        <li><a href="#"><i data-lucide="building" style="width: 16px; height: 16px;"></i> Para Empresas</a></li>
                        <li><a href="#"><i data-lucide="user" style="width: 16px; height: 16px;"></i> Para Candidatos</a></li>
                        <li><a href="auth/register.php"><i data-lucide="user-plus" style="width: 16px; height: 16px;"></i> Criar Conta</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Suporte</h3>
                    <ul>
                        <li><a href="#"><i data-lucide="help-circle" style="width: 16px; height: 16px;"></i> Central de Ajuda</a></li>
                        <li><a href="#"><i data-lucide="file-text" style="width: 16px; height: 16px;"></i> Termos de Uso</a></li>
                        <li><a href="#"><i data-lucide="shield" style="width: 16px; height: 16px;"></i> Política de Privacidade</a></li>
                        <li><a href="#"><i data-lucide="mail" style="width: 16px; height: 16px;"></i> Fale Conosco</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contacto</h3>
                    <p><i data-lucide="mail" style="width: 16px; height: 16px; margin-right: 8px;"></i> geral@empregomz.co.mz</p>
                    <p><i data-lucide="phone" style="width: 16px; height: 16px; margin-right: 8px;"></i> +258 21 123 456</p>
                    <p><i data-lucide="map-pin" style="width: 16px; height: 16px; margin-right: 8px;"></i> Maputo, Moçambique</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Emprego MZ - Todos os direitos reservados.</p>
                <p style="margin-top: 5px;">De Maputo ao Rovuma, construindo o futuro juntos.</p>
            </div>
        </div>
    </footer>

    <!-- ✨ MICRO-INTERACTION SCRIPT -->
    <script>
        // Inicializar ícones Lucide
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            
            // Animação suave para cards ao entrar na viewport
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

            // Aplicar animação aos cards
            const cards = document.querySelectorAll('.xima-card');
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });

            // Contador animado para estatísticas
            const animateCounter = (element) => {
                const target = parseInt(element.textContent);
                const duration = 2000;
                const step = target / (duration / 16);
                let current = 0;

                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) {
                        element.textContent = target;
                        clearInterval(timer);
                    } else {
                        element.textContent = Math.floor(current);
                    }
                }, 16);
            };

            const statNumbers = document.querySelectorAll('.stat-number');
            const statsObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        statsObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            statNumbers.forEach(stat => statsObserver.observe(stat));
        });

        // Efeito parallax suave no hero
        window.addEventListener('scroll', () => {
            const hero = document.querySelector('.hero');
            const scrolled = window.pageYOffset;
            if (hero && scrolled < 500) {
                hero.style.transform = `translateY(${scrolled * 0.3}px)`;
                hero.style.opacity = 1 - (scrolled / 500);
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
    </script>
</body>
</html>