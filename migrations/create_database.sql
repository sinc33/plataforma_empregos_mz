-- ==========================================
-- PLATAFORMA EMPREGO MZ
-- Base de Dados Completa - Versão Atualizada
-- ==========================================
-- Inclui todas as funcionalidades:
-- ✅ Sistema de usuários (candidatos e empresas)
-- ✅ Gestão de vagas
-- ✅ Sistema de candidaturas
-- ✅ Administração
-- ✅ Recuperação de senha
-- ✅ Foreign keys com CASCADE corretas
-- ==========================================

-- Criar e usar base de dados
DROP DATABASE IF EXISTS plataforma_emprego_mz;
CREATE DATABASE plataforma_emprego_mz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE plataforma_emprego_mz;

-- ==========================================
-- TABELA: utilizador
-- Usuários do sistema (candidatos e empresas)
-- ==========================================
CREATE TABLE utilizador (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('candidato', 'empresa') NOT NULL,
    data_registo DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_login DATETIME NULL,
    ativo BOOLEAN DEFAULT TRUE,
    INDEX idx_email (email),
    INDEX idx_tipo (tipo),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA: candidato
-- Perfil dos candidatos
-- ==========================================
CREATE TABLE candidato (
    id INT PRIMARY KEY,
    nome_completo VARCHAR(255) NOT NULL,
    foto_perfil VARCHAR(255),
    telefone VARCHAR(20),
    localizacao VARCHAR(100),
    cv_pdf VARCHAR(255),
    competencias TEXT,
    FOREIGN KEY (id) REFERENCES utilizador(id) ON DELETE CASCADE,
    INDEX idx_localizacao (localizacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA: experiencia
-- Experiência profissional dos candidatos
-- ==========================================
CREATE TABLE experiencia (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidato_id INT NOT NULL,
    empresa VARCHAR(255) NOT NULL,
    cargo VARCHAR(255) NOT NULL,
    descricao TEXT,
    data_inicio DATE,
    data_fim DATE,
    FOREIGN KEY (candidato_id) REFERENCES candidato(id) ON DELETE CASCADE,
    INDEX idx_candidato_id (candidato_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA: formacao
-- Formação académica dos candidatos
-- ==========================================
CREATE TABLE formacao (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidato_id INT NOT NULL,
    instituicao VARCHAR(255) NOT NULL,
    curso VARCHAR(255) NOT NULL,
    grau VARCHAR(100),
    data_inicio DATE,
    data_fim DATE,
    FOREIGN KEY (candidato_id) REFERENCES candidato(id) ON DELETE CASCADE,
    INDEX idx_candidato_id (candidato_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA: empresa
-- Perfil das empresas
-- ==========================================
CREATE TABLE empresa (
    id INT PRIMARY KEY,
    nome_empresa VARCHAR(255) NOT NULL,
    nuit VARCHAR(20),
    logotipo VARCHAR(255),
    website VARCHAR(255),
    descricao TEXT,
    localizacao VARCHAR(100),
    FOREIGN KEY (id) REFERENCES utilizador(id) ON DELETE CASCADE,
    INDEX idx_nome_empresa (nome_empresa),
    INDEX idx_localizacao (localizacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA: vaga
-- Vagas de emprego publicadas pelas empresas
-- ==========================================
CREATE TABLE vaga (
    id INT PRIMARY KEY AUTO_INCREMENT,
    empresa_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NOT NULL,
    area VARCHAR(100),
    localizacao VARCHAR(100),
    tipo_contrato ENUM('tempo_inteiro', 'tempo_parcial', 'estagio', 'freelance') NOT NULL,
    modalidade ENUM('presencial', 'hibrido', 'remoto') NOT NULL,
    nivel_experiencia VARCHAR(50),
    salario_estimado DECIMAL(10,2) NULL,
    data_publicacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_expiracao DATE,
    ativa BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (empresa_id) REFERENCES empresa(id) ON DELETE CASCADE,
    INDEX idx_empresa_id (empresa_id),
    INDEX idx_area (area),
    INDEX idx_localizacao (localizacao),
    INDEX idx_ativa (ativa),
    INDEX idx_data_publicacao (data_publicacao),
    INDEX idx_data_expiracao (data_expiracao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA: candidatura
-- Candidaturas dos candidatos às vagas
-- ==========================================
CREATE TABLE candidatura (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vaga_id INT NOT NULL,
    candidato_id INT NOT NULL,
    estado ENUM('submetida', 'em_analise', 'entrevista', 'rejeitada', 'contratado') DEFAULT 'submetida',
    carta_apresentacao TEXT,
    nota_interna VARCHAR(255) NULL,
    data_candidatura DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vaga_id) REFERENCES vaga(id) ON DELETE CASCADE,
    FOREIGN KEY (candidato_id) REFERENCES candidato(id) ON DELETE CASCADE,
    UNIQUE KEY unique_candidatura (vaga_id, candidato_id),
    INDEX idx_vaga_id (vaga_id),
    INDEX idx_candidato_id (candidato_id),
    INDEX idx_estado (estado),
    INDEX idx_data_candidatura (data_candidatura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA: admin
-- Administradores do sistema
-- ==========================================
CREATE TABLE admin (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    nome VARCHAR(255) NOT NULL,
    ultimo_login DATETIME NULL,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- TABELA: password_reset
-- Tokens de recuperação de senha (apenas candidato e empresa)
-- ==========================================
CREATE TABLE password_reset (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    tipo_usuario ENUM('candidato', 'empresa') NOT NULL,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_expiracao DATETIME NOT NULL,
    usado BOOLEAN DEFAULT FALSE,
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_expiracao (data_expiracao),
    INDEX idx_usado (usado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- INSERIR ADMINISTRADOR PADRÃO
-- ==========================================
-- Email: admin@plataforma.co.mz
-- Senha: admin123
-- Senha hash gerada com: password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO admin (email, senha, nome) VALUES (
    'admin@plataforma.co.mz', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Administrador Principal'
);

-- ==========================================
-- VIEWS ÚTEIS (Opcional - para relatórios)
-- ==========================================

-- View: Vagas com informações da empresa
CREATE OR REPLACE VIEW vw_vagas_completas AS
SELECT 
    v.*,
    e.nome_empresa,
    e.logotipo,
    e.localizacao as empresa_localizacao,
    (SELECT COUNT(*) FROM candidatura c WHERE c.vaga_id = v.id) as total_candidaturas,
    (SELECT COUNT(*) FROM candidatura c WHERE c.vaga_id = v.id AND c.estado = 'submetida') as candidaturas_submetidas,
    (SELECT COUNT(*) FROM candidatura c WHERE c.vaga_id = v.id AND c.estado = 'em_analise') as candidaturas_analise,
    (SELECT COUNT(*) FROM candidatura c WHERE c.vaga_id = v.id AND c.estado = 'entrevista') as candidaturas_entrevista,
    (SELECT COUNT(*) FROM candidatura c WHERE c.vaga_id = v.id AND c.estado = 'contratado') as candidaturas_contratado
FROM vaga v
JOIN empresa e ON v.empresa_id = e.id;

-- View: Candidaturas com informações completas
CREATE OR REPLACE VIEW vw_candidaturas_completas AS
SELECT 
    c.*,
    cand.nome_completo as candidato_nome,
    cand.foto_perfil as candidato_foto,
    cand.telefone as candidato_telefone,
    cand.localizacao as candidato_localizacao,
    u.email as candidato_email,
    v.titulo as vaga_titulo,
    v.area as vaga_area,
    v.localizacao as vaga_localizacao,
    e.nome_empresa,
    e.logotipo as empresa_logo
FROM candidatura c
JOIN candidato cand ON c.candidato_id = cand.id
JOIN utilizador u ON cand.id = u.id
JOIN vaga v ON c.vaga_id = v.id
JOIN empresa e ON v.empresa_id = e.id;

-- ==========================================
-- STORED PROCEDURE: Limpar Tokens Expirados
-- ==========================================
DELIMITER //

CREATE PROCEDURE limpar_tokens_expirados()
BEGIN
    DELETE FROM password_reset 
    WHERE data_expiracao < NOW() OR usado = TRUE;
    
    SELECT ROW_COUNT() as tokens_removidos;
END //

DELIMITER ;

-- ==========================================
-- EVENT: Limpeza Automática (a cada 24 horas)
-- ==========================================
-- Descomente as linhas abaixo para ativar limpeza automática
-- SET GLOBAL event_scheduler = ON;
-- 
-- CREATE EVENT IF NOT EXISTS evt_limpar_tokens
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CURRENT_TIMESTAMP
-- DO
--     CALL limpar_tokens_expirados();

-- ==========================================
-- VERIFICAÇÃO FINAL
-- ==========================================

-- Verificar todas as tabelas criadas
SELECT 
    TABLE_NAME as 'Tabela',
    TABLE_ROWS as 'Linhas (aprox)',
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as 'Tamanho (MB)'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'plataforma_emprego_mz'
ORDER BY TABLE_NAME;

-- Verificar foreign keys (todas devem ter CASCADE)
SELECT 
    kcu.TABLE_NAME as 'Tabela',
    kcu.COLUMN_NAME as 'Coluna',
    kcu.CONSTRAINT_NAME as 'Constraint',
    kcu.REFERENCED_TABLE_NAME as 'Referencia',
    rc.DELETE_RULE as 'Regra de Exclusão'
FROM information_schema.KEY_COLUMN_USAGE kcu
JOIN information_schema.REFERENTIAL_CONSTRAINTS rc 
    ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME 
    AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
WHERE kcu.TABLE_SCHEMA = 'plataforma_emprego_mz'
AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY kcu.TABLE_NAME;

-- ==========================================
-- DADOS DE TESTE (Opcional - Descomente para usar)
-- ==========================================
/*
-- Candidato de teste
INSERT INTO utilizador (email, senha, tipo) VALUES 
('candidato@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'candidato');

INSERT INTO candidato (id, nome_completo, telefone, localizacao) VALUES 
(LAST_INSERT_ID(), 'João Silva', '+258 84 123 4567', 'Maputo');

-- Empresa de teste
INSERT INTO utilizador (email, senha, tipo) VALUES 
('empresa@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empresa');

INSERT INTO empresa (id, nome_empresa, nuit, localizacao, descricao) VALUES 
(LAST_INSERT_ID(), 'Tech Solutions Moçambique', '123456789', 'Maputo', 'Empresa de tecnologia');
*/

-- ==========================================
-- FIM DO SCRIPT
-- ==========================================
-- Base de dados criada com sucesso!
-- Próximos passos:
-- 1. Execute este script no MySQL Workbench
-- 2. Verifique as tabelas criadas
-- 3. Faça login no admin: admin@plataforma.co.mz / admin123
-- 4. Teste a plataforma!
-- ==========================================
