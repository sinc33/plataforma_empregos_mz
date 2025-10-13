<?php
/**
 * Configuração da Base de Dados
 * Plataforma de Emprego Moçambique
 */

function getPDO() {
    static $pdo = null;
    
    if ($pdo === null) {
        // Configurações da base de dados
        $host = 'localhost';
        $dbname = 'plataforma_emprego_mz';
        $user = 'root';
        $pass = 'admin';
        
        try {
            // Criar conexão PDO
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            // Em produção, logar o erro em vez de exibir
            error_log("Erro na conexão com a base de dados: " . $e->getMessage());
            die("Erro ao conectar à base de dados. Por favor, tente novamente mais tarde.");
        }
    }
    
    return $pdo;
}

/**
 * Função auxiliar para executar queries com segurança
 * 
 * @param string $sql Query SQL com placeholders
 * @param array $params Parâmetros para bind
 * @return PDOStatement
 */
function executarQuery($sql, $params = []) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Erro ao executar query: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Função para buscar um único registro
 * 
 * @param string $sql Query SQL
 * @param array $params Parâmetros
 * @return array|false
 */
function buscarUm($sql, $params = []) {
    $stmt = executarQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Função para buscar múltiplos registros
 * 
 * @param string $sql Query SQL
 * @param array $params Parâmetros
 * @return array
 */
function buscarTodos($sql, $params = []) {
    $stmt = executarQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Função para inserir dados e retornar o último ID
 * 
 * @param string $sql Query SQL de INSERT
 * @param array $params Parâmetros
 * @return int Último ID inserido
 */
function inserir($sql, $params = []) {
    executarQuery($sql, $params);
    return getPDO()->lastInsertId();
}

/**
 * Verificar se a base de dados existe e está acessível
 * 
 * @return bool
 */
function verificarConexao() {
    try {
        getPDO();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>