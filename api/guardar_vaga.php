<?php
/**
 * ==========================================
 * 📌 API: Guardar/Remover Vaga
 * ==========================================
 * Permite candidatos salvarem vagas de interesse
 * 
 * Método: POST
 * Parâmetros:
 *   - vaga_id (int): ID da vaga
 *   - action (string): 'save' ou 'remove'
 * 
 * Resposta JSON:
 *   - success (bool)
 *   - message (string)
 *   - is_saved (bool)
 */

header('Content-Type: application/json');
session_start();
require_once '../config/db.php';

// Verificar se o usuário está logado e é candidato
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidato') {
    // Calcular o caminho base do projeto
    // SCRIPT_NAME será /plataforma_emprego_mz/api/guardar_vaga.php
    // Precisamos voltar para a raiz: /plataforma_emprego_mz
    $script_path = dirname($_SERVER['SCRIPT_NAME']); // /plataforma_emprego_mz/api
    $base_path = dirname($script_path); // /plataforma_emprego_mz
    $login_url = rtrim($base_path, '/') . '/auth/login.php'; // /plataforma_emprego_mz/auth/login.php
    
    echo json_encode([
        'success' => false,
        'message' => 'Você precisa estar logado como candidato para guardar vagas.',
        'redirect' => $login_url
    ]);
    exit;
}

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido.'
    ]);
    exit;
}

// Obter dados da requisição
$input = json_decode(file_get_contents('php://input'), true);
$vaga_id = isset($input['vaga_id']) ? (int)$input['vaga_id'] : 0;
$action = isset($input['action']) ? $input['action'] : '';

// Validar parâmetros
if ($vaga_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID da vaga inválido.'
    ]);
    exit;
}

if (!in_array($action, ['add', 'remove', 'save'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Ação inválida. Use "add" ou "remove".'
    ]);
    exit;
}

try {
    $pdo = getPDO();
    $candidato_id = $_SESSION['user_id'];
    
    // Verificar se a vaga existe e está ativa
    $stmt = $pdo->prepare("
        SELECT id, titulo 
        FROM vaga 
        WHERE id = ? AND ativa = TRUE AND data_expiracao >= CURDATE()
    ");
    $stmt->execute([$vaga_id]);
    $vaga = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vaga) {
        echo json_encode([
            'success' => false,
            'message' => 'Vaga não encontrada ou não está mais disponível.'
        ]);
        exit;
    }
    
    if ($action === 'add' || $action === 'save') {
        // ==========================================
        // 💾 GUARDAR VAGA
        // ==========================================
        
        // Verificar se já está guardada
        $stmt = $pdo->prepare("
            SELECT id 
            FROM vaga_guardada 
            WHERE candidato_id = ? AND vaga_id = ?
        ");
        $stmt->execute([$candidato_id, $vaga_id]);
        
        if ($stmt->fetch()) {
            echo json_encode([
                'success' => true,
                'message' => 'Esta vaga já estava guardada.',
                'is_saved' => true
            ]);
            exit;
        }
        
        // Guardar vaga
        $stmt = $pdo->prepare("
            INSERT INTO vaga_guardada (candidato_id, vaga_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$candidato_id, $vaga_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Vaga guardada com sucesso! ⭐',
            'is_saved' => true
        ]);
        
    } else {
        // ==========================================
        // 🗑️ REMOVER VAGA
        // ==========================================
        
        $stmt = $pdo->prepare("
            DELETE FROM vaga_guardada 
            WHERE candidato_id = ? AND vaga_id = ?
        ");
        $stmt->execute([$candidato_id, $vaga_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Vaga removida dos guardados.',
                'is_saved' => false
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Vaga não estava guardada.',
                'is_saved' => false
            ]);
        }
    }
    
} catch (PDOException $e) {
    error_log("Erro ao guardar vaga: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar requisição. Tente novamente.'
    ]);
}
?>


