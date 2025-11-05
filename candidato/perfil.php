<?php
session_start();
require_once '../config/db.php';

// Verificar se está logado e é candidato
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidato') {
    header("Location: ../auth/login.php");
    exit;
}

$candidato_id = $_SESSION['user_id'];

$sucesso = '';
$erro = '';
$erros_validacao = [];

// Verificar mensagens de sessão (redirecionamentos)
if (isset($_SESSION['mensagem_sucesso'])) {
    $sucesso = $_SESSION['mensagem_sucesso'];
    unset($_SESSION['mensagem_sucesso']);
}
if (isset($_SESSION['mensagem_erro'])) {
    $erro = $_SESSION['mensagem_erro'];
    unset($_SESSION['mensagem_erro']);
}

// ========================================
// BUSCAR DADOS DO CANDIDATO
// ========================================
$sql = "SELECT c.*, u.email FROM candidato c 
        JOIN utilizador u ON c.id = u.id 
        WHERE c.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$candidato_id]);
$candidato = $stmt->fetch();

if (!$candidato) {
    header("Location: ../auth/logout.php");
    exit;
}

// ========================================
// ELIMINAR FOTO DE PERFIL
// ========================================
if (isset($_GET['deletar_foto']) && $_GET['deletar_foto'] == '1') {
    try {
        if (!empty($candidato['foto_perfil']) && file_exists('../uploads/fotos/' . $candidato['foto_perfil'])) {
            unlink('../uploads/fotos/' . $candidato['foto_perfil']);
        }
        
        $sql = "UPDATE candidato SET foto_perfil = NULL WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$candidato_id]);
        
        $_SESSION['mensagem_sucesso'] = "Foto de perfil removida com sucesso!";
        header("Location: perfil.php#dados-pessoais");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['mensagem_erro'] = "Erro ao remover foto de perfil.";
        header("Location: perfil.php#dados-pessoais");
        exit;
    }
}

// ========================================
// ELIMINAR CV
// ========================================
if (isset($_GET['deletar_cv']) && $_GET['deletar_cv'] == '1') {
    try {
        if (!empty($candidato['cv_pdf']) && file_exists('../uploads/cv/' . $candidato['cv_pdf'])) {
            unlink('../uploads/cv/' . $candidato['cv_pdf']);
        }
        
        $sql = "UPDATE candidato SET cv_pdf = NULL WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$candidato_id]);
        
        $_SESSION['mensagem_sucesso'] = "CV removido com sucesso!";
        header("Location: perfil.php#dados-pessoais");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['mensagem_erro'] = "Erro ao remover CV.";
        header("Location: perfil.php#dados-pessoais");
        exit;
    }
}

// ========================================
// PROCESSAR ATUALIZAÇÃO DE PERFIL
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_perfil'])) {
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $localizacao = trim($_POST['localizacao'] ?? '');
    $competencias = trim($_POST['competencias'] ?? '');
    
    // ========================================
    // VALIDAÇÕES ROBUSTAS
    // ========================================
    
    // Validar nome completo
    if (empty($nome_completo)) {
        $erros_validacao[] = "O nome completo é obrigatório.";
    } elseif (strlen($nome_completo) < 3) {
        $erros_validacao[] = "O nome completo deve ter pelo menos 3 caracteres.";
    } elseif (strlen($nome_completo) > 150) {
        $erros_validacao[] = "O nome completo não pode ter mais de 150 caracteres.";
    } elseif (!preg_match("/^[a-zA-ZÀ-ÿ\s'-]+$/u", $nome_completo)) {
        $erros_validacao[] = "O nome completo contém caracteres inválidos.";
    }
    
    // Validar telefone (se fornecido)
    if (!empty($telefone)) {
        // Remove espaços, hífens e parênteses para validação
        $telefone_limpo = preg_replace('/[\s\-\(\)]+/', '', $telefone);
        
        if (!preg_match('/^(\+258)?[0-9]{9,12}$/', $telefone_limpo)) {
            $erros_validacao[] = "Por favor, insira um número de telefone válido (formato: +258 XX XXX XXXX ou 8X XXX XXXX).";
        } elseif (strlen($telefone) > 20) {
            $erros_validacao[] = "O telefone não pode ter mais de 20 caracteres.";
        }
    }
    
    // Validar localização
    if (!empty($localizacao) && strlen($localizacao) > 100) {
        $erros_validacao[] = "A localização não pode ter mais de 100 caracteres.";
    }
    
    // Validar competências
    if (!empty($competencias) && strlen($competencias) > 500) {
        $erros_validacao[] = "As competências não podem ter mais de 500 caracteres.";
    }
    
    // Validar foto de perfil (se enviada)
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['foto_perfil']['error'] !== UPLOAD_ERR_OK) {
            $erros_validacao[] = "Erro ao enviar foto de perfil. Por favor, tente novamente.";
        } else {
            $allowed_images = ['jpg', 'jpeg', 'png', 'gif'];
            $foto_ext = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
            $foto_size = $_FILES['foto_perfil']['size'];
            $max_foto_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($foto_ext, $allowed_images)) {
                $erros_validacao[] = "A foto de perfil deve ser uma imagem (JPG, JPEG, PNG ou GIF).";
            }
            
            if ($foto_size > $max_foto_size) {
                $erros_validacao[] = "A foto de perfil não pode ter mais de 5MB.";
            }
            
            // Validar dimensões da imagem (opcional)
            if (empty($erros_validacao)) {
                $image_info = getimagesize($_FILES['foto_perfil']['tmp_name']);
                if ($image_info === false) {
                    $erros_validacao[] = "O arquivo enviado não é uma imagem válida.";
                }
            }
        }
    }
    
    // Validar CV (se enviado)
    if (isset($_FILES['cv_pdf']) && $_FILES['cv_pdf']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['cv_pdf']['error'] !== UPLOAD_ERR_OK) {
            $erros_validacao[] = "Erro ao enviar CV. Por favor, tente novamente.";
        } else {
            $cv_ext = strtolower(pathinfo($_FILES['cv_pdf']['name'], PATHINFO_EXTENSION));
            $cv_size = $_FILES['cv_pdf']['size'];
            $max_cv_size = 10 * 1024 * 1024; // 10MB
            
            if ($cv_ext !== 'pdf') {
                $erros_validacao[] = "O CV deve ser um arquivo PDF.";
            }
            
            if ($cv_size > $max_cv_size) {
                $erros_validacao[] = "O CV não pode ter mais de 10MB.";
            }
        }
    }
    
    // Se não há erros de validação, processar atualização
    if (empty($erros_validacao)) {
        try {
        // Upload de foto
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['foto_perfil']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed) && $_FILES['foto_perfil']['size'] <= 5000000) {
                $new_filename = 'foto_' . $candidato_id . '_' . time() . '.' . $ext;
                $upload_path = '../uploads/fotos/' . $new_filename;
                
                if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $upload_path)) {
                    // Deletar foto antiga
                    if (!empty($candidato['foto_perfil']) && file_exists('../uploads/fotos/' . $candidato['foto_perfil'])) {
                        unlink('../uploads/fotos/' . $candidato['foto_perfil']);
                    }
                    $foto_perfil = $new_filename;
                } else {
                    $foto_perfil = $candidato['foto_perfil'];
                }
            } else {
                $foto_perfil = $candidato['foto_perfil'];
            }
        } else {
            $foto_perfil = $candidato['foto_perfil'];
        }
        
        // Upload de CV
        if (isset($_FILES['cv_pdf']) && $_FILES['cv_pdf']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['cv_pdf']['name'], PATHINFO_EXTENSION));
            
            if ($ext === 'pdf' && $_FILES['cv_pdf']['size'] <= 10000000) {
                $new_cv = 'cv_' . $candidato_id . '_' . time() . '.pdf';
                $upload_path = '../uploads/cv/' . $new_cv;
                
                if (move_uploaded_file($_FILES['cv_pdf']['tmp_name'], $upload_path)) {
                    // Deletar CV antigo
                    if (!empty($candidato['cv_pdf']) && file_exists('../uploads/cv/' . $candidato['cv_pdf'])) {
                        unlink('../uploads/cv/' . $candidato['cv_pdf']);
                    }
                    $cv_pdf = $new_cv;
                } else {
                    $cv_pdf = $candidato['cv_pdf'];
                }
            } else {
                $cv_pdf = $candidato['cv_pdf'];
            }
        } else {
            $cv_pdf = $candidato['cv_pdf'];
        }
        
        // Atualizar banco de dados
        $sql = "UPDATE candidato SET 
                nome_completo = ?, 
                telefone = ?, 
                localizacao = ?, 
                competencias = ?,
                foto_perfil = ?,
                cv_pdf = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nome_completo,
            $telefone,
            $localizacao,
            $competencias,
            $foto_perfil,
            $cv_pdf,
            $candidato_id
        ]);
        
            $_SESSION['user_name'] = $nome_completo;
            $sucesso = "Perfil atualizado com sucesso!";
            
            // Recarregar dados
            $stmt = $pdo->prepare("SELECT c.*, u.email FROM candidato c JOIN utilizador u ON c.id = u.id WHERE c.id = ?");
            $stmt->execute([$candidato_id]);
            $candidato = $stmt->fetch();
            
        } catch (Exception $e) {
            $erros_validacao[] = "Erro ao atualizar perfil. Por favor, tente novamente.";
            error_log("Erro ao atualizar perfil candidato: " . $e->getMessage());
        }
    }
    
    // Consolidar erros para exibição
    if (!empty($erros_validacao)) {
        $erro = implode('<br>', $erros_validacao);
    }
}

