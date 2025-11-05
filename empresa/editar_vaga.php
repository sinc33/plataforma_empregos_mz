<?php
session_start();
require_once '../config/db.php';

// Verificar autenticação
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'empresa') {
    header("Location: ../auth/login.php");
    exit;
}

$empresa_id = $_SESSION['user_id'];

// ========================================
// VALIDAÇÃO: ID DA VAGA
// ========================================
$vaga_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($vaga_id === 0 || $vaga_id < 1) {
    $_SESSION['error'] = "ID de vaga inválido.";
    header("Location: dashboard.php");
    exit;
}

// ========================================
// SEGURANÇA: VERIFICAR PROPRIEDADE DA VAGA
// ========================================
try {
    $stmt_vaga = $pdo->prepare("SELECT * FROM vaga WHERE id = ? AND empresa_id = ?");
    $stmt_vaga->execute([$vaga_id, $empresa_id]);
    $vaga = $stmt_vaga->fetch();

    if (!$vaga) {
        $_SESSION['error'] = "Vaga não encontrada ou você não tem permissão para editá-la.";
        error_log("Tentativa de acesso negado: Empresa $empresa_id tentou editar vaga $vaga_id");
        header("Location: dashboard.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erro ao carregar vaga.";
    error_log("Erro ao buscar vaga para edição: " . $e->getMessage());
    header("Location: dashboard.php");
    exit;
}

$sucesso = '';
$erro = '';

// Processar formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $area = $_POST['area'] ?? '';
    $localizacao = $_POST['localizacao'] ?? '';
    $tipo_contrato = $_POST['tipo_contrato'] ?? '';
    $modalidade = $_POST['modalidade'] ?? '';
    $nivel_experiencia = $_POST['nivel_experiencia'] ?? '';
    $salario_estimado = $_POST['salario_estimado'] ?? null;
    $data_expiracao = $_POST['data_expiracao'] ?? '';
    $ativa = isset($_POST['ativa']) ? 1 : 0;

    // Validações
    $erros_validacao = [];

    if (empty($titulo) || strlen($titulo) < 5) {
        $erros_validacao[] = "O título deve ter pelo menos 5 caracteres.";
    }

    if (empty($descricao) || strlen($descricao) < 50) {
        $erros_validacao[] = "A descrição deve ter pelo menos 50 caracteres.";
    }

    if (empty($area)) {
        $erros_validacao[] = "Selecione uma área de atuação.";
    }

    if (empty($localizacao)) {
        $erros_validacao[] = "Selecione uma localização.";
    }

    if (empty($tipo_contrato)) {
        $erros_validacao[] = "Selecione o tipo de contrato.";
    }

    if (empty($modalidade)) {
        $erros_validacao[] = "Selecione a modalidade de trabalho.";
    }

    if (empty($nivel_experiencia)) {
        $erros_validacao[] = "Selecione o nível de experiência.";
    }

    if (!empty($salario_estimado) && (!is_numeric($salario_estimado) || $salario_estimado < 0)) {
        $erros_validacao[] = "O salário estimado deve ser um valor numérico positivo.";
    }

    if (empty($data_expiracao)) {
        $erros_validacao[] = "A data de expiração é obrigatória.";
    } else {
        $data_expiracao_obj = DateTime::createFromFormat('Y-m-d', $data_expiracao);
        $hoje = new DateTime();
        $hoje->setTime(0, 0, 0);
        
        if (!$data_expiracao_obj) {
            $erros_validacao[] = "A data de expiração é inválida.";
        } elseif ($data_expiracao_obj < $hoje) {
            $erros_validacao[] = "A data de expiração deve ser hoje ou uma data futura.";
        }
    }
    
    if (!empty($titulo) && strlen($titulo) > 200) {
        $erros_validacao[] = "O título não pode ter mais de 200 caracteres.";
    }
    
    if (!empty($descricao) && strlen($descricao) > 5000) {
        $erros_validacao[] = "A descrição não pode ter mais de 5000 caracteres.";
    }

    // Se não há erros, atualizar
    if (empty($erros_validacao)) {
        try {
            $pdo->beginTransaction();

            $sql = "UPDATE vaga SET
                titulo = ?, descricao = ?, area = ?, 
                localizacao = ?, tipo_contrato = ?, 
                modalidade = ?, nivel_experiencia = ?,
                salario_estimado = ?, data_expiracao = ?, ativa = ?
            WHERE id = ? AND empresa_id = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $titulo, $descricao, $area, $localizacao,
                $tipo_contrato, $modalidade, $nivel_experiencia,
                $salario_estimado ? (float)$salario_estimado : null,
                $data_expiracao, $ativa, $vaga_id, $empresa_id
            ]);

            $pdo->commit();
            $sucesso = "Vaga atualizada com sucesso!";

            // Atualizar dados locais
            $vaga['titulo'] = $titulo;
            $vaga['descricao'] = $descricao;
            $vaga['area'] = $area;
            $vaga['localizacao'] = $localizacao;
            $vaga['tipo_contrato'] = $tipo_contrato;
            $vaga['modalidade'] = $modalidade;
            $vaga['nivel_experiencia'] = $nivel_experiencia;
            $vaga['salario_estimado'] = $salario_estimado;
            $vaga['data_expiracao'] = $data_expiracao;
            $vaga['ativa'] = $ativa;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = "Erro ao atualizar vaga. Por favor, tente novamente.";
            error_log("Erro ao editar vaga (ID: $vaga_id, Empresa: $empresa_id): " . $e->getMessage());
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = "Erro inesperado. Por favor, tente novamente.";
            error_log("Erro inesperado ao editar vaga: " . $e->getMessage());
        }
    } else {
        $erro = implode("<br>", $erros_validacao);
    }
}

