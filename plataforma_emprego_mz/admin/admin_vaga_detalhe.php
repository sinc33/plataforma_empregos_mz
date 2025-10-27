<?php
session_start();
require_once '../config/db.php';

// Verificar se o usuário está logado como admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

 $pdo = getPDO();

// Obter ID da vaga
 $vaga_id = $_GET['id'] ?? 0;

if (!$vaga_id) {
    header("Location: index.php");
    exit;
}

// Buscar detalhes da vaga
 $sql_vaga = "
    SELECT v.*, e.nome_empresa, e.nuit, e.website, e.descricao as descricao_empresa,
           u.email as email_empresa, u.ativo as empresa_ativa,
           (SELECT COUNT(*) FROM candidatura c WHERE c.vaga_id = v.id) as total_candidaturas
    FROM vaga v
    JOIN empresa e ON v.empresa_id = e.id
    JOIN utilizador u ON e.id = u.id
    WHERE v.id = ?
";

 $stmt = $pdo->prepare($sql_vaga);
 $stmt->execute([$vaga_id]);
 $vaga = $stmt->fetch();

if (!$vaga) {
    header("Location: index.php");
    exit;
}

// Buscar candidaturas para esta vaga (CORRIGIDO - removido cand.email)
 $sql_candidaturas = "
    SELECT c.*, cand.nome_completo, cand.telefone, cand.localizacao,
           cand.competencias, u.email, u.ativo as candidato_ativo
    FROM candidatura c
    JOIN candidato cand ON c.candidato_id = cand.id
    JOIN utilizador u ON cand.id = u.id
    WHERE c.vaga_id = ?
    ORDER BY c.data_candidatura DESC
";

 $candidaturas = $pdo->prepare($sql_candidaturas);
 $candidaturas->execute([$vaga_id]);
 $candidaturas = $candidaturas->fetchAll();

// Processar ações
 $sucesso = '';
 $erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    try {
        switch ($acao) {
            case 'ativar_desativar_vaga':
                $novo_estado = (int)$_POST['novo_estado'];
                $stmt = $pdo->prepare("UPDATE vaga SET ativa = ? WHERE id = ?");
                $stmt->execute([$novo_estado, $vaga_id]);
                $vaga['ativa'] = $novo_estado;
                $sucesso = $novo_estado ? "Vaga ativada com sucesso!" : "Vaga desativada com sucesso!";
                break;
                
            case 'atualizar_estado_candidatura':
                $candidatura_id = (int)$_POST['candidatura_id'];
                $novo_estado = $_POST['novo_estado'];
                
                $stmt = $pdo->prepare("UPDATE candidatura SET estado = ? WHERE id = ?");
                $stmt->execute([$novo_estado, $candidatura_id]);
                
                // Atualizar estado na lista local
                foreach ($candidaturas as &$cand) {
                    if ($cand['id'] == $candidatura_id) {
                        $cand['estado'] = $novo_estado;
                        break;
                    }
                }
                
                $sucesso = "Estado da candidatura atualizado com sucesso!";
                break;
                
            case 'adicionar_nota':
                $candidatura_id = (int)$_POST['candidatura_id'];
                $nota = trim($_POST['nota_interna']);
                
                $stmt = $pdo->prepare("UPDATE candidatura SET nota_interna = ? WHERE id = ?");
                $stmt->execute([$nota, $candidatura_id]);
                
                $sucesso = "Nota interna adicionada com sucesso!";
                break;
        }
    } catch (PDOException $e) {
        $erro = "Erro ao processar ação: " . $e->getMessage();
    }
}

// Estatísticas da vaga
 $estatisticas_estados = [
    'submetida' => 0,
    'em_analise' => 0,
    'entrevista' => 0,
    'rejeitada' => 0,
    'contratado' => 0
];

