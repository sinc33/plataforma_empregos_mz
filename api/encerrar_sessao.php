<?php
/**
 * API para encerrar sessão ao fechar aba
 * Plataforma Emprego MZ
 */

require_once '../config/session.php';

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Apagar o cookie de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Retornar resposta JSON
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Sessão encerrada']);
exit;
?>







