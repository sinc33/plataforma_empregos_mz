<?php
session_start();
require_once '../config/db.php';

// Verificar se o usuário está logado e é um candidato
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidato') {
    header("Location: ../auth/login.php");
    exit;
}

// Criar diretórios de upload se não existirem
if (!is_dir('../uploads/cv')) {
    mkdir('../uploads/cv', 0777, true);
}
if (!is_dir('../uploads/fotos')) {
    mkdir('../uploads/fotos', 0777, true);
}

 $pdo = getPDO();
 $candidato_id = $_SESSION['user_id'];
 $sucesso = '';
 $erro = '';

// Buscar dados do candidato
 $sql_candidato = "
    SELECT c.*, u.email, u.ultimo_login 
    FROM candidato c 
    JOIN utilizador u ON c.id = u.id 
    WHERE c.id = ?
";

 $stmt_candidato = $pdo->prepare($sql_candidato);
 $stmt_candidato->execute([$candidato_id]);
 $candidato = $stmt_candidato->fetch();

if (!$candidato) {
    header("Location: ../auth/login.php");
    exit;
}

// Buscar experiências do candidato
 $sql_experiencias = "SELECT * FROM experiencia WHERE candidato_id = ? ORDER BY data_inicio DESC";
 $stmt_experiencias = $pdo->prepare($sql_experiencias);
 $stmt_experiencias->execute([$candidato_id]);
 $experiencias = $stmt_experiencias->fetchAll();

// Buscar formações do candidato
 $sql_formacoes = "SELECT * FROM formacao WHERE candidato_id = ? ORDER BY data_inicio DESC";
 $stmt_formacoes = $pdo->prepare($sql_formacoes);
 $stmt_formacoes->execute([$candidato_id]);
 $formacoes = $stmt_formacoes->fetchAll();

// Buscar candidaturas do candidato
 $sql_candidaturas = "
    SELECT c.*, v.titulo, v.empresa_id, e.nome_empresa, v.localizacao
    FROM candidatura c
    JOIN vaga v ON c.vaga_id = v.id
    JOIN empresa e ON v.empresa_id = e.id
    WHERE c.candidato_id = ?
    ORDER BY c.data_candidatura DESC
    LIMIT 10
";

 $stmt_candidaturas = $pdo->prepare($sql_candidaturas);
 $stmt_candidaturas->execute([$candidato_id]);
 $candidaturas = $stmt_candidaturas->fetchAll();

