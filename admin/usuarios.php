<?php
session_start();
require_once '../config/db.php';
require_once '../config/admin_functions.php';

// Verificar se é admin logado
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$pdo = getPDO();

// ========================================
// PROCESSAR AÇÕES ADMINISTRATIVAS
// ========================================
$sucesso = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    try {
        switch ($acao) {
            case 'ativar_usuario':
                $usuario_id = (int)$_POST['usuario_id'];
                
                // Buscar dados do usuário para o log
                $stmt_info = $pdo->prepare("SELECT email, tipo FROM utilizador WHERE id = ?");
                $stmt_info->execute([$usuario_id]);
                $usuario_info = $stmt_info->fetch();
                
                $stmt = $pdo->prepare("UPDATE utilizador SET ativo = TRUE WHERE id = ?");
                $stmt->execute([$usuario_id]);
                
                // Registrar log de auditoria
                logAdminAction($pdo, $admin_id, 'ACTIVATE', 'utilizador', $usuario_id, 
                    "Ativou usuário: {$usuario_info['email']} ({$usuario_info['tipo']})");
                
                $sucesso = "Usuário ativado com sucesso!";
                break;
                
            case 'desativar_usuario':
                $usuario_id = (int)$_POST['usuario_id'];
                
                // Buscar dados do usuário para o log
                $stmt_info = $pdo->prepare("SELECT email, tipo FROM utilizador WHERE id = ?");
                $stmt_info->execute([$usuario_id]);
                $usuario_info = $stmt_info->fetch();
                
                $stmt = $pdo->prepare("UPDATE utilizador SET ativo = FALSE WHERE id = ?");
                $stmt->execute([$usuario_id]);
                
                // Registrar log de auditoria
                logAdminAction($pdo, $admin_id, 'DEACTIVATE', 'utilizador', $usuario_id, 
                    "Desativou usuário: {$usuario_info['email']} ({$usuario_info['tipo']})");
                
                $sucesso = "Usuário desativado com sucesso!";
                break;
                
            case 'excluir_usuario':
                $usuario_id = (int)$_POST['usuario_id'];
                
                try {
                    // Buscar dados do usuário
                    $stmt_tipo = $pdo->prepare("SELECT email, tipo FROM utilizador WHERE id = ?");
                    $stmt_tipo->execute([$usuario_id]);
                    $usuario_info = $stmt_tipo->fetch();
                    $tipo = $usuario_info['tipo'] ?? null;
                    
                    if (!$tipo) {
                        $erro = "Usuário não encontrado.";
                        break;
                    }
                    
                    // Verificar se é empresa com vagas ativas
                    if ($tipo === 'empresa') {
                        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM vaga WHERE empresa_id = ? AND ativa = TRUE");
                        $stmt_check->execute([$usuario_id]);
                        $tem_vagas_ativas = $stmt_check->fetchColumn() > 0;
                        
                        if ($tem_vagas_ativas) {
                            $erro = "❌ Não é possível excluir empresa com vagas ativas. Desative as vagas primeiro.";
                            break;
                        }
                    }
                    
                    // Verificar se é candidato com candidaturas
                    if ($tipo === 'candidato') {
                        $stmt_check2 = $pdo->prepare("SELECT COUNT(*) FROM candidatura WHERE candidato_id = ?");
                        $stmt_check2->execute([$usuario_id]);
                        $tem_candidaturas = $stmt_check2->fetchColumn() > 0;
                        
                        if ($tem_candidaturas) {
                            $erro = "❌ Não é possível excluir candidato com candidaturas registradas. Exclua as candidaturas primeiro.";
                            break;
                        }
                    }
                    
                    // Iniciar transação
                    $pdo->beginTransaction();
                    
                    // Excluir dados específicos do tipo de usuário
                    if ($tipo === 'candidato') {
                        // Excluir experiências
                        $stmt_exp = $pdo->prepare("DELETE FROM experiencia WHERE candidato_id = ?");
                        $stmt_exp->execute([$usuario_id]);
                        
                        // Excluir formações
                        $stmt_form = $pdo->prepare("DELETE FROM formacao WHERE candidato_id = ?");
                        $stmt_form->execute([$usuario_id]);
                        
                        // Excluir candidato
                        $stmt_cand = $pdo->prepare("DELETE FROM candidato WHERE id = ?");
                        $stmt_cand->execute([$usuario_id]);
                        
                    } elseif ($tipo === 'empresa') {
                        // Verificar se tem vagas (mesmo inativas, precisam ser excluídas antes)
                        $stmt_vagas = $pdo->prepare("SELECT COUNT(*) FROM vaga WHERE empresa_id = ?");
                        $stmt_vagas->execute([$usuario_id]);
                        $total_vagas = $stmt_vagas->fetchColumn();
                        
                        if ($total_vagas > 0) {
                            $pdo->rollBack();
                            $erro = "❌ Empresa possui $total_vagas vaga(s). Exclua as vagas primeiro na página de Gestão de Vagas.";
                            break;
                        }
                        
                        // Excluir empresa
                        $stmt_emp = $pdo->prepare("DELETE FROM empresa WHERE id = ?");
                        $stmt_emp->execute([$usuario_id]);
                    }
                    
                    // Excluir tokens de recuperação de senha (se houver)
                    $stmt_reset = $pdo->prepare("DELETE FROM password_reset WHERE email = (SELECT email FROM utilizador WHERE id = ?)");
                    $stmt_reset->execute([$usuario_id]);
                    
                    // Excluir usuário principal
                    $stmt_user = $pdo->prepare("DELETE FROM utilizador WHERE id = ?");
                    $stmt_user->execute([$usuario_id]);
                    
                    // Confirmar transação
                    $pdo->commit();
                    
                    // Registrar log de auditoria
                    logAdminAction($pdo, $admin_id, 'DELETE', 'utilizador', $usuario_id, 
                        json_encode([
                            'email' => $usuario_info['email'] ?? 'N/A',
                            'tipo' => $tipo,
                            'tinha_candidaturas' => $tem_candidaturas ?? false,
                            'tinha_vagas' => $total_vagas ?? 0
                        ]));
                    
                    $sucesso = "✅ Usuário excluído com sucesso!";
                    
                } catch (PDOException $e) {
                    // Reverter transação em caso de erro
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $erro = "❌ Erro ao excluir usuário. Por favor, tente novamente.";
                    error_log("Erro ao excluir usuário (ID: $usuario_id, Admin: $admin_id): " . $e->getMessage());
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $erro = "❌ Erro inesperado. Por favor, tente novamente.";
                    error_log("Erro inesperado ao excluir usuário: " . $e->getMessage());
                }
                break;
                
            case 'bulk_action':
                $usuarios_ids = $_POST['usuarios_selecionados'] ?? [];
                $bulk_acao = $_POST['bulk_acao'];
                
                if (empty($usuarios_ids)) {
                    $erro = "Nenhum usuário selecionado.";
                } else {
                    $placeholders = str_repeat('?,', count($usuarios_ids) - 1) . '?';
                    
                    switch ($bulk_acao) {
                        case 'ativar':
                            $stmt = $pdo->prepare("UPDATE utilizador SET ativo = TRUE WHERE id IN ($placeholders)");
                            $stmt->execute($usuarios_ids);
                            $sucesso = count($usuarios_ids) . " usuários ativados com sucesso!";
                            break;
                        case 'desativar':
                            $stmt = $pdo->prepare("UPDATE utilizador SET ativo = FALSE WHERE id IN ($placeholders)");
                            $stmt->execute($usuarios_ids);
                            $sucesso = count($usuarios_ids) . " usuários desativados com sucesso!";
                            break;
                    }
                }
                break;
        }
    } catch (PDOException $e) {
        // Só mostrar erro genérico se não houver erro específico já definido
        if (empty($erro)) {
            $erro = "❌ Erro no sistema. Por favor, tente novamente.";
            error_log("Erro PDO em admin/usuarios.php (Admin: $admin_id): " . $e->getMessage());
        }
    } catch (Exception $e) {
        if (empty($erro)) {
            $erro = "❌ Erro inesperado. Por favor, tente novamente.";
            error_log("Erro inesperado em admin/usuarios.php: " . $e->getMessage());
        }
    }
}

