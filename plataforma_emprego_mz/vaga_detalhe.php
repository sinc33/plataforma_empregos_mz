<?php
session_start();
require_once 'config/db.php';

$pdo = getPDO();

// Obter ID da vaga
$vaga_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($vaga_id === 0) {
    header("Location: vagas.php");
    exit;
}

// Buscar detalhes da vaga
$sql_vaga = "SELECT v.*, e.nome_empresa, e.logotipo, e.website, e.descricao as descricao_empresa, 
                    e.localizacao as localizacao_empresa, e.nuit
             FROM vaga v 
             JOIN empresa e ON v.empresa_id = e.id 
             WHERE v.id = ? AND v.ativa = TRUE AND v.data_expiracao >= CURDATE()";

$stmt = $pdo->prepare($sql_vaga);
$stmt->execute([$vaga_id]);
$vaga = $stmt->fetch();

if (!$vaga) {
    header("Location: vagas.php");
    exit;
}

// Verificar se já candidatado
$ja_candidatado = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'candidato') {
    $sql_candidatura = "SELECT id FROM candidatura WHERE vaga_id = ? AND candidato_id = ?";
    $stmt = $pdo->prepare($sql_candidatura);
    $stmt->execute([$vaga_id, $_SESSION['user_id']]);
    $ja_candidatado = (bool)$stmt->fetch();
}

// Contar total de vagas similares (mesma área)
$sql_count_similares = "SELECT COUNT(*) FROM vaga v 
                        WHERE v.id != ? AND v.ativa = TRUE 
                        AND v.data_expiracao >= CURDATE() 
                        AND v.area = ?";
$stmt = $pdo->prepare($sql_count_similares);
$stmt->execute([$vaga_id, $vaga['area']]);
$total_similares = $stmt->fetchColumn();

// Buscar vagas similares (sidebar)
$sql_similares = "SELECT v.*, e.nome_empresa, e.logotipo 
                  FROM vaga v 
                  JOIN empresa e ON v.empresa_id = e.id 
                  WHERE v.id != ? AND v.ativa = TRUE AND v.data_expiracao >= CURDATE() 
                  AND v.area = ? 
                  ORDER BY v.data_publicacao DESC 
                  LIMIT 3";

$stmt = $pdo->prepare($sql_similares);
$stmt->execute([$vaga_id, $vaga['area']]);
$vagas_similares = $stmt->fetchAll();

// Buscar tags relacionadas (áreas profissionais)
$sql_tags = "SELECT DISTINCT area FROM vaga 
             WHERE ativa = TRUE AND area != ? AND area IS NOT NULL 
             LIMIT 5";
$stmt = $pdo->prepare($sql_tags);
$stmt->execute([$vaga['area']]);
$tags_relacionadas = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Processar candidatura
$sucesso = '';
$erro = '';

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
            $erro = "Erro ao enviar candidatura. Tente novamente.";
        }
    }
}

// Funções auxiliares
function formatarSalario($salario) {
    if (empty($salario) || $salario == 0) {
        return 'À combinar';
    }
    return number_format($salario, 2, ',', '.') . ' MT';
}

function tempoDecorrido($data) {
    $agora = new DateTime();
    $publicacao = new DateTime($data);
    $diferenca = $agora->diff($publicacao);
    
    if ($diferenca->days == 0) {
        return 'hoje';
    } elseif ($diferenca->days == 1) {
        return 'há 1 dia';
    } elseif ($diferenca->days < 7) {
        return 'há ' . $diferenca->days . ' dias';
    } elseif ($diferenca->days < 30) {
        $semanas = floor($diferenca->days / 7);
        return 'há ' . $semanas . ($semanas > 1 ? ' semanas' : ' semana');
    } else {
        $meses = floor($diferenca->days / 30);
        return 'há ' . $meses . ($meses > 1 ? ' meses' : ' mês');
    }
}

function traduzirModalidade($modalidade) {
    $traducoes = [
        'presencial' => 'Presencial',
        'hibrido' => 'Híbrido',
        'remoto' => 'Remoto'
    ];
    return $traducoes[$modalidade] ?? $modalidade;
}

function traduzirTipoContrato($tipo) {
    $traducoes = [
        'tempo_inteiro' => 'Tempo Integral',
        'tempo_parcial' => 'Tempo Parcial',
        'estagio' => 'Estágio',
        'freelance' => 'Freelance'
    ];
    return $traducoes[$tipo] ?? $tipo;
}
?>