// Processar atualização do perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao'])) {
        try {
            $pdo->beginTransaction();

            switch ($_POST['acao']) {
                case 'atualizar_perfil':
                    $nome_completo = trim($_POST['nome_completo']);
                    $telefone = trim($_POST['telefone']);
                    $localizacao = $_POST['localizacao'];
                    $competencias = trim($_POST['competencias']);

                    // Validações
                    if (empty($nome_completo)) {
                        throw new Exception("Nome completo é obrigatório.");
                    }

                    // Atualizar candidato
                    $sql_update = "UPDATE candidato SET nome_completo = ?, telefone = ?, localizacao = ?, competencias = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql_update);
                    $stmt->execute([$nome_completo, $telefone, $localizacao, $competencias, $candidato_id]);

                    // Processar upload de foto
                    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                        $foto = $_FILES['foto_perfil'];
                        $extensao = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
                        $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif'];

                        if (in_array($extensao, $extensoes_permitidas)) {
                            if ($foto['size'] <= 5 * 1024 * 1024) { // 5MB
                                $nome_arquivo = 'foto_' . $candidato_id . '_' . time() . '.' . $extensao;
                                $caminho_destino = '../uploads/fotos/' . $nome_arquivo;

                                if (move_uploaded_file($foto['tmp_name'], $caminho_destino)) {
                                    // Remover foto antiga se existir
                                    if ($candidato['foto_perfil'] && file_exists('../uploads/fotos/' . $candidato['foto_perfil'])) {
                                        unlink('../uploads/fotos/' . $candidato['foto_perfil']);
                                    }

                                    $sql_foto = "UPDATE candidato SET foto_perfil = ? WHERE id = ?";
                                    $stmt_foto = $pdo->prepare($sql_foto);
                                    $stmt_foto->execute([$nome_arquivo, $candidato_id]);
                                    $candidato['foto_perfil'] = $nome_arquivo;
                                }
                            } else {
                                throw new Exception("A foto deve ter no máximo 5MB.");
                            }
                        } else {
                            throw new Exception("Formato de arquivo não permitido. Use JPG, PNG ou GIF.");
                        }
                    }

                    // Processar upload de CV
                    if (isset($_FILES['cv_pdf']) && $_FILES['cv_pdf']['error'] === UPLOAD_ERR_OK) {
                        $cv = $_FILES['cv_pdf'];
                        $extensao = strtolower(pathinfo($cv['name'], PATHINFO_EXTENSION));

                        if ($extensao === 'pdf') {
                            if ($cv['size'] <= 10 * 1024 * 1024) { // 10MB
                                $nome_arquivo = 'cv_' . $candidato_id . '_' . time() . '.pdf';
                                $caminho_destino = '../uploads/cv/' . $nome_arquivo;

                                if (move_uploaded_file($cv['tmp_name'], $caminho_destino)) {
                                    // Remover CV antigo se existir
                                    if ($candidato['cv_pdf'] && file_exists('../uploads/cv/' . $candidato['cv_pdf'])) {
                                        unlink('../uploads/cv/' . $candidato['cv_pdf']);
                                    }

                                    $sql_cv = "UPDATE candidato SET cv_pdf = ? WHERE id = ?";
                                    $stmt_cv = $pdo->prepare($sql_cv);
                                    $stmt_cv->execute([$nome_arquivo, $candidato_id]);
                                    $candidato['cv_pdf'] = $nome_arquivo;
                                }
                            } else {
                                throw new Exception("O CV deve ter no máximo 10MB.");
                            }
                        } else {
                            throw new Exception("Apenas arquivos PDF são aceites para CV.");
                        }
                    }

                    // Atualizar dados locais
                    $candidato['nome_completo'] = $nome_completo;
                    $candidato['telefone'] = $telefone;
                    $candidato['localizacao'] = $localizacao;
                    $candidato['competencias'] = $competencias;

                    $sucesso = "Perfil atualizado com sucesso!";
                    break;

                case 'adicionar_experiencia':
                    $empresa = trim($_POST['empresa']);
                    $cargo = trim($_POST['cargo']);
                    $descricao = trim($_POST['descricao']);
                    $data_inicio = $_POST['data_inicio'];
                    $data_fim = $_POST['data_fim'] ?: null;

                    if (empty($empresa) || empty($cargo) || empty($data_inicio)) {
                        throw new Exception("Empresa, cargo e data de início são obrigatórios.");
                    }

                    $sql_exp = "INSERT INTO experiencia (candidato_id, empresa, cargo, descricao, data_inicio, data_fim) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_exp = $pdo->prepare($sql_exp);
                    $stmt_exp->execute([$candidato_id, $empresa, $cargo, $descricao, $data_inicio, $data_fim]);

                    $sucesso = "Experiência adicionada com sucesso!";
                    break;

                case 'adicionar_formacao':
                    $instituicao = trim($_POST['instituicao']);
                    $curso = trim($_POST['curso']);
                    $grau = trim($_POST['grau']);
                    $data_inicio = $_POST['data_inicio'];
                    $data_fim = $_POST['data_fim'] ?: null;

                    if (empty($instituicao) || empty($curso) || empty($data_inicio)) {
                        throw new Exception("Instituição, curso e data de início são obrigatórios.");
                    }

                    $sql_form = "INSERT INTO formacao (candidato_id, instituicao, curso, grau, data_inicio, data_fim) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_form = $pdo->prepare($sql_form);
                    $stmt_form->execute([$candidato_id, $instituicao, $curso, $grau, $data_inicio, $data_fim]);

                    $sucesso = "Formação adicionada com sucesso!";
                    break;

                case 'excluir_experiencia':
                    $exp_id = (int)$_POST['experiencia_id'];
                    // Verificar se a experiência pertence ao candidato
                    $stmt_verificar = $pdo->prepare("SELECT id FROM experiencia WHERE id = ? AND candidato_id = ?");
                    $stmt_verificar->execute([$exp_id, $candidato_id]);
                    
                    if ($stmt_verificar->fetch()) {
                        $sql_del_exp = "DELETE FROM experiencia WHERE id = ?";
                        $stmt_del = $pdo->prepare($sql_del_exp);
                        $stmt_del->execute([$exp_id]);
                        $sucesso = "Experiência excluída com sucesso!";
                    } else {
                        throw new Exception("Experiência não encontrada.");
                    }
                    break;

                case 'excluir_formacao':
                    $form_id = (int)$_POST['formacao_id'];
                    // Verificar se a formação pertence ao candidato
                    $stmt_verificar = $pdo->prepare("SELECT id FROM formacao WHERE id = ? AND candidato_id = ?");
                    $stmt_verificar->execute([$form_id, $candidato_id]);
                    
                    if ($stmt_verificar->fetch()) {
                        $sql_del_form = "DELETE FROM formacao WHERE id = ?";
                        $stmt_del = $pdo->prepare($sql_del_form);
                        $stmt_del->execute([$form_id]);
                        $sucesso = "Formação excluída com sucesso!";
                    } else {
                        throw new Exception("Formação não encontrada.");
                    }
                    break;
            }

            $pdo->commit();

            // Recarregar dados após atualização
            $stmt_candidato->execute([$candidato_id]);
            $candidato = $stmt_candidato->fetch();
            
            $stmt_experiencias->execute([$candidato_id]);
            $experiencias = $stmt_experiencias->fetchAll();
            
            $stmt_formacoes->execute([$candidato_id]);
            $formacoes = $stmt_formacoes->fetchAll();

        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = $e->getMessage();
        }
    }
}