// ========================================
// FILTROS, TABS E PAGINAÇÃO
// ========================================
$tab_ativo = $_GET['tab'] ?? 'todos';
$filtro_status = $_GET['status'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_busca = $_GET['busca'] ?? '';
$filtro_localizacao = $_GET['localizacao'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';

$usuarios_por_pagina = 20;
$pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_atual - 1) * $usuarios_por_pagina;

// ========================================
// ESTATÍSTICAS PARA CARDS
// ========================================
$stmt = $pdo->query("SELECT COUNT(*) FROM utilizador");
$total_usuarios = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM utilizador WHERE tipo = 'candidato' AND ativo = 1");
$total_candidatos = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM utilizador WHERE tipo = 'empresa' AND ativo = 1");
$total_empresas = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM utilizador WHERE DATE(data_registo) = CURDATE()");
$novos_hoje = $stmt->fetchColumn();

// Contadores para tabs
$stmt = $pdo->query("SELECT COUNT(*) FROM utilizador WHERE tipo = 'candidato'");
$count_candidatos = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM utilizador WHERE tipo = 'empresa'");
$count_empresas = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM utilizador WHERE ativo = FALSE");
$count_inativos = $stmt->fetchColumn();

// ========================================
// QUERY PRINCIPAL COM JOINS
// ========================================
$sql = "SELECT u.id, u.email, u.tipo, u.data_registo, u.ultimo_login, u.ativo, 
               CASE 
                   WHEN u.tipo = 'candidato' THEN c.nome_completo
                   WHEN u.tipo = 'empresa' THEN e.nome_empresa
               END as nome_display,
               CASE 
                   WHEN u.tipo = 'candidato' THEN c.telefone
                   WHEN u.tipo = 'empresa' THEN NULL
               END as telefone,
               CASE 
                   WHEN u.tipo = 'candidato' THEN c.localizacao
                   WHEN u.tipo = 'empresa' THEN e.localizacao
               END as localizacao,
               CASE 
                   WHEN u.tipo = 'candidato' THEN c.foto_perfil
                   WHEN u.tipo = 'empresa' THEN e.logotipo
               END as foto,
               CASE 
                   WHEN u.tipo = 'candidato' THEN (SELECT COUNT(*) FROM candidatura ca WHERE ca.candidato_id = u.id)
                   WHEN u.tipo = 'empresa' THEN (SELECT COUNT(*) FROM vaga v WHERE v.empresa_id = u.id)
               END as atividade_count
        FROM utilizador u
        LEFT JOIN candidato c ON u.id = c.id AND u.tipo = 'candidato'
        LEFT JOIN empresa e ON u.id = e.id AND u.tipo = 'empresa'";

$where_conditions = [];
$params = [];

// Aplicar filtros por tab
if ($tab_ativo !== 'todos') {
    switch ($tab_ativo) {
        case 'candidatos':
            $where_conditions[] = "u.tipo = 'candidato'";
            break;
        case 'empresas':
            $where_conditions[] = "u.tipo = 'empresa'";
            break;
        case 'inativos':
            $where_conditions[] = "u.ativo = FALSE";
            break;
    }
}

// Aplicar outros filtros
if (!empty($filtro_status)) {
    $where_conditions[] = "u.ativo = ?";
    $params[] = $filtro_status === 'ativo' ? 1 : 0;
}

if (!empty($filtro_tipo)) {
    $where_conditions[] = "u.tipo = ?";
    $params[] = $filtro_tipo;
}

if (!empty($filtro_busca)) {
    $where_conditions[] = "(
        u.email LIKE ? OR 
        (u.tipo = 'candidato' AND c.nome_completo LIKE ?) OR 
        (u.tipo = 'empresa' AND e.nome_empresa LIKE ?)
    )";
    $busca_term = "%$filtro_busca%";
    $params[] = $busca_term;
    $params[] = $busca_term;
    $params[] = $busca_term;
}

if (!empty($filtro_localizacao)) {
    $where_conditions[] = "(
        (u.tipo = 'candidato' AND c.localizacao LIKE ?) OR 
        (u.tipo = 'empresa' AND e.localizacao LIKE ?)
    )";
    $loc_term = "%$filtro_localizacao%";
    $params[] = $loc_term;
    $params[] = $loc_term;
}