foreach ($candidaturas as $candidatura) {
    $estatisticas_estados[$candidatura['estado']]++;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Vaga - Admin</title>
    
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
        }

        /* ============================================
           🌅 HERO SECTION
        ============================================ */
        .hero {
            background: var(--gradient-oceano);
            color: var(--branco-puro);
            padding: var(--space-xl) var(--space-md);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

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
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h1 {
            font-family: var(--font-heading);
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 700;
            margin-bottom: var(--space-md);
        }

        .hero p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* ============================================
           🌊 OCEAN DIVIDER
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
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-md);
        }

        .main-content {
            padding: var(--space-xl) 0;
        }

        /* ============================================
           🎴 XIMA CARDS
        ============================================ */
        .section {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-lg);
            box-shadow: var(--shadow-medium);
            margin-bottom: var(--space-lg);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .section::before {
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

        .section:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--dourado-sol);
        }

        .section-title {
            font-family: var(--font-heading);
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--carvao);
            margin-bottom: var(--space-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-xs);
        }

        /* ============================================
           🔘 BUTTONS
        ============================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-xs);
            padding: var(--space-sm) var(--space-md);
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary {
            background: var(--gradient-oceano);
            color: var(--branco-puro);
        }

        .btn-success {
            background: var(--verde-esperanca);
            color: var(--branco-puro);
        }

        .btn-warning {
            background: var(--dourado-sol);
            color: var(--carvao);
        }

        .btn-danger {
            background: var(--coral-vivo);
            color: var(--branco-puro);
        }

        .btn-secondary {
            background: var(--cinza-baobab);
            color: var(--branco-puro);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .btn-group {
            display: flex;
            gap: var(--space-sm);
            flex-wrap: wrap;
        }

        /* ============================================
           🎭 ALERTAS & BADGES
        ============================================ */
        .alert {
            padding: var(--space-md);
            border-radius: 12px;
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: flex-start;
            gap: var(--space-sm);
        }

        .alert-success {
            background: rgba(43, 122, 75, 0.1);
            color: var(--verde-esperanca);
            border-left: 4px solid var(--verde-esperanca);
        }

        .alert-error {
            background: rgba(255, 107, 107, 0.1);
            color: var(--coral-vivo);
            border-left: 4px solid var(--coral-vivo);
        }

        .badge {
            padding: var(--space-xs) var(--space-sm);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-success { background: rgba(43, 122, 75, 0.1); color: var(--verde-esperanca); }
        .badge-danger { background: rgba(255, 107, 107, 0.1); color: var(--coral-vivo); }
        .badge-warning { background: rgba(255, 176, 59, 0.1); color: #cc8a2e; }
        .badge-info { background: rgba(30, 58, 95, 0.1); color: var(--azul-indigo); }
        .badge-primary { background: rgba(30, 58, 95, 0.1); color: var(--azul-indigo); }

        /* ============================================
           📝 FORMS & TABLES
        ============================================ */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-md);
            margin-bottom: var(--space-md);
        }

        .info-item {
            margin-bottom: var(--space-sm);
        }

        .info-label {
            font-weight: 600;
            color: var(--cinza-baobab);
            font-size: 0.9em;
            margin-bottom: var(--space-xs);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .info-value {
            color: var(--carvao);
            font-size: 1rem;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: var(--shadow-soft);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--branco-puro);
        }

        th, td {
            padding: var(--space-sm);
            text-align: left;
            border-bottom: 1px solid var(--areia-quente);
        }

        th {
            background: var(--areia-quente);
            font-weight: 700;
            color: var(--carvao);
            font-family: var(--font-heading);
        }

        tr:hover {
            background: rgba(244, 228, 193, 0.3);
        }

        select, textarea {
            width: 100%;
            padding: var(--space-xs);
            border: 2px solid var(--areia-quente);
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: var(--font-body);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        select:focus, textarea:focus {
            outline: none;
            border-color: var(--dourado-sol);
            box-shadow: 0 0 0 3px rgba(255, 176, 59, 0.2);
        }

        /* ============================================
           📊 STATS GRID
        ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: var(--space-md);
            margin: var(--space-md) 0;
        }

        .stat-card {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-md);
            box-shadow: var(--shadow-soft);
            text-align: center;
            border: 2px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--dourado-sol);
        }

        .stat-number {
            font-family: var(--font-heading);
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--gradient-por-do-sol);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
            margin-bottom: var(--space-xs);
        }

        .stat-label {
            color: var(--cinza-baobab);
            font-size: 1rem;
            font-weight: 500;
        }

        /* ============================================
           🎭 MODAL
        ============================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal {
            background: var(--branco-puro);
            padding: var(--space-xl);
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-strong);
            position: relative;
        }

        .modal-header {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--carvao);
            margin-bottom: var(--space-md);
        }

        .modal-footer {
            display: flex;
            gap: var(--space-sm);
            justify-content: flex-end;
            margin-top: var(--space-lg);
        }

        /* ============================================
           📱 RESPONSIVE DESIGN
        ============================================ */
        @media (max-width: 768px) {
            .hero {
                padding: var(--space-lg) var(--space-md);
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- 🌅 HERO SECTION -->
    <div class="hero">
        <div class="hero-content">
            <h1>
                <i data-lucide="briefcase" style="width: 48px; height: 48px; vertical-align: middle; margin-right: var(--space-sm);"></i>
                Detalhes da Vaga
            </h1>
            <p>Administração completa da vaga e suas candidaturas</p>
        </div>
    </div>

    <!-- 🌊 OCEAN DIVIDER -->
    <div class="ocean-divider">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                  style="fill: var(--gradient-oceano); opacity: 0.8;"></path>
        </svg>
    </div>

    <!-- 📊 DASHBOARD CONTENT -->
    <div class="main-content">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg);">
                <div>
                    <h2 style="font-family: var(--font-heading); color: var(--carvao); display: flex; align-items: center; gap: var(--space-sm);">
                        <i data-lucide="hash" style="width: 24px; height: 24px;"></i>
                        ID da Vaga: <?php echo $vaga['id']; ?>
                    </h2>
                </div>
                <a href="index.php" class="btn btn-secondary">
                    <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
                    Voltar ao Dashboard
                </a>
            </div>

            <?php if ($sucesso): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" style="width: 20px; height: 20px; margin-top: 2px;"></i>
                    <div><?php echo htmlspecialchars($sucesso); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle" style="width: 20px; height: 20px; margin-top: 2px;"></i>
                    <div><?php echo htmlspecialchars($erro); ?></div>
                </div>
            <?php endif; ?>

            <!-- Informações da Vaga -->
            <div class="section">
                <div class="section-title">
                    <span>
                        <i data-lucide="file-text" style="width: 24px; height: 24px;"></i>
                        Informações da Vaga
                    </span>
                    <span class="badge <?php echo $vaga['ativa'] ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo $vaga['ativa'] ? 'Ativa' : 'Inativa'; ?>
                    </span>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">
                            <i data-lucide="type" style="width: 16px; height: 16px;"></i>
                            Título
                        </div>
                        <div class="info-value" style="font-size: 1.2em; font-weight: bold;"><?php echo htmlspecialchars($vaga['titulo']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i data-lucide="building" style="width: 16px; height: 16px;"></i>
                            Empresa
                        </div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($vaga['nome_empresa']); ?>
                            <span class="badge <?php echo $vaga['empresa_ativa'] ? 'badge-success' : 'badge-danger'; ?>" style="margin-left: var(--space-sm);">
                                <?php echo $vaga['empresa_ativa'] ? 'Ativa' : 'Inativa'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i>
                            Localização
                        </div>
                        <div class="info-value"><?php echo htmlspecialchars($vaga['localizacao']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                            Tipo de Contrato
                        </div>
                        <div class="info-value">
                            <?php 
                            $contrato_labels = [
                                'tempo_inteiro' => 'Tempo Inteiro',
                                'tempo_parcial' => 'Tempo Parcial', 
                                'estagio' => 'Estágio',
                                'freelance' => 'Freelance'
                            ];
                            echo $contrato_labels[$vaga['tipo_contrato']] ?? $vaga['tipo_contrato'];
                            ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i data-lucide="monitor" style="width: 16px; height: 16px;"></i>
                            Modalidade
                        </div>
                        <div class="info-value">
                            <?php 
                            $modalidade_labels = [
                                'presencial' => 'Presencial',
                                'hibrido' => 'Híbrido',
                                'remoto' => 'Remoto'
                            ];
                            echo $modalidade_labels[$vaga['modalidade']] ?? $vaga['modalidade'];
                            ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i data-lucide="trending-up" style="width: 16px; height: 16px;"></i>
                            Nível de Experiência
                        </div>
                        <div class="info-value"><?php echo htmlspecialchars($vaga['nivel_experiencia']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i data-lucide="dollar-sign" style="width: 16px; height: 16px;"></i>
                            Salário Estimado
                        </div>
                        <div class="info-value">
                            <?php 
                            if ($vaga['salario_estimado']) {
                                echo number_format($vaga['salario_estimado'], 2, ',', ' ') . ' MZN';
                            } else {
                                echo 'A combinar';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                            Data de Publicação
                        </div>
                        <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($vaga['data_publicacao'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i data-lucide="alert-circle" style="width: 16px; height: 16px;"></i>
                            Data de Expiração
                        </div>
                        <div class="info-value">
                            <?php echo date('d/m/Y', strtotime($vaga['data_expiracao'])); ?>
                            <?php if (strtotime($vaga['data_expiracao']) < time()): ?>
                                <span class="badge badge-danger" style="margin-left: var(--space-sm);">Expirada</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">
                        <i data-lucide="file-text" style="width: 16px; height: 16px;"></i>
                        Descrição da Vaga
                    </div>
                    <div class="info-value" style="white-space: pre-wrap; background: var(--areia-quente); padding: var(--space-md); border-radius: 12px;">
                        <?php echo htmlspecialchars($vaga['descricao']); ?>
                    </div>
                </div>

                <!-- Ações da Vaga -->
                <div style="margin-top: var(--space-lg); padding-top: var(--space-md); border-top: 1px solid var(--areia-quente);">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="acao" value="ativar_desativar_vaga">
                        <input type="hidden" name="novo_estado" value="<?php echo $vaga['ativa'] ? '0' : '1'; ?>">
                        <button type="submit" class="btn <?php echo $vaga['ativa'] ? 'btn-warning' : 'btn-success'; ?>">
                            <i data-lucide="<?php echo $vaga['ativa'] ? 'pause-circle' : 'play-circle'; ?>" style="width: 18px; height: 18px;"></i>
                            <?php echo $vaga['ativa'] ? 'Desativar Vaga' : 'Ativar Vaga'; ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Estatísticas das Candidaturas -->
            <div class="section">
                <div class="section-title">
                    <span>
                        <i data-lucide="bar-chart" style="width: 24px; height: 24px;"></i>
                        Estatísticas das Candidaturas
                    </span>
                    <span class="badge badge-primary">Total: <?php echo $vaga['total_candidaturas']; ?></span>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $estatisticas_estados['submetida']; ?></span>
                        <span class="stat-label">Submetidas</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $estatisticas_estados['em_analise']; ?></span>
                        <span class="stat-label">Em Análise</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $estatisticas_estados['entrevista']; ?></span>
                        <span class="stat-label">Entrevista</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $estatisticas_estados['rejeitada']; ?></span>
                        <span class="stat-label">Rejeitadas</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $estatisticas_estados['contratado']; ?></span>
                        <span class="stat-label">Contratados</span>
                    </div>
                </div>
            </div>

            <!-- Lista de Candidaturas -->
            <div class="section">
                <div class="section-title">
                    <span>
                        <i data-lucide="users" style="width: 24px; height: 24px;"></i>
                        Candidaturas Recebidas
                    </span>
                    <span class="badge badge-primary"><?php echo count($candidaturas); ?> candidaturas</span>
                </div>

                <?php if (empty($candidaturas)): ?>
                    <div style="text-align: center; padding: var(--space-xl); background: var(--areia-quente); border-radius: 12px;">
                        <i data-lucide="inbox" style="width: 48px; height: 48px; color: var(--cinza-baobab);"></i>
                        <h3 style="font-family: var(--font-heading); color: var(--carvao); margin-top: var(--space-md);">Nenhuma candidatura recebida</h3>
                        <p>Esta vaga ainda não recebeu nenhuma candidatura.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Candidato</th>
                                    <th>Contacto</th>
                                    <th>Localização</th>
                                    <th>Data</th>
                                    <th>Estado</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidaturas as $candidatura): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($candidatura['nome_completo']); ?></strong>
                                            <?php if (!$candidatura['candidato_ativo']): ?>
                                                <span class="badge badge-danger" style="margin-left: var(--space-xs);">Inativo</span>
                                            <?php endif; ?>
                                            <br>
                                            <small style="color: var(--cinza-baobab);"><?php echo htmlspecialchars($candidatura['competencias']); ?></small>
                                            
                                            <?php if ($candidatura['nota_interna']): ?>
                                                <div style="background: rgba(255, 176, 59, 0.1); padding: var(--space-xs); border-radius: 8px; margin-top: var(--space-xs); font-size: 0.8em;">
                                                    <strong>Nota Interna:</strong> <?php echo htmlspecialchars($candidatura['nota_interna']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <i data-lucide="phone" style="width: 14px; height: 14px;"></i>
                                            <?php echo htmlspecialchars($candidatura['telefone']); ?><br>
                                            <i data-lucide="mail" style="width: 14px; height: 14px;"></i>
                                            <?php echo htmlspecialchars($candidatura['email']); ?>
                                        </td>
                                        <td>
                                            <i data-lucide="map-pin" style="width: 14px; height: 14px;"></i>
                                            <?php echo htmlspecialchars($candidatura['localizacao']); ?>
                                        </td>
                                        <td>
                                            <i data-lucide="calendar" style="width: 14px; height: 14px;"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($candidatura['data_candidatura'])); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $estado_labels = [
                                                'submetida' => ['label' => 'Submetida', 'class' => 'badge-info'],
                                                'em_analise' => ['label' => 'Em Análise', 'class' => 'badge-warning'],
                                                'entrevista' => ['label' => 'Entrevista', 'class' => 'badge-primary'],
                                                'rejeitada' => ['label' => 'Rejeitada', 'class' => 'badge-danger'],
                                                'contratado' => ['label' => 'Contratado', 'class' => 'badge-success']
                                            ];
                                            $estado = $candidatura['estado'];
                                            ?>
                                            <span class="badge <?php echo $estado_labels[$estado]['class']; ?>">
                                                <?php echo $estado_labels[$estado]['label']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <!-- Alterar Estado -->
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="acao" value="atualizar_estado_candidatura">
                                                    <input type="hidden" name="candidatura_id" value="<?php echo $candidatura['id']; ?>">
                                                    <select name="novo_estado" onchange="this.form.submit()" style="width: auto; padding: var(--space-xs);">
                                                        <?php foreach ($estado_labels as $valor => $info): ?>
                                                            <option value="<?php echo $valor; ?>" <?php echo $valor == $estado ? 'selected' : ''; ?>>
                                                                <?php echo $info['label']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>

                                                <!-- Adicionar Nota -->
                                                <button type="button" class="btn btn-secondary" 
                                                        onclick="abrirModalNota(<?php echo $candidatura['id']; ?>, '<?php echo htmlspecialchars($candidatura['nota_interna'] ?? ''); ?>')">
                                                    <i data-lucide="edit-3" style="width: 16px; height: 16px;"></i>
                                                    Nota
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Informações da Empresa -->
            <div class="section">
                <div class="section-title">
                    <i data-lucide="building" style="width: 24px; height: 24px;"></i>
                    Informações da Empresa
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">
                            <i data-lucide="type" style="width: 16px; height: 16px;"></i>
                            Nome da Empresa
                        </div>
                        <div class="info-value"><?php echo htmlspecialchars($vaga['nome_empresa']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i data-lucide="hash" style="width: 16px; height: 16px;"></i>
                            NUIT
                        </div>
                        <div class="info-value"><?php echo htmlspecialchars($vaga['nuit']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i data-lucide="mail" style="width: 16px; height: 16px;"></i>
                            Email
                        </div>
                        <div class="info-value"><?php echo htmlspecialchars($vaga['email_empresa']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i data-lucide="globe" style="width: 16px; height: 16px;"></i>
                            Website
                        </div>
                        <div class="info-value">
                            <?php if ($vaga['website']): ?>
                                <a href="<?php echo htmlspecialchars($vaga['website']); ?>" target="_blank" style="color: var(--azul-indigo);">
                                    <?php echo htmlspecialchars($vaga['website']); ?>
                                </a>
                            <?php else: ?>
                                Não informado
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php if ($vaga['descricao_empresa']): ?>
                    <div class="info-item">
                        <div class="info-label">
                            <i data-lucide="file-text" style="width: 16px; height: 16px;"></i>
                            Descrição da Empresa
                        </div>
                        <div class="info-value" style="white-space: pre-wrap; background: var(--areia-quente); padding: var(--space-md); border-radius: 12px;">
                            <?php echo htmlspecialchars($vaga['descricao_empresa']); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para Adicionar Nota -->
    <div id="modalNota" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <i data-lucide="edit-3" style="width: 24px; height: 24px; vertical-align: middle; margin-right: var(--space-sm);"></i>
                Adicionar Nota Interna
            </div>
            <form method="POST" id="formNota">
                <input type="hidden" name="acao" value="adicionar_nota">
                <input type="hidden" name="candidatura_id" id="candidatura_id">
                
                <div style="margin-bottom: var(--space-md);">
                    <label for="nota_interna" style="display: block; margin-bottom: var(--space-xs); font-weight: 600;">Nota:</label>
                    <textarea name="nota_interna" id="nota_interna" rows="4"></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fecharModalNota()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save" style="width: 18px; height: 18px;"></i>
                        Salvar Nota
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ✨ MICRO-INTERACTION SCRIPT -->
    <script>
        // Inicializar ícones Lucide
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            
            // Animação de entrada dos cards
            const animateElements = document.querySelectorAll('.section, .stat-card');
            
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
        });

        function abrirModalNota(candidaturaId, notaAtual) {
            document.getElementById('candidatura_id').value = candidaturaId;
            document.getElementById('nota_interna').value = notaAtual || '';
            document.getElementById('modalNota').style.display = 'flex';
            lucide.createIcons();
        }

        function fecharModalNota() {
            document.getElementById('modalNota').style.display = 'none';
        }

        // Fechar modal ao clicar fora
        document.getElementById('modalNota').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalNota();
            }
        });

        // Confirmação para desativar vaga
        document.addEventListener('submit', function(e) {
            if (e.target.querySelector('input[name="acao"][value="ativar_desativar_vaga"]')) {
                const isAtiva = <?php echo $vaga['ativa'] ? 'true' : 'false'; ?>;
                const message = isAtiva ? 
                    '⚠️ Tem certeza que deseja desativar esta vaga?\n\nA vaga não será mais visível para candidatos.' :
                    '✅ Tem certeza que deseja ativar esta vaga?\n\nA vaga ficará visível para candidatos.';
                
                if (!confirm(message)) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>