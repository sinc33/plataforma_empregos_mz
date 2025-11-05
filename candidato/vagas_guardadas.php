<?php
session_start();
require_once '../config/db.php';

// Verificar se está logado e é candidato
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidato') {
    header("Location: ../auth/login.php");
    exit;
}

$candidato_id = $_SESSION['user_id'];

// ========================================
// BUSCAR VAGAS GUARDADAS
// ========================================
$sql = "SELECT vg.id as guardada_id, vg.data_guardada,
               v.id, v.titulo, v.descricao, v.area, v.localizacao, 
               v.salario_estimado, v.modalidade, v.data_publicacao, v.data_expiracao,
               e.nome_empresa, e.logotipo
        FROM vaga_guardada vg
        JOIN vaga v ON vg.vaga_id = v.id
        JOIN empresa e ON v.empresa_id = e.id
        WHERE vg.candidato_id = ?
        ORDER BY vg.data_guardada DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$candidato_id]);
$vagas_guardadas = $stmt->fetchAll();

// ========================================
// ESTATÍSTICAS
// ========================================
$total_guardadas = count($vagas_guardadas);

// Contar vagas ativas vs expiradas
$vagas_ativas = 0;
$vagas_expiradas = 0;
foreach ($vagas_guardadas as $vaga) {
    if ($vaga['data_expiracao'] >= date('Y-m-d')) {
        $vagas_ativas++;
    } else {
        $vagas_expiradas++;
    }
}

// Funções auxiliares
function formatarSalario($salario) {
    if (empty($salario) || $salario == 0) {
        return 'À combinar';
    }
    return number_format($salario, 2, ',', '.') . ' MT';
}

function traduzirModalidade($modalidade) {
    $traducoes = [
        'presencial' => 'Presencial',
        'hibrido' => 'Híbrido',
        'remoto' => 'Remoto'
    ];
    return $traducoes[$modalidade] ?? $modalidade;
}

function tempoDecorrido($data) {
    $agora = new DateTime();
    $publicacao = new DateTime($data);
    $diferenca = $agora->diff($publicacao);

    if ($diferenca->days == 0) {
        return 'Hoje';
    } elseif ($diferenca->days == 1) {
        return 'Há 1 dia';
    } elseif ($diferenca->days < 7) {
        return 'Há ' . $diferenca->days . ' dias';
    } elseif ($diferenca->days < 30) {
        $semanas = floor($diferenca->days / 7);
        return 'Há ' . $semanas . ($semanas > 1 ? ' semanas' : ' semana');
    } else {
        $meses = floor($diferenca->days / 30);
        return 'Há ' . $meses . ($meses > 1 ? ' meses' : ' mês');
    }
}

