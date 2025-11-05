<?php
header('Content-Type: application/json');
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidato') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$field = $data['field'] ?? '';
$value = $data['value'] ?? '';

$allowed = [
    'nome_completo' => ['min' => 3, 'max' => 150],
    'telefone' => ['min' => 0, 'max' => 20],
    'localizacao' => ['min' => 0, 'max' => 100],
    'competencias' => ['min' => 0, 'max' => 500],
];

if (!array_key_exists($field, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Campo inválido.']);
    exit;
}

// Validações básicas
$value = trim((string)$value);
$len = strlen($value);
$rules = $allowed[$field];
if ($len < $rules['min'] || $len > $rules['max']) {
    echo json_encode(['success' => false, 'message' => 'Tamanho inválido para o campo.']);
    exit;
}

if ($field === 'nome_completo' && !preg_match("/^[a-zA-ZÀ-ÿ\s'-]+$/u", $value)) {
    echo json_encode(['success' => false, 'message' => 'Nome inválido.']);
    exit;
}
if ($field === 'telefone' && $value !== '') {
    $clean = preg_replace('/[\s\-()]+/','', str_replace('+','', $value));
    if (!preg_match('/^(258)?[0-9]{9,12}$/', $clean)) {
        echo json_encode(['success' => false, 'message' => 'Telefone inválido.']);
        exit;
    }
}

try {
    $pdo = getPDO();
    $sql = "UPDATE candidato SET $field = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$value, $_SESSION['user_id']]);
    // Atualiza session name se necessário
    if ($field === 'nome_completo') {
        $_SESSION['user_name'] = $value;
    }
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('Perfil autosave error: '.$e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao guardar.']);
}

