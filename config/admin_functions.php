<?php
/**
 * FUNÇÕES AUXILIARES PARA ADMINISTRAÇÃO
 * Plataforma Emprego MZ
 * 
 * Funções para logs de auditoria e validações administrativas
 */

/**
 * Registrar ação administrativa nos logs
 * 
 * @param PDO $pdo Conexão com banco de dados
 * @param int $admin_id ID do administrador
 * @param string $acao Ação realizada (DELETE, UPDATE, ACTIVATE, DEACTIVATE, etc)
 * @param string $tabela Tabela afetada
 * @param int|null $registro_id ID do registro afetado
 * @param string|array|null $detalhes Detalhes adicionais (será convertido para JSON se for array)
 * @return bool True se registrou com sucesso, False caso contrário
 */
function logAdminAction($pdo, $admin_id, $acao, $tabela, $registro_id = null, $detalhes = null) {
    try {
        // Converter detalhes para JSON se for array
        if (is_array($detalhes)) {
            $detalhes = json_encode($detalhes, JSON_UNESCAPED_UNICODE);
        }
        
        // Obter IP do usuário
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        
        // Obter User Agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if ($user_agent && strlen($user_agent) > 255) {
            $user_agent = substr($user_agent, 0, 255);
        }
        
        // Inserir log
        $sql = "INSERT INTO admin_logs (admin_id, acao, tabela, registro_id, detalhes, ip, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $admin_id,
            $acao,
            $tabela,
            $registro_id,
            $detalhes,
            $ip,
            $user_agent
        ]);
        
        return true;
        
    } catch (PDOException $e) {
        // Registrar erro no log do servidor, mas não falhar a operação principal
        error_log("Erro ao registrar log de admin: " . $e->getMessage());
        return false;
    }
}

/**
 * Verificar se admin está autenticado
 * 
 * @return bool True se autenticado, False caso contrário
 */
function isAdminAuthenticated() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_nome']);
}

/**
 * Redirecionar para login de admin se não estiver autenticado
 * 
 * @return void
 */
function requireAdminAuth() {
    if (!isAdminAuthenticated()) {
        header("Location: ../auth/admin_login.php");
        exit;
    }
}

/**
 * Obter informações do admin logado
 * 
 * @return array|null Array com id e nome do admin, ou null se não autenticado
 */
function getCurrentAdmin() {
    if (!isAdminAuthenticated()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['admin_id'],
        'nome' => $_SESSION['admin_nome'],
        'email' => $_SESSION['admin_email'] ?? null
    ];
}

/**
 * Validar ID numérico
 * 
 * @param mixed $id ID a ser validado
 * @return int|false ID válido ou false
 */
function validateId($id) {
    if (!is_numeric($id) || $id <= 0) {
        return false;
    }
    return (int)$id;
}

/**
 * Sanitizar string para exibição
 * 
 * @param string $string String a ser sanitizada
 * @return string String sanitizada
 */
function sanitizeOutput($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Formatar data para exibição
 * 
 * @param string $date Data no formato Y-m-d H:i:s
 * @return string Data formatada
 */
function formatDateBR($date) {
    if (empty($date)) {
        return '-';
    }
    
    $timestamp = strtotime($date);
    return date('d/m/Y H:i', $timestamp);
}

/**
 * Gerar mensagem de sucesso para sessão
 * 
 * @param string $message Mensagem de sucesso
 * @return void
 */
function setSuccessMessage($message) {
    $_SESSION['success_message'] = $message;
}

/**
 * Gerar mensagem de erro para sessão
 * 
 * @param string $message Mensagem de erro
 * @return void
 */
function setErrorMessage($message) {
    $_SESSION['error_message'] = $message;
}

/**
 * Obter e limpar mensagem de sucesso
 * 
 * @return string|null Mensagem ou null
 */
function getSuccessMessage() {
    if (isset($_SESSION['success_message'])) {
        $message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        return $message;
    }
    return null;
}

/**
 * Obter e limpar mensagem de erro
 * 
 * @return string|null Mensagem ou null
 */
function getErrorMessage() {
    if (isset($_SESSION['error_message'])) {
        $message = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        return $message;
    }
    return null;
}

/**
 * Verificar se tabela admin_logs existe
 * 
 * @param PDO $pdo Conexão com banco de dados
 * @return bool True se existe, False caso contrário
 */
function adminLogsTableExists($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_logs'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}
?>







