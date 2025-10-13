<?php
session_start();
require_once 'config/db.php';

 $pdo = getPDO();

// Filtros avançados
 $filtro_pesquisa = $_GET['pesquisa'] ?? '';
 $filtro_localizacao = $_GET['localizacao'] ?? '';
 $filtro_area = $_GET['area'] ?? '';
 $filtro_tipo_contrato = $_GET['tipo_contrato'] ?? '';
 $filtro_modalidade = $_GET['modalidade'] ?? '';
 $filtro_nivel_experiencia = $_GET['nivel_experiencia'] ?? '';
 $filtro_salario_min = $_GET['salario_min'] ?? '';

// Construir query base
 $sql = "SELECT v.*, e.nome_empresa, e.logotipo, e.localizacao as localizacao_empresa,
               (SELECT COUNT(*) FROM candidatura c WHERE c.vaga_id = v.id) as total_candidaturas
        FROM vaga v 
        JOIN empresa e ON v.empresa_id = e.id 
        WHERE v.ativa = TRUE AND v.data_expiracao >= CURDATE()";

 $params = [];

// Aplicar filtros
if (!empty($filtro_pesquisa)) {
    $sql .= " AND (v.titulo LIKE ? OR v.descricao LIKE ? OR v.area LIKE ? OR e.nome_empresa LIKE ?)";
    $termo_pesquisa = "%$filtro_pesquisa%";
    $params[] = $termo_pesquisa;
    $params[] = $termo_pesquisa;
    $params[] = $termo_pesquisa;
    $params[] = $termo_pesquisa;
}

if (!empty($filtro_localizacao)) {
    $sql .= " AND (v.localizacao = ? OR e.localizacao = ?)";
    $params[] = $filtro_localizacao;
    $params[] = $filtro_localizacao;
}

if (!empty($filtro_area)) {
    $sql .= " AND v.area = ?";
    $params[] = $filtro_area;
}

if (!empty($filtro_tipo_contrato)) {
    $sql .= " AND v.tipo_contrato = ?";
    $params[] = $filtro_tipo_contrato;
}

if (!empty($filtro_modalidade)) {
    $sql .= " AND v.modalidade = ?";
    $params[] = $filtro_modalidade;
}

if (!empty($filtro_nivel_experiencia)) {
    $sql .= " AND v.nivel_experiencia = ?";
    $params[] = $filtro_nivel_experiencia;
}

if (!empty($filtro_salario_min) && is_numeric($filtro_salario_min)) {
    $sql .= " AND v.salario_estimado >= ?";
    $params[] = $filtro_salario_min;
}

// Ordenação
 $ordenacao = $_GET['ordenacao'] ?? 'recentes';
switch ($ordenacao) {
    case 'salario':
        $sql .= " ORDER BY v.salario_estimado DESC";
        break;
    case 'empresa':
        $sql .= " ORDER BY e.nome_empresa ASC";
        break;
    case 'candidaturas':
        $sql .= " ORDER BY total_candidaturas DESC";
        break;
    default:
        $sql .= " ORDER BY v.data_publicacao DESC";
        break;
}

// Executar query
 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $vagas = $stmt->fetchAll();