// ========================================
// ADICIONAR EXPERIÊNCIA
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_experiencia'])) {
    $empresa = trim($_POST['empresa'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $data_inicio = $_POST['data_inicio'] ?? null;
    $data_fim = $_POST['data_fim'] ?? null;
    
    $erros_exp = [];
    
    // Validar empresa
    if (empty($empresa)) {
        $erros_exp[] = "O nome da empresa é obrigatório.";
    } elseif (strlen($empresa) < 2) {
        $erros_exp[] = "O nome da empresa deve ter pelo menos 2 caracteres.";
    } elseif (strlen($empresa) > 100) {
        $erros_exp[] = "O nome da empresa não pode ter mais de 100 caracteres.";
    }
    
    // Validar cargo
    if (empty($cargo)) {
        $erros_exp[] = "O cargo é obrigatório.";
    } elseif (strlen($cargo) < 2) {
        $erros_exp[] = "O cargo deve ter pelo menos 2 caracteres.";
    } elseif (strlen($cargo) > 100) {
        $erros_exp[] = "O cargo não pode ter mais de 100 caracteres.";
    }
    
    // Validar descrição (opcional)
    if (!empty($descricao) && strlen($descricao) > 1000) {
        $erros_exp[] = "A descrição não pode ter mais de 1000 caracteres.";
    }
    
    // Validar e converter datas (formato Y-m do input type="month")
    $data_inicio_converted = null;
    $data_fim_converted = null;
    
    if (!empty($data_inicio)) {
        // Formato Y-m (2024-01) → Y-m-d (2024-01-01)
        $data_inicio_obj = DateTime::createFromFormat('Y-m', $data_inicio);
        if (!$data_inicio_obj) {
            $erros_exp[] = "Data de início inválida.";
        } elseif ($data_inicio_obj > new DateTime()) {
            $erros_exp[] = "A data de início não pode ser futura.";
        } else {
            $data_inicio_converted = $data_inicio_obj->format('Y-m-01'); // Primeiro dia do mês
        }
    }
    
    if (!empty($data_fim)) {
        // Formato Y-m (2024-01) → Y-m-d (2024-01-01)
        $data_fim_obj = DateTime::createFromFormat('Y-m', $data_fim);
        if (!$data_fim_obj) {
            $erros_exp[] = "Data de término inválida.";
        } else {
            $data_fim_converted = $data_fim_obj->format('Y-m-01'); // Primeiro dia do mês
        }
    }
    
    // Validar ordem das datas
    if (!empty($data_inicio_converted) && !empty($data_fim_converted)) {
        $data_inicio_check = DateTime::createFromFormat('Y-m-d', $data_inicio_converted);
        $data_fim_check = DateTime::createFromFormat('Y-m-d', $data_fim_converted);
        
        if ($data_fim_check < $data_inicio_check) {
            $erros_exp[] = "A data de término não pode ser anterior à data de início.";
        }
    }
    
    if (empty($erros_exp)) {
        try {
            $sql = "INSERT INTO experiencia (candidato_id, empresa, cargo, descricao, data_inicio, data_fim) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $candidato_id, 
                $empresa, 
                $cargo, 
                $descricao, 
                $data_inicio_converted, 
                $data_fim_converted
            ]);
            
            $_SESSION['mensagem_sucesso'] = "Experiência adicionada com sucesso!";
            header("Location: perfil.php");
            exit;
        } catch (Exception $e) {
            $erro = "Erro ao adicionar experiência. Por favor, tente novamente.";
            error_log("Erro ao adicionar experiência: " . $e->getMessage());
        }
    } else {
        $erro = implode('<br>', $erros_exp);
    }
}