<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars( $vaga['titulo']); ?> - <?php echo htmlspecialchars( $vaga['nome_empresa']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --cor-primaria: #0088CC;
            --cor-secundaria: #FF8C00;
            --cor-texto: #1A1A1A;
            --cor-texto-claro: #5A5A5A;
            --cor-borda: #E0E0E0;
            --cor-fundo: #F5F5F5;
            --cor-branco: #FFFFFF;
            --cor-hover: #006699;
            --cor-sucesso: #28A745;
            --cor-icone-bg: #E8F4F8;

            --font-principal: 'Inter', -apple-system, sans-serif;
            --border-radius: 8px;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: var(--font-principal);
            color: var(--cor-texto);
            background: var(--cor-fundo);
            line-height: 1.6;
        }

        /* ========================================
           📱 HEADER
        ======================================== */
        .header {
            background: var(--cor-branco);
            border-bottom: 1px solid var(--cor-borda);
            padding: 16px 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: var(--shadow-sm);
        }

        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--cor-primaria);
            font-size: 24px;
            font-weight: 800;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--cor-primaria);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .header-nav {
            display: flex;
            gap: 32px;
        }

        .nav-link {
            color: var(--cor-texto);
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-link:hover {
            color: var(--cor-primaria);
        }

        .header-buttons {
            display: flex;
            gap: 12px;
        }

        .btn-cadastro {
            padding: 10px 24px;
            background: var(--cor-primaria);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-cadastro:hover {
            background: var(--cor-hover);
        }

        .btn-entrar {
            padding: 10px 24px;
            background: var(--cor-secundaria);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-entrar:hover {
            background: #E67E00;
        }

        /* ========================================
           📋 LAYOUT PRINCIPAL
        ======================================== */
        .main-container {
            max-width: 1400px;
            margin: 100px auto 60px;
            padding: 0 24px;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 32px;
        }

        /* ========================================
           💼 CARD DA VAGA
        ======================================== */
        .vaga-content {
            background: transparent;
        }

        .vaga-header {
            background: var(--cor-branco);
            border-radius: var(--border-radius);
            padding: 32px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }

        .vaga-top {
            display: flex;
            gap: 24px;
            margin-bottom: 24px;
        }

        /* Logo da empresa */
        .empresa-logo {
            width: 80px;
            height: 80px;
            flex-shrink: 0;
            border-radius: var(--border-radius);
            overflow: hidden;
            background: var(--cor-fundo);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--cor-borda);
        }

        .empresa-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .vaga-header-info {
            flex: 1;
        }

        .vaga-title {
            font-size: 26px;
            font-weight: 700;
            color: var(--cor-texto);
            margin-bottom: 8px;
        }

        .vaga-empresa {
            font-size: 16px;
            color: var(--cor-primaria);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .vaga-empresa:hover {
            text-decoration: underline;
        }

        /* Grid de ícones informativos */
        .vaga-info-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 24px;
            padding: 20px 0;
            border-top: 1px solid var(--cor-borda);
            border-bottom: 1px solid var(--cor-borda);
        }

        .info-box {
            text-align: center;
        }

        .info-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 8px;
            background: var(--cor-icone-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .info-icon img {
            width: 24px;
            height: 24px;
        }

        .info-label {
            font-size: 13px;
            color: var(--cor-texto-claro);
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--cor-texto);
        }

        /* Seções de conteúdo */
        .vaga-body {
            background: var(--cor-branco);
            border-radius: var(--border-radius);
            padding: 32px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }

        .section {
            margin-bottom: 32px;
        }

        .section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--cor-texto);
            margin-bottom: 16px;
        }

        .section-content {
            font-size: 15px;
            color: var(--cor-texto-claro);
            line-height: 1.8;
            white-space: pre-wrap;
        }

        .section-content strong {
            color: var(--cor-texto);
        }

        .btn-whatsapp {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: white;
            color: var(--cor-primaria);
            border: 2px solid var(--cor-primaria);
            border-radius: var(--border-radius);
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            margin-top: 24px;
            transition: all 0.2s;
        }

        .btn-whatsapp:hover {
            background: var(--cor-fundo);
        }

        /* Rodapé da vaga */
        .vaga-footer {
            display: flex;
            gap: 16px;
            justify-content: center;
            padding: 24px 32px;
        }

        .btn-ver-mais {
            flex: 1;
            padding: 14px 24px;
            background: white;
            color: var(--cor-primaria);
            border: 2px solid var(--cor-primaria);
            border-radius: var(--border-radius);
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s;
        }

        .btn-ver-mais:hover {
            background: var(--cor-fundo);
        }

        .btn-candidatar {
            flex: 1;
            padding: 14px 24px;
            background: var(--cor-primaria);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-candidatar:hover {
            background: var(--cor-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-candidatar:disabled {
            background: var(--cor-texto-claro);
            cursor: not-allowed;
            transform: none;
        }

        /* ========================================
           📌 SIDEBAR DIREITA
        ======================================== */
        .sidebar {
            position: sticky;
            top: 100px;
            height: fit-content;
        }

        .sidebar-section {
            background: var(--cor-branco);
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }

        .sidebar-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--cor-texto);
            margin-bottom: 16px;
        }

        /* Compartilhar */
        .share-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .share-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 12px 8px;
            border: none;
            background: var(--cor-fundo);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: var(--cor-texto-claro);
            font-size: 12px;
        }

        .share-btn:hover {
            background: var(--cor-borda);
        }

        .share-btn i {
            width: 24px;
            height: 24px;
            color: var(--cor-primaria);
        }

        /* Vagas similares (sidebar) */
        .vaga-similar {
            padding: 16px;
            border: 1px solid var(--cor-borda);
            border-radius: var(--border-radius);
            margin-bottom: 12px;
            text-decoration: none;
            color: inherit;
            display: block;
            transition: all 0.2s;
        }

        .vaga-similar:hover {
            box-shadow: var(--shadow-sm);
            transform: translateY(-2px);
        }

        .similar-badge {
            display: inline-block;
            padding: 4px 10px;
            background: #FFF3CD;
            color: #856404;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .similar-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--cor-texto);
            margin-bottom: 4px;
        }

        .similar-empresa {
            font-size: 13px;
            color: var(--cor-primaria);
            margin-bottom: 8px;
        }

        .similar-meta {
            display: flex;
            gap: 12px;
            font-size: 12px;
            color: var(--cor-texto-claro);
        }

        .similar-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Tags relacionadas */
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .tag {
            padding: 8px 16px;
            background: var(--cor-fundo);
            border: 1px solid var(--cor-borda);
            border-radius: 20px;
            font-size: 13px;
            color: var(--cor-texto);
            text-decoration: none;
            transition: all 0.2s;
        }

        .tag:hover {
            background: var(--cor-primaria);
            color: white;
            border-color: var(--cor-primaria);
        }

        /* Alertas */
        .alert {
            padding: 16px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #D4EDDA;
            color: #155724;
            border: 1px solid #C3E6CB;
        }

        .alert-error {
            background: #F8D7DA;
            color: #721C24;
            border: 1px solid #F5C6CB;
        }

        /* Modal de candidatura */
        .modal-candidatura {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .modal-candidatura.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: var(--border-radius);
            padding: 32px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--cor-texto-claro);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--cor-texto);
            margin-bottom: 8px;
        }

        .form-textarea {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            border: 1px solid var(--cor-borda);
            border-radius: var(--border-radius);
            font-size: 14px;
            font-family: var(--font-principal);
            resize: vertical;
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--cor-primaria);
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn-cancelar {
            flex: 1;
            padding: 12px;
            background: white;
            color: var(--cor-texto);
            border: 1px solid var(--cor-borda);
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-enviar {
            flex: 1;
            padding: 12px;
            background: var(--cor-primaria);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        /* ========================================
           📱 RESPONSIVE
        ======================================== */
        @media (max-width: 1024px) {
            .main-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .vaga-info-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }

            .vaga-footer {
                flex-direction: column;
            }

            .share-buttons {
                grid-template-columns: repeat(3, 1fr);
            }

            .header-nav {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- ========================================
         📱 HEADER
    ======================================== -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i data-lucide="briefcase" style="width: 24px; height: 24px;"></i>
                </div>
                emprego<strong>s</strong>
            </a>

            <nav class="header-nav">
                <a href="vagas.php" class="nav-link">Para candidatos</a>
                <a href="#" class="nav-link">Para empresas</a>
            </nav>

            <div class="header-buttons">
                <?php if (isset( $_SESSION['user_id'])): ?>
                    <a href="<?php echo  $_SESSION['user_type'] === 'empresa' ? 'empresa/dashboard.php' : 'candidato/perfil.php'; ?>" class="btn-cadastro">
                        Meu Perfil
                    </a>
                    <a href="auth/logout.php" class="btn-entrar">Sair</a>
                <?php else: ?>
                    <a href="auth/register.php" class="btn-cadastro">Cadastre-se</a>
                    <a href="auth/login.php" class="btn-entrar">Entrar</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- ========================================
         📋 LAYOUT PRINCIPAL
    ======================================== -->
    <div class="main-container">

        <!-- CONTEÚDO PRINCIPAL -->
        <div class="vaga-content">

            <?php if ( $sucesso): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
                    <?php echo htmlspecialchars( $sucesso); ?>
                </div>
            <?php endif; ?>

            <?php if ( $erro): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
                    <?php echo htmlspecialchars( $erro); ?>
                </div>
            <?php endif; ?>

            <!-- HEADER DA VAGA -->
            <div class="vaga-header">
                <div class="vaga-top">
                    <div class="empresa-logo">
                        <?php if (!empty( $vaga['logotipo']) && file_exists('uploads/' .  $vaga['logotipo'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars( $vaga['logotipo']); ?>" 
                                 alt="<?php echo htmlspecialchars( $vaga['nome_empresa']); ?>">
                        <?php else: ?>
                            <img src="assets/images/empresa-default.png" alt="Logo">
                        <?php endif; ?>
                    </div>

                    <div class="vaga-header-info">
                        <h1 class="vaga-title"><?php echo htmlspecialchars( $vaga['titulo']); ?></h1>
                        <a href="#" class="vaga-empresa"><?php echo htmlspecialchars( $vaga['nome_empresa']); ?></a>
                    </div>
                </div>

                <!-- GRID DE INFORMAÇÕES COM ÍCONES -->
                <div class="vaga-info-grid">
                    <!-- Localidade -->
                    <div class="info-box">
                        <div class="info-icon">
                            <img src="assets/images/icon-location.png" alt="Localidade">
                        </div>
                        <div class="info-label">Localidade</div>
                        <div class="info-value"><?php echo htmlspecialchars( $vaga['localizacao']); ?></div>
                    </div>

                    <!-- Tipo de vaga -->
                    <div class="info-box">
                        <div class="info-icon">
                            <img src="assets/images/icon-type.png" alt="Tipo">
                        </div>
                        <div class="info-label">Tipo de vaga</div>
                        <div class="info-value"><?php echo traduzirModalidade( $vaga['modalidade']); ?></div>
                    </div>

                    <!-- Nº de vagas -->
                    <div class="info-box">
                        <div class="info-icon">
                            <img src="assets/images/icon-number.png" alt="Vagas">
                        </div>
                        <div class="info-label">Nº de vagas</div>
                        <div class="info-value">1</div>
                    </div>

                    <!-- Remuneração -->
                    <div class="info-box">
                        <div class="info-icon">
                            <img src="assets/images/icon-salary.png" alt="Salário">
                        </div>
                        <div class="info-label">Remuneração</div>
                        <div class="info-value"><?php echo formatarSalario( $vaga['salario_estimado']); ?></div>
                    </div>

                    <!-- Publicada -->
                    <div class="info-box">
                        <div class="info-icon">
                            <img src="assets/images/icon-calendar.png" alt="Data">
                        </div>
                        <div class="info-label">Publicada</div>
                        <div class="info-value"><?php echo tempoDecorrido( $vaga['data_publicacao']); ?></div>
                    </div>
                </div>
            </div>

            <!-- CORPO DA VAGA -->
            <div class="vaga-body">
                <div class="section">
                    <h2 class="section-title">Descrição da vaga</h2>
                    <div class="section-content">
                        <?php echo nl2br(htmlspecialchars($vaga['descricao'])); ?>
                    </div>
                </div>

                <?php if (!empty($vaga['descricao_empresa'])): ?>
                <div class="section">
                    <h2 class="section-title">Sobre a empresa</h2>
                    <div class="section-content">
                        <strong><?php echo htmlspecialchars($vaga['nome_empresa']); ?></strong>
                        <br><br>
                        <?php echo nl2br(htmlspecialchars($vaga['descricao_empresa'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Botão WhatsApp -->
                <a href="https://wa.me/?text=Confira esta vaga: <?php echo urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                   class="btn-whatsapp" target="_blank">
                    <i data-lucide="message-circle" style="width: 20px; height: 20px;"></i>
                    Compartilhar pelo WhatsApp
                </a>
            </div>

            <!-- RODAPÉ DA VAGA -->
            <div class="vaga-footer">
                <a href="vagas.php?area=<?php echo urlencode($vaga['area']); ?>" class="btn-ver-mais">
                    Ver mais outras <?php echo $total_similares; ?> vagas como esta
                </a>

                <?php if ($ja_candidatado): ?>
                    <button class="btn-candidatar" disabled>
                        Candidatura enviada
                    </button>
                <?php else: ?>
                    <button class="btn-candidatar" onclick="abrirModalCandidatura()">
                        Me candidatar
                    </button>
                <?php endif; ?>
            </div>

        </div>

        <!-- ========================================
             📌 SIDEBAR DIREITA
        ======================================== -->
        <aside class="sidebar">

            <!-- Compartilhar vaga -->
            <div class="sidebar-section">
                <h3 class="sidebar-title">Compartilhar vaga por</h3>
                <div class="share-buttons">
                    <a href="mailto:?subject=<?php echo urlencode($vaga['titulo']); ?>&body=<?php echo urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                       class="share-btn">
                        <i data-lucide="mail"></i>
                        Email
                    </a>
                    <a href="https://wa.me/?text=<?php echo urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                       class="share-btn" target="_blank">
                        <i data-lucide="message-circle"></i>
                        WhatsApp
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                       class="share-btn" target="_blank">
                        <i data-lucide="linkedin"></i>
                        LinkedIn
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                       class="share-btn" target="_blank">
                        <i data-lucide="facebook"></i>
                        Facebook
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                       class="share-btn" target="_blank">
                        <i data-lucide="twitter"></i>
                        X (Twitter)
                    </a>
                    <button class="share-btn" onclick="copiarLink()">
                        <i data-lucide="link"></i>
                        Copiar link
                    </button>
                </div>
            </div>

            <!-- Vagas similares -->
            <?php if (count( $vagas_similares) > 0): ?>
            <div class="sidebar-section">
                <h3 class="sidebar-title">Vagas que podem ser do seu interesse</h3>

                <?php foreach ($vagas_similares as $similar): ?>
                    <a href="vaga_detalhe.php?id=<?php echo $similar['id']; ?>" class="vaga-similar">
                        <?php if ($similar['salario_estimado'] > 50000): ?>
                            <span class="similar-badge">DESTAQUE</span>
                        <?php endif; ?>
                        <div class="similar-title"><?php echo htmlspecialchars($similar['titulo']); ?></div>
                        <div class="similar-empresa"><?php echo htmlspecialchars($similar['nome_empresa']); ?></div>
                        <div class="similar-meta">
                            <span>
                                <i data-lucide="map-pin" style="width: 12px; height: 12px;"></i>
                                <?php echo htmlspecialchars($similar['localizacao']); ?>
                            </span>
                            <span>
                                <i data-lucide="monitor" style="width: 12px; height: 12px;"></i>
                                <?php echo traduzirModalidade($similar['modalidade']); ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Tags relacionadas -->
            <?php if (count($tags_relacionadas) > 0): ?>
            <div class="sidebar-section">
                <h3 class="sidebar-title">Quem busca por <?php echo htmlspecialchars($vaga['area']); ?> também se interessa por</h3>
                <div class="tags-container">
                    <?php foreach ($tags_relacionadas as $tag): ?>
                        <a href="vagas.php?area=<?php echo urlencode($tag); ?>" class="tag">
                            <?php echo htmlspecialchars($tag); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </aside>

    </div>

    <!-- ========================================
         📋 MODAL DE CANDIDATURA
    ======================================== -->
    <?php if (!isset($_SESSION['user_id'])): ?>
        <script>
            function abrirModalCandidatura() {
                window.location.href = 'auth/login.php?redirect=vaga_detalhe.php?id=<?php echo $vaga_id; ?>';
            }
        </script>
    <?php elseif ($_SESSION['user_type'] !== 'candidato'): ?>
        <script>
            function abrirModalCandidatura() {
                alert('Apenas candidatos podem se candidatar a vagas.');
            }
        </script>
    <?php else: ?>
        <div class="modal-candidatura" id="modalCandidatura">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Candidatar-se a esta vaga</h3>
                    <button class="modal-close" onclick="fecharModalCandidatura()">&times;</button>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="carta_apresentacao">
                            Carta de Apresentação (Opcional)
                        </label>
                        <textarea 
                            name="carta_apresentacao" 
                            id="carta_apresentacao" 
                            class="form-textarea"
                            placeholder="Conte um pouco sobre você e por que se interessa por esta vaga..."
                        ></textarea>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-cancelar" onclick="fecharModalCandidatura()">
                            Cancelar
                        </button>
                        <button type="submit" name="candidatar" class="btn-enviar">
                            Enviar Candidatura
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function abrirModalCandidatura() {
                document.getElementById('modalCandidatura').classList.add('active');
            }

            function fecharModalCandidatura() {
                document.getElementById('modalCandidatura').classList.remove('active');
            }

            // Fechar ao clicar fora do modal
            document.getElementById('modalCandidatura').addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalCandidatura();
                }
            });
        </script>
    <?php endif; ?>

    <!-- ========================================
         ✨ SCRIPTS
    ======================================== -->
    <script>
        lucide.createIcons();

        function copiarLink() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                alert('Link copiado para a área de transferência!');
            }).catch(() => {
                alert('Erro ao copiar link');
            });
        }
    </script>
</body>
</html>
