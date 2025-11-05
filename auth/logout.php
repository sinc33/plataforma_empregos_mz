<?php
require_once '../config/session.php';  // Configuração de sessão não-persistente

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

// Redirecionar para a página inicial
header("Location: ../index.php");
exit;
?>