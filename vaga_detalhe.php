<?php
session_start();
require_once 'config/db.php';

// ========================================
// VALIDAÇÃO 1: ID DA VAGA
// ========================================
$vaga_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($vaga_id === 0 || $vaga_id < 1) {
    $_SESSION['erro'] = "ID de vaga inválido.";
    header("Location: vagas.php");
    exit;
}

// ========================================
// VALIDAÇÃO 2: BUSCAR DETALHES DA VAGA
// ========================================
$vaga = null;

try {
    $sql_vaga = "SELECT v.*, e.nome_empresa, e.logotipo, e.website, e.descricao as descricao_empresa, 
                        e.localizacao as localizacao_empresa, e.nuit
                 FROM vaga v 
                 JOIN empresa e ON v.empresa_id = e.id 
                 WHERE v.id = ? AND v.ativa = TRUE AND v.data_expiracao >= CURDATE()";

    $stmt = $pdo->prepare($sql_vaga);
    $stmt->execute([$vaga_id]);
    $vaga = $stmt->fetch();

    if (!$vaga) {
        $_SESSION['erro'] = "Vaga não encontrada ou já expirou.";
        header("Location: vagas.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar vaga (ID: $vaga_id): " . $e->getMessage());
    $_SESSION['erro'] = "Erro ao carregar vaga.";
    header("Location: vagas.php");
    exit;
} catch (Exception $e) {
    error_log("Erro inesperado ao buscar vaga: " . $e->getMessage());
    $_SESSION['erro'] = "Erro inesperado.";
    header("Location: vagas.php");
    exit;
}

// ========================================
// VERIFICAR SE JÁ CANDIDATADO + SOCIAL PROOF
// ========================================
$ja_candidatado = false;
$total_candidaturas = 0;
$vaga_guardada = false;

if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'candidato') {
    try {
        // Verificar se já candidatado
        $sql_candidatura = "SELECT id FROM candidatura WHERE vaga_id = ? AND candidato_id = ?";
        $stmt = $pdo->prepare($sql_candidatura);
        $stmt->execute([$vaga_id, $_SESSION['user_id']]);
        $ja_candidatado = (bool)$stmt->fetch();
        
        // Verificar se vaga está guardada
        $sql_guardada = "SELECT id FROM vaga_guardada WHERE vaga_id = ? AND candidato_id = ?";
        $stmt = $pdo->prepare($sql_guardada);
        $stmt->execute([$vaga_id, $_SESSION['user_id']]);
        $vaga_guardada = (bool)$stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erro ao verificar candidatura: " . $e->getMessage());
        // Não bloqueia a página, apenas não mostra se candidatado
    }
}

// Contar total de candidaturas (SOCIAL PROOF)
try {
    $sql_count = "SELECT COUNT(*) FROM candidatura WHERE vaga_id = ?";
    $stmt = $pdo->prepare($sql_count);
    $stmt->execute([$vaga_id]);
    $total_candidaturas = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Erro ao contar candidaturas: " . $e->getMessage());
}

// ========================================
// BUSCAR VAGAS SIMILARES E TAGS
// ========================================
$total_similares = 0;
$vagas_similares = [];
$tags_relacionadas = [];

try {
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
                      LIMIT 5";

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
} catch (PDOException $e) {
    error_log("Erro ao buscar vagas similares: " . $e->getMessage());
    // Não bloqueia a página, apenas não mostra similares
}