function isVagaExpirada($data_expiracao) {
    return $data_expiracao < date('Y-m-d');
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vagas Guardadas - Emprego MZ</title>
    
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
            /* Cores principais */
            --cor-primaria: #14213d;
            --cor-primaria-escura: #0f1a2e;
            --cor-primaria-clara: #1e2c47;
            --cor-primaria-alpha: rgba(20, 33, 61, 0.1);
            
            --cor-secundaria: #fca311;
            --cor-secundaria-hover: #e3940f;
            --cor-secundaria-alpha: rgba(252, 163, 17, 0.1);
            
            /* Hierarquia visual */
            --cor-fundo: #f8fafc;
            --cor-cards: #ffffff;
            --cor-acento: #f1f5ff;
            --cor-acento-escuro: #e1ebff;
            
            /* Texto */
            --cor-texto: #1a202c;
            --cor-texto-claro: #64748b;
            --cor-texto-muito-claro: #94a3b8;
            
            /* Estados */
            --cor-sucesso: #10B981;
            --cor-erro: #EF4444;
            --cor-aviso: #F59E0B;
            --cor-salario: #059669;
            
            /* Bordas e sombras */
            --cor-borda: #e2e8f0;
            --cor-borda-clara: #f1f5f9;
            --cor-borda-ativa: #fca311;
            --sombra-card: 0 2px 8px rgba(20, 33, 61, 0.08);
            --sombra-card-hover: 0 8px 24px rgba(20, 33, 61, 0.15);
            --sombra-filtros: 0 1px 3px rgba(20, 33, 61, 0.06);
            
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
            
            /* Borders */
            --radius: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-full: 9999px;
            
            /* Transitions */
            --transition: all 0.2s ease;
        }

        body {
            font-family: var(--font-primary);
            color: var(--cor-texto);
            background: var(--cor-fundo);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ==========================================
           📱 HEADER
        ========================================== */
        .header {
            background: var(--cor-primaria);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: var(--sombra-card);
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
            gap: var(--space-10);
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--cor-secundaria);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--cor-secundaria);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }

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

        .btn-outline {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: var(--white);
        }

        .btn-outline:hover {
            border-color: var(--cor-secundaria);
            color: var(--cor-secundaria);
            background: rgba(252, 163, 17, 0.1);
        }

        /* ==========================================
           🍞 BREADCRUMB
        ========================================== */
        .breadcrumb {
            background: var(--cor-cards);
            border-bottom: 1px solid var(--cor-borda);
            margin-top: 70px;
            box-shadow: var(--sombra-filtros);
        }

        .breadcrumb-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-3) var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 14px;
            color: var(--cor-texto-claro);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .breadcrumb-container {
                max-width: 1300px;
                padding: var(--space-3) var(--space-8);
            }
        }

        .breadcrumb-link {
            color: var(--cor-primaria);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb-link:hover {
            color: var(--cor-secundaria);
            text-decoration: underline;
        }

        .breadcrumb-separator {
            width: 16px;
            height: 16px;
            color: var(--cor-texto-muito-claro);
        }

        /* ==========================================
           📋 MAIN CONTAINER
        ========================================== */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-8) var(--space-6);
        }

        /* Otimização para 1366x768 e resoluções similares */
        @media (max-width: 1500px) {
            .main-container {
                max-width: 1300px;                  /* Otimizado para 1366px */
                padding: var(--space-6) var(--space-8);
            }
        }

        /* ==========================================
           📊 HEADER DO PAINEL
        ========================================== */
        .page-header {
            margin-bottom: var(--space-8);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .page-header {
                margin-bottom: var(--space-6);       /* Menos espaço */
            }
        }

        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--cor-primaria);
            margin-bottom: var(--space-2);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .page-title {
                font-size: 28px;                     /* Título menor */
            }
        }

        .page-title i {
            width: 40px;
            height: 40px;
            color: var(--cor-secundaria);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .page-title i {
                width: 36px;
                height: 36px;
            }
        }

        .page-subtitle {
            font-size: 16px;
            color: var(--cor-texto-claro);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .page-subtitle {
                font-size: 14px;
            }
        }

        /* ==========================================
           📊 STATS CARDS
        ========================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);   /* 3 colunas por padrão */
            gap: var(--space-6);
            margin-bottom: var(--space-8);
        }

        /* Otimização para 1366x768 - mantém 3 colunas mas ajusta espaçamento */
        @media (max-width: 1500px) {
            .stats-grid {
                gap: var(--space-5);
                margin-bottom: var(--space-6);
            }
        }

        .stat-card {
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            box-shadow: var(--sombra-card);
            transition: var(--transition);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .stat-card {
                padding: var(--space-5);             /* Padding menor */
            }
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--sombra-card-hover);
        }

        .stat-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--space-3);
        }

        .stat-card-icon {
            width: 48px;
            height: 48px;
            background: var(--cor-primaria-alpha);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--cor-primaria);
        }

        .stat-card-icon.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--cor-sucesso);
        }

        .stat-card-icon.warning {
            background: var(--cor-secundaria-alpha);
            color: var(--cor-secundaria);
        }

        .stat-card-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--cor-primaria);
            margin-bottom: var(--space-1);
        }

        .stat-card-label {
            font-size: 14px;
            color: var(--cor-texto-claro);
            font-weight: 500;
        }

        /* ==========================================
           💼 JOB CARDS
        ========================================== */
        .jobs-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);   /* 2 colunas por padrão */
            gap: var(--space-6);
        }

        /* Desktop muito grande - 3 colunas */
        @media (min-width: 1700px) {
            .jobs-list {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Desktop médio (1366x768) - 2 colunas otimizadas */
        @media (max-width: 1500px) {
            .jobs-list {
                grid-template-columns: repeat(2, 1fr);  /* 2 colunas confortáveis */
                gap: var(--space-5);                    /* Gap um pouco menor */
            }
        }

        .job-card {
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            box-shadow: var(--sombra-card);
            transition: all 0.3s ease;
            position: relative;
            min-height: 380px;                          /* Altura mínima para uniformidade */
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .job-card {
                padding: var(--space-5);                /* Padding um pouco menor */
                min-height: 360px;                      /* Altura otimizada */
            }
        }

        .job-card:hover {
            background: var(--cor-acento);
            box-shadow: var(--sombra-card-hover);
            transform: translateY(-2px);
            border-color: var(--cor-borda-ativa);
        }

        .job-card.expired {
            opacity: 0.6;
        }

        .job-card-header {
            display: flex;
            gap: var(--space-4);
            margin-bottom: var(--space-4);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .job-card-header {
                gap: var(--space-3);
                margin-bottom: var(--space-3);
            }
        }

        .company-logo {
            width: 64px;
            height: 64px;
            flex-shrink: 0;
            border-radius: var(--radius);
            border: 1px solid var(--cor-borda);
            overflow: hidden;
            background: var(--cor-acento);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .company-logo {
                width: 56px;
                height: 56px;
            }
        }

        .company-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .job-card-content {
            flex: 1;
            min-width: 0;
        }

        .job-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--cor-primaria);
            text-decoration: none;
            display: block;
            margin-bottom: var(--space-1);
            transition: var(--transition);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .job-title {
                font-size: 17px;
            }
        }

        .job-title:hover {
            color: var(--cor-secundaria);
            text-decoration: underline;
        }

        .company-name {
            font-size: 15px;
            color: var(--cor-primaria);
            font-weight: 600;
            margin-bottom: var(--space-3);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .company-name {
                font-size: 14px;
                margin-bottom: var(--space-2);
            }
        }

        .job-badges {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
            margin-bottom: var(--space-4);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .job-badges {
                margin-bottom: var(--space-3);
            }
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-1);
            padding: 4px var(--space-2);
            border-radius: var(--radius);
            font-size: 12px;
            font-weight: 600;
        }

        .badge-expired {
            background: rgba(239, 68, 68, 0.1);
            color: var(--cor-erro);
            border: 1px solid var(--cor-erro);
        }

        .badge-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--cor-sucesso);
            border: 1px solid var(--cor-sucesso);
        }

        .badge-remote {
            background: var(--cor-acento-escuro);
            color: var(--cor-primaria);
            border: 1px solid var(--cor-primaria);
        }

        .job-info {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-4);
            padding: var(--space-4) 0;
            border-top: 1px solid var(--cor-borda);
            border-bottom: 1px solid var(--cor-borda);
            margin-bottom: var(--space-4);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .job-info {
                gap: var(--space-3);
                padding: var(--space-3) 0;
                margin-bottom: var(--space-3);
            }
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 14px;
            color: var(--cor-texto-claro);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .info-item {
                font-size: 13px;
            }
        }

        .info-item svg {
            width: 18px;
            height: 18px;
            color: var(--cor-primaria);
            flex-shrink: 0;
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .info-item svg {
                width: 16px;
                height: 16px;
            }
        }

        .info-item.salary {
            font-weight: 700;
            font-size: 16px;
            color: var(--cor-salario);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .info-item.salary {
                font-size: 15px;
            }
        }

        .job-description {
            font-size: 14px;
            line-height: 1.6;
            color: var(--cor-texto-claro);
            margin-bottom: var(--space-4);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .job-description {
                font-size: 13px;
                line-height: 1.5;
                margin-bottom: var(--space-3);
            }
        }

        .job-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-4);
            flex-wrap: wrap;
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .job-card-footer {
                gap: var(--space-3);
            }
        }

        .job-time {
            display: flex;
            flex-direction: column;
            gap: var(--space-1);
            font-size: 13px;
            color: var(--cor-texto-muito-claro);
        }

        .job-time-label {
            font-weight: 600;
            color: var(--cor-texto-claro);
        }

        .job-card-actions {
            display: flex;
            gap: var(--space-2);
        }

        .btn-secondary {
            padding: var(--space-3) var(--space-4);
            border: 2px solid var(--cor-primaria);
            background: transparent;
            color: var(--cor-primaria);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            cursor: pointer;
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .btn-secondary {
                padding: var(--space-2) var(--space-3);
                font-size: 13px;
            }
        }

        .btn-secondary:hover {
            background: var(--cor-primaria);
            color: white;
        }

        .btn-remove {
            padding: var(--space-3) var(--space-4);
            border: 2px solid var(--cor-erro);
            background: transparent;
            color: var(--cor-erro);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .btn-remove {
                padding: var(--space-2) var(--space-3);
                font-size: 13px;
            }
        }

        .btn-remove:hover {
            background: var(--cor-erro);
            color: white;
        }

        .btn-primary {
            padding: var(--space-3) var(--space-5);
            border: 2px solid var(--cor-secundaria);
            background: var(--cor-secundaria);
            color: white;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .btn-primary {
                padding: var(--space-2) var(--space-4);
                font-size: 13px;
            }
        }

        .btn-primary:hover {
            background: var(--cor-secundaria-hover);
            border-color: var(--cor-secundaria-hover);
            transform: translateY(-1px);
        }

        /* ==========================================
           ⚠️ EMPTY STATE
        ========================================== */
        .empty-state {
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius-lg);
            padding: var(--space-16);
            text-align: center;
            box-shadow: var(--sombra-card);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .empty-state {
                padding: var(--space-12);
            }
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto var(--space-6);
            color: var(--cor-texto-muito-claro);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .empty-state-icon {
                width: 64px;
                height: 64px;
                margin: 0 auto var(--space-5);
            }
        }

        .empty-state h3 {
            font-size: 22px;
            font-weight: 700;
            color: var(--cor-primaria);
            margin-bottom: var(--space-3);
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .empty-state h3 {
                font-size: 20px;
            }
        }

        .empty-state p {
            font-size: 15px;
            color: var(--cor-texto-claro);
            margin-bottom: var(--space-6);
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Otimização para 1366x768 */
        @media (max-width: 1500px) {
            .empty-state p {
                font-size: 14px;
                margin-bottom: var(--space-5);
            }
        }

        /* ==========================================
           🔔 NOTIFICAÇÕES
        ========================================== */
        .notification {
            position: fixed;
            top: 90px;
            right: var(--space-6);
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
            border-left: 4px solid var(--cor-primaria);
            border-radius: var(--radius-lg);
            padding: var(--space-4) var(--space-5);
            box-shadow: var(--sombra-card-hover);
            z-index: 10000;
            min-width: 320px;
            max-width: 400px;
            opacity: 0;
            transform: translateX(400px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .notification.show {
            opacity: 1;
            transform: translateX(0);
        }

        .notification-content {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            color: var(--cor-texto);
            font-size: 14px;
            font-weight: 500;
        }

        .notification-content i {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
        }

        .notification-success {
            border-left-color: var(--cor-sucesso);
        }

        .notification-success .notification-content i {
            color: var(--cor-sucesso);
        }

        .notification-error {
            border-left-color: var(--cor-erro);
        }

        .notification-error .notification-content i {
            color: var(--cor-erro);
        }

        /* ==========================================
           🎭 MODAL DE CONFIRMAÇÃO
        ========================================== */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.75);     /* Fundo escuro semi-transparente */
            backdrop-filter: blur(4px);              /* Efeito blur no fundo */
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: var(--space-4);
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-container {
            background: var(--cor-cards);
            border-radius: var(--radius-xl);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            max-width: 480px;
            width: 100%;
            transform: scale(0.95) translateY(-20px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        .modal-overlay.active .modal-container {
            transform: scale(1) translateY(0);
        }

        .modal-header {
            padding: var(--space-6);
            border-bottom: 1px solid var(--cor-borda);
            display: flex;
            align-items: center;
            gap: var(--space-4);
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.05), rgba(239, 68, 68, 0.02));
        }

        .modal-icon {
            width: 56px;
            height: 56px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .modal-icon i {
            width: 28px;
            height: 28px;
            color: var(--cor-erro);
        }

        .modal-title {
            flex: 1;
            min-width: 0;
        }

        .modal-title h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--cor-primaria);
            margin-bottom: var(--space-1);
        }

        .modal-title p {
            font-size: 14px;
            color: var(--cor-texto-claro);
        }

        .modal-body {
            padding: var(--space-6);
        }

        .modal-body p {
            font-size: 15px;
            line-height: 1.6;
            color: var(--cor-texto-claro);
            margin-bottom: var(--space-4);
        }

        .modal-highlight {
            background: var(--cor-acento);
            border-left: 3px solid var(--cor-primaria);
            padding: var(--space-4);
            border-radius: var(--radius);
            margin-bottom: var(--space-4);
        }

        .modal-highlight strong {
            color: var(--cor-primaria);
            font-weight: 600;
            display: block;
            margin-bottom: var(--space-1);
        }

        .modal-highlight p {
            font-size: 14px;
            color: var(--cor-texto-claro);
            margin-bottom: 0;
        }

        .modal-footer {
            padding: var(--space-6);
            border-top: 1px solid var(--cor-borda);
            display: flex;
            gap: var(--space-3);
            justify-content: flex-end;
            background: var(--cor-fundo);
        }

        .modal-btn {
            padding: var(--space-3) var(--space-6);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
        }

        .modal-btn-cancel {
            background: transparent;
            color: var(--cor-texto-claro);
            border: 2px solid var(--cor-borda);
        }

        .modal-btn-cancel:hover {
            background: var(--cor-acento);
            color: var(--cor-primaria);
            border-color: var(--cor-primaria);
        }

        .modal-btn-confirm {
            background: var(--cor-erro);
            color: white;
            border: 2px solid var(--cor-erro);
        }

        .modal-btn-confirm:hover {
            background: #dc2626;
            border-color: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .modal-btn-confirm:active {
            transform: translateY(0);
        }

        .modal-btn i {
            width: 18px;
            height: 18px;
        }

        /* Mobile */
        @media (max-width: 768px) {
            .modal-container {
                max-width: 100%;
                border-radius: var(--radius-lg);
            }

            .modal-header {
                padding: var(--space-5);
            }

            .modal-icon {
                width: 48px;
                height: 48px;
            }

            .modal-icon i {
                width: 24px;
                height: 24px;
            }

            .modal-title h3 {
                font-size: 18px;
            }

            .modal-body {
                padding: var(--space-5);
            }

            .modal-footer {
                padding: var(--space-5);
                flex-direction: column-reverse;
            }

            .modal-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* ==========================================
           📱 RESPONSIVE
        ========================================== */
        @media (max-width: 768px) {
            .header-container {
                padding: var(--space-3) var(--space-4);
            }

            .nav-menu {
                display: none;
            }

            .main-container {
                padding: var(--space-6) var(--space-4);
            }

            .page-title {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .jobs-list {
                grid-template-columns: 1fr;
            }

            .job-card-footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .job-card-actions {
                width: 100%;
                flex-direction: column;
            }

            .btn-secondary,
            .btn-remove,
            .btn-primary {
                width: 100%;
                justify-content: center;
            }

            .notification {
                top: 80px;
                right: var(--space-4);
                left: var(--space-4);
                min-width: auto;
                max-width: none;
            }
        }
    </style>
</head>
<body>

    <!-- ==========================================
         📱 HEADER
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
                        <a href="perfil.php" class="dropdown-item">
                            <i data-lucide="user"></i>
                            <span>Meu Perfil</span>
                        </a>
                        <a href="vagas_guardadas.php" class="dropdown-item">
                            <i data-lucide="bookmark"></i>
                            <span>Vagas Guardadas</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="candidaturas.php" class="dropdown-item">
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
                        <a href="../empresa/dashboard.php" class="dropdown-item">
                            <i data-lucide="layout-dashboard"></i>
                            <span>Painel de Controle</span>
                        </a>
                        <a href="../empresa/criar_vaga.php" class="dropdown-item">
                            <i data-lucide="plus-circle"></i>
                            <span>Publicar Vaga</span>
                        </a>
                        <a href="../empresa/candidaturas.php" class="dropdown-item">
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
                        <a href="../empresa/dashboard.php" class="btn btn-primary">
                            <i data-lucide="layout-dashboard"></i>
                            Dashboard
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
                        <a href="candidaturas.php" class="btn btn-outline">
                            <i data-lucide="briefcase"></i>
                            Candidaturas
                        </a>
                        <a href="perfil.php" class="btn btn-outline">
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
            <a href="perfil.php" class="breadcrumb-link">Minha Conta</a>
            <i data-lucide="chevron-right" class="breadcrumb-separator"></i>
            <span>Vagas Guardadas</span>
        </div>
    </div>

    <!-- ==========================================
         📋 MAIN CONTENT
    ========================================== -->
    <div class="main-container">

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i data-lucide="bookmark"></i>
                Vagas Guardadas
            </h1>
            <p class="page-subtitle">
                Acompanhe as vagas que você salvou para consultar depois
            </p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-card-icon">
                        <i data-lucide="bookmark"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo $total_guardadas; ?></div>
                <div class="stat-card-label">Vagas Guardadas</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-card-icon success">
                        <i data-lucide="check-circle"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo $vagas_ativas; ?></div>
                <div class="stat-card-label">Vagas Ativas</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-card-icon warning">
                        <i data-lucide="alert-circle"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo $vagas_expiradas; ?></div>
                <div class="stat-card-label">Vagas Expiradas</div>
            </div>
        </div>

        <!-- Jobs List -->
        <?php if (count($vagas_guardadas) > 0): ?>
            <div class="jobs-list">
                <?php foreach ($vagas_guardadas as $vaga): 
                    $is_expired = isVagaExpirada($vaga['data_expiracao']);
                ?>
                    <article class="job-card <?php echo $is_expired ? 'expired' : ''; ?>">
                        <div class="job-card-header">
                            <!-- Company Logo -->
                            <div class="company-logo">
                                <?php if (!empty($vaga['logotipo']) && file_exists('../uploads/' . $vaga['logotipo'])): ?>
                                    <img src="../uploads/<?php echo htmlspecialchars($vaga['logotipo']); ?>" 
                                         alt="<?php echo htmlspecialchars($vaga['nome_empresa']); ?>">
                                <?php else: ?>
                                    <img src="../assets/images/empresa-default.png" alt="Logo padrão">
                                <?php endif; ?>
                            </div>

                            <div class="job-card-content">
                                <a href="../vaga_detalhe.php?id=<?php echo $vaga['id']; ?>" class="job-title">
                                    <?php echo htmlspecialchars($vaga['titulo']); ?>
                                </a>
                                <div class="company-name">
                                    <?php echo htmlspecialchars($vaga['nome_empresa']); ?>
                                </div>

                                <div class="job-badges">
                                    <?php if ($is_expired): ?>
                                    <span class="badge badge-expired">
                                        <i data-lucide="x-circle" style="width: 12px; height: 12px;"></i>
                                        Expirada
                                    </span>
                                    <?php else: ?>
                                    <span class="badge badge-active">
                                        <i data-lucide="check-circle" style="width: 12px; height: 12px;"></i>
                                        Ativa
                                    </span>
                                    <?php endif; ?>

                                    <?php if ($vaga['modalidade'] === 'remoto'): ?>
                                    <span class="badge badge-remote">
                                        <i data-lucide="home" style="width: 12px; height: 12px;"></i>
                                        Remoto
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="job-info">
                            <div class="info-item">
                                <i data-lucide="map-pin"></i>
                                <?php echo htmlspecialchars($vaga['localizacao']); ?>
                            </div>
                            <div class="info-item">
                                <i data-lucide="monitor"></i>
                                <?php echo traduzirModalidade($vaga['modalidade']); ?>
                            </div>
                            <div class="info-item salary">
                                <i data-lucide="banknote"></i>
                                <?php echo formatarSalario($vaga['salario_estimado']); ?>
                            </div>
                            <div class="info-item">
                                <i data-lucide="briefcase"></i>
                                <?php echo htmlspecialchars($vaga['area']); ?>
                            </div>
                        </div>

                        <p class="job-description">
                            <?php echo htmlspecialchars(substr($vaga['descricao'], 0, 200)); ?>...
                        </p>

                        <div class="job-card-footer">
                            <div class="job-time">
                                <span class="job-time-label">
                                    <i data-lucide="bookmark" style="width: 14px; height: 14px;"></i>
                                    Guardada:
                                </span>
                                <?php echo tempoDecorrido($vaga['data_guardada']); ?>
                            </div>
                            <div class="job-card-actions">
                                <a href="../vaga_detalhe.php?id=<?php echo $vaga['id']; ?>" class="btn-secondary">
                                    <i data-lucide="eye"></i>
                                    Ver Detalhes
                                </a>
                                <?php if (!$is_expired): ?>
                                <a href="../vaga_detalhe.php?id=<?php echo $vaga['id']; ?>" class="btn-primary">
                                    <i data-lucide="send"></i>
                                    Candidatar-me
                                </a>
                                <?php endif; ?>
                                <button class="btn-remove" onclick="removeFromSaved(<?php echo $vaga['id']; ?>, this)">
                                    <i data-lucide="trash-2"></i>
                                    Remover
                                </button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <i data-lucide="bookmark-x" class="empty-state-icon"></i>
                <h3>Nenhuma vaga guardada</h3>
                <p>Você ainda não guardou nenhuma vaga. Explore as oportunidades disponíveis e salve as que mais interessarem!</p>
                <a href="../vagas.php" class="btn-primary">
                    <i data-lucide="search"></i>
                    Explorar Vagas
                </a>
            </div>
        <?php endif; ?>

    </div>

    <!-- ==========================================
         🎭 MODAL DE CONFIRMAÇÃO
    ========================================== -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-container">
            <div class="modal-header">
                <div class="modal-icon">
                    <i data-lucide="alert-triangle"></i>
                </div>
                <div class="modal-title">
                    <h3>Remover Vaga Guardada</h3>
                    <p>Esta ação não pode ser desfeita</p>
                </div>
            </div>

            <div class="modal-body">
                <p>Tem certeza que deseja remover esta vaga dos seus guardados?</p>
                
                <div class="modal-highlight">
                    <strong id="modalVagaTitulo"><!-- Título da vaga será inserido aqui --></strong>
                    <p>Você poderá salvar esta vaga novamente a qualquer momento na lista de vagas.</p>
                </div>
            </div>

            <div class="modal-footer">
                <button class="modal-btn modal-btn-cancel" onclick="closeModal()">
                    <i data-lucide="x"></i>
                    Cancelar
                </button>
                <button class="modal-btn modal-btn-confirm" id="modalConfirmBtn">
                    <i data-lucide="trash-2"></i>
                    Sim, Remover
                </button>
            </div>
        </div>
    </div>

    <!-- ==========================================
         ✨ SCRIPTS
    ========================================== -->
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // ==========================================
        // 🎭 MODAL DE CONFIRMAÇÃO
        // ==========================================
        let currentVagaId = null;
        let currentBtnElement = null;

        function openModal(vagaId, btnElement, vagaTitulo = '') {
            currentVagaId = vagaId;
            currentBtnElement = btnElement;

            // Atualizar título da vaga no modal
            const modalTitulo = document.getElementById('modalVagaTitulo');
            
            if (modalTitulo && vagaTitulo) {
                modalTitulo.textContent = vagaTitulo;
            }

            // Abrir modal
            const modal = document.getElementById('confirmModal');
            
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden'; // Prevenir scroll
            } else {
                return;
            }

            // Atualizar ícones do Lucide
            lucide.createIcons();

            // Event listener para confirmação
            const confirmBtn = document.getElementById('modalConfirmBtn');
            if (confirmBtn) {
                confirmBtn.onclick = () => confirmRemove();
            }

            // Event listener para fechar ao clicar fora
            modal.onclick = (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            };

            // Event listener para ESC
            document.addEventListener('keydown', handleEscKey);
        }

        function closeModal() {
            const modal = document.getElementById('confirmModal');
            modal.classList.remove('active');
            document.body.style.overflow = ''; // Restaurar scroll
            document.removeEventListener('keydown', handleEscKey);

            // Limpar dados
            currentVagaId = null;
            currentBtnElement = null;
        }

        function handleEscKey(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        }

        async function confirmRemove() {
            if (!currentVagaId || !currentBtnElement) {
                return;
            }

            // IMPORTANTE: Salvar referências ANTES de fechar o modal
            const vagaId = currentVagaId;
            const btnElement = currentBtnElement;

            // Fechar modal
            closeModal();

            // Desabilitar botão
            btnElement.disabled = true;
            btnElement.style.opacity = '0.6';

            try {
                const response = await fetch('../api/guardar_vaga.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        vaga_id: vagaId,
                        action: 'remove'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Vaga removida dos guardados! 🗑️', 'success');
                    
                    // Recarregar página após 1 segundo
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                    btnElement.disabled = false;
                    btnElement.style.opacity = '1';
                }

            } catch (error) {
                showNotification('Erro ao processar requisição.', 'error');
                btnElement.disabled = false;
                btnElement.style.opacity = '1';
            }
        }

        // ==========================================
        // 🗑️ REMOVER VAGA GUARDADA
        // ==========================================
        function removeFromSaved(vagaId, btnElement) {
            // Buscar o título da vaga
            let vagaTitulo = '';
            try {
                const jobCard = btnElement.closest('.job-card');
                
                if (jobCard) {
                    const titleElement = jobCard.querySelector('.job-title');
                    
                    if (titleElement) {
                        vagaTitulo = titleElement.textContent.trim();
                    }
                }
            } catch (error) {
                // Silenciosamente ignorar erro
            }

            // Abrir modal de confirmação
            openModal(vagaId, btnElement, vagaTitulo);
        }

        // ==========================================
        // 🔔 SISTEMA DE NOTIFICAÇÕES
        // ==========================================
        function showNotification(message, type = 'info') {
            const oldNotification = document.querySelector('.notification');
            if (oldNotification) {
                oldNotification.remove();
            }

            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i data-lucide="${getNotificationIcon(type)}"></i>
                    <span>${message}</span>
                </div>
            `;

            document.body.appendChild(notification);
            lucide.createIcons();

            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 4000);
        }

        function getNotificationIcon(type) {
            const icons = {
                'success': 'check-circle',
                'error': 'x-circle',
                'warning': 'alert-circle',
                'info': 'info'
            };
            return icons[type] || 'info';
        }

        // Reinitialize icons
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
        });
    </script>
    
    <!-- 🔒 Proteção de Sessão - Encerra ao fechar aba -->
    <script src="../assets/js/session-guard.js"></script>

</body>
</html>

