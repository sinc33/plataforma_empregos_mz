<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$cargo = $_GET['cargo'] ?? '';
$provincia = $_GET['provincia'] ?? '';

try {
    $sql = "SELECT COUNT(*) as total 
            FROM vaga v
            WHERE v.ativa = TRUE 
            AND v.data_expiracao >= CURDATE()";
    
    $params = [];
    
    // Filtro por cargo
    if (!empty($cargo)) {
        $sql .= " AND v.titulo LIKE :cargo";
        $params['cargo'] = '%' . $cargo . '%';
    }
    
    // Filtro por província
    if (!empty($provincia)) {
        $sql .= " AND v.localizacao = :provincia";
        $params['provincia'] = $provincia;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'total' => (int)$total
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao contar vagas: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'total' => 0
    ]);
}