// ========================================
// PROCESSAR CANDIDATURA
// ========================================
$sucesso = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidatar'])) {
    // VALIDAÇÃO 1: Usuário autenticado
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidato') {
        $_SESSION['redirect_after_login'] = "vaga_detalhe.php?id=$vaga_id";
        header("Location: auth/login.php");
        exit;
    }
    
    // VALIDAÇÃO 2: Já candidatado
    if ($ja_candidatado) {
        $erro = "Você já se candidatou a esta vaga.";
    } 
    // VALIDAÇÃO 3: Carta de apresentação (opcional, mas validar se enviada)
    else {
        $carta_apresentacao = trim($_POST['carta_apresentacao'] ?? '');
        
        // Validar tamanho da carta
        if (!empty($carta_apresentacao) && strlen($carta_apresentacao) > 2000) {
            $erro = "A carta de apresentação não pode ter mais de 2000 caracteres.";
        } else {
            try {
                // Iniciar transação
                $pdo->beginTransaction();
                
                // Verificar novamente se não candidatado (prevenção de duplicatas)
                $sql_check = "SELECT id FROM candidatura WHERE vaga_id = ? AND candidato_id = ?";
                $stmt = $pdo->prepare($sql_check);
                $stmt->execute([$vaga_id, $_SESSION['user_id']]);
                
                if ($stmt->fetch()) {
                    $pdo->rollBack();
                    $erro = "Você já se candidatou a esta vaga.";
                    $ja_candidatado = true;
                } else {
                    // Inserir candidatura
                    $sql_inserir = "INSERT INTO candidatura (vaga_id, candidato_id, carta_apresentacao) 
                                   VALUES (?, ?, ?)";
                    $stmt = $pdo->prepare($sql_inserir);
                    $resultado = $stmt->execute([$vaga_id, $_SESSION['user_id'], $carta_apresentacao]);
                    
                    if (!$resultado) {
                        throw new Exception("Falha ao inserir candidatura.");
                    }
                    
                    // Confirmar transação
                    $pdo->commit();
                    
                    $sucesso = "Candidatura enviada com sucesso!";
                    $ja_candidatado = true;
                    
                    // Log de sucesso
                    error_log("Candidatura enviada: Candidato {$_SESSION['user_id']} -> Vaga $vaga_id");
                }
                
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
                // Verificar se é erro de chave duplicada
                if ($e->getCode() == 23000) { // Duplicate entry
                    $erro = "Você já se candidatou a esta vaga.";
                    $ja_candidatado = true;
                } else {
                    $erro = "Erro ao enviar candidatura. Por favor, tente novamente.";
                    error_log("Erro PDO ao enviar candidatura: " . $e->getMessage());
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $erro = "Erro inesperado. Por favor, tente novamente.";
                error_log("Erro ao enviar candidatura: " . $e->getMessage());
            }
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

function traduzirNivelExperiencia($nivel) {
    // O nível já vem traduzido do banco, mas garantimos consistência
    $niveis = [
        'Estagiário' => 'Estagiário',
        'Júnior' => 'Júnior',
        'Pleno' => 'Pleno',
        'Sénior' => 'Sénior',
        'Gestor' => 'Gestor',
        'Diretor' => 'Diretor'
    ];
    return $niveis[$nivel] ?? $nivel ?? 'Não especificado';
}

function getCorNivelExperiencia($nivel) {
    $cores = [
        'Estagiário' => '#06B6D4',      // Azul claro
        'Júnior' => '#10B981',          // Verde
        'Pleno' => '#F59E0B',           // Laranja
        'Sénior' => '#8B5CF6',          // Roxo
        'Gestor' => '#EF4444',          // Vermelho
        'Diretor' => '#14213d'          // Oxford Blue
    ];
    return $cores[$nivel] ?? '#64748b';
}

function isVagaNova($data_publicacao) {
    $agora = new DateTime();
    $publicacao = new DateTime($data_publicacao);
    $diferenca = $agora->diff($publicacao);
    return $diferenca->days <= 3;
}

function diasParaExpirar($data_expiracao) {
    $agora = new DateTime();
    $expiracao = new DateTime($data_expiracao);
    $diferenca = $agora->diff($expiracao);
    return $diferenca->days;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($vaga['titulo']); ?> - <?php echo htmlspecialchars($vaga['nome_empresa']); ?> | Emprego MZ</title>
    <meta name="description" content="<?php echo htmlspecialchars(substr($vaga['descricao'], 0, 160)); ?>">

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
            /* === 🎨 PALETA DEFINITIVA PARA DETALHES DA VAGA === */
            
            /* 🔵 CORES PRINCIPAIS */
            --cor-primaria: #14213d;              /* Oxford Blue - Header, empresa, elementos principais */
            --cor-primaria-escura: #0f1a2e;       /* Oxford Blue Dark - Hover intenso */
            --cor-primaria-clara: #1e2c47;        /* Oxford Blue Light - Elementos secundários */
            --cor-primaria-alpha: rgba(20, 33, 61, 0.1);
            
            /* 🟠 CORES SECUNDÁRIAS */
            --cor-secundaria: #fca311;            /* Orange Web - Botão "Candidatar-se", CTAs */
            --cor-secundaria-hover: #e3940f;      /* Orange Hover - Hover no botão candidatar-se */
            --cor-secundaria-clara: #fdb541;      /* Orange Light */
            --cor-secundaria-muito-clara: #fec871; /* Orange Very Light */
            --cor-secundaria-alpha: rgba(252, 163, 17, 0.1);
            
            /* 🌫️ HIERARQUIA VISUAL - Soft Gray System */
            --cor-fundo: #f8fafc;                 /* Soft Gray - Fundo geral da página */
            --cor-cards: #ffffff;                 /* White - Card principal, sidebar, modais */
            --cor-acento: #f1f5ff;                /* Light Blue - Hover states, seções especiais */
            --cor-acento-escuro: #e1ebff;         /* Light Blue Dark - Seções destacadas */
            
            /* 📝 TEXTO REFINADO - Hierarquia */
            --cor-texto: #1a202c;                 /* Título da vaga */
            --cor-texto-claro: #64748b;           /* Descrição, requisitos */
            --cor-texto-muito-claro: #94a3b8;     /* Metadados, datas */
            
            /* 🎯 ELEMENTOS ESPECÍFICOS DE CONVERSÃO */
            --cor-empresa: #14213d;               /* Nome da empresa - Oxford Blue */
            --cor-salario: #059669;               /* Valor do salário - Verde escuro */
            --cor-urgente: #EF4444;               /* Badge "Urgente" - Vermelho */
            --cor-nova: #10B981;                  /* Badge "Nova" - Verde */
            
            /* 🎯 BORDAS E SOMBRAS ESPECÍFICAS */
            --cor-borda: #e2e8f0;                 /* Bordas suaves */
            --cor-borda-destaque: #fca311;        /* Bordas em destaque - Orange */
            --sombra-card: 0 4px 16px rgba(20, 33, 61, 0.08);
            --sombra-card-hover: 0 8px 24px rgba(20, 33, 61, 0.15);
            --sombra-botao: 0 2px 8px rgba(252, 163, 17, 0.3);
            
            /* ✅ ESTADOS */
            --cor-sucesso: #10B981;
            --cor-erro: #EF4444;
            --cor-aviso: #F59E0B;
            --cor-info: #14213d;
            
            /* 🔄 Aliases compatibilidade */
            --primary: var(--cor-primaria);
            --primary-dark: var(--cor-primaria-escura);
            --secondary: var(--cor-secundaria);
            --secondary-hover: var(--cor-secundaria-hover);
            --accent: var(--cor-sucesso);
            
            /* Texto */
            --text: var(--cor-texto);
            --text-light: var(--cor-texto-claro);
            --text-lighter: var(--cor-texto-muito-claro);
            
            /* Backgrounds e Bordas */
            --border: var(--cor-borda);
            --border-highlight: var(--cor-borda-destaque);
            --gray-50: var(--cor-fundo);
            --gray-100: #f1f5f9;
            --gray-200: var(--cor-borda);
            --white: #ffffff;
            
            /* Estados */
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
            
            /* Shadows - Usando variáveis específicas */
            --shadow-sm: 0 1px 3px rgba(20, 33, 61, 0.06);
            --shadow: var(--sombra-card);
            --shadow-md: var(--sombra-card);
            --shadow-lg: var(--sombra-card-hover);
            --shadow-xl: 0 16px 64px rgba(20, 33, 61, 0.2);
            
            /* Transitions */
            --transition: all 0.2s ease;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--cor-texto);             /* #1a202c - Texto principal */
            background: var(--cor-fundo);        /* #f8fafc - Soft Gray */
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ==========================================
           📱 HEADER PROFISSIONAL - Oxford Blue (CONSISTENTE COM INDEX)
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
           🍞 BREADCRUMB
        ========================================== */
        .breadcrumb {
            background: var(--cor-cards);            /* #ffffff - White */
            border-bottom: 1px solid var(--cor-borda);
            margin-top: 73px;
            box-shadow: var(--shadow-sm);
        }

        .breadcrumb-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-3) var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 14px;
            color: var(--cor-texto-claro);           /* #64748b */
            flex-wrap: wrap;
        }

        .breadcrumb-link {
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb-link:hover {
            color: var(--cor-secundaria);            /* #fca311 - Orange */
            text-decoration: underline;
        }

        .breadcrumb-separator {
            width: 16px;
            height: 16px;
            color: var(--cor-texto-muito-claro);     /* #94a3b8 */
        }

        .breadcrumb-current {
            color: var(--cor-texto);                 /* #1a202c */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 400px;
        }

        /* ==========================================
           📋 MAIN LAYOUT
        ========================================== */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-8) var(--space-6);
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: var(--space-8);
            align-items: start;
        }

        /* ==========================================
           💼 JOB CONTENT
        ========================================== */
        .job-content {
            min-width: 0;
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

        /* Job Header Card */
        .job-header-card {
            background: var(--cor-cards);            /* #ffffff - White card principal */
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: 16px;
            padding: 32px;
            margin-bottom: var(--space-6);
            box-shadow: var(--sombra-card);          /* Sombra profissional */
            position: relative;
        }
        

        .job-header-top {
            display: flex;
            gap: var(--space-6);
            margin-bottom: var(--space-6);
        }

        .company-logo-large {
            width: 96px;
            height: 96px;
            flex-shrink: 0;
            border-radius: var(--radius-lg);
            border: 2px solid var(--cor-borda);      /* #e2e8f0 */
            overflow: hidden;
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .company-logo-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .job-header-info {
            flex: 1;
            min-width: 0;
        }

        .job-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            margin-bottom: 12px;
            line-height: 1.2;
        }

        .company-name-link {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 20px;
            font-weight: 600;
            color: var(--cor-empresa);               /* #14213d - Oxford Blue */
            text-decoration: none;
            margin-bottom: 8px;
            transition: var(--transition);
        }

        .company-name-link:hover {
            color: var(--cor-secundaria);            /* #fca311 - Orange */
        }

        .company-name-link svg {
            width: 20px;
            height: 20px;
        }

        .job-header-badges {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-1);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 600;
        }

        .badge svg {
            width: 14px;
            height: 14px;
        }

        .badge-new {
            background: var(--cor-nova);             /* #10B981 - Verde "Nova" */
            color: var(--white);
            padding: 6px 12px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }

        .badge-remote {
            background: var(--cor-acento-escuro);    /* #e1ebff - Light Blue */
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            border: 1px solid var(--cor-primaria);
            padding: 6px 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-urgent {
            background: var(--cor-urgente);          /* #EF4444 - Vermelho "Urgente" */
            color: var(--white);
            padding: 6px 12px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
        }

        .badge-featured {
            background: rgba(255, 140, 0, 0.1);
            color: var(--secondary);
        }
        
        /* Badge Social Proof */
        .badge-social-proof {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--white);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            text-transform: none;
            letter-spacing: 0.3px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
            animation: pulse-soft 3s infinite;
        }
        
        .badge-social-proof svg {
            width: 14px;
            height: 14px;
        }
        
        @keyframes pulse-soft {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.03); }
        }
        
        /* Badge Urgência com Countdown */
        .badge-countdown {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: var(--white);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
            animation: pulse-urgent 1.5s infinite;
            position: relative;
            overflow: hidden;
        }
        
        .badge-countdown::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shine 3s infinite;
        }
        
        @keyframes pulse-urgent {
            0%, 100% { transform: scale(1); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 6px 16px rgba(239, 68, 68, 0.6); }
        }
        
        @keyframes shine {
            0% { left: -100%; }
            100% { left: 200%; }
        }
        
        .countdown-number {
            font-size: 16px;
            font-weight: 900;
            min-width: 24px;
            text-align: center;
        }

        /* Job Info Grid - MELHORADO */
        .job-info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);   /* 3 colunas fixas para melhor organização */
            gap: var(--space-6);
            padding: var(--space-6) 0;
            border-top: 2px solid var(--cor-acento); /* Border mais visível */
        }

        .info-item-detailed {
            display: flex;
            align-items: flex-start;
            gap: var(--space-3);
            padding: var(--space-4);                 /* Padding para melhor toque */
            border-radius: var(--radius-lg);
            transition: var(--transition);
        }

        .info-item-detailed:hover {
            background: var(--cor-acento);           /* Hover state */
            transform: translateY(-1px);
        }

        /* Salário - DESTAQUE ESPECIAL */
        .info-item-detailed.salary-highlight {
            grid-column: 1 / -1;                     /* Ocupa toda a largura */
            background: linear-gradient(135deg, rgba(5, 150, 105, 0.05), rgba(5, 150, 105, 0.1));
            border: 2px solid var(--cor-salario);
            padding: var(--space-5);
            margin-bottom: var(--space-4);
        }

        .info-icon-wrapper {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            border-radius: var(--radius-lg);
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            border: 2px solid var(--cor-acento-escuro);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .info-item-detailed:hover .info-icon-wrapper {
            background: var(--cor-primaria);
            border-color: var(--cor-primaria);
        }

        .info-item-detailed:hover .info-icon-wrapper svg {
            color: var(--cor-cards);                 /* White no hover */
        }

        /* Nível de Experiência - Destaque especial */
        .info-item-experiencia .info-icon-wrapper {
            width: 52px;
            height: 52px;
            border-width: 3px;
        }

        .info-item-experiencia .info-icon-wrapper svg {
            color: var(--cor-cards);                 /* White icon */
            width: 24px;
            height: 24px;
        }

        .salary-highlight .info-icon-wrapper {
            background: var(--cor-salario);
            border-color: var(--cor-salario);
            width: 56px;
            height: 56px;
        }

        .info-icon-wrapper svg {
            width: 22px;
            height: 22px;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            transition: var(--transition);
        }

        .salary-highlight .info-icon-wrapper svg {
            width: 28px;
            height: 28px;
            color: var(--cor-cards);                 /* White */
        }

        .info-item-content {
            flex: 1;
        }

        .info-item-label {
            font-size: 11px;
            color: var(--cor-texto-muito-claro);     /* #94a3b8 - Metadados */
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .info-item-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            line-height: 1.3;
        }
        
        /* Salário - Destaque MEGA especial */
        .salary-highlight .info-item-label {
            font-size: 13px;
            color: var(--cor-salario);
            margin-bottom: 8px;
        }

        .salary-highlight .info-item-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--cor-salario);               /* #059669 - Verde escuro */
            letter-spacing: -0.5px;
        }

        /* Job Description Card */
        .job-description-card {
            background: var(--cor-cards);            /* #ffffff - White */
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: var(--sombra-card);
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: var(--space-3);
            border-bottom: 2px solid var(--cor-acento); /* #f1f5ff - Light Blue */
            padding-bottom: 8px;
        }

        .section-title svg {
            width: 24px;
            height: 24px;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
        }

        .section-content {
            font-size: 16px;
            line-height: 1.8;                        /* Melhor legibilidade */
            color: var(--cor-texto-claro);           /* #64748b - Descrição, requisitos */
            white-space: pre-wrap;
        }

        .section-content p {
            margin-bottom: var(--space-4);           /* Espaçamento entre parágrafos */
        }

        .section-content strong {
            color: var(--cor-primaria);              /* #14213d - Oxford Blue para destaque */
            font-weight: 700;
        }
        
        .section-content ul,
        .section-content ol {
            padding-left: 24px;
            margin: var(--space-4) 0;
        }
        
        .section-content li {
            margin-bottom: var(--space-3);
            color: var(--cor-texto-claro);           /* #64748b */
            line-height: 1.6;
        }

        .section-content li::marker {
            color: var(--cor-secundaria);            /* Orange para bullets */
        }

        .divider {
            height: 1px;
            background: var(--border);
            margin: var(--space-8) 0;
        }

        /* Apply Button - Híbrido para Melhor UX */
        /* Desktop: Sticky dentro do sidebar (aparece naturalmente ao rolar, não bloqueia conteúdo inicial) */
        .apply-button-wrapper {
            position: sticky;
            bottom: var(--space-6); /* Fica no final da viewport ao rolar */
            z-index: 10;
            margin-bottom: 0;
        }
        
        /* Quando é sidebar-card, remover padding extra e manter estilo consistente */
        .apply-button-wrapper.sidebar-card {
            padding: var(--space-6);
            margin-bottom: var(--space-5);
        }

        .apply-button {
            width: 100%;
            padding: 12px 20px;
            background: var(--cor-secundaria);       /* #fca311 - Orange */
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            box-shadow: var(--sombra-botao);         /* Sombra com Orange */
        }

        .apply-button:hover:not(:disabled) {
            background: var(--cor-secundaria-hover); /* #e3940f */
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(252, 163, 17, 0.4);
        }
        
        .apply-button:active {
            transform: translateY(0);
        }

        .apply-button:disabled {
            background: #94a3b8;
            color: white;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .apply-button svg {
            width: 18px;
            height: 18px;
        }

        /* ==========================================
           📌 SIDEBAR - STICKY MELHORADO
        ========================================== */
        .sidebar {
            position: sticky;
            top: 90px;                               /* Header height + espaço */
            align-self: start;
            max-height: calc(100vh - 110px);         /* Limita altura */
            overflow-y: auto;                        /* Scroll interno se necessário */
            overflow-x: hidden;
        }

        /* Scrollbar customizada para sidebar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--cor-borda);
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: var(--cor-texto-claro);
        }

        .sidebar-card {
            background: var(--cor-cards);            /* #ffffff - White */
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            margin-bottom: var(--space-5);
            box-shadow: var(--sombra-card);
            transition: var(--transition);
        }

        .sidebar-card:hover {
            box-shadow: var(--sombra-card-hover);
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            margin-bottom: var(--space-5);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            border-bottom: 3px solid var(--cor-acento); /* Border mais visível */
            padding-bottom: var(--space-3);
        }

        .sidebar-title svg {
            width: 22px;
            height: 22px;
            color: var(--cor-secundaria);            /* Orange para ícones */
        }

        /* Share Buttons - MELHORADO */
        .share-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-3);
        }

        .share-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            padding: var(--space-4);
            border: 2px solid var(--cor-borda);
            background: var(--cor-cards);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: var(--cor-texto-claro);
            font-size: 13px;
            font-weight: 600;
        }

        .share-btn:hover {
            background: var(--cor-acento);           /* Light Blue */
            border-color: var(--cor-secundaria);     /* Orange */
            color: var(--cor-primaria);              /* Oxford Blue */
            transform: translateY(-2px);
            box-shadow: var(--sombra-card);
        }

        .share-btn svg {
            width: 24px;
            height: 24px;
            transition: var(--transition);
        }

        .share-btn:hover svg {
            color: var(--cor-secundaria);            /* Orange no hover */
            transform: scale(1.1);
        }

        /* Similar Jobs - MELHORADO */
        .similar-job-card {
            padding: var(--space-4);
            border: 2px solid var(--cor-borda);      /* Border mais visível */
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-4);
            text-decoration: none;
            color: inherit;
            display: block;
            transition: all 0.3s ease;
            background: var(--cor-cards);
        }

        .similar-job-card:hover {
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            border-color: var(--cor-secundaria);     /* #fca311 - Orange */
            box-shadow: var(--sombra-card-hover);
            transform: translateY(-2px);
        }

        .similar-job-header {
            display: flex;
            gap: var(--space-3);
            margin-bottom: var(--space-3);
        }

        .similar-company-logo {
            width: 48px;                             /* Maior para melhor visibilidade */
            height: 48px;
            flex-shrink: 0;
            border-radius: var(--radius-lg);
            border: 2px solid var(--cor-borda);
            overflow: hidden;
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            transition: var(--transition);
        }

        .similar-job-card:hover .similar-company-logo {
            border-color: var(--cor-secundaria);
            transform: scale(1.05);
        }

        .similar-company-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .similar-job-info {
            flex: 1;
            min-width: 0;
        }

        .similar-job-title {
            font-size: 15px;                         /* Ligeiramente maior */
            font-weight: 700;                        /* Mais bold */
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            margin-bottom: 6px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;                   /* Permite 2 linhas */
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .similar-job-card:hover .similar-job-title {
            color: var(--cor-secundaria);            /* Orange no hover */
        }

        .similar-company-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--cor-texto-claro);           /* #64748b */
            margin-bottom: var(--space-2);
        }

        .similar-job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-3);
            font-size: 12px;
            color: var(--cor-texto-muito-claro);     /* #94a3b8 */
            padding-top: var(--space-2);
            border-top: 1px solid var(--cor-borda);
        }

        .similar-job-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
            background: var(--cor-acento);
            padding: 4px 8px;
            border-radius: var(--radius);
        }

        .similar-job-card:hover .similar-job-meta span {
            background: var(--cor-cards);
        }

        .similar-job-meta svg {
            width: 13px;
            height: 13px;
            color: var(--cor-secundaria);            /* Orange para ícones */
        }

        .view-more-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            text-align: center;
            padding: var(--space-3) var(--space-4);
            background: var(--cor-acento);           /* Light Blue */
            border: 2px solid var(--cor-borda);
            border-radius: var(--radius-lg);
            color: var(--cor-primaria);              /* Oxford Blue */
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: var(--transition);
            margin-top: var(--space-4);
        }

        .view-more-link:hover {
            background: var(--cor-secundaria);       /* Orange */
            color: var(--cor-cards);                 /* White */
            border-color: var(--cor-secundaria);
            transform: translateY(-1px);
            box-shadow: var(--sombra-card);
        }

        /* Tags - MELHORADO */
        .tags-grid {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }

        .tag {
            padding: var(--space-2) var(--space-4);
            background: var(--cor-acento);           /* Light Blue */
            border: 2px solid var(--cor-borda);
            border-radius: var(--radius-full);
            font-size: 13px;
            font-weight: 600;
            color: var(--cor-texto);
            text-decoration: none;
            transition: var(--transition);
        }

        .tag:hover {
            background: var(--cor-primaria);         /* Oxford Blue */
            color: var(--cor-cards);                 /* White */
            border-color: var(--cor-primaria);
            transform: translateY(-1px);
            box-shadow: var(--sombra-card);
        }

        /* ==========================================
           📋 MODAL DE CANDIDATURA
        ========================================== */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(20, 33, 61, 0.8);       /* Oxford Blue overlay */
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
            background: var(--cor-cards);            /* #ffffff - White */
            border-radius: 16px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 16px 64px rgba(20, 33, 61, 0.2);
            animation: slideUp 0.3s ease-out;
        }

        .modal-header {
            padding: 32px 32px 24px;
            border-bottom: 1px solid var(--cor-borda);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
        }

        .modal-close {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            color: var(--cor-texto-claro);
            cursor: pointer;
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .modal-close:hover {
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            color: var(--cor-primaria);
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
            color: var(--cor-texto);                 /* #1a202c */
            margin-bottom: 8px;
        }

        .form-textarea {
            width: 100%;
            min-height: 140px;
            padding: 12px;
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: 8px;
            font-size: 16px;
            font-family: var(--font-primary);
            color: var(--cor-texto);                 /* #1a202c */
            background: var(--cor-cards);            /* #ffffff */
            resize: vertical;
            transition: var(--transition);
        }

        .form-textarea:focus {
            outline: none;
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            border-color: var(--cor-primaria);       /* #14213d - Oxford Blue */
            box-shadow: 0 0 0 3px rgba(20, 33, 61, 0.1);
        }

        .form-textarea::placeholder {
            color: var(--cor-texto-muito-claro);     /* #94a3b8 - Placeholders */
        }

        .form-help-text {
            font-size: 13px;
            color: var(--cor-texto-claro);           /* #64748b */
            margin-top: var(--space-2);
        }

        .modal-footer {
            padding: var(--space-6);
            border-top: 1px solid var(--border);
            display: flex;
            gap: var(--space-3);
        }

        .btn-cancel {
            flex: 1;
            padding: var(--space-3) var(--space-6);
            background: var(--white);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-cancel:hover {
            background: var(--gray-100);
        }

        .btn-submit {
            flex: 2;
            padding: var(--space-3) var(--space-6);
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            box-shadow: var(--shadow-md);
        }

        /* ==========================================
           🎨 FOOTER RICO (CONSISTENTE COM INDEX)
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
        @media (max-width: 1200px) {
            .job-info-grid {
                grid-template-columns: repeat(2, 1fr); /* 2 colunas em tablets */
                gap: var(--space-5);
            }

            .salary-highlight {
                grid-column: 1 / -1;                 /* Salário continua em largura total */
            }
        }

        @media (max-width: 1024px) {
            .main-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;                    /* Sidebar não-sticky em mobile */
                max-height: none;
                overflow-y: visible;
            }

            .nav-menu {
                display: none;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .job-info-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-4);
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

            .breadcrumb-current {
                max-width: 200px;
            }

            .main-container {
                padding: var(--space-6) var(--space-4);
                gap: var(--space-6);
            }

            .job-header-card {
                padding: var(--space-5);
                border-radius: var(--radius-lg);
            }

            .job-header-top {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .company-logo-large {
                width: 80px;
                height: 80px;
            }

            .job-title {
                font-size: 24px;
            }

            .company-name-link {
                font-size: 18px;
            }

            /* Grid em 1 coluna no mobile */
            .job-info-grid {
                grid-template-columns: 1fr;
                gap: var(--space-3);
                padding: var(--space-5) 0;
            }

            .salary-highlight {
                padding: var(--space-4);
            }

            .salary-highlight .info-item-value {
                font-size: 28px;                    /* Ligeiramente menor no mobile */
            }

            .info-icon-wrapper {
                width: 40px;
                height: 40px;
            }

            .salary-highlight .info-icon-wrapper {
                width: 48px;
                height: 48px;
            }

            .job-description-card {
                padding: var(--space-5);
                border-radius: var(--radius-lg);
            }

            .section-content {
                font-size: 15px;                    /* Ligeiramente menor no mobile */
            }

            .share-buttons {
                grid-template-columns: repeat(2, 1fr);
            }

            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-6);
            }

            .header-actions .btn {
                font-size: 13px;
                padding: var(--space-2) var(--space-3);
            }

            .header-actions .btn svg {
                width: 16px;
                height: 16px;
            }

            /* Mobile: Fixed na parte inferior (melhor para telas pequenas) */
            .apply-button-wrapper {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: var(--white);
                border-top: 2px solid var(--cor-borda);
                border-radius: 0;
                padding: var(--space-4);
                margin: 0;
                z-index: 999;
                box-shadow: 0 -4px 20px rgba(20, 33, 61, 0.2);
            }
            
            /* Padding inferior no mobile para não cobrir conteúdo */
            .main-container {
                padding-bottom: 100px;
            }
            
            .footer {
                padding-bottom: 100px;
            }
        }

        @media (max-width: 480px) {
            .footer-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ==========================================
           🍞 TOAST NOTIFICATIONS
        ========================================== */
        .toast {
            position: fixed;
            bottom: var(--space-6);
            right: var(--space-6);
            background: var(--cor-primaria);
            color: var(--white);
            padding: var(--space-4) var(--space-6);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            z-index: 9999;
            animation: slideInRight 0.3s ease-out;
            max-width: 400px;
        }
        
        .toast.success {
            background: var(--cor-sucesso);
        }
        
        .toast.error {
            background: var(--cor-erro);
        }
        
        .toast svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        
        .toast-message {
            font-size: 14px;
            font-weight: 600;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
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
    </style>
</head>
<body>

    <!-- ==========================================
         📱 HEADER - CONSISTENT WITH OTHER PAGES
    ========================================== -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <img src="assets/images/empregos-logo.svg" alt="Emprego MZ" class="logo-img">
            </a>

            <nav class="nav-menu">
                <a href="index.php" class="nav-link">Início</a>
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
         🍞 BREADCRUMB
    ========================================== -->
    <div class="breadcrumb">
        <div class="breadcrumb-container">
            <a href="index.php" class="breadcrumb-link">Início</a>
            <i data-lucide="chevron-right" class="breadcrumb-separator"></i>
            <a href="vagas.php" class="breadcrumb-link">Vagas</a>
            <i data-lucide="chevron-right" class="breadcrumb-separator"></i>
            <span class="breadcrumb-current"><?php echo htmlspecialchars($vaga['titulo']); ?></span>
        </div>
    </div>

    <!-- ==========================================
         📋 MAIN CONTENT
    ========================================== -->
    <div class="main-container">

        <!-- JOB CONTENT -->
        <div class="job-content">

            <!-- Alerts -->
            <?php if ($sucesso): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle"></i>
                    <?php echo htmlspecialchars($sucesso); ?>
                </div>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle"></i>
                    <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>

            <!-- Job Header Card -->
            <div class="job-header-card">
                <div class="job-header-top">
                    <div class="company-logo-large">
                        <?php if (!empty($vaga['logotipo']) && file_exists('uploads/' . $vaga['logotipo'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($vaga['logotipo']); ?>" 
                                 alt="<?php echo htmlspecialchars($vaga['nome_empresa']); ?>">
                        <?php else: ?>
                            <img src="assets/images/empresa-default.png" alt="Logo padrão">
                        <?php endif; ?>
                    </div>

                    <div class="job-header-info">
                        <h1 class="job-title"><?php echo htmlspecialchars($vaga['titulo']); ?></h1>
                        <a href="vagas.php?empresa=<?php echo urlencode($vaga['nome_empresa']); ?>" class="company-name-link">
                            <i data-lucide="building-2"></i>
                            <?php echo htmlspecialchars($vaga['nome_empresa']); ?>
                        </a>
                        <div class="job-header-badges">
                            <?php if (isVagaNova($vaga['data_publicacao'])): ?>
                            <span class="badge badge-new">
                                <i data-lucide="sparkles"></i>
                                Nova
                            </span>
                            <?php endif; ?>
                            
                            <?php 
                            // SOCIAL PROOF - Badge de candidaturas
                            if ($total_candidaturas > 0):
                                $texto_candidaturas = $total_candidaturas == 1 
                                    ? '1 pessoa já se candidatou' 
                                    : "$total_candidaturas pessoas já se candidataram";
                            ?>
                            <span class="badge badge-social-proof">
                                <i data-lucide="users"></i>
                                <?php echo $texto_candidaturas; ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($vaga['modalidade'] === 'remoto'): ?>
                            <span class="badge badge-remote">
                                <i data-lucide="home"></i>
                                Remoto
                            </span>
                            <?php endif; ?>
                            
                            <?php 
                            // URGÊNCIA MELHORADA - Countdown se expira em breve
                            $dias_expiracao = diasParaExpirar($vaga['data_expiracao']);
                            if ($dias_expiracao <= 7): 
                            ?>
                            <span class="badge badge-countdown">
                                <i data-lucide="alert-circle"></i>
                                <?php if ($dias_expiracao === 0): ?>
                                    Expira HOJE
                                <?php elseif ($dias_expiracao === 1): ?>
                                    Expira AMANHÃ
                                <?php else: ?>
                                    <span class="countdown-number"><?php echo $dias_expiracao; ?></span> dias restantes
                                <?php endif; ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($vaga['salario_estimado'] > 50000): ?>
                            <span class="badge badge-featured">
                                <i data-lucide="star"></i>
                                Destaque
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="job-info-grid">
                    <!-- SALÁRIO - DESTAQUE ESPECIAL (ocupa toda largura) -->
                    <div class="info-item-detailed salary-highlight">
                        <div class="info-icon-wrapper">
                            <i data-lucide="banknote"></i>
                        </div>
                        <div class="info-item-content">
                            <div class="info-item-label">💰 Salário Oferecido</div>
                            <div class="info-item-value"><?php echo formatarSalario($vaga['salario_estimado']); ?></div>
                        </div>
                    </div>

                    <!-- OUTRAS INFORMAÇÕES (3 colunas) -->
                    <div class="info-item-detailed">
                        <div class="info-icon-wrapper">
                            <i data-lucide="map-pin"></i>
                        </div>
                        <div class="info-item-content">
                            <div class="info-item-label">Localização</div>
                            <div class="info-item-value"><?php echo htmlspecialchars($vaga['localizacao']); ?></div>
                        </div>
                    </div>

                    <div class="info-item-detailed">
                        <div class="info-icon-wrapper">
                            <i data-lucide="monitor"></i>
                        </div>
                        <div class="info-item-content">
                            <div class="info-item-label">Modalidade</div>
                            <div class="info-item-value"><?php echo traduzirModalidade($vaga['modalidade']); ?></div>
                        </div>
                    </div>

                    <div class="info-item-detailed">
                        <div class="info-icon-wrapper">
                            <i data-lucide="briefcase"></i>
                        </div>
                        <div class="info-item-content">
                            <div class="info-item-label">Área Profissional</div>
                            <div class="info-item-value"><?php echo htmlspecialchars($vaga['area']); ?></div>
                        </div>
                    </div>

                    <div class="info-item-detailed">
                        <div class="info-icon-wrapper">
                            <i data-lucide="users"></i>
                        </div>
                        <div class="info-item-content">
                            <div class="info-item-label">Tipo de Contrato</div>
                            <div class="info-item-value"><?php echo traduzirTipoContrato($vaga['tipo_contrato']); ?></div>
                        </div>
                    </div>

                    <?php if (!empty($vaga['nivel_experiencia'])): ?>
                    <div class="info-item-detailed info-item-experiencia">
                        <div class="info-icon-wrapper" style="background: <?php echo getCorNivelExperiencia($vaga['nivel_experiencia']); ?>; border-color: <?php echo getCorNivelExperiencia($vaga['nivel_experiencia']); ?>;">
                            <i data-lucide="award"></i>
                        </div>
                        <div class="info-item-content">
                            <div class="info-item-label">Nível de Experiência</div>
                            <div class="info-item-value" style="color: <?php echo getCorNivelExperiencia($vaga['nivel_experiencia']); ?>; font-weight: 800;">
                                <?php echo traduzirNivelExperiencia($vaga['nivel_experiencia']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="info-item-detailed">
                        <div class="info-icon-wrapper">
                            <i data-lucide="calendar"></i>
                        </div>
                        <div class="info-item-content">
                            <div class="info-item-label">Publicada</div>
                            <div class="info-item-value"><?php echo tempoDecorrido($vaga['data_publicacao']); ?></div>
                        </div>
                    </div>

                    <?php if (!empty($vaga['data_expiracao'])): ?>
                    <div class="info-item-detailed">
                        <div class="info-icon-wrapper">
                            <i data-lucide="calendar-x"></i>
                        </div>
                        <div class="info-item-content">
                            <div class="info-item-label">Expira em</div>
                            <div class="info-item-value">
                                <?php 
                                $dias_expiracao = diasParaExpirar($vaga['data_expiracao']);
                                if ($dias_expiracao === 0) {
                                    echo 'Hoje';
                                } elseif ($dias_expiracao === 1) {
                                    echo 'Amanhã';
                                } elseif ($dias_expiracao <= 7) {
                                    echo $dias_expiracao . ' dias';
                                } else {
                                    echo date('d/m/Y', strtotime($vaga['data_expiracao']));
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Job Description Card -->
            <div class="job-description-card">
                <h2 class="section-title">
                    <i data-lucide="file-text"></i>
                    Descrição da Vaga
                </h2>
                <div class="section-content">
                    <?php echo nl2br(htmlspecialchars($vaga['descricao'])); ?>
                </div>

                <?php if (!empty($vaga['descricao_empresa'])): ?>
                <div class="divider"></div>
                <h2 class="section-title">
                    <i data-lucide="building"></i>
                    Sobre a Empresa
                </h2>
                <div class="section-content">
                    <strong><?php echo htmlspecialchars($vaga['nome_empresa']); ?></strong><br><br>
                    <?php echo nl2br(htmlspecialchars($vaga['descricao_empresa'])); ?>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- ==========================================
             📌 SIDEBAR
        ========================================== -->
        <aside class="sidebar">

            <!-- Apply Button - Dentro do Sidebar (Desktop Sticky, Mobile Fixed) -->
            <div class="apply-button-wrapper sidebar-card">
                <?php if ($ja_candidatado): ?>
                    <button class="apply-button" disabled>
                        <i data-lucide="check-circle"></i>
                        Candidatura Enviada
                    </button>
                <?php else: ?>
                    <button class="apply-button" onclick="openApplicationModal()">
                        <i data-lucide="send"></i>
                        Candidatar-me a esta Vaga
                    </button>
                <?php endif; ?>
            </div>

            <!-- Share Job -->
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    <i data-lucide="share-2"></i>
                    Compartilhar Vaga
                </h3>
                <div class="share-buttons">
                    <a href="https://wa.me/?text=<?php echo urlencode('Confira esta vaga: ' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                       class="share-btn" target="_blank">
                        <i data-lucide="message-circle"></i>
                        WhatsApp
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                       class="share-btn" target="_blank">
                        <i data-lucide="linkedin"></i>
                        LinkedIn
                    </a>
                    <a href="mailto:?subject=<?php echo urlencode($vaga['titulo']); ?>&body=<?php echo urlencode('Confira esta vaga: http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                       class="share-btn">
                        <i data-lucide="mail"></i>
                        Email
                    </a>
                    <button class="share-btn" onclick="copyJobLink()">
                        <i data-lucide="link"></i>
                        Copiar Link
                    </button>
                </div>
            </div>

            <!-- Similar Jobs -->
            <?php if (count($vagas_similares) > 0): ?>
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    <i data-lucide="briefcase"></i>
                    Vagas Similares
                </h3>

                <?php foreach ($vagas_similares as $similar): ?>
                    <a href="vaga_detalhe.php?id=<?php echo $similar['id']; ?>" class="similar-job-card">
                        <div class="similar-job-header">
                            <div class="similar-company-logo">
                                <?php if (!empty($similar['logotipo']) && file_exists('uploads/' . $similar['logotipo'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($similar['logotipo']); ?>" 
                                         alt="<?php echo htmlspecialchars($similar['nome_empresa']); ?>">
                                <?php else: ?>
                                    <img src="assets/images/empresa-default.png" alt="Logo padrão">
                                <?php endif; ?>
                            </div>
                            <div class="similar-job-info">
                                <div class="similar-job-title"><?php echo htmlspecialchars($similar['titulo']); ?></div>
                                <div class="similar-company-name"><?php echo htmlspecialchars($similar['nome_empresa']); ?></div>
                            </div>
                        </div>
                        <div class="similar-job-meta">
                            <span>
                                <i data-lucide="map-pin"></i>
                                <?php echo htmlspecialchars($similar['localizacao']); ?>
                            </span>
                            <span>
                                <i data-lucide="banknote"></i>
                                <?php echo formatarSalario($similar['salario_estimado']); ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>

                <?php if ($total_similares > 5): ?>
                    <a href="vagas.php?area=<?php echo urlencode($vaga['area']); ?>" class="view-more-link">
                        Ver mais <?php echo $total_similares - 5; ?> vagas em <?php echo htmlspecialchars($vaga['area']); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Related Tags -->
            <?php if (count($tags_relacionadas) > 0): ?>
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    <i data-lucide="tag"></i>
                    Áreas Relacionadas
                </h3>
                <div class="tags-grid">
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

    <!-- ==========================================
         📋 MODAL DE CANDIDATURA
    ========================================== -->
    <?php if (!isset($_SESSION['user_id'])): ?>
        <script>
            function openApplicationModal() {
                window.location.href = 'auth/login.php?redirect=vaga_detalhe.php?id=<?php echo $vaga_id; ?>';
            }
        </script>
    <?php elseif ($_SESSION['user_type'] !== 'candidato'): ?>
        <script>
            function openApplicationModal() {
                alert('Apenas candidatos podem se candidatar a vagas.');
            }
        </script>
    <?php else: ?>
        <div class="modal-overlay" id="applicationModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Candidatar-me a esta Vaga</h3>
                    <button class="modal-close" onclick="closeApplicationModal()">
                        <i data-lucide="x"></i>
                    </button>
                </div>

                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label" for="carta_apresentacao">
                                Carta de Apresentação (Opcional)
                            </label>
                            <textarea 
                                name="carta_apresentacao" 
                                id="carta_apresentacao" 
                                class="form-textarea"
                                placeholder="Conte um pouco sobre você, suas experiências e por que você é o candidato ideal para esta vaga..."
                            ></textarea>
                            <p class="form-help-text">
                                Uma boa carta de apresentação pode aumentar suas chances de ser contratado.
                            </p>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="closeApplicationModal()">
                            Cancelar
                        </button>
                        <button type="submit" name="candidatar" class="btn-submit">
                            Enviar Candidatura
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openApplicationModal() {
                document.getElementById('applicationModal').classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeApplicationModal() {
                document.getElementById('applicationModal').classList.remove('active');
                document.body.style.overflow = '';
            }

            // Close modal when clicking outside
            document.getElementById('applicationModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeApplicationModal();
                }
            });

            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && document.getElementById('applicationModal').classList.contains('active')) {
                    closeApplicationModal();
                }
            });
        </script>
    <?php endif; ?>

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

        // Copy job link
        function copyJobLink() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                showToast('Link copiado para a área de transferência!', 'success');
            }).catch(() => {
                showToast('Erro ao copiar link. Tente novamente.', 'error');
            });
        }
        
        // Bookmark removido nesta página; funcionalidade mantida nas listagens
        
        // ==========================================
        // 🍞 TOAST NOTIFICATIONS
        // ==========================================
        function showToast(message, type = 'success') {
            // Remove toasts anteriores
            const existingToast = document.querySelector('.toast');
            if (existingToast) {
                existingToast.remove();
            }
            
            // Criar toast
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icon = type === 'success' ? 'check-circle' : 'alert-circle';
            
            toast.innerHTML = `
                <i data-lucide="${icon}"></i>
                <span class="toast-message">${message}</span>
            `;
            
            document.body.appendChild(toast);
            lucide.createIcons();
            
            // Auto-remover após 3 segundos
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }, 300);
            }, 3000);
        }
        
        // Animação de saída do toast
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(150%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Reinitialize Lucide icons after dynamic content
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
        });
    </script>

</body>
</html>
