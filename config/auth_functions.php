<?php
/**
 * FUNÇÕES DE AUTENTICAÇÃO E RATE LIMITING
 * Plataforma Emprego MZ
 * 
 * Funções para gerenciar tentativas de login, rate limiting e bloqueios
 */

/**
 * Verificar se identifier (email ou IP) está bloqueado
 * 
 * @param PDO $pdo Conexão com banco
 * @param string $identifier Email ou IP
 * @param string $tipo Tipo: 'email', 'ip', 'admin_email', 'admin_ip'
 * @return array|false Array com info do bloqueio ou false se não bloqueado
 */
function verificarBloqueio($pdo, $identifier, $tipo) {
    try {
        $stmt = $pdo->prepare("
            SELECT tentativas, bloqueado_ate 
            FROM login_attempts 
            WHERE identifier = ? AND tipo = ? AND bloqueado_ate > NOW()
        ");
        $stmt->execute([$identifier, $tipo]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado ?: false;
    } catch (PDOException $e) {
        error_log("Erro ao verificar bloqueio: " . $e->getMessage());
        return false;
    }
}

/**
 * Registrar tentativa de login falhada
 * 
 * @param PDO $pdo Conexão com banco
 * @param string $identifier Email ou IP
 * @param string $tipo Tipo: 'email', 'ip', 'admin_email', 'admin_ip'
 * @param int $max_tentativas Número máximo de tentativas antes de bloquear
 * @param int $minutos_bloqueio Minutos de bloqueio após exceder tentativas
 * @return bool True se foi bloqueado agora, false caso contrário
 */
function registrarTentativaFalhada($pdo, $identifier, $tipo, $max_tentativas = 5, $minutos_bloqueio = 15) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if ($user_agent && strlen($user_agent) > 255) {
            $user_agent = substr($user_agent, 0, 255);
        }
        
        // Verificar se já existe registro
        $stmt = $pdo->prepare("
            SELECT tentativas 
            FROM login_attempts 
            WHERE identifier = ? AND tipo = ?
        ");
        $stmt->execute([$identifier, $tipo]);
        $registro = $stmt->fetch();
        
        if ($registro) {
            // Incrementar tentativas
            $novas_tentativas = $registro['tentativas'] + 1;
            
            // Verificar se deve bloquear
            if ($novas_tentativas >= $max_tentativas) {
                $bloqueado_ate = date('Y-m-d H:i:s', strtotime("+$minutos_bloqueio minutes"));
                
                $stmt = $pdo->prepare("
                    UPDATE login_attempts 
                    SET tentativas = ?, bloqueado_ate = ?, ip = ?, user_agent = ? 
                    WHERE identifier = ? AND tipo = ?
                ");
                $stmt->execute([$novas_tentativas, $bloqueado_ate, $ip, $user_agent, $identifier, $tipo]);
                
                return true; // Foi bloqueado agora
            } else {
                // Apenas incrementar
                $stmt = $pdo->prepare("
                    UPDATE login_attempts 
                    SET tentativas = ?, ip = ?, user_agent = ? 
                    WHERE identifier = ? AND tipo = ?
                ");
                $stmt->execute([$novas_tentativas, $ip, $user_agent, $identifier, $tipo]);
                
                return false; // Não bloqueado ainda
            }
        } else {
            // Criar novo registro
            $stmt = $pdo->prepare("
                INSERT INTO login_attempts (identifier, tipo, tentativas, ip, user_agent) 
                VALUES (?, ?, 1, ?, ?)
            ");
            $stmt->execute([$identifier, $tipo, $ip, $user_agent]);
            
            return false; // Primeira tentativa
        }
    } catch (PDOException $e) {
        error_log("Erro ao registrar tentativa falhada: " . $e->getMessage());
        return false;
    }
}

/**
 * Limpar tentativas de login após sucesso
 * 
 * @param PDO $pdo Conexão com banco
 * @param string $identifier Email ou IP
 * @param string $tipo Tipo: 'email', 'ip', 'admin_email', 'admin_ip'
 * @return bool True se limpou, false em caso de erro
 */
function limparTentativas($pdo, $identifier, $tipo) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM login_attempts 
            WHERE identifier = ? AND tipo = ?
        ");
        $stmt->execute([$identifier, $tipo]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao limpar tentativas: " . $e->getMessage());
        return false;
    }
}

/**
 * Obter número de tentativas restantes antes do bloqueio
 * 
 * @param PDO $pdo Conexão com banco
 * @param string $identifier Email ou IP
 * @param string $tipo Tipo: 'email', 'ip', 'admin_email', 'admin_ip'
 * @param int $max_tentativas Número máximo de tentativas
 * @return int Número de tentativas restantes
 */
function getTentativasRestantes($pdo, $identifier, $tipo, $max_tentativas = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT tentativas 
            FROM login_attempts 
            WHERE identifier = ? AND tipo = ?
        ");
        $stmt->execute([$identifier, $tipo]);
        $resultado = $stmt->fetch();
        
        if ($resultado) {
            return max(0, $max_tentativas - $resultado['tentativas']);
        }
        
        return $max_tentativas;
    } catch (PDOException $e) {
        error_log("Erro ao obter tentativas restantes: " . $e->getMessage());
        return $max_tentativas;
    }
}

/**
 * Validar email (formato + tamanho)
 * 
 * @param string $email Email a validar
 * @return array ['valido' => bool, 'erro' => string|null]
 */
function validarEmail($email) {
    if (empty($email)) {
        return ['valido' => false, 'erro' => 'O email é obrigatório.'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valido' => false, 'erro' => 'Por favor, informe um email válido.'];
    }
    
    if (strlen($email) > 255) {
        return ['valido' => false, 'erro' => 'O email informado é muito longo.'];
    }
    
    return ['valido' => true, 'erro' => null];
}

/**
 * Validar tipo de usuário (whitelist)
 * 
 * @param string $tipo Tipo a validar
 * @param array $permitidos Tipos permitidos
 * @return bool True se válido
 */
function validarTipoUsuario($tipo, $permitidos = ['candidato', 'empresa']) {
    return in_array($tipo, $permitidos);
}

/**
 * Formatar tempo restante de bloqueio
 * 
 * @param string $bloqueado_ate Datetime do fim do bloqueio
 * @return string Tempo formatado (ex: "14 minutos", "2 horas")
 */
function formatarTempoBloqueio($bloqueado_ate) {
    $agora = time();
    $fim = strtotime($bloqueado_ate);
    $diferenca = $fim - $agora;
    
    if ($diferenca <= 0) {
        return "0 minutos";
    }
    
    $minutos = floor($diferenca / 60);
    $horas = floor($minutos / 60);
    $minutos_restantes = $minutos % 60;
    
    if ($horas > 0) {
        return $horas . " hora" . ($horas > 1 ? "s" : "") . 
               ($minutos_restantes > 0 ? " e " . $minutos_restantes . " minuto" . ($minutos_restantes > 1 ? "s" : "") : "");
    } else {
        return $minutos . " minuto" . ($minutos > 1 ? "s" : "");
    }
}

/**
 * Gerar mensagem de bloqueio amigável
 * 
 * @param string $bloqueado_ate Datetime do fim do bloqueio
 * @return string Mensagem formatada
 */
function getMensagemBloqueio($bloqueado_ate) {
    $tempo = formatarTempoBloqueio($bloqueado_ate);
    return "Muitas tentativas de login falhadas. Tente novamente em $tempo.";
}

/**
 * Verificar se tabela login_attempts existe
 * 
 * @param PDO $pdo Conexão com banco
 * @return bool True se existe
 */
function loginAttemptsTableExists($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'login_attempts'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Registrar login bem-sucedido de admin (para auditoria)
 * 
 * @param PDO $pdo Conexão com banco
 * @param int $admin_id ID do admin
 * @param string $email Email do admin
 * @return void
 */
function registrarLoginAdmin($pdo, $admin_id, $email) {
    try {
        // Verificar se admin_logs existe
        if (!adminLogsTableExists($pdo)) {
            return;
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if ($user_agent && strlen($user_agent) > 255) {
            $user_agent = substr($user_agent, 0, 255);
        }
        
        $sql = "INSERT INTO admin_logs (admin_id, acao, tabela, detalhes, ip, user_agent) 
                VALUES (?, 'LOGIN', 'admin', ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $admin_id,
            "Login bem-sucedido: $email",
            $ip,
            $user_agent
        ]);
    } catch (PDOException $e) {
        error_log("Erro ao registrar login de admin: " . $e->getMessage());
    }
}
?>