// Buscar estatísticas da vaga
$sql_estatisticas = "
    SELECT 
        COUNT(c.id) as total_candidaturas,
        SUM(CASE WHEN c.estado = 'submetida' THEN 1 ELSE 0 END) as candidaturas_novas,
        SUM(CASE WHEN c.estado = 'entrevista' THEN 1 ELSE 0 END) as entrevistas,
        SUM(CASE WHEN c.estado = 'contratado' THEN 1 ELSE 0 END) as contratados
    FROM candidatura c
    WHERE c.vaga_id = ?
";
$stmt_estatisticas = $pdo->prepare($sql_estatisticas);
$stmt_estatisticas->execute([$vaga_id]);
$estatisticas = $stmt_estatisticas->fetch();

// Dados para os selects
$areas = [
    'Tecnologia da Informação', 'Comercial e Vendas', 'Administrativa',
    'Financeira', 'Operações e Logística', 'Recursos Humanos',
    'Marketing e Comunicação', 'Engenharia', 'Jurídica', 'Saúde',
    'Educação', 'Construção Civil', 'Agricultura', 'Turismo e Hotelaria',
    'Segurança', 'Transportes', 'Artes e Design', 'Outros'
];

$provincias_mz = [
    'Maputo', 'Gaza', 'Inhambane', 'Sofala', 
    'Manica', 'Tete', 'Zambézia', 'Nampula', 'Cabo Delgado', 'Niassa', 'Remoto'
];

$tipos_contrato = [
    'tempo_inteiro' => 'Tempo Inteiro',
    'tempo_parcial' => 'Tempo Parcial',
    'estagio' => 'Estágio',
    'freelance' => 'Freelance'
];

$modalidades = [
    'presencial' => 'Presencial',
    'hibrido' => 'Híbrido',
    'remoto' => 'Remoto'
];