// ========================================
// DELETAR EXPERIÊNCIA
// ========================================
if (isset($_GET['deletar_exp'])) {
    $exp_id = (int)$_GET['deletar_exp'];
    try {
        $stmt = $pdo->prepare("DELETE FROM experiencia WHERE id = ? AND candidato_id = ?");
        $stmt->execute([$exp_id, $candidato_id]);
        $_SESSION['mensagem_sucesso'] = "Experiência removida com sucesso!";
        header("Location: perfil.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['mensagem_erro'] = "Erro ao remover experiência.";
        header("Location: perfil.php");
        exit;
    }
}

// ========================================
// ADICIONAR FORMAÇÃO
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_formacao'])) {
    $instituicao = trim($_POST['instituicao'] ?? '');
    $curso = trim($_POST['curso'] ?? '');
    $grau = trim($_POST['grau'] ?? '');
    $data_inicio = $_POST['data_inicio_form'] ?? null;
    $data_fim = $_POST['data_fim_form'] ?? null;
    
    $erros_form = [];
    
    // Validar instituição
    if (empty($instituicao)) {
        $erros_form[] = "O nome da instituição é obrigatório.";
    } elseif (strlen($instituicao) < 2) {
        $erros_form[] = "O nome da instituição deve ter pelo menos 2 caracteres.";
    } elseif (strlen($instituicao) > 150) {
        $erros_form[] = "O nome da instituição não pode ter mais de 150 caracteres.";
    }
    
    // Validar curso
    if (empty($curso)) {
        $erros_form[] = "O nome do curso é obrigatório.";
    } elseif (strlen($curso) < 2) {
        $erros_form[] = "O nome do curso deve ter pelo menos 2 caracteres.";
    } elseif (strlen($curso) > 150) {
        $erros_form[] = "O nome do curso não pode ter mais de 150 caracteres.";
    }
    
    // Validar grau (opcional)
    if (!empty($grau) && strlen($grau) > 50) {
        $erros_form[] = "O grau não pode ter mais de 50 caracteres.";
    }
    
    // Validar e converter datas (formato Y-m do input type="month")
    $data_inicio_converted = null;
    $data_fim_converted = null;
    
    if (!empty($data_inicio)) {
        // Formato Y-m (2024-01) → Y-m-d (2024-01-01)
        $data_inicio_obj = DateTime::createFromFormat('Y-m', $data_inicio);
        if (!$data_inicio_obj) {
            $erros_form[] = "Data de início inválida.";
        } else {
            $data_inicio_converted = $data_inicio_obj->format('Y-m-01'); // Primeiro dia do mês
        }
    }
    
    if (!empty($data_fim)) {
        // Formato Y-m (2024-01) → Y-m-d (2024-01-01)
        $data_fim_obj = DateTime::createFromFormat('Y-m', $data_fim);
        if (!$data_fim_obj) {
            $erros_form[] = "Data de término inválida.";
        } else {
            $data_fim_converted = $data_fim_obj->format('Y-m-01'); // Primeiro dia do mês
        }
    }
    
    // Validar ordem das datas
    if (!empty($data_inicio_converted) && !empty($data_fim_converted)) {
        $data_inicio_check = DateTime::createFromFormat('Y-m-d', $data_inicio_converted);
        $data_fim_check = DateTime::createFromFormat('Y-m-d', $data_fim_converted);
        
        if ($data_fim_check < $data_inicio_check) {
            $erros_form[] = "A data de término não pode ser anterior à data de início.";
        }
    }
    
    if (empty($erros_form)) {
        try {
            $sql = "INSERT INTO formacao (candidato_id, instituicao, curso, grau, data_inicio, data_fim) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $candidato_id, 
                $instituicao, 
                $curso, 
                $grau, 
                $data_inicio_converted, 
                $data_fim_converted
            ]);
            
            $_SESSION['mensagem_sucesso'] = "Formação adicionada com sucesso!";
            header("Location: perfil.php");
            exit;
        } catch (Exception $e) {
            $erro = "Erro ao adicionar formação. Por favor, tente novamente.";
            error_log("Erro ao adicionar formação: " . $e->getMessage());
        }
    } else {
        $erro = implode('<br>', $erros_form);
    }
}