// Dados para filtros
 $areas_populares = [
    'TI e Tecnologia', 'Agricultura e Pecuária', 'Construção Civil',
    'Educação e Formação', 'Saúde e Medicina', 'Comércio e Vendas',
    'Hotelaria e Turismo', 'Administração e Secretariado', 'Finanças e Contabilidade',
    'Recursos Humanos', 'Marketing e Publicidade', 'Logística e Transportes',
    'Mineração e Recursos Naturais', 'Pesca e Aquicultura', 'Energia e Água',
    'Telecomunicações', 'Segurança', 'Social e Comunidade'
];

 $localizacoes_mz = [
    'Maputo Cidade', 'Maputo Província', 'Matola', 'Gaza', 'Inhambane', 'Maxixe',
    'Sofala', 'Beira', 'Manica', 'Chimoio', 'Tete', 'Zambézia', 'Quelimane',
    'Nampula', 'Cabo Delgado', 'Pemba', 'Niassa', 'Lichinga', 'Remoto'
];

 $niveis_experiencia = ['Estagiário', 'Júnior', 'Pleno', 'Sénior', 'Gestor', 'Diretor'];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vagas de Emprego - Emprego MZ</title>
    
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
            max-width: 1400px;
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
            color: var(--azul-indico);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }
        
        /* ============================================
           🌅 HERO SECTION - Pôr do Sol de Maputo
        ============================================ */
        .hero {
            background: var(--gradient-oceano);
            color: var(--branco-puro);
            padding: var(--space-xl) var(--space-md);
            text-align: center;
            position: relative;
            overflow: hidden;
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
            margin: 0 auto;
        }
        
        .hero h1 {
            font-family: var(--font-heading);
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            margin-bottom: var(--space-md);
            font-weight: 700;
        }
        
        .search-bar {
            display: flex;
            gap: var(--space-sm);
            background: var(--branco-puro);
            padding: var(--space-xs);
            border-radius: 60px;
            box-shadow: var(--shadow-strong);
            max-width: 800px;
            margin: 0 auto;
        }

        .search-bar input {
            flex: 1;
            padding: var(--space-sm) var(--space-md);
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            outline: none;
            color: var(--carvao);
        }

        .search-bar button {
            background: var(--gradient-por-do-sol);
            color: var(--branco-puro);
            border: none;
            padding: var(--space-sm) var(--space-lg);
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .search-bar button:hover {
            transform: scale(1.05);
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
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-lg) var(--space-md);
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: var(--space-lg);
            align-items: start;
        }

        /* ============================================
           🎛️ SIDEBAR - Filtros Avançados
        ============================================ */
        .sidebar {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-lg);
            box-shadow: var(--shadow-medium);
            position: sticky;
            top: var(--space-md);
            height: fit-content;
        }

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-md);
            padding-bottom: var(--space-md);
            border-bottom: 2px solid var(--areia-quente);
        }

        .sidebar-header h3 {
            font-family: var(--font-heading);
            font-size: 1.3rem;
            color: var(--carvao);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .filter-group {
            margin-bottom: var(--space-md);
        }

        .filter-group label {
            display: block;
            font-weight: 600;
            color: var(--carvao);
            margin-bottom: var(--space-xs);
            font-size: 0.95rem;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: var(--space-sm);
            border: 2px solid var(--areia-quente);
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: var(--branco-marfim);
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--verde-esperanca);
            background: var(--branco-puro);
        }

        .btn-filter {
            width: 100%;
            background: var(--gradient-oceano);
            color: var(--branco-puro);
            border: none;
            padding: var(--space-sm);
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: var(--space-sm);
            transition: transform 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-xs);
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-clear {
            width: 100%;
            background: transparent;
            color: var(--cinza-baobab);
            border: 2px solid var(--cinza-baobab);
            padding: var(--space-sm);
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: block;
            text-align: center;
            transition: all 0.3s;
        }

        .btn-clear:hover {
            background: var(--cinza-baobab);
            color: var(--branco-puro);
        }

        /* ============================================
           📊 RESULTS AREA
        ============================================ */
        .results-area {
            min-width: 0;
        }

        .results-header {
            background: var(--branco-puro);
            padding: var(--space-md);
            border-radius: 16px;
            box-shadow: var(--shadow-soft);
            margin-bottom: var(--space-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--space-md);
        }

        .results-count {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--carvao);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .results-count span {
            background: var(--gradient-por-do-sol);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sort-container {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .sort-select {
            padding: var(--space-sm) var(--space-md);
            border: 2px solid var(--areia-quente);
            border-radius: 50px;
            font-size: 0.95rem;
            background: var(--branco-marfim);
            cursor: pointer;
        }

        /* ============================================
           🎴 XIMA CARDS - Cards com Textura
        ============================================ */
        .vaga-grid {
            display: grid;
            gap: var(--space-lg);
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
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: var(--dourado-sol);
        }

        .xima-card:hover::before {
            opacity: 1;
            transform: scale(1.1);
        }

        .card-header {
            display: flex;
            gap: var(--space-md);
            margin-bottom: var(--space-md);
            padding-bottom: var(--space-md);
            border-bottom: 2px solid var(--areia-quente);
        }

        .card-logo {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            background: var(--gradient-terra);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .card-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 16px;
        }

        .card-main {
            flex: 1;
            min-width: 0;
        }

        .card-title {
            font-family: var(--font-heading);
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--carvao);
            margin-bottom: var(--space-xs);
            line-height: 1.3;
        }

        .card-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s;
        }

        .card-title a:hover {
            color: var(--verde-esperanca);
        }

        .card-company {
            color: var(--azul-indico);
            font-weight: 600;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .card-meta-row {
            display: flex;
            gap: var(--space-md);
            margin-bottom: var(--space-sm);
            flex-wrap: wrap;
            font-size: 0.95rem;
            color: var(--cinza-baobab);
        }

        .card-meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .badge-container {
            display: flex;
            gap: var(--space-xs);
            flex-wrap: wrap;
            margin-bottom: var(--space-md);
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

        .badge-contrato {
            background: linear-gradient(135deg, rgba(43, 122, 75, 0.15), rgba(43, 122, 75, 0.08));
            color: var(--verde-esperanca);
        }

        .badge-modalidade {
            background: linear-gradient(135deg, rgba(30, 58, 95, 0.15), rgba(30, 58, 95, 0.08));
            color: var(--azul-indico);
        }

        .badge-nivel {
            background: linear-gradient(135deg, rgba(255, 176, 59, 0.15), rgba(255, 176, 59, 0.08));
            color: #cc8a2e;
        }

        .card-description {
            color: var(--cinza-baobab);
            line-height: 1.6;
            margin-bottom: var(--space-md);
        }

        .card-salary {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--verde-esperanca);
            padding: var(--space-sm);
            background: linear-gradient(135deg, rgba(43, 122, 75, 0.08), rgba(255, 176, 59, 0.08));
            border-radius: 12px;
            text-align: center;
            margin-bottom: var(--space-md);
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
            font-size: 0.9rem;
            color: var(--cinza-baobab);
            flex-wrap: wrap;
            gap: var(--space-sm);
        }

        .card-footer-meta {
            display: flex;
            gap: var(--space-md);
            flex-wrap: wrap;
        }

        .card-footer-meta span {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .card-link {
            color: var(--verde-esperanca);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .card-link:hover {
            color: var(--dourado-sol);
        }

        /* Card Destaque - Estado Especial */
        .xima-card--destaque {
            border-left: 4px solid var(--coral-vivo);
        }

        .card-destaque-badge {
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
           🎭 EMPTY STATE
        ============================================ */
        .empty-state {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-xl);
            text-align: center;
            box-shadow: var(--shadow-soft);
        }

        .empty-state-icon {
            font-size: 5rem;
            margin-bottom: var(--space-md);
            color: var(--cinza-baobab);
        }

        .empty-state h3 {
            font-family: var(--font-heading);
            font-size: 1.8rem;
            color: var(--carvao);
            margin-bottom: var(--space-sm);
        }

        .empty-state p {
            color: var(--cinza-baobab);
            font-size: 1.1rem;
        }

        .empty-state a {
            color: var(--verde-esperanca);
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .empty-state a:hover {
            color: var(--dourado-sol);
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
           📱 MOBILE RESPONSIVE
        ============================================ */
        .filter-toggle {
            display: none;
            width: 100%;
            background: var(--gradient-oceano);
            color: var(--branco-puro);
            border: none;
            padding: var(--space-md);
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: var(--space-md);
            box-shadow: var(--shadow-medium);
            justify-content: center;
            align-items: center;
            gap: var(--space-xs);
        }

        @media (max-width: 1024px) {
            .main-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
                display: none;
            }

            .sidebar.active {
                display: block;
            }

            .filter-toggle {
                display: flex;
            }
        }

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
            .search-bar {
                flex-direction: column;
                border-radius: 20px;
            }

            .search-bar button {
                width: 100%;
            }

            .results-header {
                flex-direction: column;
                align-items: stretch;
            }

            .sort-container {
                width: 100%;
            }

            .sort-select {
                width: 100%;
            }

            .card-header {
                flex-direction: column;
            }

            .card-footer {
                flex-direction: column;
                align-items: flex-start;
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
        .btn-filter:focus, .btn-clear:focus, .sort-select:focus, 
        .filter-group select:focus, .filter-group input:focus {
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
                <a href="vagas.php" style="color: var(--verde-esperanca); font-weight: 700;">
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
                <h1>Todas as Vagas de Emprego em Moçambique</h1>
                <form method="GET" class="search-bar">
                    <input type="text" name="pesquisa" 
                           placeholder="Procurar por cargo, empresa ou palavra-chave..." 
                           value="<?php echo htmlspecialchars($filtro_pesquisa); ?>">
                    <button type="submit">
                        <i data-lucide="search"></i>
                        Buscar
                    </button>
                </form>
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

        <!-- 📦 CONTAINER PRINCIPAL -->
        <div class="main-container">
            <!-- Mobile toggle -->
            <button class="filter-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
                <i data-lucide="filter"></i>
                Filtros Avançados
            </button>

            <!-- 🎛️ SIDEBAR DE FILTROS -->
            <aside class="sidebar">
                <div class="sidebar-header">
                    <h3>
                        <i data-lucide="filter"></i>
                        Filtros
                    </h3>
                    <?php if (!empty($_GET) && count(array_filter($_GET)) > 0): ?>
                        <span style="background: var(--coral-vivo); color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                            <?php echo count(array_filter($_GET)); ?> ativos
                        </span>
                    <?php endif; ?>
                </div>

                <form method="GET">
                    <div class="filter-group">
                        <label>
                            <i data-lucide="map-pin" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"></i>
                            Localização
                        </label>
                        <select name="localizacao">
                            <option value="">Todas</option>
                            <?php foreach ($localizacoes_mz as $local): ?>
                                <option value="<?php echo $local; ?>" <?php echo $filtro_localizacao === $local ? 'selected' : ''; ?>>
                                    <?php echo $local; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>
                            <i data-lucide="briefcase" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"></i>
                            Área de Atuação
                        </label>
                        <select name="area">
                            <option value="">Todas</option>
                            <?php foreach ($areas_populares as $area): ?>
                                <option value="<?php echo $area; ?>" <?php echo $filtro_area === $area ? 'selected' : ''; ?>>
                                    <?php echo $area; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>
                            <i data-lucide="clock" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"></i>
                            Tipo de Contrato
                        </label>
                        <select name="tipo_contrato">
                            <option value="">Todos</option>
                            <option value="tempo_inteiro" <?php echo $filtro_tipo_contrato === 'tempo_inteiro' ? 'selected' : ''; ?>>Tempo Inteiro</option>
                            <option value="tempo_parcial" <?php echo $filtro_tipo_contrato === 'tempo_parcial' ? 'selected' : ''; ?>>Tempo Parcial</option>
                            <option value="estagio" <?php echo $filtro_tipo_contrato === 'estagio' ? 'selected' : ''; ?>>Estágio</option>
                            <option value="freelance" <?php echo $filtro_tipo_contrato === 'freelance' ? 'selected' : ''; ?>>Freelance</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>
                            <i data-lucide="monitor" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"></i>
                            Modalidade
                        </label>
                        <select name="modalidade">
                            <option value="">Todas</option>
                            <option value="presencial" <?php echo $filtro_modalidade === 'presencial' ? 'selected' : ''; ?>>Presencial</option>
                            <option value="hibrido" <?php echo $filtro_modalidade === 'hibrido' ? 'selected' : ''; ?>>Híbrido</option>
                            <option value="remoto" <?php echo $filtro_modalidade === 'remoto' ? 'selected' : ''; ?>>Remoto</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>
                            <i data-lucide="award" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"></i>
                            Nível de Experiência
                        </label>
                        <select name="nivel_experiencia">
                            <option value="">Todos</option>
                            <?php foreach ($niveis_experiencia as $nivel): ?>
                                <option value="<?php echo $nivel; ?>" <?php echo $filtro_nivel_experiencia === $nivel ? 'selected' : ''; ?>>
                                    <?php echo $nivel; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>
                            <i data-lucide="dollar-sign" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"></i>
                            Salário Mínimo (MT)
                        </label>
                        <input type="number" name="salario_min" placeholder="Ex: 15000" 
                               value="<?php echo htmlspecialchars($filtro_salario_min); ?>">
                    </div>

                    <button type="submit" class="btn-filter">
                        <i data-lucide="check"></i>
                        Aplicar Filtros
                    </button>
                    <a href="vagas.php" class="btn-clear">
                        <i data-lucide="x-circle"></i>
                        Limpar Filtros
                    </a>
                </form>
            </aside>

            <!-- 📊 ÁREA DE RESULTADOS -->
            <main class="results-area">
                <div class="results-header">
                    <div class="results-count">
                        <i data-lucide="briefcase"></i>
                        <span><?php echo count($vagas); ?></span> vagas encontradas
                    </div>

                    <div class="sort-container">
                        <form method="GET" style="display: flex; align-items: center; gap: 10px;">
                            <?php foreach ($_GET as $key => $value): ?>
                                <?php if ($key !== 'ordenacao'): ?>
                                    <input type="hidden" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <label style="font-size: 0.95rem; color: var(--cinza-baobab); display: flex; align-items: center; gap: 4px;">
                                <i data-lucide="arrow-up-down" style="width: 16px; height: 16px;"></i>
                                Ordenar:
                            </label>
                            <select name="ordenacao" class="sort-select" onchange="this.form.submit()">
                                <option value="recentes" <?php echo $ordenacao === 'recentes' ? 'selected' : ''; ?>>Mais Recentes</option>
                                <option value="salario" <?php echo $ordenacao === 'salario' ? 'selected' : ''; ?>>Maior Salário</option>
                                <option value="empresa" <?php echo $ordenacao === 'empresa' ? 'selected' : ''; ?>>Nome da Empresa</option>
                                <option value="candidaturas" <?php echo $ordenacao === 'candidaturas' ? 'selected' : ''; ?>>Mais Candidaturas</option>
                            </select>
                        </form>
                    </div>
                </div>

                <?php if (empty($vagas)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i data-lucide="inbox"></i>
                        </div>
                        <h3>Nenhuma vaga encontrada</h3>
                        <p>Tente ajustar os filtros ou <a href="vagas.php">limpar todos os filtros</a></p>
                    </div>
                <?php else: ?>
                    <div class="vaga-grid">
                        <?php foreach ($vagas as $index => $vaga): ?>
                            <?php 
                            // Simulação: vamos dizer que as primeiras 2 vagas são destaques
                            $is_destaque = ($index < 2);
                            ?>
                            <div class="xima-card <?php echo $is_destaque ? 'xima-card--destaque' : ''; ?>">
                                <?php if ($is_destaque): ?>
                                    <div class="card-destaque-badge">
                                        <i data-lucide="star" style="width: 14px; height: 14px;"></i>
                                        Destaque
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-header">
                                    <div class="card-logo">
                                        <?php if ($vaga['logotipo']): ?>
                                            <img src="<?php echo htmlspecialchars($vaga['logotipo']); ?>" 
                                                 alt="<?php echo htmlspecialchars($vaga['nome_empresa']); ?>">
                                        <?php else: ?>
                                            <i data-lucide="building" style="width: 32px; height: 32px; color: var(--azul-indico);"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-main">
                                        <div class="card-title">
                                            <a href="vaga_detalhe.php?id=<?php echo $vaga['id']; ?>">
                                                <?php echo htmlspecialchars($vaga['titulo']); ?>
                                            </a>
                                        </div>
                                        <div class="card-company">
                                            <i data-lucide="building" style="width: 16px; height: 16px;"></i>
                                            <?php echo htmlspecialchars($vaga['nome_empresa']); ?>
                                        </div>
                                        <div class="card-meta-row">
                                            <div class="card-meta-item">
                                                <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i>
                                                <?php echo htmlspecialchars($vaga['localizacao'] ?: $vaga['localizacao_empresa']); ?>
                                            </div>
                                            <div class="card-meta-item">
                                                <i data-lucide="tag" style="width: 16px; height: 16px;"></i>
                                                <?php echo htmlspecialchars($vaga['area']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="badge-container">
                                    <span class="badge badge-contrato">
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
                                    <span class="badge badge-modalidade">
                                        <i data-lucide="monitor" style="width: 14px; height: 14px;"></i>
                                        <?php 
                                            echo [
                                                'presencial' => 'Presencial',
                                                'hibrido' => 'Híbrido',
                                                'remoto' => 'Remoto'
                                            ][$vaga['modalidade']] ?? $vaga['modalidade'];
                                        ?>
                                    </span>
                                    <?php if ($vaga['nivel_experiencia']): ?>
                                        <span class="badge badge-nivel">
                                            <i data-lucide="award" style="width: 14px; height: 14px;"></i>
                                            <?php echo htmlspecialchars($vaga['nivel_experiencia']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($vaga['salario_estimado']): ?>
                                    <div class="card-salary">
                                        <i data-lucide="dollar-sign" style="width: 20px; height: 20px;"></i>
                                        <?php echo number_format($vaga['salario_estimado'], 0, ',', ' '); ?> MT
                                    </div>
                                <?php endif; ?>

                                <div class="card-description">
                                    <?php 
                                    $descricao = strip_tags($vaga['descricao']);
                                    echo strlen($descricao) > 200 ? substr($descricao, 0, 200) . '...' : $descricao;
                                    ?>
                                </div>

                                <div class="card-footer">
                                    <div class="card-footer-meta">
                                        <span>
                                            <i data-lucide="calendar" style="width: 14px; height: 14px;"></i>
                                            <?php echo date('d/m/Y', strtotime($vaga['data_publicacao'])); ?>
                                        </span>
                                        <?php if ($vaga['data_expiracao']): ?>
                                            <span>
                                                <i data-lucide="hourglass" style="width: 14px; height: 14px;"></i>
                                                Expira: <?php echo date('d/m/Y', strtotime($vaga['data_expiracao'])); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span>
                                            <i data-lucide="users" style="width: 14px; height: 14px;"></i>
                                            <?php echo $vaga['total_candidaturas']; ?> candidatura(s)
                                        </span>
                                    </div>
                                    <a href="vaga_detalhe.php?id=<?php echo $vaga['id']; ?>" class="card-link">
                                        Ver detalhes
                                        <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
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
            
            // Animação de entrada dos cards
            const cards = document.querySelectorAll('.xima-card');
            
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
                        }, index * 80);
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(card);
            });

            // Smooth scroll para o topo ao mudar ordenação
            const sortSelect = document.querySelector('.sort-select');
            if (sortSelect) {
                sortSelect.addEventListener('change', () => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }

            // Fechar sidebar mobile ao aplicar filtros
            const filterForm = document.querySelector('.sidebar form');
            if (filterForm && window.innerWidth <= 1024) {
                filterForm.addEventListener('submit', () => {
                    document.querySelector('.sidebar').classList.remove('active');
                });
            }
        });

        // Adicionar indicador de filtros ativos no mobile
        const activeFilters = <?php echo count(array_filter($_GET)); ?>;
        if (activeFilters > 0 && window.innerWidth <= 1024) {
            const toggleBtn = document.querySelector('.filter-toggle');
            if (toggleBtn) {
                toggleBtn.innerHTML = `
                    <i data-lucide="filter"></i>
                    Filtros Avançados 
                    <span style="background: var(--coral-vivo); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; margin-left: 8px;">
                        ${activeFilters}
                    </span>
                `;
                // Re-inicializar ícones após alterar o HTML
                lucide.createIcons();
            }
        }
    </script>
</body>
</html>