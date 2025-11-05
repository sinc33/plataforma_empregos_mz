<?php
/**
 * ==========================================
 * 📌 API: Obter Vagas Guardadas
 * ==========================================
 * Retorna IDs das vagas guardadas pelo candidato logado
 * 
 * Método: GET
 * Resposta JSON:
 *   - success (bool)
 *   - vagas_ids (array): Array com IDs das vagas guardadas
 */

header('Content-Type: application/json');
session_start();
require_once '../config/db.php';

// Verificar se o usuário está logado e é candidato
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidato') {
    echo json_encode([
        'success' => true,
        'vagas_ids' => []
    ]);
    exit;
}

try {
    $pdo = getPDO();
    $candidato_id = $_SESSION['user_id'];
    
    // Buscar IDs das vagas guardadas
    $stmt = $pdo->prepare("
        SELECT vaga_id 
        FROM vagas_guardadas 
        WHERE candidato_id = ?
        ORDER BY data_guardada DESC
    ");
    $stmt->execute([$candidato_id]);
    
    $vagas_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'vagas_ids' => array_map('intval', $vagas_ids)
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao obter vagas guardadas: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'vagas_ids' => []
    ]);
}
?>