$niveis_experiencia = [
    'Estagiário', 'Júnior', 'Pleno', 'Sénior', 'Gestor', 'Diretor'
];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Vaga - <?= htmlspecialchars($vaga['titulo']) ?> | Emprego MZ</title>
    
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
            /* === 🎨 PALETA DEFINITIVA PROFISSIONAL PARA EDIÇÃO DE VAGAS === */
            
            /* 🔵 CORES PRINCIPAIS */
            --cor-primaria: #14213d;              /* Oxford Blue - Headers, títulos principais */
            --cor-primaria-escura: #0f1a2e;       /* Oxford Blue Dark - Hover intenso */
            --cor-primaria-clara: #1e2c47;        /* Oxford Blue Light - Elementos secundários */
            --cor-primaria-alpha: rgba(20, 33, 61, 0.1);
            
            /* 🟠 CORES SECUNDÁRIAS */
            --cor-secundaria: #fca311;            /* Orange Web - Botões principais, CTAs */
            --cor-secundaria-hover: #e3940f;      /* Orange Hover */
            --cor-secundaria-clara: #fdb541;      /* Orange Light */
            --cor-secundaria-muito-clara: #fec871; /* Orange Very Light */
            --cor-secundaria-alpha: rgba(252, 163, 17, 0.1);
            
            /* 🌫️ HIERARQUIA VISUAL - Soft Gray System */
            --cor-fundo: #f8fafc;                 /* Soft Gray - Fundo geral da página */
            --cor-cards: #ffffff;                 /* White - Formulário, cards, estatísticas */
            --cor-acento: #f1f5ff;                /* Light Blue - Hover states, seções ativas */
            --cor-acento-escuro: #e1ebff;         /* Light Blue Dark - Bordas ativas */
            
            /* 📝 TEXTO - Hierarquia Refinada */
            --cor-texto: #1a202c;                 /* Títulos, labels importantes */
            --cor-texto-claro: #64748b;           /* Texto secundário, descrições */
            --cor-texto-muito-claro: #94a3b8;     /* Placeholders, metadados */
            
            /* 👥 EMPRESA (IDENTIDADE ESPECÍFICA) */
            --cor-empresa: #8B5CF6;               /* Roxo para contexto empresarial */
            --cor-empresa-light: #EDE9FE;         /* Fundo suave empresa */
            --cor-empresa-dark: #7c3aed;          /* Hover empresa */
            
            /* 🎯 STATUS DA VAGA */
            --cor-vaga-ativa: #10B981;            /* Verde para vaga ativa */
            --cor-vaga-ativa-light: #D1FAE5;      /* Fundo suave ativa */
            --cor-vaga-inativa: #EF4444;          /* Vermelho para vaga inativa */
            --cor-vaga-inativa-light: #FEE2E2;    /* Fundo suave inativa */
            
            /* 📊 ESTATÍSTICAS */
            --cor-candidaturas: #06B6D4;          /* Azul para candidaturas */
            --cor-candidaturas-light: #CFFAFE;    /* Fundo candidaturas */
            --cor-entrevistas: #8B5CF6;           /* Roxo para entrevistas */
            --cor-entrevistas-light: #EDE9FE;     /* Fundo entrevistas */
            --cor-contratados: #10B981;           /* Verde para contratados */
            --cor-contratados-light: #D1FAE5;     /* Fundo contratados */
            --cor-novas: #F59E0B;                 /* Laranja para novas candidaturas */
            --cor-novas-light: #FEF3C7;           /* Fundo novas candidaturas */
            
            /* 📝 FORMULÁRIO E VALIDAÇÃO */
            --cor-campo-valido: #10B981;          /* Campos válidos */
            --cor-campo-valido-light: #D1FAE5;    /* Fundo campos válidos */
            --cor-campo-erro: #EF4444;            /* Campos com erro */
            --cor-campo-erro-light: #FEE2E2;      /* Fundo campos com erro */
            --cor-campo-obrigatorio: #F59E0B;     /* Indicador obrigatório */
            
            /* 🎨 ESTADOS E FEEDBACK */
            --cor-sucesso: #10B981;               /* Mensagens de sucesso */
            --cor-erro: #EF4444;                  /* Mensagens de erro */
            --cor-aviso: #F59E0B;                 /* Avisos */
            --cor-info: var(--cor-primaria);      /* Informações gerais */
            
            /* 💰 ELEMENTOS ESPECÍFICOS */
            --cor-salario: #059669;               /* Campo de salário */
            --cor-data-expiracao: #F59E0B;        /* Campo de data */
            
            /* 🎨 BORDAS E SOMBRAS */
            --cor-borda: #e2e8f0;                 /* Bordas padrão */
            --cor-borda-ativa: var(--cor-primaria);
            --sombra-formulario: 0 8px 40px rgba(20, 33, 61, 0.12);
            --sombra-card: 0 4px 20px rgba(20, 33, 61, 0.08);
            --sombra-estatisticas: 0 6px 30px rgba(20, 33, 61, 0.10);
            --sombra-input-focus: 0 0 0 4px rgba(20, 33, 61, 0.1);
            --sombra-botao: 0 4px 16px rgba(252, 163, 17, 0.3);
            
            /* 🔄 Aliases para compatibilidade */
            --primary: var(--cor-primaria);
            --primary-dark: var(--cor-primaria-escura);
            --secondary: var(--cor-secundaria);
            --secondary-dark: var(--cor-secundaria-hover);
            --success: var(--cor-sucesso);
            --error: var(--cor-erro);
            --warning: var(--cor-aviso);
            --text: var(--cor-texto);
            --text-light: var(--cor-texto-claro);
            --border: var(--cor-borda);
            --white: var(--cor-cards);
            
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
            --radius: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-2xl: 20px;
            --radius-full: 9999px;
            
            /* Transitions */
            --transition: all 0.2s ease;
            --transition-slow: all 0.3s ease;
        }

        body {
            font-family: var(--font-family);
            color: var(--cor-texto);
            background: var(--cor-fundo);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ==========================================
           📱 HEADER PROFISSIONAL - Oxford Blue (IGUAL INDEX.PHP)
        ========================================== */
        .header {
            background: var(--cor-primaria);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(20, 33, 61, 0.12);
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
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .nav-link:hover {
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

        .nav-link:hover::after {
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
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
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
            color: var(--cor-texto-claro);
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background: var(--cor-acento);
            color: var(--cor-primaria);
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
            box-shadow: 0 4px 6px rgba(20, 33, 61, 0.15);
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
            color: var(--text-light);
        }

        /* ==========================================
           📋 MAIN LAYOUT
        ========================================== */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--space-10) var(--space-6);
        }

        /* ==========================================
           🎯 HERO/CABEÇALHO DA PÁGINA
        ========================================== */
        .edit-hero {
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
            border-radius: 24px;
            box-shadow: var(--sombra-formulario);
            padding: var(--space-10);
            margin-bottom: var(--space-8);
            position: relative;
            overflow: hidden;
        }

        .edit-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--cor-empresa), var(--cor-empresa-dark));
        }

        .hero-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-8);
        }

        .hero-text {
            flex: 1;
        }

        .page-title {
            color: var(--cor-primaria);
            font-size: 32px;
            font-weight: 800;
            margin-bottom: var(--space-2);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .title-icon {
            color: var(--cor-secundaria);
            font-size: 36px;
        }

        .page-subtitle {
            color: var(--cor-texto-claro);
            font-size: 18px;
            line-height: 1.5;
        }

        .job-status {
            text-align: center;
        }

        .status-badge {
            padding: var(--space-3) var(--space-5);
            border-radius: var(--radius-full);
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            margin-bottom: var(--space-2);
            display: inline-block;
        }

        .status-badge.ativa {
            background: var(--cor-vaga-ativa-light);
            color: var(--cor-vaga-ativa);
            border: 2px solid var(--cor-vaga-ativa);
        }

        .status-badge.inativa {
            background: var(--cor-vaga-inativa-light);
            color: var(--cor-vaga-inativa);
            border: 2px solid var(--cor-vaga-inativa);
        }

        .status-description {
            color: var(--cor-texto-claro);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* ==========================================
           📊 ESTATÍSTICAS DA VAGA
        ========================================== */
        .job-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: var(--space-6);
            margin-bottom: var(--space-8);
        }

        .stat-card {
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius-2xl);
            padding: var(--space-8);
            box-shadow: var(--sombra-estatisticas);
            text-align: center;
            transition: var(--transition-slow);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 48px rgba(20, 33, 61, 0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.candidaturas::before {
            background: linear-gradient(90deg, var(--cor-candidaturas), #0891b2);
        }

        .stat-card.novas::before {
            background: linear-gradient(90deg, var(--cor-novas), #d97706);
        }

        .stat-card.entrevistas::before {
            background: linear-gradient(90deg, var(--cor-entrevistas), #7c3aed);
        }

        .stat-card.contratados::before {
            background: linear-gradient(90deg, var(--cor-contratados), #059669);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 auto var(--space-4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        .stat-card.candidaturas .stat-icon {
            background: linear-gradient(135deg, var(--cor-candidaturas), #0891b2);
        }

        .stat-card.novas .stat-icon {
            background: linear-gradient(135deg, var(--cor-novas), #d97706);
        }

        .stat-card.entrevistas .stat-icon {
            background: linear-gradient(135deg, var(--cor-entrevistas), #7c3aed);
        }

        .stat-card.contratados .stat-icon {
            background: linear-gradient(135deg, var(--cor-contratados), #059669);
        }

        .stat-number {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: var(--space-2);
            display: block;
        }

        .stat-card.candidaturas .stat-number {
            color: var(--cor-candidaturas);
        }

        .stat-card.novas .stat-number {
            color: var(--cor-novas);
        }

        .stat-card.entrevistas .stat-number {
            color: var(--cor-entrevistas);
        }

        .stat-card.contratados .stat-number {
            color: var(--cor-contratados);
        }

        .stat-label {
            color: var(--cor-texto-claro);
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ==========================================
           📝 FORMULÁRIO PRINCIPAL
        ========================================== */
        .edit-form {
            background: var(--cor-cards);
            border: 1px solid var(--cor-borda);
            border-radius: 24px;
            box-shadow: var(--sombra-formulario);
            overflow: hidden;
            margin-bottom: var(--space-8);
        }

        .form-header {
            background: var(--cor-acento);
            border-bottom: 1px solid var(--cor-acento-escuro);
            padding: var(--space-6) var(--space-10);
        }

        .form-title {
            color: var(--cor-primaria);
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .form-icon {
            color: var(--cor-secundaria);
            font-size: 28px;
        }

        .form-body {
            padding: var(--space-10);
        }

        .form-section {
            margin-bottom: var(--space-10);
            padding-bottom: var(--space-8);
            border-bottom: 1px solid var(--cor-borda);
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-title {
            color: var(--cor-primaria);
            font-size: 20px;
            font-weight: 700;
            margin-bottom: var(--space-5);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding-bottom: var(--space-3);
            border-bottom: 2px solid var(--cor-acento-escuro);
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: var(--cor-secundaria);
        }

        .section-icon {
            color: var(--cor-secundaria);
            font-size: 22px;
        }

        .section-description {
            color: var(--cor-texto-claro);
            font-size: 14px;
            margin-bottom: var(--space-6);
            line-height: 1.6;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-6);
            margin-bottom: var(--space-6);
        }

        .form-row.single {
            grid-template-columns: 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }

        .form-label {
            color: var(--cor-texto);
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .required-indicator {
            color: var(--cor-campo-obrigatorio);
            font-size: 16px;
            font-weight: 700;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: var(--space-4) var(--space-5);
            background: var(--cor-cards);
            border: 2px solid var(--cor-borda);
            border-radius: var(--radius-lg);
            font-size: 16px;
            font-family: inherit;
            color: var(--cor-texto);
            transition: var(--transition-slow);
            resize: vertical;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            background: var(--cor-acento);
            border-color: var(--cor-borda-ativa);
            box-shadow: var(--sombra-input-focus);
            outline: none;
            transform: scale(1.01);
        }

        .form-input::placeholder,
        .form-textarea::placeholder {
            color: var(--cor-texto-muito-claro);
            font-style: italic;
        }

        .form-textarea {
            min-height: 160px;
            line-height: 1.7;
        }

        .form-help {
            font-size: 13px;
            color: var(--cor-texto-claro);
            margin-top: var(--space-1);
        }

        .character-count {
            font-size: 12px;
            color: var(--cor-texto-muito-claro);
            text-align: right;
            margin-top: var(--space-1);
        }

        /* ==========================================
           💰 CAMPO DE SALÁRIO ESPECIAL
        ========================================== */
        .salary-input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .currency-prefix {
            position: absolute;
            left: var(--space-5);
            color: var(--cor-salario);
            font-weight: 700;
            font-size: 16px;
            z-index: 2;
        }

        .salary-input {
            padding-left: 90px !important;
            border-color: var(--cor-salario) !important;
        }

        .salary-input:focus {
            border-color: var(--cor-salario) !important;
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.1) !important;
        }

        /* ==========================================
           📅 CAMPO DE DATA ESPECIAL
        ========================================== */
        .date-input-group {
            position: relative;
        }

        .date-icon {
            position: absolute;
            right: var(--space-5);
            top: 50%;
            transform: translateY(-50%);
            color: var(--cor-data-expiracao);
            font-size: 18px;
            pointer-events: none;
        }

        .date-input {
            border-color: var(--cor-data-expiracao) !important;
        }

        .date-input:focus {
            border-color: var(--cor-data-expiracao) !important;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1) !important;
        }

        /* ==========================================
           🔘 TOGGLE PARA STATUS ATIVO/INATIVO
        ========================================== */
        .status-toggle {
            background: var(--cor-acento);
            border: 1px solid var(--cor-acento-escuro);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            margin-bottom: var(--space-6);
        }

        .toggle-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-3);
        }

        .toggle-title {
            color: var(--cor-primaria);
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .toggle-icon {
            font-size: 18px;
        }

        .switch-container {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 32px;
        }

        .switch-input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .switch-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--cor-vaga-inativa);
            transition: .3s;
            border-radius: 16px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .switch-slider:before {
            position: absolute;
            content: "";
            height: 24px;
            width: 24px;
            left: 4px;
            bottom: 4px;
            background: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .switch-input:checked + .switch-slider {
            background: var(--cor-vaga-ativa);
        }

        .switch-input:checked + .switch-slider:before {
            transform: translateX(28px);
        }

        .toggle-description {
            color: var(--cor-texto-claro);
            font-size: 14px;
            line-height: 1.5;
        }

        /* ==========================================
           🔔 MENSAGENS DE FEEDBACK
        ========================================== */
        .global-alert {
            padding: var(--space-4) var(--space-5);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-8);
            font-weight: 500;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .global-alert.success {
            background: var(--cor-campo-valido-light);
            color: #065f46;
            border-left-color: var(--cor-sucesso);
        }

        .global-alert.error {
            background: var(--cor-campo-erro-light);
            color: #991b1b;
            border-left-color: var(--cor-erro);
        }

        .alert-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        /* ==========================================
           🔘 BOTÕES DE AÇÃO
        ========================================== */
        .form-actions {
            background: var(--cor-acento);
            border: 1px solid var(--cor-acento-escuro);
            border-radius: var(--radius-2xl);
            padding: var(--space-8);
            margin-top: var(--space-10);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-5);
        }

        .actions-primary {
            display: flex;
            gap: var(--space-4);
        }

        .actions-secondary {
            display: flex;
            gap: var(--space-3);
        }

        .btn-update {
            background: var(--cor-secundaria);
            color: white;
            border: none;
            border-radius: var(--radius-lg);
            padding: var(--space-4) var(--space-8);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition-slow);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--sombra-botao);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .btn-update:hover {
            background: var(--cor-secundaria-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(252, 163, 17, 0.4);
        }

        .btn-update:disabled {
            background: #94a3b8;
            color: white;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-preview {
            background: var(--cor-primaria);
            color: white;
            border: none;
            border-radius: var(--radius-lg);
            padding: var(--space-4) var(--space-6);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            text-decoration: none;
        }

        .btn-preview:hover {
            background: var(--cor-primaria-escura);
            transform: translateY(-1px);
        }

        .btn-cancel {
            background: transparent;
            color: var(--cor-texto-claro);
            border: 1px solid var(--cor-borda);
            border-radius: var(--radius);
            padding: var(--space-3) var(--space-5);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .btn-cancel:hover {
            background: var(--cor-acento);
            color: var(--cor-texto);
            border-color: var(--cor-primaria);
        }

        /* ==========================================
           🦶 FOOTER (IGUAL INDEX.PHP)
        ========================================== */
        .footer {
            background: var(--cor-primaria);
            color: rgba(255, 255, 255, 0.8);
            padding: var(--space-16) var(--space-6) var(--space-8);
            margin-top: var(--space-20);
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
            color: var(--cor-secundaria);
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
            background: var(--cor-secundaria);
            color: var(--white);
            transform: translateY(-2px);
        }

        .social-link svg {
            width: 18px;
            height: 18px;
        }

        /* ==========================================
           📱 RESPONSIVIDADE
        ========================================== */
        @media (max-width: 1024px) {
            .hero-content {
                flex-direction: column;
                text-align: center;
                gap: var(--space-6);
            }

            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-6);
            }
        }

        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .header-container {
                padding: var(--space-3) var(--space-4);
            }

            .logo-img {
                height: 32px;
            }

            .main-container {
                padding: var(--space-6) var(--space-4);
            }

            .edit-hero,
            .edit-form {
                padding: var(--space-6);
                border-radius: var(--radius-xl);
            }

            .form-body {
                padding: var(--space-6);
            }

            .page-title {
                font-size: 28px;
            }

            .job-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-4);
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: var(--space-5);
            }

            .form-actions {
                flex-direction: column;
                gap: var(--space-4);
                text-align: center;
            }

            .actions-primary,
            .actions-secondary {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }

            .btn-update {
                width: 100%;
                justify-content: center;
            }

            .footer-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .job-stats {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: var(--space-6);
            }

            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }

            .stat-number {
                font-size: 28px;
            }

            .form-input,
            .form-select,
            .form-textarea {
                padding: var(--space-3) var(--space-4);
                font-size: 16px;
            }

            .btn-update {
                padding: var(--space-3) var(--space-6);
                font-size: 14px;
            }

            .section-title {
                font-size: 18px;
            }
        }

        /* ==========================================
           ✨ ANIMAÇÕES
        ========================================== */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .edit-hero,
        .stat-card,
        .edit-form {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>

    <!-- ==========================================
         📱 HEADER - CONSISTENT WITH INDEX.PHP
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
            <a href="dashboard.php" class="breadcrumb-link">Dashboard</a>
            <i data-lucide="chevron-right" class="breadcrumb-separator"></i>
            <span>Editar Vaga</span>
        </div>
    </div>

    <!-- ==========================================
         📋 MAIN CONTENT
    ========================================== -->
    <div class="main-container">

        <!-- Hero/Cabeçalho -->
        <div class="edit-hero">
            <div class="hero-content">
                <div class="hero-text">
                    <h1 class="page-title">
                        <i data-lucide="edit-3" class="title-icon"></i>
                        Editar Vaga
                    </h1>
                    <p class="page-subtitle">Atualize as informações para atrair os melhores talentos para sua empresa</p>
                </div>
                <div class="job-status">
                    <span class="status-badge <?= $vaga['ativa'] ? 'ativa' : 'inativa' ?>">
                        <?= $vaga['ativa'] ? 'ATIVA' : 'INATIVA' ?>
                    </span>
                    <div class="status-description">Status Atual</div>
                </div>
            </div>
        </div>

        <!-- Alertas -->
        <?php if ($sucesso): ?>
            <div class="global-alert success">
                <i data-lucide="check-circle" class="alert-icon"></i>
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="global-alert error">
                <i data-lucide="alert-circle" class="alert-icon"></i>
                <?= $erro ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas da Vaga -->
        <div class="job-stats">
            <div class="stat-card candidaturas">
                <i data-lucide="users" class="stat-icon"></i>
                <span class="stat-number"><?= $estatisticas['total_candidaturas'] ?? 0 ?></span>
                <span class="stat-label">Total Candidaturas</span>
            </div>

            <div class="stat-card novas">
                <i data-lucide="inbox" class="stat-icon"></i>
                <span class="stat-number"><?= $estatisticas['candidaturas_novas'] ?? 0 ?></span>
                <span class="stat-label">Novas</span>
            </div>

            <div class="stat-card entrevistas">
                <i data-lucide="video" class="stat-icon"></i>
                <span class="stat-number"><?= $estatisticas['entrevistas'] ?? 0 ?></span>
                <span class="stat-label">Entrevistas</span>
            </div>

            <div class="stat-card contratados">
                <i data-lucide="user-check" class="stat-icon"></i>
                <span class="stat-number"><?= $estatisticas['contratados'] ?? 0 ?></span>
                <span class="stat-label">Contratados</span>
            </div>
        </div>

        <!-- Formulário -->
        <form method="POST" class="edit-form">
            <div class="form-header">
                <h2 class="form-title">
                    <i data-lucide="file-text" class="form-icon"></i>
                    Informações da Vaga
                </h2>
            </div>

            <div class="form-body">
                <!-- Informações Básicas -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i data-lucide="info" class="section-icon"></i>
                        Informações Básicas
                    </h3>
                    <p class="section-description">Defina os dados fundamentais da vaga para atrair candidatos qualificados.</p>
                    
                    <div class="form-row single">
                        <div class="form-group">
                            <label class="form-label" for="titulo">
                                Título da Vaga
                                <span class="required-indicator">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="titulo" 
                                name="titulo" 
                                class="form-input" 
                                value="<?= htmlspecialchars($vaga['titulo']) ?>"
                                placeholder="Ex: Desenvolvedor Full Stack"
                                maxlength="200"
                                required
                            >
                            <p class="form-help">Mínimo 5 caracteres, máximo 200</p>
                            <div class="character-count">
                                <span id="titulo-count"><?= strlen($vaga['titulo']) ?></span>/200 caracteres
                            </div>
                        </div>
                    </div>

                    <div class="form-row single">
                        <div class="form-group">
                            <label class="form-label" for="descricao">
                                Descrição da Vaga
                                <span class="required-indicator">*</span>
                            </label>
                            <textarea 
                                id="descricao" 
                                name="descricao" 
                                class="form-textarea" 
                                placeholder="Descreva as responsabilidades, requisitos e benefícios da vaga..."
                                maxlength="5000"
                                required
                            ><?= htmlspecialchars($vaga['descricao']) ?></textarea>
                            <p class="form-help">Mínimo 50 caracteres, máximo 5000. Seja claro e detalhado.</p>
                            <div class="character-count">
                                <span id="descricao-count"><?= strlen($vaga['descricao']) ?></span>/5000 caracteres
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="area">
                                Área de Atuação
                                <span class="required-indicator">*</span>
                            </label>
                            <select id="area" name="area" class="form-select" required>
                                <option value="">Selecione uma área...</option>
                                <?php foreach ($areas as $a): ?>
                                    <option value="<?= htmlspecialchars($a) ?>" <?= $vaga['area'] === $a ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($a) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="localizacao">
                                Localização
                                <span class="required-indicator">*</span>
                            </label>
                            <select id="localizacao" name="localizacao" class="form-select" required>
                                <option value="">Selecione a província...</option>
                                <?php foreach ($provincias_mz as $provincia): ?>
                                    <option value="<?= htmlspecialchars($provincia) ?>" <?= $vaga['localizacao'] === $provincia ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($provincia) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Detalhes da Posição -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i data-lucide="briefcase" class="section-icon"></i>
                        Detalhes da Posição
                    </h3>
                    <p class="section-description">Especifique os detalhes contratuais e o perfil profissional desejado.</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="tipo_contrato">
                                Tipo de Contrato
                                <span class="required-indicator">*</span>
                            </label>
                            <select id="tipo_contrato" name="tipo_contrato" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($tipos_contrato as $key => $value): ?>
                                    <option value="<?= $key ?>" <?= $vaga['tipo_contrato'] === $key ? 'selected' : '' ?>>
                                        <?= $value ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="modalidade">
                                Modalidade
                                <span class="required-indicator">*</span>
                            </label>
                            <select id="modalidade" name="modalidade" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($modalidades as $key => $value): ?>
                                    <option value="<?= $key ?>" <?= $vaga['modalidade'] === $key ? 'selected' : '' ?>>
                                        <?= $value ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="nivel_experiencia">
                                Nível de Experiência
                                <span class="required-indicator">*</span>
                            </label>
                            <select id="nivel_experiencia" name="nivel_experiencia" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($niveis_experiencia as $nivel): ?>
                                    <option value="<?= htmlspecialchars($nivel) ?>" <?= $vaga['nivel_experiencia'] === $nivel ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($nivel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="salario_estimado">
                                Salário Estimado (opcional)
                            </label>
                            <div class="salary-input-group">
                                <span class="currency-prefix">MZN</span>
                                <input 
                                    type="number" 
                                    id="salario_estimado" 
                                    name="salario_estimado" 
                                    class="form-input salary-input" 
                                    value="<?= htmlspecialchars($vaga['salario_estimado'] ?? '') ?>"
                                    placeholder="0.00"
                                    min="0"
                                    step="100"
                                >
                            </div>
                            <p class="form-help">Vagas com salário informado atraem 3x mais candidatos</p>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="data_expiracao">
                                Data de Expiração
                                <span class="required-indicator">*</span>
                            </label>
                            <div class="date-input-group">
                                <input 
                                    type="date" 
                                    id="data_expiracao" 
                                    name="data_expiracao" 
                                    class="form-input date-input" 
                                    value="<?= htmlspecialchars($vaga['data_expiracao']) ?>"
                                    min="<?php echo date('Y-m-d'); ?>"
                                    required
                                >
                                <i data-lucide="calendar" class="date-icon"></i>
                            </div>
                            <p class="form-help">Data até quando a vaga estará disponível para candidaturas</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status da Vaga</label>
                            <div class="status-toggle">
                                <div class="toggle-header">
                                    <span class="toggle-title">
                                        <i data-lucide="zap" class="toggle-icon"></i>
                                        Vaga Ativa
                                    </span>
                                    <label class="switch-container">
                                        <input 
                                            type="checkbox" 
                                            class="switch-input" 
                                            id="ativa" 
                                            name="ativa" 
                                            value="1" 
                                            <?= $vaga['ativa'] ? 'checked' : '' ?>
                                        >
                                        <span class="switch-slider"></span>
                                    </label>
                                </div>
                                <p class="toggle-description">
                                    Mantenha ativa para que candidatos possam visualizar e se candidatar
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <div class="actions-primary">
                        <button type="submit" class="btn-update">
                            <i data-lucide="save"></i>
                            Salvar Alterações
                        </button>
                    </div>
                    <div class="actions-secondary">
                        <a href="candidaturas.php?vaga_id=<?= $vaga_id ?>" class="btn-preview">
                            <i data-lucide="eye"></i>
                            Ver Candidaturas
                        </a>
                        <a href="dashboard.php" class="btn-cancel">
                            <i data-lucide="x"></i>
                            Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </form>

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
         ✨ SCRIPTS
    ========================================== -->
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Character counter para título
        const tituloInput = document.getElementById('titulo');
        const tituloCount = document.getElementById('titulo-count');
        
        if (tituloInput && tituloCount) {
            tituloInput.addEventListener('input', () => {
                tituloCount.textContent = tituloInput.value.length;
                if (tituloInput.value.length > 180) {
                    tituloCount.parentElement.classList.add('warning');
                } else {
                    tituloCount.parentElement.classList.remove('warning');
                }
            });
        }

        // Character counter para descrição
        const descricaoInput = document.getElementById('descricao');
        const descricaoCount = document.getElementById('descricao-count');
        
        if (descricaoInput && descricaoCount) {
            descricaoInput.addEventListener('input', () => {
                descricaoCount.textContent = descricaoInput.value.length;
                if (descricaoInput.value.length > 4500) {
                    descricaoCount.parentElement.classList.add('warning');
                } else {
                    descricaoCount.parentElement.classList.remove('warning');
                }
            });
        }

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
    </script>
    
    <!-- 🔒 Proteção de Sessão - Encerra ao fechar aba -->
    <script src="../assets/js/session-guard.js"></script>

</body>
</html>
