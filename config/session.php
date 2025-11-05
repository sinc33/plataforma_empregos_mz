<?php
/**
 * Configuração de Sessão
 * Plataforma Emprego MZ
 * 
 * Configura sessões para expirarem ao fechar o navegador
 */

// Verificar se a sessão já foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    // Configurar cookie de sessão para expirar ao fechar navegador
    session_set_cookie_params([
        'lifetime' => 0,           // Expira ao fechar navegador
        'path' => '/',             // Disponível em todo o site
        'domain' => '',            // Domínio atual
        'secure' => false,         // true em produção com HTTPS
        'httponly' => true,        // Não acessível via JavaScript
        'samesite' => 'Lax'        // Proteção CSRF
    ]);
    
    // Iniciar a sessão
    session_start();
    
    // Configuração adicional de segurança
    // Regenerar ID da sessão periodicamente para evitar session fixation
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Regenerar ID a cada 30 minutos
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}
?>







