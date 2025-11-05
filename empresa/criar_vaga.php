<?php
session_start();
require_once '../config/db.php';

// Verificar autenticação
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'empresa') {
    header("Location: ../auth/login.php");
    exit;
}

$empresa_id = $_SESSION['user_id'];

// Buscar informações da empresa
$stmt_empresa = $pdo->prepare("SELECT nome_empresa, localizacao FROM empresa WHERE id = ?");
$stmt_empresa->execute([$empresa_id]);
$empresa = $stmt_empresa->fetch();

// Processar formulário
$sucesso = '';
$erro = '';

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
        $hoje->setTime(0, 0, 0); // Resetar para meia-noite para comparar apenas datas
        
        if (!$data_expiracao_obj) {
            $erros_validacao[] = "A data de expiração é inválida.";
        } elseif ($data_expiracao_obj < $hoje) {
            $erros_validacao[] = "A data de expiração deve ser hoje ou uma data futura.";
        }
    }
    
    // Validações adicionais de tamanho
    if (!empty($titulo) && strlen($titulo) > 200) {
        $erros_validacao[] = "O título não pode ter mais de 200 caracteres.";
    }
    
    if (!empty($descricao) && strlen($descricao) > 5000) {
        $erros_validacao[] = "A descrição não pode ter mais de 5000 caracteres.";
    }

    // Se não há erros, inserir no banco
    if (empty($erros_validacao)) {
        try {
            $pdo->beginTransaction();

            $sql = "INSERT INTO vaga (
                empresa_id, titulo, descricao, area, localizacao,
                tipo_contrato, modalidade, nivel_experiencia,
                salario_estimado, data_expiracao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $empresa_id,
                $titulo,
                $descricao,
                $area,
                $localizacao,
                $tipo_contrato,
                $modalidade,
                $nivel_experiencia,
                $salario_estimado ? (float)$salario_estimado : null,
                $data_expiracao
            ]);

            $pdo->commit();
            $sucesso = "Vaga publicada com sucesso!";
            $_POST = []; // Limpar formulário
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = "Erro ao publicar vaga: " . $e->getMessage();
        }
    } else {
        $erro = implode("<br>", $erros_validacao);
    }
}