// Dados para selects
 $provincias_mz = [
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

 $niveis_grau = [
    'Ensino Médio',
    'Curso Técnico',
    'Bacharelato',
    'Licenciatura',
    'Pós-Graduação',
    'Mestrado',
    'Doutoramento'
];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Emprego MZ</title>
    
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
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: var(--space-lg);
        }

        .hero h1 {
            font-family: var(--font-heading);
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 700;
            margin-bottom: var(--space-sm);
        }

        .hero p {
            font-size: 1.1rem;
            opacity: 0.9;
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
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-md);
        }

        .main-content {
            padding: var(--space-xl) 0;
        }

        /* ============================================
           📊 STATS GRID
        ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        .stat-card {
            background: var(--branco-puro);
            border-radius: 20px;
            padding: var(--space-lg);
            box-shadow: var(--shadow-medium);
            text-align: center;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Padrão Capulana muito sutil no card */
        .stat-card::before {
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

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--dourado-sol);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--gradient-terra);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-md);
            box-shadow: var(--shadow-soft);
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
           🎴 XIMA CARDS - Cards de Vagas/Itens
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
            align-items: center;
            gap: var(--space-xs);
        }

        .item-card {
            background: var(--areia-quente);
            border-radius: 16px;
            padding: var(--space-md);
            margin-bottom: var(--space-md);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .item-title {
            font-family: var(--font-heading);
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--carvao);
            margin-bottom: var(--space-xs);
        }

        .item-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s;
        }

        .item-title a:hover {
            color: var(--verde-esperanca);
        }

        .item-subtitle {
            color: var(--azul-indigo);
            font-weight: 600;
            margin-bottom: var(--space-xs);
        }

        .item-meta {
            color: var(--cinza-baobab);
            font-size: 0.9rem;
        }

        /* ============================================
           🔘 BUTTONS - Sistema de Botões
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

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-success {
            background: var(--verde-esperanca);
            color: var(--branco-puro);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-warning {
            background: var(--dourado-sol);
            color: var(--carvao);
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-danger {
            background: var(--coral-vivo);
            color: var(--branco-puro);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-secondary {
            background: var(--cinza-baobab);
            color: var(--branco-puro);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-group {
            display: flex;
            gap: var(--space-sm);
            flex-wrap: wrap;
        }

        /* ============================================
           🎭 ALERTAS
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

        /* ============================================
           🎭 EMPTY STATE
        ============================================ */
        .empty-state {
            text-align: center;
            padding: var(--space-xl);
            background: var(--branco-puro);
            border-radius: 20px;
            box-shadow: var(--shadow-medium);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: var(--space-md);
            color: var(--cinza-baobab);
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
            margin-bottom: var(--space-lg);
        }

        /* ============================================
           📱 NAVIGATION & TABS
        ============================================ */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            margin-bottom: var(--space-md);
            font-size: 0.9rem;
            color: var(--cinza-baobab);
        }

        .breadcrumb a {
            color: var(--azul-indigo);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            transition: color 0.3s;
        }

        .breadcrumb a:hover {
            color: var(--verde-esperanca);
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid var(--areia-quente);
            margin-bottom: var(--space-lg);
            gap: var(--space-sm);
            flex-wrap: wrap;
        }

        .tab {
            padding: var(--space-sm) var(--space-md);
            cursor: pointer;
            border: none;
            background: none;
            font-size: 1rem;
            font-weight: 600;
            color: var(--cinza-baobab);
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            border-radius: 8px 8px 0 0;
        }

        .tab.active {
            color: var(--azul-indigo);
            border-bottom-color: var(--azul-indigo);
        }
        
        .tab:hover {
            color: var(--verde-esperanca);
            background: rgba(43, 122, 75, 0.05);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* ============================================
           📝 FORMS
        ============================================ */
        .form-group {
            margin-bottom: var(--space-md);
        }

        label {
            display: block;
            margin-bottom: var(--space-xs);
            font-weight: 600;
            color: var(--carvao);
        }

        input, select, textarea {
            width: 100%;
            padding: var(--space-sm);
            border: 2px solid var(--areia-quente);
            border-radius: 12px;
            font-size: 1rem;
            font-family: var(--font-body);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--dourado-sol);
            box-shadow: 0 0 0 3px rgba(255, 176, 59, 0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-md);
        }

        .file-upload {
            border: 2px dashed var(--cinza-baobab);
            padding: var(--space-md);
            text-align: center;
            border-radius: 12px;
            margin-bottom: var(--space-sm);
            transition: border-color 0.3s;
        }

        .file-upload:hover {
            border-color: var(--dourado-sol);
        }

        .file-info {
            font-size: 0.9rem;
            color: var(--cinza-baobab);
            margin-top: var(--space-xs);
        }

        .competencias-tags {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-xs);
            margin-top: var(--space-sm);
        }

        .competencia-tag {
            background: var(--gradient-terra);
            color: var(--carvao);
            padding: var(--space-xs) var(--space-sm);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .candidatura-status {
            padding: var(--space-xs) var(--space-sm);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-submetida { background: rgba(30, 58, 95, 0.1); color: var(--azul-indigo); }
        .status-em_analise { background: rgba(255, 176, 59, 0.1); color: #cc8a2e; }
        .status-entrevista { background: rgba(118, 75, 162, 0.1); color: #764ba2; }
        .status-rejeitada { background: rgba(255, 107, 107, 0.1); color: var(--coral-vivo); }
        .status-contratado { background: rgba(43, 122, 75, 0.1); color: var(--verde-esperanca); }


        /* ============================================
           📱 RESPONSIVE DESIGN
        ============================================ */
        @media (max-width: 768px) {
            .hero {
                padding: var(--space-lg) var(--space-md);
            }

            .hero-content {
                flex-direction: column;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-md);
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .tabs {
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
        .btn:focus, .breadcrumb a:focus, .tab:focus {
            outline: 3px solid var(--dourado-sol);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <!-- 🌅 HERO SECTION -->
    <div class="hero">
        <div class="hero-content">
            <div>
                <?php if ($candidato['foto_perfil']): ?>
                    <img src="../uploads/fotos/<?php echo htmlspecialchars($candidato['foto_perfil']); ?>" 
                         alt="Foto de perfil" class="profile-avatar" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--branco-puro);">
                <?php else: ?>
                    <div style="width: 120px; height: 120px; border-radius: 50%; background: rgba(255,255,255,0.2); 
                                display: flex; align-items: center; justify-content: center; font-size: 3rem;">
                        <i data-lucide="user" style="width: 60px; height: 60px;"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div style="text-align: left;">
                <h1><?php echo htmlspecialchars($candidato['nome_completo']); ?></h1>
                <p style="margin: 5px 0; opacity: 0.9; display: flex; align-items: center; gap: var(--space-sm);">
                    <i data-lucide="mail" style="width: 18px; height: 18px;"></i>
                    <?php echo htmlspecialchars($candidato['email']); ?>
                </p>
                <p style="margin: 5px 0; opacity: 0.9; display: flex; align-items: center; gap: var(--space-sm);">
                    <i data-lucide="map-pin" style="width: 18px; height: 18px;"></i>
                    <?php echo htmlspecialchars($candidato['localizacao'] ?: 'Localização não definida'); ?>
                </p>
                <p style="margin: 5px 0; opacity: 0.9; display: flex; align-items: center; gap: var(--space-sm);">
                    <i data-lucide="phone" style="width: 18px; height: 18px;"></i>
                    <?php echo htmlspecialchars($candidato['telefone'] ?: 'Telefone não definido'); ?>
                </p>
                <?php if ($candidato['cv_pdf']): ?>
                    <a href="../uploads/cv/<?php echo htmlspecialchars($candidato['cv_pdf']); ?>" 
                       target="_blank" class="btn btn-success" style="margin-top: var(--space-sm);">
                        <i data-lucide="file-text" style="width: 18px; height: 18px;"></i>
                        Ver Meu CV
                    </a>
                <?php endif; ?>
            </div>
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
            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <a href="../index.php">
                    <i data-lucide="home" style="width: 16px; height: 16px;"></i>
                    Início
                </a>
                <span>/</span>
                <span>Meu Perfil</span>
            </nav>

            <!-- Mensagens de Sucesso/Erro -->
            <?php if (isset($sucesso)): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" style="width: 20px; height: 20px; margin-top: 2px;"></i>
                    <div><?php echo htmlspecialchars($sucesso); ?></div>
                </div>
            <?php endif; ?>

            <?php if (isset($erro)): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle" style="width: 20px; height: 20px; margin-top: 2px;"></i>
                    <div><?php echo htmlspecialchars($erro); ?></div>
                </div>
            <?php endif; ?>

            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="briefcase" style="width: 30px; height: 30px; color: var(--azul-indigo);"></i>
                    </div>
                    <div class="stat-number"><?php echo count($experiencias); ?></div>
                    <div class="stat-label">Experiências</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="graduation-cap" style="width: 30px; height: 30px; color: var(--dourado-sol);"></i>
                    </div>
                    <div class="stat-number"><?php echo count($formacoes); ?></div>
                    <div class="stat-label">Formações</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="file-text" style="width: 30px; height: 30px; color: var(--verde-esperanca);"></i>
                    </div>
                    <div class="stat-number"><?php echo count($candidaturas); ?></div>
                    <div class="stat-label">Candidaturas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-lucide="send" style="width: 30px; height: 30px; color: var(--coral-vivo);"></i>
                    </div>
                    <div class="stat-number">
                        <?php 
                        $candidaturas_ativas = array_filter($candidaturas, function($c) {
                            return in_array($c['estado'], ['submetida', 'em_analise', 'entrevista']);
                        });
                        echo count($candidaturas_ativas);
                        ?>
                    </div>
                    <div class="stat-label">Candidaturas Ativas</div>
                </div>
            </div>

            <!-- Sistema de Tabs -->
            <div class="section">
                <div class="tabs">
                    <button class="tab active" onclick="openTab('perfil')">
                        <i data-lucide="user" style="width: 18px; height: 18px;"></i>
                        Perfil
                    </button>
                    <button class="tab" onclick="openTab('experiencia')">
                        <i data-lucide="briefcase" style="width: 18px; height: 18px;"></i>
                        Experiência
                    </button>
                    <button class="tab" onclick="openTab('formacao')">
                        <i data-lucide="graduation-cap" style="width: 18px; height: 18px;"></i>
                        Formação
                    </button>
                    <button class="tab" onclick="openTab('candidaturas')">
                        <i data-lucide="file-text" style="width: 18px; height: 18px;"></i>
                        Candidaturas
                    </button>
                </div>

                <!-- Tab: Perfil -->
                <div id="perfil" class="tab-content active">
                    <h2 class="section-title">
                        <i data-lucide="edit-3" style="width: 24px; height: 24px;"></i>
                        Editar Perfil
                    </h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="atualizar_perfil">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nome_completo">Nome Completo *</label>
                                <input type="text" id="nome_completo" name="nome_completo" 
                                       value="<?php echo htmlspecialchars($candidato['nome_completo']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($candidato['email']); ?>" disabled>
                                <small style="color: var(--cinza-baobab);">O email não pode ser alterado</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="telefone">Telefone</label>
                                <input type="text" id="telefone" name="telefone" 
                                       value="<?php echo htmlspecialchars($candidato['telefone']); ?>" 
                                       placeholder="Ex: +258 84 123 4567">
                            </div>
                            <div class="form-group">
                                <label for="localizacao">Localização</label>
                                <select id="localizacao" name="localizacao">
                                    <option value="">Selecione sua localização</option>
                                    <?php foreach ($provincias_mz as $provincia): ?>
                                        <option value="<?php echo htmlspecialchars($provincia); ?>" 
                                            <?php echo $candidato['localizacao'] === $provincia ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($provincia); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="competencias">Competências</label>
                            <textarea id="competencias" name="competencias" 
                                      placeholder="Separe as competências por vírgula. Ex: PHP, JavaScript, Gestão de Equipas, Excel Avançado"><?php echo htmlspecialchars($candidato['competencias']); ?></textarea>
                            <small style="color: var(--cinza-baobab);">Exemplo: PHP, JavaScript, Gestão de Projetos, Inglês Avançado</small>
                            
                            <?php if ($candidato['competencias']): ?>
                                <div class="competencias-tags">
                                    <?php 
                                    $competencias_array = explode(',', $candidato['competencias']);
                                    foreach ($competencias_array as $competencia):
                                        $competencia_trim = trim($competencia);
                                        if (!empty($competencia_trim)):
                                    ?>
                                        <span class="competencia-tag"><?php echo htmlspecialchars($competencia_trim); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="foto_perfil">Foto de Perfil</label>
                                <div class="file-upload">
                                    <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*">
                                    <div class="file-info">Formatos: JPG, PNG, GIF (Máx. 5MB)</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="cv_pdf">Currículo (CV)</label>
                                <div class="file-upload">
                                    <input type="file" id="cv_pdf" name="cv_pdf" accept=".pdf">
                                    <div class="file-info">Apenas PDF (Máx. 10MB)</div>
                                </div>
                                <?php if ($candidato['cv_pdf']): ?>
                                    <div style="margin-top: var(--space-sm);">
                                        <a href="../uploads/cv/<?php echo htmlspecialchars($candidato['cv_pdf']); ?>" 
                                           target="_blank" style="color: var(--azul-indigo); font-weight: 500;">
                                            📄 CV atual: <?php echo htmlspecialchars($candidato['cv_pdf']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i data-lucide="save" style="width: 18px; height: 18px;"></i>
                            Atualizar Perfil
                        </button>
                    </form>
                </div>

                <!-- Tab: Experiência -->
                <div id="experiencia" class="tab-content">
                    <h2 class="section-title">
                        <i data-lucide="briefcase" style="width: 24px; height: 24px;"></i>
                        Experiência Profissional
                    </h2>
                    
                    <!-- Formulário de Adicionar Experiência -->
                    <form method="POST" style="background: var(--areia-quente); padding: var(--space-md); border-radius: 16px; margin-bottom: var(--space-lg);">
                        <input type="hidden" name="acao" value="adicionar_experiencia">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="empresa">Empresa *</label>
                                <input type="text" id="empresa" name="empresa" required>
                            </div>
                            <div class="form-group">
                                <label for="cargo">Cargo *</label>
                                <input type="text" id="cargo" name="cargo" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="data_inicio_exp">Data de Início *</label>
                                <input type="date" id="data_inicio_exp" name="data_inicio" required>
                            </div>
                            <div class="form-group">
                                <label for="data_fim_exp">Data de Fim</label>
                                <input type="date" id="data_fim_exp" name="data_fim">
                                <small style="color: var(--cinza-baobab);">Deixe em branco se for o emprego atual</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="descricao">Descrição das Funções</label>
                            <textarea id="descricao" name="descricao" placeholder="Descreva suas responsabilidades e conquistas..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="plus-circle" style="width: 18px; height: 18px;"></i>
                            Adicionar Experiência
                        </button>
                    </form>

                    <!-- Lista de Experiências -->
                    <div class="item-list">
                        <?php if (empty($experiencias)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i data-lucide="inbox" style="width: 64px; height: 64px;"></i>
                                </div>
                                <h3>Nenhuma experiência cadastrada</h3>
                                <p>Adicione sua primeira experiência profissional acima.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($experiencias as $exp): ?>
                                <div class="item-card">
                                    <div class="item-header">
                                        <div style="flex: 1;">
                                            <h3 class="item-title"><?php echo htmlspecialchars($exp['cargo']); ?></h3>
                                            <div class="item-subtitle">🏢 <?php echo htmlspecialchars($exp['empresa']); ?></div>
                                            <div class="item-meta">
                                                📅 <?php echo date('m/Y', strtotime($exp['data_inicio'])); ?> - 
                                                <?php echo $exp['data_fim'] ? date('m/Y', strtotime($exp['data_fim'])) : 'Atual'; ?>
                                                <?php 
                                                    $inicio = new DateTime($exp['data_inicio']);
                                                    $fim = $exp['data_fim'] ? new DateTime($exp['data_fim']) : new DateTime();
                                                    $interval = $inicio->diff($fim);
                                                    echo ' (' . $interval->y . ' anos ' . $interval->m . ' meses)';
                                                ?>
                                            </div>
                                            <?php if ($exp['descricao']): ?>
                                                <p style="margin: var(--space-sm) 0 0 0; color: var(--carvao);"><?php echo nl2br(htmlspecialchars($exp['descricao'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="item-actions" style="margin-top: var(--space-sm);">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="acao" value="excluir_experiencia">
                                            <input type="hidden" name="experiencia_id" value="<?php echo $exp['id']; ?>">
                                            <button type="submit" class="btn btn-danger" 
                                                    onclick="return confirm('Tem certeza que deseja excluir esta experiência?')">
                                                <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                                                Excluir
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab: Formação -->
                <div id="formacao" class="tab-content">
                    <h2 class="section-title">
                        <i data-lucide="graduation-cap" style="width: 24px; height: 24px;"></i>
                        Formação Académica
                    </h2>
                    
                    <!-- Formulário de Adicionar Formação -->
                    <form method="POST" style="background: var(--areia-quente); padding: var(--space-md); border-radius: 16px; margin-bottom: var(--space-lg);">
                        <input type="hidden" name="acao" value="adicionar_formacao">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="instituicao">Instituição *</label>
                                <input type="text" id="instituicao" name="instituicao" required>
                            </div>
                            <div class="form-group">
                                <label for="curso">Curso *</label>
                                <input type="text" id="curso" name="curso" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="grau">Grau/Nível</label>
                                <select id="grau" name="grau">
                                    <option value="">Selecione o grau</option>
                                    <?php foreach ($niveis_grau as $nivel): ?>
                                        <option value="<?php echo htmlspecialchars($nivel); ?>"><?php echo htmlspecialchars($nivel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="data_inicio_form">Data de Início *</label>
                                <input type="date" id="data_inicio_form" name="data_inicio" required>
                            </div>
                            <div class="form-group">
                                <label for="data_fim_form">Data de Fim</label>
                                <input type="date" id="data_fim_form" name="data_fim">
                                <small style="color: var(--cinza-baobab);">Deixe em branco se ainda estiver a estudar</small>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="plus-circle" style="width: 18px; height: 18px;"></i>
                            Adicionar Formação
                        </button>
                    </form>

                    <!-- Lista de Formações -->
                    <div class="item-list">
                        <?php if (empty($formacoes)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i data-lucide="inbox" style="width: 64px; height: 64px;"></i>
                                </div>
                                <h3>Nenhuma formação cadastrada</h3>
                                <p>Adicione sua primeira formação académica acima.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($formacoes as $form): ?>
                                <div class="item-card">
                                    <div class="item-header">
                                        <div style="flex: 1;">
                                            <h3 class="item-title"><?php echo htmlspecialchars($form['curso']); ?></h3>
                                            <div class="item-subtitle">🏫 <?php echo htmlspecialchars($form['instituicao']); ?></div>
                                            <div class="item-meta">
                                                <?php if ($form['grau']): ?>
                                                    🎓 <?php echo htmlspecialchars($form['grau']); ?> • 
                                                <?php endif; ?>
                                                📅 <?php echo date('m/Y', strtotime($form['data_inicio'])); ?> - 
                                                <?php echo $form['data_fim'] ? date('m/Y', strtotime($form['data_fim'])) : 'Atual'; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="item-actions" style="margin-top: var(--space-sm);">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="acao" value="excluir_formacao">
                                            <input type="hidden" name="formacao_id" value="<?php echo $form['id']; ?>">
                                            <button type="submit" class="btn btn-danger" 
                                                    onclick="return confirm('Tem certeza que deseja excluir esta formação?')">
                                                <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                                                Excluir
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab: Candidaturas -->
                <div id="candidaturas" class="tab-content">
                    <h2 class="section-title">
                        <i data-lucide="file-text" style="width: 24px; height: 24px;"></i>
                        Minhas Candidaturas Recentes
                    </h2>
                    
                    <div class="item-list">
                        <?php if (empty($candidaturas)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i data-lucide="inbox" style="width: 64px; height: 64px;"></i>
                                </div>
                                <h3>Nenhuma candidatura encontrada</h3>
                                <p>Explore as vagas disponíveis e candidate-se às oportunidades que mais combinam com seu perfil.</p>
                                <a href="../vagas.php" class="btn btn-primary" style="margin-top: var(--space-md);">
                                    <i data-lucide="search" style="width: 18px; height: 18px;"></i>
                                    Explorar Vagas
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($candidaturas as $cand): ?>
                                <div class="item-card">
                                    <div class="item-header">
                                        <div style="flex: 1;">
                                            <h3 class="item-title">
                                                <a href="../vaga_detalhe.php?id=<?php echo $cand['vaga_id']; ?>" 
                                                   style="text-decoration: none; color: inherit;">
                                                    <?php echo htmlspecialchars($cand['titulo']); ?>
                                                </a>
                                            </h3>
                                            <div class="item-subtitle">🏢 <?php echo htmlspecialchars($cand['nome_empresa']); ?></div>
                                            <div class="item-meta">
                                                📍 <?php echo htmlspecialchars($cand['localizacao']); ?> • 
                                                📅 Candidatou-se em: <?php echo date('d/m/Y H:i', strtotime($cand['data_candidatura'])); ?>
                                            </div>
                                            <?php if ($cand['carta_apresentacao']): ?>
                                                <div style="margin-top: var(--space-sm); padding: var(--space-sm); background: var(--branco-marfim); border-radius: 8px;">
                                                    <strong style="color: var(--carvao);">📝 Sua carta de apresentação:</strong>
                                                    <p style="margin: var(--space-xs) 0 0 0; font-size: 0.9em;"><?php echo nl2br(htmlspecialchars($cand['carta_apresentacao'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <?php 
                                            $status_classes = [
                                                'submetida' => 'status-submetida',
                                                'em_analise' => 'status-em_analise',
                                                'entrevista' => 'status-entrevista',
                                                'rejeitada' => 'status-rejeitada',
                                                'contratado' => 'status-contratado'
                                            ];
                                            ?>
                                            <span class="candidatura-status <?php echo $status_classes[$cand['estado']]; ?>">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $cand['estado']))); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($candidaturas) >= 10): ?>
                                <div style="text-align: center; margin-top: var(--space-lg);">
                                    <a href="candidaturas.php" class="btn btn-secondary">
                                        <i data-lucide="list" style="width: 18px; height: 18px;"></i>
                                        Ver Todas as Candidaturas
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🌊 OCEAN DIVIDER -->
    <div class="ocean-divider">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none" transform="rotate(180)">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                  style="fill: var(--gradient-oceano); opacity: 0.8;"></path>
        </svg>
    </div>

    <!-- ✨ MICRO-INTERACTION SCRIPT -->
    <script>
        // Inicializar ícones Lucide
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            
            // Animação de entrada dos cards
            const animateElements = document.querySelectorAll('.stat-card, .section, .item-card');
            
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

            // Animação de contagem para estatísticas
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

        // Sistema de Tabs (mantido do original)
        function openTab(tabName) {
            // Esconder todas as tabs
            var tabContents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Remover active de todas as tabs
            var tabs = document.getElementsByClassName('tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Mostrar a tab selecionada
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // Validação de datas (mantido do original)
        document.getElementById('data_fim_exp')?.addEventListener('change', function() {
            var dataInicio = document.getElementById('data_inicio_exp').value;
            var dataFim = this.value;
            
            if (dataInicio && dataFim && dataFim < dataInicio) {
                alert('A data de fim não pode ser anterior à data de início.');
                this.value = '';
            }
        });

        document.getElementById('data_fim_form')?.addEventListener('change', function() {
            var dataInicio = document.getElementById('data_inicio_form').value;
            var dataFim = this.value;
            
            if (dataInicio && dataFim && dataFim < dataInicio) {
                alert('A data de fim não pode ser anterior à data de início.');
                this.value = '';
            }
        });
    </script>
</body>
</html>