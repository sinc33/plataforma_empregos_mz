<?php
header('Content-Type: application/json');
require_once '../config/db.php';

// Pegar termo de busca
$termo = $_GET['q'] ?? '';

if (strlen($termo) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Buscar títulos de vagas que correspondem
    $sql = "SELECT DISTINCT titulo 
            FROM vaga 
            WHERE ativa = TRUE 
            AND data_expiracao >= CURDATE()
            AND titulo LIKE :termo 
            ORDER BY data_publicacao DESC 
            LIMIT 8";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['termo' => '%' . $termo . '%']);
    $resultados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode($resultados);
    
} catch (Exception $e) {
    error_log("Erro ao buscar sugestões: " . $e->getMessage());
    echo json_encode([]);
}