// ========================================
// DELETAR FORMAÇÃO
// ========================================
if (isset($_GET['deletar_form'])) {
    $form_id = (int)$_GET['deletar_form'];
    try {
        $stmt = $pdo->prepare("DELETE FROM formacao WHERE id = ? AND candidato_id = ?");
        $stmt->execute([$form_id, $candidato_id]);
        $_SESSION['mensagem_sucesso'] = "Formação removida com sucesso!";
        header("Location: perfil.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['mensagem_erro'] = "Erro ao remover formação.";
        header("Location: perfil.php");
        exit;
    }
}

// ========================================
// BUSCAR EXPERIÊNCIAS
// ========================================
$experiencias = $pdo->prepare("SELECT * FROM experiencia WHERE candidato_id = ? ORDER BY data_inicio DESC");
$experiencias->execute([$candidato_id]);
$experiencias = $experiencias->fetchAll();

// ========================================
// BUSCAR FORMAÇÕES
// ========================================
$formacoes = $pdo->prepare("SELECT * FROM formacao WHERE candidato_id = ? ORDER BY data_inicio DESC");
$formacoes->execute([$candidato_id]);
$formacoes = $formacoes->fetchAll();

// ========================================
// BUSCAR CANDIDATURAS
// ========================================
$candidaturas = $pdo->prepare("SELECT c.*, v.titulo, v.area, v.localizacao, e.nome_empresa 
                                FROM candidatura c
                                JOIN vaga v ON c.vaga_id = v.id
                                JOIN empresa e ON v.empresa_id = e.id
                                WHERE c.candidato_id = ?
                                ORDER BY c.data_candidatura DESC
                                LIMIT 5");
$candidaturas->execute([$candidato_id]);
$candidaturas = $candidaturas->fetchAll();

// ========================================
// CALCULAR COMPLETUDE DO PERFIL
// ========================================
$completude = 0;
if (!empty($candidato['nome_completo'])) $completude += 15;
if (!empty($candidato['telefone'])) $completude += 10;
if (!empty($candidato['localizacao'])) $completude += 10;
if (!empty($candidato['competencias'])) $completude += 15;
if (!empty($candidato['foto_perfil'])) $completude += 15;
if (!empty($candidato['cv_pdf'])) $completude += 15;
if (count($experiencias) > 0) $completude += 10;
if (count($formacoes) > 0) $completude += 10;

// Funções auxiliares
function formatarData($data) {
    if (empty($data)) return 'Atual';
    return date('m/Y', strtotime($data));
}

function traduzirEstado($estado) {
    $estados = [
        'submetida' => 'Submetida',
        'em_analise' => 'Em Análise',
        'entrevista' => 'Entrevista',
        'rejeitada' => 'Rejeitada',
        'contratado' => 'Contratado'
    ];
    return $estados[$estado] ?? $estado;
}

function getCorEstado($estado) {
    $cores = [
        'submetida' => '#0088CC',
        'em_analise' => '#FF8C00',
        'entrevista' => '#10B981',
        'rejeitada' => '#EF4444',
        'contratado' => '#6F42C1'
    ];
    return $cores[$estado] ?? '#6B7280';
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - <?php echo htmlspecialchars($candidato['nome_completo']); ?> | Emprego MZ</title>
    
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
            /* === 🎨 PALETA DEFINITIVA PROFISSIONAL PARA PERFIL === */
            
            /* 🔵 CORES PRINCIPAIS */
            --cor-primaria: #14213d;              /* Oxford Blue - Headers, títulos principais */
            --cor-primaria-escura: #0f1a2e;       /* Oxford Blue Dark - Hover intenso em botões */
            --cor-primaria-clara: #1e2c47;        /* Oxford Blue Light - Elementos secundários */
            --cor-primaria-alpha: rgba(20, 33, 61, 0.1);
            
            /* 🟠 CORES SECUNDÁRIAS */
            --cor-secundaria: #fca311;            /* Orange Web - Botões de ação, editar */
            --cor-secundaria-hover: #e3940f;      /* Orange Hover */
            --cor-secundaria-clara: #fdb541;      /* Orange Light */
            --cor-secundaria-muito-clara: #fec871; /* Orange Very Light */
            --cor-secundaria-alpha: rgba(252, 163, 17, 0.1);
            
            /* 🌫️ HIERARQUIA VISUAL - Soft Gray System */
            --cor-fundo: #f8fafc;                 /* Soft Gray - Fundo geral da página */
            --cor-cards: #ffffff;                 /* White - Cards, seções, modais */
            --cor-acento: #f1f5ff;                /* Light Blue - Hover states, seções ativas */
            --cor-acento-escuro: #e1ebff;         /* Light Blue Dark - Bordas ativas, seções destacadas */
            
            /* 📝 TEXTO - Hierarquia Refinada */
            --cor-texto: #1a202c;                 /* Títulos, informações principais */
            --cor-texto-claro: #64748b;           /* Texto secundário, descrições */
            --cor-texto-muito-claro: #94a3b8;     /* Placeholders, metadados */
            
            /* 🎯 TIPOS DE USUÁRIO */
            --cor-candidato: #10B981;             /* Verde para candidatos */
            --cor-candidato-light: #D1FAE5;       /* Fundo suave candidato */
            --cor-empresa: #8B5CF6;               /* Roxo para empresas */
            --cor-empresa-light: #EDE9FE;         /* Fundo suave empresa */
            
            /* ✅ ESTADOS ESPECÍFICOS */
            --cor-sucesso: #10B981;               /* Sucesso, perfil completo */
            --cor-erro: #EF4444;                  /* Erros, validações */
            --cor-aviso: #F59E0B;                 /* Avisos, perfil incompleto */
            --cor-info: var(--cor-primaria);      /* Informações, dicas */
            
            /* 🎯 ELEMENTOS ESPECIAIS */
            --cor-progresso: var(--cor-secundaria);  /* Barra de progresso */
            --cor-progresso-fundo: #E5E7EB;          /* Fundo da barra */
            --cor-badge-status: var(--cor-sucesso);  /* Status badges */
            --cor-avatar-border: var(--cor-primaria); /* Borda do avatar */
            
            /* 🎨 BORDAS E SOMBRAS */
            --cor-borda: #e2e8f0;                 /* Bordas padrão */
            --cor-borda-ativa: var(--cor-primaria); /* Bordas em foco */
            --sombra-card: 0 4px 20px rgba(20, 33, 61, 0.08);
            --sombra-avatar: 0 8px 32px rgba(20, 33, 61, 0.15);
            --sombra-botao: 0 2px 8px rgba(252, 163, 17, 0.3);
            --sombra-input-focus: 0 0 0 4px rgba(20, 33, 61, 0.1);
            
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
        }
        
        /* ==========================================
           📱 HEADER PROFISSIONAL - Oxford Blue (Igual index.php)
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
            background: var(--cor-cards);            /* #ffffff - White card */
            border: 1px solid var(--cor-borda);      /* #e2e8f0 - Borda refinada */
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);            /* Sombra profissional */
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
            background: var(--cor-primaria);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--cor-primaria-escura);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--cor-secundaria);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: var(--cor-secundaria-hover);
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
            color: rgba(255, 255, 255, 0.9);
        }

        .mobile-menu-toggle svg {
            width: 24px;
            height: 24px;
        }

        /* ==========================================
           🍞 BREADCRUMB
        ========================================== */
        .breadcrumb {
            background: var(--cor-cards);           /* #ffffff - White */
            border-bottom: 1px solid var(--cor-borda);
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
            color: var(--cor-texto-claro);
        }

        .breadcrumb-link {
            color: var(--cor-primaria);             /* #14213d - Oxford Blue */
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .breadcrumb-link:hover {
            color: var(--cor-secundaria);           /* #fca311 - Orange */
            text-decoration: underline;
        }

        .breadcrumb-separator {
            width: 16px;
            height: 16px;
            color: var(--cor-texto-muito-claro);
        }

        /* ==========================================
           📋 MAIN LAYOUT
        ========================================== */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-8) var(--space-6);
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: var(--space-8);
            align-items: start;
        }

        /* ==========================================
           📌 SIDEBAR - STICKY SEM OVERFLOW (Bloco único fixo)
        ========================================== */
        .sidebar {
            position: sticky;
            top: 90px;                               /* Header (70px) + breadcrumb (20px) */
            align-self: start;
            /* SEM max-height e SEM overflow - mantém tudo fixo como bloco único */
        }

        .profile-card {
            background: var(--cor-cards);            /* #ffffff - White */
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-top: 4px solid var(--cor-candidato); /* #10B981 - Verde Candidato */
            border-radius: var(--radius-lg);
            padding: var(--space-8);
            margin-bottom: var(--space-6);
            box-shadow: var(--sombra-card);          /* Sombra profissional */
            text-align: center;
            position: relative;
            overflow: hidden;
            /* Sticky junto com a sidebar */
        }

        .avatar-wrapper {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto var(--space-5);
        }

        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--cor-avatar-border); /* #14213d - Oxford Blue */
            box-shadow: var(--sombra-avatar);        /* Sombra especial avatar */
        }

        .avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--cor-primaria), var(--cor-secundaria));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 48px;
            font-weight: 800;
            border: 4px solid var(--cor-avatar-border); /* #14213d - Oxford Blue */
            box-shadow: var(--sombra-avatar);
        }

        .profile-name {
            font-size: 20px;
            font-weight: 800;
            color: var(--cor-texto);                 /* #1a202c */
            margin-bottom: var(--space-2);
        }

        .profile-email {
            font-size: 14px;
            color: var(--cor-texto-claro);           /* #64748b */
            margin-bottom: var(--space-6);
        }

        .profile-progress {
            padding-top: var(--space-5);
            border-top: 2px solid var(--cor-acento-escuro); /* #e1ebff */
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-3);
        }

        .progress-label {
            font-size: 14px;
            font-weight: 700;
            color: var(--cor-texto);                 /* #1a202c */
        }

        .progress-value {
            font-size: 20px;
            font-weight: 800;
            color: var(--cor-secundaria);            /* #fca311 - Orange */
        }

        .progress-bar-bg {
            width: 100%;
            height: 10px;
            background: var(--cor-progresso-fundo);  /* #E5E7EB */
            border-radius: var(--radius-full);
            overflow: hidden;
            margin-bottom: var(--space-3);
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--cor-secundaria), var(--cor-secundaria-hover)); /* Orange gradient */
            border-radius: var(--radius-full);
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(252, 163, 17, 0.3);
        }

        .progress-tip {
            font-size: 13px;
            color: var(--cor-texto-claro);           /* #64748b */
            text-align: left;
            line-height: 1.5;
        }

        .menu-card {
            background: var(--cor-cards);            /* #ffffff - White */
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: var(--radius-lg);
            box-shadow: var(--sombra-card);
            overflow: hidden;
            /* Sticky junto com a sidebar - ambos os cards fixos */
            margin-bottom: 0;                        /* Remove margin no último elemento */
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4);
            color: var(--cor-texto-claro);           /* #64748b */
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            border-bottom: 1px solid var(--cor-borda);
            transition: var(--transition);
        }

        .menu-item:last-child {
            border-bottom: none;
        }

        .menu-item:hover {
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
        }

        .menu-item.active {
            background: var(--cor-candidato-light);  /* #D1FAE5 - Verde Candidato Light */
            color: var(--cor-candidato);             /* #10B981 - Verde Candidato */
            font-weight: 700;
            border-left: 4px solid var(--cor-candidato);
        }

        .menu-item svg {
            width: 20px;
            height: 20px;
        }

        /* ==========================================
           📄 CONTENT AREA
        ========================================== */
        .content-area {
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

        /* Section Card */
        .section-card {
            background: var(--cor-cards);            /* #ffffff - White */
            border: 1px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: var(--radius-xl);         /* Mais arredondado */
            padding: var(--space-8);
            margin-bottom: var(--space-6);
            box-shadow: var(--sombra-card);          /* Sombra profissional */
        }

        /* Tabs (visual) */
        .tabs { display: flex; flex-wrap: wrap; gap: var(--space-2); border-bottom: 1px solid var(--cor-borda); }
        .tab { padding: 10px 14px; border: 1px solid var(--cor-borda); border-bottom: none; border-radius: 8px 8px 0 0; background: var(--cor-cards); color: var(--cor-primaria); font-weight: 700; text-decoration: none; }
        .tab[aria-selected="true"], .tab.active { background: var(--cor-acento); border-color: var(--cor-primaria); color: var(--cor-primaria); }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-6);
            padding-bottom: var(--space-4);
            border-bottom: 3px solid var(--cor-acento);  /* #f1f5ff - Light Blue */
        }

        .section-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--cor-texto);                 /* #1a202c */
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .section-title svg {
            width: 26px;
            height: 26px;
            color: var(--cor-secundaria);            /* #fca311 - Orange */
        }

        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-5);
            background: var(--cor-secundaria);       /* #fca311 - Orange */
            color: var(--white);
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--sombra-botao);        /* Sombra orange */
        }

        .btn-add:hover {
            background: var(--cor-secundaria-hover); /* #e3940f */
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(252, 163, 17, 0.4);
        }

        .btn-add svg {
            width: 18px;
            height: 18px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: var(--space-5);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-5);
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: var(--space-2);
        }

        .form-input,
        .form-textarea {
            width: 100%;
            padding: var(--space-4);
            border: 2px solid var(--cor-borda);      /* #e2e8f0 */
            border-radius: var(--radius-lg);
            font-size: 15px;
            font-family: var(--font-primary);
            color: var(--cor-texto);                 /* #1a202c */
            background: var(--cor-cards);            /* #ffffff */
            transition: var(--transition);
        }

        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            border-color: var(--cor-borda-ativa);    /* #14213d - Oxford Blue */
            box-shadow: var(--sombra-input-focus);   /* Sombra Oxford Blue */
        }

        .form-input::placeholder,
        .form-textarea::placeholder {
            color: var(--cor-texto-muito-claro);     /* #94a3b8 */
            font-style: italic;
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.6;
        }

        .file-upload-area {
            border: 2px dashed var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: var(--primary);
            background: rgba(0, 136, 204, 0.05);
        }

        .file-upload-area.has-file {
            border-color: var(--success);
            background: rgba(16, 185, 129, 0.05);
        }

        .file-upload-icon { display:none; }
        .icon-badge {
            width: 64px;
            height: 64px;
            margin: 0 auto var(--space-3);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f1f5ff 0%, #e1ebff 100%);
            border: 1px solid var(--cor-acento-escuro);
            box-shadow: 0 6px 18px rgba(20, 33, 61, 0.08);
        }
        .icon-badge i { width: 30px; height: 30px; color: var(--cor-primaria); }
        .file-upload-area:hover .icon-badge { transform: translateY(-2px); transition: var(--transition); box-shadow: 0 10px 24px rgba(20,33,61,.12); }
        .file-upload-area.has-file .icon-badge { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.3); }

        .file-upload-text {
            font-size: 14px;
            color: var(--text);
            font-weight: 600;
            margin-bottom: var(--space-1);
        }

        .file-upload-hint {
            font-size: 13px;
            color: var(--text-light);
        }

        .file-upload-input {
            display: none;
        }

        .current-file {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-4);
            background: rgba(16, 185, 129, 0.05);    /* Verde suave */
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: var(--radius-lg);
            margin-top: var(--space-3);
        }

        .current-file-info {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 14px;
            font-weight: 600;
            color: var(--cor-candidato);             /* Verde Candidato */
        }

        .current-file-info svg {
            width: 18px;
            height: 18px;
            color: var(--cor-candidato);
        }

        .file-actions {
            display: flex;
            gap: var(--space-2);
        }

        .btn-remove-file {
            padding: var(--space-2) var(--space-3);
            background: transparent;
            color: var(--cor-erro);                  /* #EF4444 */
            border: 1px solid var(--cor-erro);
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: var(--space-1);
            text-decoration: none;
        }

        .btn-remove-file:hover {
            background: var(--cor-erro);
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

        .btn-remove-file svg {
            width: 14px;
            height: 14px;
        }

        .btn-download-file {
            padding: var(--space-2) var(--space-3);
            background: transparent;
            color: var(--cor-primaria);              /* Oxford Blue */
            border: 1px solid var(--cor-primaria);
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: var(--space-1);
            text-decoration: none;
        }

        .btn-download-file:hover {
            background: var(--cor-primaria);
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(20, 33, 61, 0.3);
        }

        .btn-download-file svg {
            width: 14px;
            height: 14px;
        }

        .btn-submit {
            padding: var(--space-4) var(--space-8);
            background: var(--cor-candidato);        /* #10B981 - Verde Candidato */
            color: var(--white);
            border: none;
            border-radius: var(--radius-lg);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-submit:hover {
            background: #059669;                     /* Verde mais escuro */
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        /* Timeline */
        .timeline-item {
            position: relative;
            padding-left: var(--space-8);
            padding-bottom: var(--space-6);
            border-left: 3px solid var(--cor-borda);  /* #e2e8f0 */
            margin-bottom: var(--space-4);
        }

        .timeline-item:last-child {
            border-left: none;
            padding-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -11px;
            top: 0;
            width: 18px;
            height: 18px;
            background: var(--cor-secundaria);       /* #fca311 - Orange */
            border-radius: 50%;
            border: 3px solid var(--cor-fundo);      /* #f8fafc - Soft Gray */
            box-shadow: 0 2px 8px rgba(252, 163, 17, 0.3);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--space-4);
            margin-bottom: var(--space-2);
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--cor-primaria);              /* #14213d - Oxford Blue */
            margin-bottom: var(--space-1);
        }

        .timeline-subtitle {
            font-size: 15px;
            color: var(--cor-texto-claro);           /* #64748b */
            font-weight: 600;
            margin-bottom: var(--space-1);
        }

        .timeline-date {
            font-size: 13px;
            color: var(--cor-texto-muito-claro);     /* #94a3b8 */
            margin-bottom: var(--space-3);
            background: var(--cor-acento);           /* #f1f5ff - Light Blue */
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .timeline-description {
            font-size: 14px;
            color: var(--cor-texto-claro);           /* #64748b */
            line-height: 1.6;
        }

        .btn-delete {
            padding: var(--space-2) var(--space-4);
            background: transparent;
            color: var(--cor-erro);                  /* #EF4444 */
            border: 2px solid var(--cor-erro);
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: var(--transition);
            white-space: nowrap;
        }

        .btn-delete:hover {
            background: var(--cor-erro);
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

        /* Candidatura Card */
        .candidatura-card {
            padding: var(--space-5);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-4);
            transition: var(--transition);
        }

        .candidatura-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .candidatura-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-3);
        }

        .candidatura-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: var(--space-1);
        }

        .candidatura-company {
            font-size: 14px;
            color: var(--text-light);
        }

        .status-badge {
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 600;
            color: var(--white);
        }

        .candidatura-meta {
            display: flex;
            gap: var(--space-5);
            font-size: 13px;
            color: var(--text-lighter);
        }

        .candidatura-meta span {
            display: flex;
            align-items: center;
            gap: var(--space-1);
        }

        .candidatura-meta svg {
            width: 14px;
            height: 14px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: var(--space-16) var(--space-6);
            color: var(--text-light);
        }

        .empty-state-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto var(--space-5);
            color: var(--text-lighter);
        }

        .empty-state-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: var(--space-2);
        }

        .empty-state-text {
            font-size: 14px;
            color: var(--text-light);
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
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
            background: var(--white);
            border-radius: var(--radius-lg);
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            animation: slideUp 0.3s ease-out;
        }

        .modal-header {
            padding: var(--space-6);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
        }

        .modal-close {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .modal-close:hover {
            background: var(--gray-100);
            color: var(--text);
        }

        .modal-close svg {
            width: 24px;
            height: 24px;
        }

        .modal-body {
            padding: var(--space-6);
        }

        /* ==========================================
           🦶 FOOTER PROFISSIONAL - Oxford Blue (Igual index.php)
        ========================================== */
        .footer {
            background: var(--cor-primaria);         /* #14213d - Oxford Blue */
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

        .footer-column h3,
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
            color: rgba(255, 255, 255, 0.7);
        }

        .footer-links {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
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
        @media (max-width: 1024px) {
            .main-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
            }

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
                padding: var(--space-6) var(--space-4);
                gap: var(--space-6);
            }

            .section-card {
                padding: var(--space-6);
            }

            .form-row {
                grid-template-columns: 1fr;
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
            <span>Meu Perfil</span>
        </div>
    </div>

    <!-- ==========================================
         📋 MAIN CONTENT
    ========================================== -->
    <div class="main-container">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <!-- Profile Card -->
            <div class="profile-card">
                <div class="avatar-wrapper">
                    <?php if (!empty($candidato['foto_perfil']) && file_exists('../uploads/fotos/' . $candidato['foto_perfil'])): ?>
                        <img src="../uploads/fotos/<?php echo htmlspecialchars($candidato['foto_perfil']); ?>" 
                             class="avatar" alt="Foto de perfil">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <?php echo strtoupper(substr($candidato['nome_completo'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h2 class="profile-name"><?php echo htmlspecialchars($candidato['nome_completo']); ?></h2>
                <p class="profile-email"><?php echo htmlspecialchars($candidato['email']); ?></p>
                
                <div class="profile-progress">
                    <div class="progress-header">
                        <span class="progress-label">Completude do Perfil</span>
                        <span class="progress-value"><?php echo $completude; ?>%</span>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width: <?php echo $completude; ?>%;"></div>
                    </div>
                    <?php if ($completude < 100): ?>
                    <p class="progress-tip">
                        <?php if ($completude < 50): ?>
                        Complete seu perfil para aumentar suas chances!
                        <?php elseif ($completude < 80): ?>
                        Você está quase lá! Continue completando.
                        <?php else: ?>
                        Falta pouco para um perfil completo!
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Menu -->
            <div class="menu-card">
                <a href="#dados-pessoais" class="menu-item active">
                    <i data-lucide="user"></i>
                    Dados Pessoais
                </a>
                <a href="#experiencia" class="menu-item">
                    <i data-lucide="briefcase"></i>
                    Experiência
                </a>
                <a href="#formacao" class="menu-item">
                    <i data-lucide="graduation-cap"></i>
                    Formação
                </a>
                <a href="#candidaturas" class="menu-item">
                    <i data-lucide="send"></i>
                    Candidaturas
                </a>
                <a href="../vagas.php" class="menu-item">
                    <i data-lucide="search"></i>
                    Explorar Vagas
                </a>
            </div>
        </aside>

        <!-- CONTENT AREA -->
        <main class="content-area">

            <!-- Alerts -->
            <?php if ($sucesso): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle"></i>
                    <?php echo htmlspecialchars($sucesso); ?>
                </div>
            <?php endif; ?>

            <!-- Navegação por Abas (visual) -->
            <div class="tabs" role="tablist" aria-label="Seções do Perfil" style="margin-bottom: 16px;">
                <a href="#dados-pessoais" class="tab top-tab" role="tab" aria-selected="true">Dados Pessoais</a>
                <a href="#documentos" class="tab top-tab" role="tab" aria-selected="false">Documentos</a>
                <a href="#experiencia" class="tab top-tab" role="tab" aria-selected="false">Experiência</a>
                <a href="#formacao" class="tab top-tab" role="tab" aria-selected="false">Formação</a>
            </div>

            <?php if ($erro): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle"></i>
                    <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>

            <!-- Dados Pessoais -->
            <div class="section-card" id="dados-pessoais">
                <div class="section-header">
                    <h2 class="section-title">
                        <i data-lucide="user"></i>
                        Dados Pessoais
                    </h2>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <!-- Documentos -->
                    <div class="form-row" id="documentos">
                        <!-- Upload Foto -->
                        <div class="form-group">
                            <label class="form-label">Foto de Perfil</label>
                            <label for="foto_perfil" class="file-upload-area <?php echo !empty($candidato['foto_perfil']) ? 'has-file' : ''; ?>">
                                <div class="icon-badge"><i data-lucide="image-up"></i></div>
                                <div class="file-upload-text">Clique para escolher foto</div>
                                <div class="file-upload-hint">JPG, JPEG ou PNG • Máx. 5MB</div>
                            </label>
                            <input type="file" id="foto_perfil" name="foto_perfil" class="file-upload-input" accept="image/*">
                            <?php if (!empty($candidato['foto_perfil'])): ?>
                            <div class="current-file">
                                <div class="current-file-info">
                                    <i data-lucide="check-circle"></i>
                                    Foto atual carregada
                                </div>
                                <div class="file-actions">
                                    <a href="?deletar_foto=1" class="btn-remove-file" id="btn-deletar-foto">
                                        <i data-lucide="trash-2"></i>
                                        Remover
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Upload CV -->
                        <div class="form-group">
                            <label class="form-label">Currículo (PDF)</label>
                            <label for="cv_pdf" class="file-upload-area <?php echo !empty($candidato['cv_pdf']) ? 'has-file' : ''; ?>">
                                <div class="icon-badge"><i data-lucide="file-up"></i></div>
                                <div class="file-upload-text">Clique para escolher CV</div>
                                <div class="file-upload-hint">Apenas PDF • Máx. 10MB</div>
                            </label>
                            <input type="file" id="cv_pdf" name="cv_pdf" class="file-upload-input" accept=".pdf">
                            <?php if (!empty($candidato['cv_pdf'])): ?>
                            <div class="current-file">
                                <div class="current-file-info">
                                    <i data-lucide="check-circle"></i>
                                    CV atual carregado
                                </div>
                                <div class="file-actions">
                                    <a href="../uploads/cv/<?php echo htmlspecialchars($candidato['cv_pdf']); ?>" 
                                       target="_blank" class="btn-download-file">
                                        <i data-lucide="download"></i>
                                        Baixar
                                    </a>
                                    <a href="?deletar_cv=1" class="btn-remove-file" id="btn-deletar-cv">
                                        <i data-lucide="trash-2"></i>
                                        Remover
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nome_completo" class="form-label">Nome Completo *</label>
                        <input type="text" id="nome_completo" name="nome_completo" class="form-input" 
                               value="<?php echo htmlspecialchars($candidato['nome_completo']); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="tel" id="telefone" name="telefone" class="form-input" 
                                   value="<?php echo htmlspecialchars($candidato['telefone'] ?? ''); ?>" 
                                   placeholder="+258 XX XXX XXXX">
                        </div>
                        
                        <div class="form-group">
                            <label for="localizacao" class="form-label">Localização</label>
                            <input type="text" id="localizacao" name="localizacao" class="form-input" 
                                   value="<?php echo htmlspecialchars($candidato['localizacao'] ?? ''); ?>" 
                                   placeholder="Ex: Maputo, Moçambique">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="competencias" class="form-label">Competências / Habilidades</label>
                        <textarea id="competencias" name="competencias" class="form-textarea" 
                                  placeholder="Ex: PHP, JavaScript, Gestão de Projectos, Comunicação..."><?php echo htmlspecialchars($candidato['competencias'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" name="atualizar_perfil" class="btn-submit">
                        Guardar Alterações
                    </button>
                </form>
            </div>

            <!-- Experiência Profissional -->
            <div class="section-card" id="experiencia">
                <div class="section-header">
                    <h2 class="section-title">
                        <i data-lucide="briefcase"></i>
                        Experiência Profissional
                    </h2>
                    <button class="btn-add" onclick="openModal('modalExperiencia')">
                        <i data-lucide="plus"></i>
                        Adicionar
                    </button>
                </div>
                
                <?php if (count($experiencias) > 0): ?>
                    <?php foreach ($experiencias as $exp): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-header">
                                <div class="timeline-content">
                                    <h3 class="timeline-title"><?php echo htmlspecialchars($exp['cargo']); ?></h3>
                                    <p class="timeline-subtitle"><?php echo htmlspecialchars($exp['empresa']); ?></p>
                                    <p class="timeline-date">
                                        <?php echo formatarData($exp['data_inicio']); ?> - <?php echo formatarData($exp['data_fim']); ?>
                                    </p>
                                    <?php if (!empty($exp['descricao'])): ?>
                                        <p class="timeline-description"><?php echo nl2br(htmlspecialchars($exp['descricao'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                <a href="?deletar_exp=<?php echo $exp['id']; ?>" 
                                   class="btn-delete btn-delete-exp" 
                                   data-exp-id="<?php echo $exp['id']; ?>"
                                   data-cargo="<?php echo htmlspecialchars($exp['cargo']); ?>"
                                   data-empresa="<?php echo htmlspecialchars($exp['empresa']); ?>">
                                    Remover
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="briefcase" class="empty-state-icon"></i>
                        <p class="empty-state-title">Nenhuma experiência cadastrada</p>
                        <p class="empty-state-text">Adicione suas experiências profissionais para fortalecer seu perfil</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Formação Académica -->
            <div class="section-card" id="formacao">
                <div class="section-header">
                    <h2 class="section-title">
                        <i data-lucide="graduation-cap"></i>
                        Formação Académica
                    </h2>
                    <button class="btn-add" onclick="openModal('modalFormacao')">
                        <i data-lucide="plus"></i>
                        Adicionar
                    </button>
                </div>
                
                <?php if (count($formacoes) > 0): ?>
                    <?php foreach ($formacoes as $form): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-header">
                                <div class="timeline-content">
                                    <h3 class="timeline-title"><?php echo htmlspecialchars($form['curso']); ?></h3>
                                    <p class="timeline-subtitle"><?php echo htmlspecialchars($form['instituicao']); ?></p>
                                    <?php if (!empty($form['grau'])): ?>
                                        <p class="timeline-subtitle"><?php echo htmlspecialchars($form['grau']); ?></p>
                                    <?php endif; ?>
                                    <p class="timeline-date">
                                        <?php echo formatarData($form['data_inicio']); ?> - <?php echo formatarData($form['data_fim']); ?>
                                    </p>
                                </div>
                                <a href="?deletar_form=<?php echo $form['id']; ?>" 
                                   class="btn-delete btn-delete-form" 
                                   data-form-id="<?php echo $form['id']; ?>"
                                   data-curso="<?php echo htmlspecialchars($form['curso']); ?>"
                                   data-instituicao="<?php echo htmlspecialchars($form['instituicao']); ?>">
                                    Remover
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="graduation-cap" class="empty-state-icon"></i>
                        <p class="empty-state-title">Nenhuma formação cadastrada</p>
                        <p class="empty-state-text">Adicione sua formação académica para enriquecer seu perfil</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Candidaturas Recentes -->
            <div class="section-card" id="candidaturas">
                <div class="section-header">
                    <h2 class="section-title">
                        <i data-lucide="send"></i>
                        Candidaturas Recentes
                    </h2>
                    <a href="candidaturas.php" class="btn-add">
                        Ver Todas
                    </a>
                </div>
                
                <?php if (count($candidaturas) > 0): ?>
                    <?php foreach ($candidaturas as $cand): ?>
                        <div class="candidatura-card">
                            <div class="candidatura-header">
                                <div>
                                    <h3 class="candidatura-title"><?php echo htmlspecialchars($cand['titulo']); ?></h3>
                                    <p class="candidatura-company"><?php echo htmlspecialchars($cand['nome_empresa']); ?></p>
                                </div>
                                <span class="status-badge" style="background: <?php echo getCorEstado($cand['estado']); ?>;">
                                    <?php echo traduzirEstado($cand['estado']); ?>
                                </span>
                            </div>
                            <div class="candidatura-meta">
                                <span>
                                    <i data-lucide="briefcase"></i>
                                    <?php echo htmlspecialchars($cand['area']); ?>
                                </span>
                                <span>
                                    <i data-lucide="map-pin"></i>
                                    <?php echo htmlspecialchars($cand['localizacao']); ?>
                                </span>
                                <span>
                                    <i data-lucide="calendar"></i>
                                    <?php echo date('d/m/Y', strtotime($cand['data_candidatura'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="inbox" class="empty-state-icon"></i>
                        <p class="empty-state-title">Nenhuma candidatura encontrada</p>
                        <p class="empty-state-text">Explore as vagas disponíveis e candidate-se!</p>
                        <a href="../vagas.php" class="btn-add" style="margin-top: 16px;">
                            <i data-lucide="search"></i>
                            Explorar Vagas
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        </main>

    </div>

    <!-- ==========================================
         🦶 FOOTER PROFISSIONAL - Oxford Blue
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
                    <a href="perfil.php" class="footer-link">Meu Perfil</a>
                    <a href="candidaturas.php" class="footer-link">Candidaturas</a>
                </div>
                <?php endif; ?>

                <?php 
                // Mostrar "Para Empresas" apenas se não for candidato
                if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'candidato'): 
                ?>
                <div class="footer-column">
                    <h4>Para Empresas</h4>
                    <a href="../empresa/dashboard.php" class="footer-link">Dashboard</a>
                    <a href="../empresa/criar_vaga.php" class="footer-link">Publicar Vaga</a>
                    <a href="../empresa/candidaturas.php" class="footer-link">Candidatos</a>
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
         📋 MODAL EXPERIÊNCIA
    ========================================== -->
    <div class="modal-overlay" id="modalExperiencia">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Adicionar Experiência</h3>
                <button class="modal-close" onclick="closeModal('modalExperiencia')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="cargo" class="form-label">Cargo *</label>
                        <input type="text" id="cargo" name="cargo" class="form-input" 
                               placeholder="Ex: Desenvolvedor Full Stack" required>
                    </div>
                    <div class="form-group">
                        <label for="empresa" class="form-label">Empresa *</label>
                        <input type="text" id="empresa" name="empresa" class="form-input" 
                               placeholder="Ex: Empresa XYZ" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="data_inicio" class="form-label">Data Início</label>
                            <input type="month" id="data_inicio" name="data_inicio" class="form-input" 
                                   max="<?php echo date('Y-m'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="data_fim" class="form-label">Data Fim</label>
                            <input type="month" id="data_fim" name="data_fim" class="form-input" 
                                   max="<?php echo date('Y-m'); ?>">
                            <small style="font-size: 12px; color: var(--text-light);">Deixe em branco se ainda trabalha aqui</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea id="descricao" name="descricao" class="form-textarea" 
                                  placeholder="Descreva suas responsabilidades e conquistas..."></textarea>
                    </div>
                    <button type="submit" name="adicionar_experiencia" class="btn-submit">
                        Adicionar Experiência
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ==========================================
         📋 MODAL FORMAÇÃO
    ========================================== -->
    <div class="modal-overlay" id="modalFormacao">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Adicionar Formação</h3>
                <button class="modal-close" onclick="closeModal('modalFormacao')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="curso" class="form-label">Curso *</label>
                        <input type="text" id="curso" name="curso" class="form-input" 
                               placeholder="Ex: Engenharia Informática" required>
                    </div>
                    <div class="form-group">
                        <label for="instituicao" class="form-label">Instituição *</label>
                        <input type="text" id="instituicao" name="instituicao" class="form-input" 
                               placeholder="Ex: Universidade Eduardo Mondlane" required>
                    </div>
                    <div class="form-group">
                        <label for="grau" class="form-label">Grau</label>
                        <input type="text" id="grau" name="grau" class="form-input" 
                               placeholder="Ex: Licenciatura, Mestrado, Doutoramento">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="data_inicio_form" class="form-label">Data Início</label>
                            <input type="month" id="data_inicio_form" name="data_inicio_form" class="form-input">
                        </div>
                        <div class="form-group">
                            <label for="data_fim_form" class="form-label">Data Fim</label>
                            <input type="month" id="data_fim_form" name="data_fim_form" class="form-input">
                            <small style="font-size: 12px; color: var(--text-light);">Deixe em branco se ainda está a estudar</small>
                        </div>
                    </div>
                    <button type="submit" name="adicionar_formacao" class="btn-submit">
                        Adicionar Formação
                    </button>
                </form>
            </div>
        </div>
    </div>

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

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    closeModal(modal.id);
                });
            }
        });

        // File upload feedback
        document.querySelectorAll('.file-upload-input').forEach(input => {
            input.addEventListener('change', function(e) {
                const label = document.querySelector(`label[for="${this.id}"]`);
                if (this.files.length > 0) {
                    label.classList.add('has-file');
                    const textElement = label.querySelector('.file-upload-text');
                    if (textElement) {
                        textElement.textContent = this.files[0].name;
                    }
                }
            });
        });

        // Smooth scroll to sections
        function activateTab(link){
            document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
            if (link && link.classList.contains('tab')) link.classList.add('active');
        }
        document.querySelectorAll('.menu-item[href^="#"], .top-tab[href^="#"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    const offset = 120; // Header height + some space
                    const targetPosition = targetElement.offsetTop - offset;
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });

                    // Update active menu item
                    document.querySelectorAll('.menu-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    this.classList.add('active');

                    // Update top tabs
                    activateTab(this);
                }
            });
        });

        // Reinitialize Lucide icons after dynamic content
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
        });
    </script>

    <!-- 🎭 Sistema de Modal de Confirmação -->
    <link rel="stylesheet" href="../assets/css/modal-confirm.css">
    <script src="../assets/js/modal-confirm.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Configurando botões de deletar...');
            
            // Aguardar um pouco para garantir que o modal foi carregado
            setTimeout(function() {
                // Botão deletar foto
                const btnDeletarFoto = document.getElementById('btn-deletar-foto');
                if (btnDeletarFoto) {
                    console.log('Botão deletar foto encontrado');
                    btnDeletarFoto.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('Clicou em deletar foto');
                        
                        if (typeof window.confirmModal === 'function') {
                            console.log('📞 Chamando window.confirmModal...');
                            window.confirmModal({
                                type: 'warning',
                                title: 'Remover Foto de Perfil',
                                message: 'Tem certeza que deseja remover sua foto de perfil?',
                                confirmText: 'Sim, Remover',
                                cancelText: 'Cancelar',
                                confirmIcon: 'trash-2',
                                onConfirm: function() {
                                    console.log('🎯 Callback onConfirm executado! Redirecionando...');
                                    window.location.href = '?deletar_foto=1'; 
                                }
                            });
                            console.log('✅ confirmModal foi chamado');
                        } else {
                            console.error('❌ window.confirmModal não está disponível!');
                            alert('Sistema de confirmação não disponível. Recarregue a página.');
                        }
                    });
                }

                // Botão deletar CV
                const btnDeletarCV = document.getElementById('btn-deletar-cv');
                if (btnDeletarCV) {
                    console.log('Botão deletar CV encontrado');
                    btnDeletarCV.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('Clicou em deletar CV');
                        
                        if (typeof window.confirmModal === 'function') {
                            window.confirmModal({
                                type: 'warning',
                                title: 'Remover Currículo (CV)',
                                message: 'Tem certeza que deseja remover seu CV?',
                                confirmText: 'Sim, Remover',
                                cancelText: 'Cancelar',
                                confirmIcon: 'file-x',
                                onConfirm: function() { 
                                    window.location.href = '?deletar_cv=1'; 
                                }
                            });
                        } else {
                            alert('Sistema de confirmação não disponível. Recarregue a página.');
                        }
                    });
                }

                // Botões deletar experiência
                document.querySelectorAll('.btn-delete-exp').forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const expId = this.getAttribute('data-exp-id');
                        const cargo = this.getAttribute('data-cargo');
                        const empresa = this.getAttribute('data-empresa');
                        
                        if (typeof window.confirmModal === 'function') {
                            window.confirmModal({
                                type: 'warning',
                                title: 'Remover Experiência',
                                message: 'Tem certeza que deseja remover esta experiência?',
                                highlightTitle: 'Cargo: ' + cargo,
                                highlightText: 'Empresa: ' + empresa,
                                confirmText: 'Sim, Remover',
                                cancelText: 'Cancelar',
                                confirmIcon: 'trash-2',
                                onConfirm: function() { 
                                    window.location.href = '?deletar_exp=' + expId; 
                                }
                            });
                        } else {
                            if (confirm('Remover experiência: ' + cargo + '?')) {
                                window.location.href = '?deletar_exp=' + expId;
                            }
                        }
                    });
                });

                // Botões deletar formação
                document.querySelectorAll('.btn-delete-form').forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const formId = this.getAttribute('data-form-id');
                        const curso = this.getAttribute('data-curso');
                        const instituicao = this.getAttribute('data-instituicao');
                        
                        if (typeof window.confirmModal === 'function') {
                            window.confirmModal({
                                type: 'warning',
                                title: 'Remover Formação',
                                message: 'Tem certeza que deseja remover esta formação?',
                                highlightTitle: 'Curso: ' + curso,
                                highlightText: 'Instituição: ' + instituicao,
                                confirmText: 'Sim, Remover',
                                cancelText: 'Cancelar',
                                confirmIcon: 'trash-2',
                                onConfirm: function() { 
                                    window.location.href = '?deletar_form=' + formId; 
                                }
                            });
                        } else {
                            if (confirm('Remover formação: ' + curso + '?')) {
                                window.location.href = '?deletar_form=' + formId;
                            }
                        }
                    });
                });
                
                console.log('✅ Todos os botões configurados');
            }, 300);
        });
    </script>
    
    <!-- 🔒 Proteção de Sessão - Encerra ao fechar aba -->
    <script src="../assets/js/session-guard.js"></script>

</body>
</html>