// Dados para os selects
$areas = [
    'TI e Tecnologia', 'Agricultura e Pecuária', 'Construção Civil', 
    'Educação e Formação', 'Saúde e Medicina', 'Comércio e Vendas', 
    'Hotelaria e Turismo', 'Administração e Secretariado', 
    'Finanças e Contabilidade', 'Recursos Humanos', 'Marketing e Publicidade', 
    'Logística e Transportes', 'Mineração e Recursos Naturais', 
    'Pesca e Aquicultura', 'Energia e Água', 'Telecomunicações', 
    'Segurança', 'Social e Comunidade'
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
    <title>Publicar Nova Vaga - <?php echo htmlspecialchars($empresa['nome_empresa'] ?? 'Empresa'); ?> | Emprego MZ</title>
    
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
            /* === 🎨 PALETA DEFINITIVA PROFISSIONAL PARA CRIAÇÃO DE VAGAS === */
            
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
            --cor-cards: #ffffff;                 /* White - Formulário, seções, cards */
            --cor-acento: #f1f5ff;                /* Light Blue - Hover states, campos ativos */
            --cor-acento-escuro: #e1ebff;         /* Light Blue Dark - Bordas ativas, focus states */
            
            /* 📝 TEXTO - Hierarquia Refinada */
            --cor-texto: #1a202c;                 /* Títulos, labels importantes */
            --cor-texto-claro: #64748b;           /* Texto secundário, descrições */
            --cor-texto-muito-claro: #94a3b8;     /* Placeholders, textos auxiliares */
            
            /* 🟣 EMPRESA (ESPECÍFICO) - DIFERENCIAÇÃO VISUAL */
            --cor-empresa: #8B5CF6;               /* Roxo para identidade empresarial */
            --cor-empresa-light: #EDE9FE;         /* Fundo suave empresa */
            --cor-empresa-dark: #7c3aed;          /* Hover empresa */
            
            /* 📝 FORMULÁRIO E VALIDAÇÃO */
            --cor-campo-valido: #10B981;          /* Campos com validação ok */
            --cor-campo-valido-light: #D1FAE5;    /* Fundo suave válido */
            --cor-campo-erro: #EF4444;            /* Campos com erro */
            --cor-campo-erro-light: #FEE2E2;      /* Fundo suave erro */
            --cor-campo-obrigatorio: #F59E0B;     /* Indicador de obrigatório */
            --cor-campo-opcional: #6B7280;        /* Indicador de opcional */
            
            /* 🎯 ESTADOS E STATUS */
            --cor-sucesso: #10B981;               /* Mensagens de sucesso */
            --cor-erro: #EF4444;                  /* Mensagens de erro */
            --cor-aviso: #F59E0B;                 /* Avisos, informações importantes */
            --cor-info: var(--cor-primaria);      /* Dicas, informações gerais */
            
            /* 📊 PROGRESS E STEPS */
            --cor-progresso: var(--cor-secundaria); /* Barra de progresso */
            --cor-progresso-fundo: #E5E7EB;       /* Fundo da barra */
            --cor-step-ativo: var(--cor-secundaria); /* Step atual */
            --cor-step-completo: var(--cor-sucesso); /* Steps completados */
            --cor-step-pendente: #CBD5E1;         /* Steps pendentes */
            
            /* 🎨 BORDAS E SOMBRAS ESPECÍFICAS */
            --cor-borda: #e2e8f0;                 /* Bordas padrão */
            --cor-borda-ativa: var(--cor-primaria); /* Bordas em foco */
            --cor-borda-erro: var(--cor-campo-erro); /* Bordas de erro */
            --sombra-formulario: 0 8px 32px rgba(20, 33, 61, 0.12);
            --sombra-campo-focus: 0 0 0 4px rgba(20, 33, 61, 0.1);
            --sombra-botao: 0 4px 16px rgba(252, 163, 17, 0.3);
            
            /* 🔄 Aliases para compatibilidade */
            --primary: var(--cor-primaria);
            --primary-dark: var(--cor-primaria-escura);
            --primary-light: var(--cor-primaria-clara);
            --secondary: var(--cor-secundaria);
            --secondary-dark: var(--cor-secundaria-hover);
            --success: var(--cor-sucesso);
            --error: var(--cor-erro);
            --warning: var(--cor-aviso);
            --info: var(--cor-info);
            --text: var(--cor-texto);
            --text-light: var(--cor-texto-claro);
            --text-lighter: var(--cor-texto-muito-claro);
            --border: var(--cor-borda);
            --white: var(--cor-cards);
            
            /* 🎨 Grays - Refinados */
            --gray-900: var(--cor-texto);
            --gray-800: #333333;
            --gray-700: var(--cor-texto-claro);
            --gray-600: var(--cor-texto-muito-claro);
            --gray-500: #8a8a8a;
            --gray-400: #b8b8b8;
            --gray-300: var(--cor-borda);
            --gray-200: #ebebeb;
            --gray-100: #f1f5f9;
            --gray-50: var(--cor-fundo);
            
            /* Typography */
            --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --font-family: var(--font-primary);
            
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
            
            /* Shadows - Profissionais com Oxford Blue */
            --shadow-sm: 0 1px 2px 0 rgba(20, 33, 61, 0.05);
            --shadow: 0 1px 3px 0 rgba(20, 33, 61, 0.1), 0 1px 2px 0 rgba(20, 33, 61, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(20, 33, 61, 0.1), 0 2px 4px -1px rgba(20, 33, 61, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(20, 33, 61, 0.1), 0 4px 6px -2px rgba(20, 33, 61, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(20, 33, 61, 0.1), 0 10px 10px -5px rgba(20, 33, 61, 0.04);
            
            /* Transitions */
            --transition: all 0.2s ease;
            --transition-slow: all 0.3s ease;
        }
        
        body {
            font-family: var(--font-primary);
            color: var(--cor-texto);                 /* #1a202c - Texto principal */
            background: var(--cor-fundo);            /* #f8fafc - Soft Gray */
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            margin: 0;
            padding: 0;
        }
        
        /* ==========================================
           📱 HEADER PROFISSIONAL - Oxford Blue (IGUAL INDEX.PHP)
        ========================================== */
        .header {
            background: var(--cor-primaria);         /* #14213d - Oxford Blue */
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
            color: rgba(255, 255, 255, 0.9);         /* Branco sobre Oxford Blue */
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .nav-link:hover {
            color: var(--cor-secundaria);            /* #fca311 - Orange */
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--cor-secundaria);       /* #fca311 - Orange */
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
            top: calc(100% + 8px);
            left: 0;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            min-width: 220px;
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
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition);
        }

        .dropdown-item:first-child {
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .dropdown-item:last-child {
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
        }

        .dropdown-item:hover {
            background: var(--gray-50);
            color: var(--primary);
        }

        .dropdown-item svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
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
            cursor: pointer;
        }

        .btn svg {
            width: 18px;
            height: 18px;
        }

        .btn-outline {
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--text);
        }

        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
            border: 1px solid var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text);
            cursor: pointer;
            padding: var(--space-2);
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
            max-width: 1000px;
            margin: 0 auto;
            padding: var(--space-8) var(--space-6) var(--space-20);
        }

        .page-header {
            margin-bottom: var(--space-8);
        }

        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: var(--space-2);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .page-title svg {
            width: 36px;
            height: 36px;
            color: var(--primary);
        }

        .page-subtitle {
            font-size: 16px;
            color: var(--text-light);
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
           📝 FORM SECTIONS
        ========================================== */
        .form-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .form-section {
            padding: var(--space-6);
            border-bottom: 1px solid var(--border);
        }

        .form-section:last-of-type {
            border-bottom: none;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-bottom: var(--space-6);
        }

        .section-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 136, 204, 0.1);
            border-radius: var(--radius);
            flex-shrink: 0;
        }

        .section-icon svg {
            width: 22px;
            height: 22px;
            color: var(--primary);
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        .form-group {
            margin-bottom: var(--space-5);
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: var(--space-2);
        }

        .form-label-required::after {
            content: " *";
            color: var(--error);
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: var(--space-3) var(--space-4);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 15px;
            font-family: var(--font-primary);
            color: var(--text);
            transition: var(--transition);
            background: var(--white);
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 136, 204, 0.1);
        }

        .form-textarea {
            min-height: 150px;
            resize: vertical;
            line-height: 1.6;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-5);
        }

        .form-help {
            font-size: 13px;
            color: var(--text-light);
            margin-top: var(--space-2);
            display: flex;
            align-items: center;
            gap: var(--space-1);
        }

        .form-help svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }

        .char-counter {
            font-size: 12px;
            color: var(--text-lighter);
            margin-top: var(--space-1);
            text-align: right;
        }

        .char-counter.warning {
            color: var(--warning);
        }

        .char-counter.success {
            color: var(--success);
        }

        /* ==========================================
           🎯 FORM ACTIONS
        ========================================== */
        .form-actions {
            padding: var(--space-6);
            background: var(--gray-50);
            display: flex;
            gap: var(--space-3);
            border-top: 1px solid var(--border);
        }

        .btn-submit {
            flex: 1;
            padding: var(--space-4) var(--space-6);
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: var(--radius);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-submit svg {
            width: 20px;
            height: 20px;
        }

        .btn-cancel {
            padding: var(--space-4) var(--space-6);
            background: var(--white);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .btn-cancel:hover {
            background: var(--gray-100);
        }

        .btn-cancel svg {
            width: 20px;
            height: 20px;
        }

        /* ==========================================
           🦶 FOOTER
        ========================================== */
        .footer {
            background: var(--text);
            color: var(--white);
            padding: var(--space-12) var(--space-6) var(--space-6);
            margin-top: 0;
        }

        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-8);
            margin-bottom: var(--space-8);
        }

        .footer-column h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: var(--space-4);
        }

        .footer-links {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }

        .footer-link {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition);
        }

        .footer-link:hover {
            color: var(--white);
            padding-left: var(--space-2);
        }

        .footer-bottom {
            padding-top: var(--space-6);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
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
                padding: var(--space-6) var(--space-4) var(--space-16);
            }

            .page-title {
                font-size: 24px;
            }

            .form-section {
                padding: var(--space-5);
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn-submit,
            .btn-cancel {
                width: 100%;
                justify-content: center;
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
        }

        @media (max-width: 480px) {
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
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-card {
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
            <span>Publicar Nova Vaga</span>
        </div>
    </div>

    <!-- ==========================================
         📋 MAIN CONTENT
    ========================================== -->
    <div class="main-container">

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i data-lucide="plus-circle"></i>
                Publicar Nova Vaga
            </h1>
            <p class="page-subtitle">Preencha as informações para encontrar o candidato ideal para sua empresa</p>
        </div>

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
                <div><?php echo $erro; ?></div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" class="form-card">
            
            <!-- Section 1: Basic Information -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i data-lucide="file-text"></i>
                    </div>
                    <h2 class="section-title">Informações Básicas</h2>
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required" for="titulo">Título da Vaga</label>
                    <input 
                        type="text" 
                        id="titulo" 
                        name="titulo" 
                        class="form-input" 
                        placeholder="Ex: Desenvolvedor Full Stack Sénior"
                        value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>"
                        required
                        maxlength="200"
                        oninput="updateCharCounter('titulo', 200, 5)"
                    >
                    <div id="titulo-counter" class="char-counter">0 / 200 caracteres (mínimo 5)</div>
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required" for="descricao">Descrição da Vaga</label>
                    <textarea 
                        id="descricao" 
                        name="descricao" 
                        class="form-textarea" 
                        placeholder="Descreva as responsabilidades, requisitos e benefícios da vaga..."
                        required
                        oninput="updateCharCounter('descricao', null, 50)"
                    ><?php echo htmlspecialchars($_POST['descricao'] ?? ''); ?></textarea>
                    <div class="form-help">
                        <i data-lucide="info"></i>
                        Seja claro e detalhado. Inclua responsabilidades, requisitos e benefícios.
                    </div>
                    <div id="descricao-counter" class="char-counter">0 caracteres (mínimo 50)</div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label form-label-required" for="area">Área de Atuação</label>
                        <select id="area" name="area" class="form-select" required>
                            <option value="">Selecione uma área...</option>
                            <?php foreach ($areas as $a): ?>
                                <option value="<?php echo htmlspecialchars($a); ?>" <?php echo (isset($_POST['area']) && $_POST['area'] === $a) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($a); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label form-label-required" for="localizacao">Localização</label>
                        <select id="localizacao" name="localizacao" class="form-select" required>
                            <option value="">Selecione a localização...</option>
                            <?php foreach ($provincias_mz as $provincia): ?>
                                <option value="<?php echo htmlspecialchars($provincia); ?>" <?php echo (isset($_POST['localizacao']) && $_POST['localizacao'] === $provincia) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($provincia); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 2: Position Details -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i data-lucide="briefcase"></i>
                    </div>
                    <h2 class="section-title">Detalhes da Posição</h2>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label form-label-required" for="tipo_contrato">Tipo de Contrato</label>
                        <select id="tipo_contrato" name="tipo_contrato" class="form-select" required>
                            <option value="">Selecione o tipo...</option>
                            <?php foreach ($tipos_contrato as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo (isset($_POST['tipo_contrato']) && $_POST['tipo_contrato'] === $key) ? 'selected' : ''; ?>>
                                    <?php echo $value; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label form-label-required" for="modalidade">Modalidade de Trabalho</label>
                        <select id="modalidade" name="modalidade" class="form-select" required>
                            <option value="">Selecione a modalidade...</option>
                            <?php foreach ($modalidades as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo (isset($_POST['modalidade']) && $_POST['modalidade'] === $key) ? 'selected' : ''; ?>>
                                    <?php echo $value; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label form-label-required" for="nivel_experiencia">Nível de Experiência</label>
                        <select id="nivel_experiencia" name="nivel_experiencia" class="form-select" required>
                            <option value="">Selecione o nível...</option>
                            <?php foreach ($niveis_experiencia as $nivel): ?>
                                <option value="<?php echo htmlspecialchars($nivel); ?>" <?php echo (isset($_POST['nivel_experiencia']) && $_POST['nivel_experiencia'] === $nivel) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($nivel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="salario_estimado">Salário Estimado (MZN)</label>
                        <input 
                            type="number" 
                            id="salario_estimado" 
                            name="salario_estimado" 
                            class="form-input" 
                            placeholder="Ex: 25000"
                            value="<?php echo htmlspecialchars($_POST['salario_estimado'] ?? ''); ?>"
                            min="0"
                            step="1000"
                        >
                        <div class="form-help">
                            <i data-lucide="trending-up"></i>
                            Opcional. Aumenta a atração de candidatos qualificados.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Publication Details -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i data-lucide="calendar"></i>
                    </div>
                    <h2 class="section-title">Data de Publicação</h2>
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required" for="data_expiracao">Data de Expiração da Vaga</label>
                    <input 
                        type="date" 
                        id="data_expiracao" 
                        name="data_expiracao" 
                        class="form-input" 
                        value="<?php echo htmlspecialchars($_POST['data_expiracao'] ?? ''); ?>"
                        min="<?php echo date('Y-m-d'); ?>"
                        required
                    >
                    <div class="form-help">
                        <i data-lucide="clock"></i>
                        Data até quando a vaga estará disponível para receber candidaturas.
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <i data-lucide="send"></i>
                    Publicar Vaga
                </button>
                <a href="dashboard.php" class="btn-cancel">
                    <i data-lucide="x"></i>
                    Cancelar
                </a>
            </div>

        </form>

    </div>

    <!-- ==========================================
         🦶 FOOTER
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

        // Header scroll effect
        window.addEventListener('scroll', () => {
            const header = document.querySelector('.header');
            if (window.scrollY > 20) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Character counter function
        function updateCharCounter(fieldId, maxLength, minLength) {
            const field = document.getElementById(fieldId);
            const counter = document.getElementById(fieldId + '-counter');
            const length = field.value.length;

            if (maxLength) {
                counter.textContent = `${length} / ${maxLength} caracteres (mínimo ${minLength})`;
            } else {
                counter.textContent = `${length} caracteres (mínimo ${minLength})`;
            }

            // Update counter color based on length
            if (length < minLength) {
                counter.classList.remove('success');
                counter.classList.add('warning');
            } else {
                counter.classList.remove('warning');
                counter.classList.add('success');
            }
        }

        // Initialize character counters on page load
        document.addEventListener('DOMContentLoaded', () => {
            const titulo = document.getElementById('titulo');
            const descricao = document.getElementById('descricao');

            if (titulo.value) {
                updateCharCounter('titulo', 200, 5);
            }

            if (descricao.value) {
                updateCharCounter('descricao', null, 50);
            }

            // Reinitialize Lucide icons
            lucide.createIcons();
        });
    </script>
    
    <!-- 🔒 Proteção de Sessão - Encerra ao fechar aba -->
    <script src="../assets/js/session-guard.js"></script>

</body>
</html>