if (!empty($filtro_data_inicio)) {
    $where_conditions[] = "u.data_registo >= ?";
    $params[] = $filtro_data_inicio;
}

if (!empty($filtro_data_fim)) {
    $where_conditions[] = "u.data_registo <= ?";
    $params[] = $filtro_data_fim;
}

// Construir WHERE clause
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// Contar total para paginação
$sql_count = preg_replace('/^SELECT.*FROM/', 'SELECT COUNT(*) FROM', $sql);
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $usuarios_por_pagina);

// Adicionar ORDER BY e LIMIT - Ordenar por último login (mais recente primeiro), depois por data de registro
// MySQL não suporta NULLS LAST, então usamos ISNULL para colocar NULLs por último
$sql .= " ORDER BY ISNULL(u.ultimo_login), u.ultimo_login DESC, u.data_registo DESC LIMIT ? OFFSET ?";
$params[] = $usuarios_por_pagina;
$params[] = $offset;

// Executar query principal
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// ========================================
// BUSCAR LOCALIZAÇÕES PARA FILTRO
// ========================================
$stmt = $pdo->query("
    SELECT DISTINCT localizacao FROM (
        SELECT localizacao FROM candidato WHERE localizacao IS NOT NULL
        UNION
        SELECT localizacao FROM empresa WHERE localizacao IS NOT NULL
    ) as all_locations 
    ORDER BY localizacao
");
$localizacoes_disponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ========================================
// FUNÇÕES AUXILIARES
// ========================================
function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

function formatarDataHora($data) {
    return date('d/m/Y H:i', strtotime($data));
}

function tempoDecorrido($data) {
    $agora = time();
    $tempo_post = strtotime($data);
    $diferenca = $agora - $tempo_post;
    
    if ($diferenca < 60) return 'Agora mesmo';
    if ($diferenca < 3600) return floor($diferenca/60) . ' min atrás';
    if ($diferenca < 86400) return floor($diferenca/3600) . ' h atrás';
    if ($diferenca < 2592000) return floor($diferenca/86400) . ' dias atrás';
    if ($diferenca < 31536000) return floor($diferenca/2592000) . ' meses atrás';
    return floor($diferenca/31536000) . ' anos atrás';
}

function getStatusUsuario($ativo) {
    return $ativo ? 
        ['label' => 'Ativo', 'class' => 'status-ativo'] : 
        ['label' => 'Inativo', 'class' => 'status-inativo'];
}

function getTipoUsuario($tipo) {
    return $tipo === 'candidato' ? 
        ['label' => 'Candidato', 'class' => 'tipo-candidato'] : 
        ['label' => 'Empresa', 'class' => 'tipo-empresa'];
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários - Administração</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        /* ========================================
           🎨 RESET & BASE
        ======================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* 🎨 CORES PRINCIPAIS - Nova Identidade Visual Profissional */
            --cor-primaria: #14213d;              /* Oxford Blue */
            --cor-primaria-escura: #0f1a2e;       /* Oxford Blue Dark */
            --cor-primaria-clara: #1e2c47;        /* Oxford Blue Light */
            --cor-primaria-alpha: rgba(20, 33, 61, 0.08);
            
            --cor-secundaria: #fca311;            /* Orange Web */
            --cor-secundaria-hover: #e3940f;      /* Orange Hover */
            --cor-secundaria-alpha: rgba(252, 163, 17, 0.08);
            
            /* Aliases */
            --primary: var(--cor-primaria);
            --primary-dark: var(--cor-primaria-escura);
            --primary-light: var(--cor-primaria-alpha);
            --secondary: var(--cor-secundaria);
            --text: #1A202C;
            --text-medium: #475569;
            --text-light: #64748B;
            --border: #e5e7eb;
            --border-light: #f1f5f9;
            --background: #f8fafc;
            --cor-fundo: #f8fafc;
            --cor-cards: #ffffff;
            --cor-acento: #f1f5ff;
            --white: #FFFFFF;
            --success: #10B981;
            --success-light: rgba(16, 185, 129, 0.08);
            --warning: var(--cor-secundaria);
            --warning-light: var(--cor-secundaria-alpha);
            --error: #EF4444;
            --error-light: rgba(239, 68, 68, 0.08);
            --info: var(--cor-primaria);
            --info-light: var(--cor-primaria-alpha);
            
            /* Admin Executive Colors - usando Oxford Blue */
            --admin-primary: var(--cor-primaria);
            --admin-primary-light: var(--cor-primaria-alpha);
            --admin-dark: #1E293B;
            --admin-darker: var(--cor-primaria-escura);
            --cor-primaria-admin: var(--cor-primaria);
            --cor-primaria-admin-escura: var(--cor-primaria-escura);
            --cor-primaria-admin-clara: var(--cor-primaria-clara);
            --cor-secundaria-admin: var(--cor-secundaria);
            --cor-secundaria-admin-hover: var(--cor-secundaria-hover);
            
            /* === GRADIENTES ADMIN === */
            --gradiente-admin-primario: linear-gradient(135deg, var(--cor-primaria-admin), var(--cor-primaria-admin-clara));
            --gradiente-admin-secundario: linear-gradient(135deg, var(--cor-secundaria-admin), var(--cor-secundaria-admin-hover));
            --gradiente-admin-fundo: linear-gradient(135deg, rgba(20, 33, 61, 0.02), rgba(20, 33, 61, 0.01));
            
            /* === SIDEBAR E NAVEGAÇÃO === */
            --cor-sidebar-admin: var(--cor-primaria-admin);
            --cor-sidebar-hover: rgba(252, 163, 17, 0.1);
            --cor-sidebar-ativo: var(--cor-secundaria-admin);
            --cor-topbar-admin: var(--cor-cards-admin);
            
            /* === BORDAS E SOMBRAS ADMIN === */
            --cor-borda-admin: var(--border);
            --cor-borda-admin-ativa: var(--cor-primaria-admin);
            --sombra-admin-suave: 0 1px 3px rgba(20, 33, 61, 0.05);
            --sombra-admin-card: 0 4px 24px rgba(20, 33, 61, 0.08);
            --sombra-admin-elevada: 0 8px 40px rgba(20, 33, 61, 0.12);
            --sombra-admin-modal: 0 16px 64px rgba(20, 33, 61, 0.20);
            --sombra-admin-button: 0 2px 8px rgba(252, 163, 17, 0.3);
            --sombra-admin-input-focus: 0 0 0 4px rgba(20, 33, 61, 0.1);
            
            /* === TRANSIÇÕES ADMIN === */
            --transicao-admin-suave: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --transicao-admin-elevacao: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            
            /* Typography Scale */
            --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-size-xs: 11px;
            --font-size-sm: 13px;
            --font-size-base: 14px;
            --font-size-md: 16px;
            --font-size-lg: 18px;
            --font-size-xl: 20px;
            --font-size-2xl: 24px;
            --font-size-3xl: 32px;
            --font-size-4xl: 40px;
            --font-weight-normal: 400;
            --font-weight-medium: 500;
            --font-weight-semibold: 600;
            --font-weight-bold: 700;
            --font-weight-extrabold: 800;
            --line-height-tight: 1.25;
            --line-height-normal: 1.5;
            --line-height-relaxed: 1.625;
            
            /* Spacing System - 8px Grid */
            --space-0: 0;
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
            
            /* Border Radius System */
            --radius-sm: 6px;
            --radius: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-2xl: 20px;
            --radius-full: 9999px;
            
            /* Shadow System - Elevation Hierarchy */
            --shadow-xs: 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.08), 0 1px 2px 0 rgba(0, 0, 0, 0.04);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 6px 12px -2px rgba(0, 0, 0, 0.08), 0 3px 7px -3px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.12);
            
            /* Transition */
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: var(--font-primary);
            background: var(--background);
            color: var(--text);
            font-size: var(--font-size-base);
            line-height: var(--line-height-normal);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ==========================================
           🏛️ ADMIN HEADER - Organized & Structured
        ========================================== */
        .admin-header {
            background: var(--gradiente-admin-primario);
            color: var(--white);
            box-shadow: var(--sombra-admin-card);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 2px solid var(--cor-secundaria-admin);
        }
        
        .header-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 var(--space-8);
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
            gap: var(--space-8);
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: var(--space-6);
        }
        
        .admin-logo {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            text-decoration: none;
            color: var(--white);
        }
        
        .admin-logo img {
            height: 40px;
            width: auto;
            /* Removido filtro para manter cores originais do logo */
        }
        
        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: 8px var(--space-4);
            background: rgba(252, 163, 17, 0.15);
            border: 2px solid var(--cor-secundaria-admin);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-extrabold);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            line-height: 1;
            color: var(--cor-secundaria-admin);
            box-shadow: var(--sombra-admin-button);
        }
        
        .admin-badge svg {
            width: 14px;
            height: 14px;
        }
        
        .header-nav {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: 10px var(--space-4);
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
            border-radius: var(--radius-lg);
            transition: var(--transicao-admin-suave);
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }
        
        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--cor-sidebar-hover);
            transition: var(--transicao-admin-suave);
        }
        
        .nav-item:hover::before {
            left: 0;
        }
        
        .nav-item:hover {
            color: var(--white);
        }
        
        .nav-item.active {
            background: var(--cor-sidebar-hover);
            color: var(--white);
            font-weight: var(--font-weight-semibold);
            border-left: 3px solid var(--cor-secundaria-admin);
            padding-left: calc(var(--space-4) - 3px);
        }
        
        .nav-item svg {
            width: 18px;
            height: 18px;
            position: relative;
            z-index: 1;
        }
        
        .nav-item span {
            position: relative;
            z-index: 1;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: var(--space-4);
        }
        
        .admin-user {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-2) var(--space-3);
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
        }
        
        .admin-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--admin-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }
        
        .admin-user-info {
            display: flex;
            flex-direction: column;
        }
        
        .admin-user-name {
            font-size: 14px;
            font-weight: 600;
        }
        
        .admin-user-role {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .btn-logout {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
            background: rgba(239, 68, 68, 0.2);
            color: var(--white);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .btn-logout:hover {
            background: var(--error);
            border-color: var(--error);
        }
        
        .btn-logout svg {
            width: 16px;
            height: 16px;
        }
        
        /* ==========================================
           📍 BREADCRUMB - Clear Navigation Path
        ========================================== */
        .breadcrumb-section {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: var(--space-4) var(--space-8);
        }
        
        .breadcrumb-container {
            max-width: 1440px;
            margin: 0 auto;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: var(--font-size-sm);
            color: var(--text-light);
            font-weight: var(--font-weight-medium);
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .breadcrumb a:hover {
            color: var(--primary-dark);
        }
        
        .breadcrumb svg {
            width: 16px;
            height: 16px;
        }

        /* ==========================================
           📊 MAIN CONTENT & OVERVIEW CARDS
        ========================================== */
        .main-content {
            flex: 1;
            max-width: 1440px;
            margin: 0 auto;
            padding: var(--space-8);
            width: 100%;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 8px;
        }

        .page-description {
            color: var(--text-light);
            font-size: 16px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .stat-icon.total { background: var(--primary); }
        .stat-icon.candidatos { background: var(--success); }
        .stat-icon.empresas { background: var(--admin-primary); }
        .stat-icon.novos { background: var(--warning); }

        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            font-weight: 500;
        }

        /* ========================================
           🏷️ SISTEMA DE TABS
        ======================================== */
        .tabs-section {
            margin-bottom: 24px;
        }

        .tabs-nav {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 8px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            display: flex;
            gap: 4px;
            overflow-x: auto;
        }

        .tab-btn {
            padding: 12px 16px;
            border: none;
            background: transparent;
            border-radius: calc(var(--border-radius) - 4px);
            font-size: 14px;
            font-weight: 600;
            color: var(--text-light);
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            background: var(--background);
            color: var(--text);
        }

        .tab-btn.active {
            background: var(--primary);
            color: white;
        }

        .tab-count {
            background: rgba(255, 255, 255, 0.2);
            color: currentColor;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .tab-btn.active .tab-count {
            background: rgba(255, 255, 255, 0.3);
        }

        /* ========================================
           🔍 FILTROS
        ======================================== */
        .filters-section {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .filters-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .filters-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }

        .filter-input,
        .filter-select {
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: var(--border-radius);
            font-size: 14px;
            font-family: var(--font-principal);
            background: var(--white);
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .filters-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--background);
        }

        /* ========================================
           📋 TABELA PRINCIPAL
        ======================================== */
        .table-section {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        .bulk-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .bulk-select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--border-radius);
            font-size: 14px;
        }

        .table-container {
            overflow-x: auto;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            background: var(--background);
            padding: 16px 12px;
            text-align: left;
            font-size: var(--font-size-base);
            font-weight: var(--font-weight-semibold);
            color: var(--text);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .users-table td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border);
            font-size: var(--font-size-base);
        }

        .users-table tbody tr:hover {
            background: var(--background);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
            flex-shrink: 0;
        }

        .user-avatar-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.3);
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .user-avatar-placeholder i {
            width: 24px;
            height: 24px;
            color: white;
        }

        .user-details h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 2px;
        }

        .user-details p {
            font-size: 12px;
            color: var(--text-light);
        }

        .tipo-badge {
            padding: 6px 12px;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: var(--space-1);
        }

        .tipo-candidato {
            background: var(--success-light);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .tipo-empresa {
            background: var(--admin-primary-light);
            color: var(--admin-primary);
            border: 1px solid rgba(111, 66, 193, 0.2);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-ativo {
            background: var(--success-light);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-inativo {
            background: var(--error-light);
            color: var(--error);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .atividade-count {
            color: var(--primary);
            font-weight: 600;
        }

        .table-actions {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 4px;
        }

        .btn-success {
            background: var(--success);
            color: white;
            border: none;
        }
        
        .btn-success:hover {
            background: #0F9876;
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn-danger {
            background: var(--error);
            color: white;
            border: none;
        }
        
        .btn-danger:hover {
            background: #DC2626;
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn-warning {
            background: var(--warning);
            color: white;
            border: none;
        }
        
        .btn-warning:hover {
            background: #D97706;
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn-info {
            background: var(--info);
            color: white;
            border: none;
        }
        
        .btn-info:hover {
            background: #2563EB;
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        
        .table-actions {
            display: flex;
            gap: var(--space-2);
            align-items: center;
        }

        /* ========================================
           📄 PAGINAÇÃO
        ======================================== */
        .pagination-section {
            padding: 20px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .pagination-info {
            color: var(--text-light);
            font-size: 14px;
        }

        .pagination {
            display: flex;
            gap: 8px;
        }

        .page-link {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text);
            font-size: 14px;
            transition: all 0.2s;
        }

        .page-link:hover {
            background: var(--background);
        }

        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* ========================================
           🔔 NOTIFICAÇÕES
        ======================================== */
        .notification {
            padding: 16px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .notification.success {
            background: #DCFCE7;
            color: #166534;
            border: 1px solid #BBF7D0;
        }

        .notification.error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        /* ==========================================
           🦶 FOOTER - Organized Structure
        ========================================== */
        .footer {
            background: var(--cor-primaria-admin);
            color: rgba(255, 255, 255, 0.95);
            padding: var(--space-12) var(--space-8) var(--space-8);
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .footer-container {
            max-width: 1440px;
            margin: 0 auto;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-10);
            margin-bottom: var(--space-10);
        }
        
        .footer-column h3,
        .footer-column h4 {
            color: #ffffff !important;
            font-size: 17px;
            font-weight: 800;
            margin-bottom: var(--space-5);
            line-height: var(--line-height-tight);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .footer-links {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }
        
        .footer-link {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition-fast);
            font-weight: 500;
            line-height: var(--line-height-relaxed);
        }
        
        .footer-link:hover {
            color: var(--cor-secundaria-admin);
            padding-left: var(--space-2);
            text-decoration: none;
        }
        
        .footer-bottom {
            padding-top: var(--space-8);
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            font-weight: 500;
            line-height: var(--line-height-relaxed);
        }

        /* ==========================================
           📱 RESPONSIVE - Mobile-First Organization
        ========================================== */
        
        /* Tablet */
        @media (max-width: 1024px) {
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-8);
            }
        }

        /* Mobile */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .table-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
            
            .bulk-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .tabs-nav {
                justify-content: flex-start;
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
                gap: var(--space-6);
            }
        }
    </style>
</head>
<body>
    <!-- ==========================================
         🏛️ ADMIN HEADER
    ========================================== -->
    <header class="admin-header">
        <div class="header-container">
            <div class="header-left">
                <a href="../index.php" class="admin-logo">
                    <img src="../assets/images/empregos-logo.svg" alt="Emprego MZ">
                    <span class="admin-badge">
                        <i data-lucide="shield-check"></i>
                        ADMIN
                    </span>
                </a>
                
                <nav class="header-nav">
                    <a href="index.php" class="nav-item">
                        <i data-lucide="layout-dashboard"></i>
                        Dashboard
                    </a>
                    <a href="vagas.php" class="nav-item">
                        <i data-lucide="briefcase"></i>
                        Gestão de Vagas
                    </a>
                    <a href="usuarios.php" class="nav-item active">
                        <i data-lucide="users"></i>
                        Usuários
                    </a>
                    <a href="candidaturas.php" class="nav-item">
                        <i data-lucide="file-check"></i>
                        Candidaturas
                    </a>
                </nav>
            </div>
            
            <div class="header-right">
                <div class="admin-user">
                    <div class="admin-user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin_nome'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div class="admin-user-info">
                        <div class="admin-user-name"><?php echo htmlspecialchars($_SESSION['admin_nome'] ?? 'Administrador'); ?></div>
                        <div class="admin-user-role">Administrador</div>
                    </div>
                </div>
                
                <a href="../auth/logout.php" class="btn-logout">
                    <i data-lucide="log-out"></i>
                    Sair
                </a>
            </div>
        </div>
    </header>

    <!-- ==========================================
         📍 BREADCRUMB
    ========================================== -->
    <div class="breadcrumb-section">
        <div class="breadcrumb-container">
            <div class="breadcrumb">
                <i data-lucide="home"></i>
                <span>Painel Administrativo</span>
                <i data-lucide="chevron-right"></i>
                <a href="index.php">Dashboard</a>
                <i data-lucide="chevron-right"></i>
                <span>Gestão de Usuários</span>
            </div>
        </div>
    </div>

    <!-- ==========================================
         📊 MAIN CONTENT
    ========================================== -->
    <main class="main-content">
        <!-- Cabeçalho da Página -->
        <div class="page-header">
            <h1 class="page-title">Gestão de Usuários</h1>
            <p class="page-description">Controle todos os candidatos e empresas registrados na plataforma</p>
        </div>

        <!-- Notificações -->
        <?php if (!empty($sucesso)): ?>
            <div class="notification success">
                <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
                <?php echo htmlspecialchars($sucesso); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($erro)): ?>
            <div class="notification error">
                <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <!-- Cards de Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon total">
                        <i data-lucide="users" style="width: 24px; height: 24px;"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($total_usuarios); ?></div>
                <div class="stat-label">Total de Usuários</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon candidatos">
                        <i data-lucide="user-check" style="width: 24px; height: 24px;"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($total_candidatos); ?></div>
                <div class="stat-label">Candidatos Ativos</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon empresas">
                        <i data-lucide="building" style="width: 24px; height: 24px;"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($total_empresas); ?></div>
                <div class="stat-label">Empresas Ativas</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon novos">
                        <i data-lucide="user-plus" style="width: 24px; height: 24px;"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($novos_hoje); ?></div>
                <div class="stat-label">Novos Hoje</div>
            </div>
        </div>

        <!-- Tabs de Usuários -->
        <div class="tabs-section" id="filtros-usuarios">
            <div class="tabs-nav">
                <a href="?tab=todos<?php echo !empty($_SERVER['QUERY_STRING']) && strpos($_SERVER['QUERY_STRING'], 'tab=') === false ? '&' . $_SERVER['QUERY_STRING'] : ''; ?>" 
                   class="tab-btn <?php echo $tab_ativo === 'todos' ? 'active' : ''; ?>">
                    <i data-lucide="users" style="width: 16px; height: 16px;"></i>
                    Todos
                    <span class="tab-count"><?php echo number_format($total_usuarios); ?></span>
                </a>
                
                <a href="?tab=candidatos<?php echo !empty($_GET) && !isset($_GET['tab']) ? '&' . http_build_query(array_diff_key($_GET, ['tab' => ''])) : ''; ?>" 
                   class="tab-btn <?php echo $tab_ativo === 'candidatos' ? 'active' : ''; ?>">
                    <i data-lucide="user-check" style="width: 16px; height: 16px;"></i>
                    Candidatos
                    <span class="tab-count"><?php echo number_format($count_candidatos); ?></span>
                </a>
                
                <a href="?tab=empresas<?php echo !empty($_GET) && !isset($_GET['tab']) ? '&' . http_build_query(array_diff_key($_GET, ['tab' => ''])) : ''; ?>" 
                   class="tab-btn <?php echo $tab_ativo === 'empresas' ? 'active' : ''; ?>">
                    <i data-lucide="building" style="width: 16px; height: 16px;"></i>
                    Empresas
                    <span class="tab-count"><?php echo number_format($count_empresas); ?></span>
                </a>
                
                <a href="?tab=inativos<?php echo !empty($_GET) && !isset($_GET['tab']) ? '&' . http_build_query(array_diff_key($_GET, ['tab' => ''])) : ''; ?>" 
                   class="tab-btn <?php echo $tab_ativo === 'inativos' ? 'active' : ''; ?>">
                    <i data-lucide="user-x" style="width: 16px; height: 16px;"></i>
                    Inativos
                    <span class="tab-count"><?php echo number_format($count_inativos); ?></span>
                </a>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <div class="filters-header">
                <h2 class="filters-title">Filtros de Busca</h2>
            </div>
            
            <form method="GET" action="usuarios.php">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab_ativo); ?>">
                
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="">Todos os status</option>
                            <option value="ativo" <?php echo $filtro_status === 'ativo' ? 'selected' : ''; ?>>Ativos</option>
                            <option value="inativo" <?php echo $filtro_status === 'inativo' ? 'selected' : ''; ?>>Inativos</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Tipo</label>
                        <select name="tipo" class="filter-select">
                            <option value="">Todos os tipos</option>
                            <option value="candidato" <?php echo $filtro_tipo === 'candidato' ? 'selected' : ''; ?>>Candidatos</option>
                            <option value="empresa" <?php echo $filtro_tipo === 'empresa' ? 'selected' : ''; ?>>Empresas</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Localização</label>
                        <select name="localizacao" class="filter-select">
                            <option value="">Todas as localizações</option>
                            <?php foreach ($localizacoes_disponiveis as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc); ?>" 
                                        <?php echo $filtro_localizacao === $loc ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Buscar</label>
                        <input type="text" name="busca" class="filter-input" 
                               placeholder="Nome, email ou empresa"
                               value="<?php echo htmlspecialchars($filtro_busca); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Data Início</label>
                        <input type="date" name="data_inicio" class="filter-input"
                               value="<?php echo htmlspecialchars($filtro_data_inicio); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Data Fim</label>
                        <input type="date" name="data_fim" class="filter-input"
                               value="<?php echo htmlspecialchars($filtro_data_fim); ?>">
                    </div>
                </div>

                <div class="filters-actions">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="search" style="width: 16px; height: 16px;"></i>
                        Aplicar Filtros
                    </button>
                    <a href="usuarios.php?tab=<?php echo urlencode($tab_ativo); ?>" class="btn btn-secondary">
                        <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                        Limpar Filtros
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabela de Usuários -->
        <div class="table-section">
            <div class="table-header">
                <h2 class="table-title">
                    Usuários Encontrados (<?php echo number_format($total_registros); ?>)
                </h2>
                
                <form method="POST" class="bulk-actions" id="bulkForm">
                    <input type="hidden" name="acao" value="bulk_action">
                    <select name="bulk_acao" class="bulk-select">
                        <option value="">Ações em lote</option>
                        <option value="ativar">Ativar selecionados</option>
                        <option value="desativar">Desativar selecionados</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary" onclick="return confirmarBulkAction()">
                        Executar
                    </button>
                </form>
            </div>

            <div class="table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes()">
                            </th>
                            <th>ID</th>
                            <th>Usuário</th>
                            <th>Tipo</th>
                            <th>Localização</th>
                            <th>Atividade</th>
                            <th>Registro</th>
                            <th>Último Acesso</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 40px; color: var(--text-light);">
                                    <i data-lucide="users-x" style="width: 48px; height: 48px; margin-bottom: 16px;"></i>
                                    <br>Nenhum usuário encontrado com os filtros aplicados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $usuario): ?>
                                <?php 
                                    $status = getStatusUsuario($usuario['ativo']); 
                                    $tipo = getTipoUsuario($usuario['tipo']);
                                    
                                    // Determinar caminho da foto
                                    $tem_foto = false;
                                    $caminho_foto = '';
                                    
                                    if (!empty($usuario['foto'])) {
                                        // Para candidatos: fotos ficam em uploads/fotos/
                                        if ($usuario['tipo'] === 'candidato' && file_exists('../uploads/fotos/' . $usuario['foto'])) {
                                            $tem_foto = true;
                                            $caminho_foto = '../uploads/fotos/' . htmlspecialchars($usuario['foto']);
                                        }
                                        // Para empresas: logotipos também ficam em uploads/fotos/
                                        elseif ($usuario['tipo'] === 'empresa' && file_exists('../uploads/fotos/' . $usuario['foto'])) {
                                            $tem_foto = true;
                                            $caminho_foto = '../uploads/fotos/' . htmlspecialchars($usuario['foto']);
                                        }
                                    }
                                    
                                    // Foto padrão caso não tenha
                                    if (!$tem_foto) {
                                        $caminho_foto = $usuario['tipo'] === 'candidato' 
                                            ? '../assets/images/candidato-icon.svg' 
                                            : '../assets/images/empresa-default.png';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="usuarios_selecionados[]" 
                                               value="<?php echo $usuario['id']; ?>" 
                                               form="bulkForm" class="usuario-checkbox">
                                    </td>
                                    <td><?php echo $usuario['id']; ?></td>
                                    <td>
                                        <div class="user-info">
                                            <?php if ($tem_foto): ?>
                                                <img src="<?php echo $caminho_foto; ?>" 
                                                     class="user-avatar" 
                                                     alt="<?php echo htmlspecialchars($usuario['nome_display'] ?? 'Usuário'); ?>">
                                            <?php else: ?>
                                                <div class="user-avatar user-avatar-placeholder" 
                                                     style="background: <?php echo $usuario['tipo'] === 'candidato' ? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'; ?>">
                                                    <i data-lucide="<?php echo $usuario['tipo'] === 'candidato' ? 'user' : 'building-2'; ?>" 
                                                       style="width: 24px; height: 24px; color: white;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="user-details">
                                                <h4><?php echo htmlspecialchars($usuario['nome_display'] ?? 'Sem nome'); ?></h4>
                                                <p><?php echo htmlspecialchars($usuario['email']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="tipo-badge <?php echo $tipo['class']; ?>">
                                            <?php echo $tipo['label']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($usuario['localizacao'] ?? 'Não informado'); ?></td>
                                    <td>
                                        <span class="atividade-count">
                                            <?php 
                                            $atividade = $usuario['atividade_count'];
                                            if ($usuario['tipo'] === 'candidato') {
                                                echo $atividade . ' candidatura' . ($atividade != 1 ? 's' : '');
                                            } else {
                                                echo $atividade . ' vaga' . ($atividade != 1 ? 's' : '');
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatarData($usuario['data_registo']); ?></td>
                                    <td>
                                        <?php 
                                        $ultimo_login = $usuario['ultimo_login'] ?? null;
                                        if (!empty($ultimo_login) && $ultimo_login !== null) {
                                            $tempo = tempoDecorrido($ultimo_login);
                                            $data_hora = formatarDataHora($ultimo_login);
                                            echo '<span style="color: var(--text-light); font-size: 12px;" title="' . htmlspecialchars($data_hora) . '">' . htmlspecialchars($tempo) . '</span>';
                                        } else {
                                            echo '<span style="color: var(--text-light); opacity: 0.7; font-size: 12px;" title="Usuário nunca fez login">Nunca</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status['class']; ?>">
                                            <?php echo $status['label']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <?php if ($usuario['ativo']): ?>
                                                <form method="POST" style="display: inline;" 
                                                      class="form-desativar-usuario">
                                                    <input type="hidden" name="acao" value="desativar_usuario">
                                                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning" title="Desativar">
                                                        <i data-lucide="user-x" style="width: 14px; height: 14px;"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;" 
                                                      class="form-ativar-usuario">
                                                    <input type="hidden" name="acao" value="ativar_usuario">
                                                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Ativar">
                                                        <i data-lucide="user-check" style="width: 14px; height: 14px;"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" style="display: inline;" 
                                                  class="form-excluir-usuario"
                                                  data-usuario="<?php echo htmlspecialchars($usuario['nome'] ?? $usuario['email']); ?>">
                                                <input type="hidden" name="acao" value="excluir_usuario">
                                                <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Excluir">
                                                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_paginas > 1): ?>
                <div class="pagination-section">
                    <div class="pagination-info">
                        Exibindo <?php echo min($offset + 1, $total_registros); ?>-<?php echo min($offset + $usuarios_por_pagina, $total_registros); ?> 
                        de <?php echo number_format($total_registros); ?> usuários
                    </div>
                    
                    <nav class="pagination">
                        <?php if ($pagina_atual > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])); ?>" 
                               class="page-link">Anterior</a>
                        <?php endif; ?>

                        <?php
                        $inicio = max(1, $pagina_atual - 2);
                        $fim = min($total_paginas, $pagina_atual + 2);
                        
                        for ($i = $inicio; $i <= $fim; $i++):
                        ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
                               class="page-link <?php echo $i === $pagina_atual ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($pagina_atual < $total_paginas): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])); ?>" 
                               class="page-link">Próxima</a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Inicializar ícones Lucide
        lucide.createIcons();

        // Auto-scroll para a seção de filtros quando mudar de tab ou aplicar filtros
        const urlParams = new URLSearchParams(window.location.search);
        const hasFilters = urlParams.has('tab') || 
                          urlParams.has('status') || 
                          urlParams.has('tipo') || 
                          urlParams.has('busca') || 
                          urlParams.has('localizacao') ||
                          urlParams.has('pagina');
        
        if (hasFilters) {
            setTimeout(() => {
                const filtrosSection = document.getElementById('filtros-usuarios');
                if (filtrosSection) {
                    filtrosSection.scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }, 100);
        }

        // Toggle all checkboxes
        function toggleAllCheckboxes() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.usuario-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        // Confirmar bulk actions
        function confirmarBulkAction() {
            const checkboxes = document.querySelectorAll('.usuario-checkbox:checked');
            const action = document.querySelector('select[name="bulk_acao"]').value;
            
            if (checkboxes.length === 0) {
                alert('Selecione pelo menos um usuário.');
                return false;
            }
            
            if (!action) {
                alert('Selecione uma ação.');
                return false;
            }
            
            const actionText = action === 'ativar' ? 'ativar' : 'desativar';
            const actionTextCapitalized = actionText.charAt(0).toUpperCase() + actionText.slice(1);
            
            confirmModal({
                type: 'bulk',
                title: 'Ação em Massa',
                subtitle: `${checkboxes.length} usuário(s) selecionado(s)`,
                message: `Tem certeza que deseja ${actionText} os ${checkboxes.length} usuários selecionados?`,
                confirmText: `Sim, ${actionTextCapitalized}`,
                confirmIcon: 'users',
                onConfirm: () => {
                    document.getElementById('bulk-form').submit();
                }
            });
            return false;
        }

        // Auto-hide notifications
        setTimeout(() => {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            });
        }, 5000);

        // Preserve tab in form submissions
        document.querySelectorAll('form').forEach(form => {
            if (!form.querySelector('input[name="tab"]')) {
                const currentTab = '<?php echo addslashes($tab_ativo); ?>';
                if (currentTab && currentTab !== 'todos') {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'tab';
                    hiddenInput.value = currentTab;
                    form.appendChild(hiddenInput);
                }
            }
        });
    </script>

    <!-- ==========================================
         🦶 FOOTER
    ========================================== -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-column">
                    <img src="../assets/images/empregos-logo.svg" alt="Emprego MZ" class="footer-logo">
                    <p class="footer-description">
                        Painel administrativo da plataforma líder de empregos em Moçambique.
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
                <div class="footer-column">
                    <h4>Administração</h4>
                    <a href="index.php" class="footer-link">Dashboard</a>
                    <a href="vagas.php" class="footer-link">Gestão de Vagas</a>
                    <a href="usuarios.php" class="footer-link">Gestão de Usuários</a>
                    <a href="candidaturas.php" class="footer-link">Candidaturas</a>
                </div>
                <div class="footer-column">
                    <h4>Plataforma</h4>
                    <a href="../index.php" class="footer-link">Site Principal</a>
                    <a href="../vagas.php" class="footer-link">Vagas</a>
                    <a href="../sobre.php" class="footer-link">Sobre Nós</a>
                    <a href="../contacto.php" class="footer-link">Contacto</a>
                </div>
                <div class="footer-column">
                    <h4>Suporte</h4>
                    <a href="#" class="footer-link">Documentação</a>
                    <a href="#" class="footer-link">Ajuda</a>
                    <a href="#" class="footer-link">Segurança</a>
                    <a href="../auth/logout.php" class="footer-link">Sair</a>
                </div>
            </div>

            <div class="footer-bottom">
                <div>&copy; <?php echo date('Y'); ?> Emprego MZ. Todos os direitos reservados. | Painel Administrativo</div>
            </div>
        </div>
    </footer>

    <!-- 🎭 Sistema de Modal de Confirmação -->
    <link rel="stylesheet" href="../assets/css/modal-confirm.css">
    <script src="../assets/js/modal-confirm.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                // Formulários de desativar usuário
                document.querySelectorAll('.form-desativar-usuario').forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formElement = this;
                        
                        if (typeof window.confirmModal === 'function') {
                            window.confirmModal({
                                type: 'warning',
                                title: 'Desativar Usuário',
                                message: 'O usuário não poderá mais acessar a plataforma. Você pode reativá-lo a qualquer momento.',
                                confirmText: 'Sim, Desativar',
                                confirmIcon: 'user-x',
                                onConfirm: function() { 
                                    formElement.submit(); 
                                }
                            });
                        } else {
                            if (confirm('Desativar este usuário?')) {
                                formElement.submit();
                            }
                        }
                    });
                });

                // Formulários de ativar usuário
                document.querySelectorAll('.form-ativar-usuario').forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formElement = this;
                        
                        if (typeof window.confirmModal === 'function') {
                            window.confirmModal({
                                type: 'warning',
                                title: 'Ativar Usuário',
                                message: 'O usuário poderá acessar a plataforma novamente.',
                                confirmText: 'Sim, Ativar',
                                confirmIcon: 'user-check',
                                onConfirm: function() { 
                                    formElement.submit(); 
                                }
                            });
                        } else {
                            if (confirm('Ativar este usuário?')) {
                                formElement.submit();
                            }
                        }
                    });
                });

                // Formulários de excluir usuário
                document.querySelectorAll('.form-excluir-usuario').forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const usuario = this.getAttribute('data-usuario');
                        const formElement = this;
                        
                        if (typeof window.confirmModal === 'function') {
                            window.confirmModal({
                                type: 'danger',
                                title: 'Excluir Usuário',
                                subtitle: 'Esta ação não pode ser desfeita',
                                message: 'Tem certeza que deseja excluir este usuário permanentemente?',
                                highlightTitle: 'Usuário: ' + usuario,
                                highlightText: 'Todos os dados associados a este usuário serão removidos.',
                                confirmText: 'Sim, Excluir Permanentemente',
                                confirmIcon: 'trash-2',
                                onConfirm: function() { 
                                    formElement.submit(); 
                                }
                            });
                        } else {
                            if (confirm('Excluir usuário: ' + usuario + '?')) {
                                formElement.submit();
                            }
                        }
                    });
                });
            }, 300);
        });
    </script>
    
    <!-- 🔒 Proteção de Sessão - Encerra ao fechar aba -->
    <script src="../assets/js/session-guard.js"></script>

</body>
</html>