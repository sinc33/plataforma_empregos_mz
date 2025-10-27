-- Criar base de dados
create database plataforma_emprego_mz;
USE plataforma_emprego_mz;

-- Tabela principal de utilizadores
CREATE TABLE utilizador (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('candidato', 'empresa') NOT NULL,
    data_registo DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_login DATETIME NULL,
    ativo BOOLEAN DEFAULT TRUE
);

-- Perfil do candidato
CREATE TABLE candidato (
    id INT PRIMARY KEY,
    nome_completo VARCHAR(255) NOT NULL,
    foto_perfil VARCHAR(255),
    telefone VARCHAR(20),
    localizacao VARCHAR(100),
    cv_pdf VARCHAR(255),
    competencias TEXT,
    FOREIGN KEY (id) REFERENCES utilizador(id) ON DELETE CASCADE
);

-- Experiência profissional
CREATE TABLE experiencia (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidato_id INT NOT NULL,
    empresa VARCHAR(255) NOT NULL,
    cargo VARCHAR(255) NOT NULL,
    descricao TEXT,
    data_inicio DATE,
    data_fim DATE,
    FOREIGN KEY (candidato_id) REFERENCES candidato(id) ON DELETE CASCADE
);

CREATE INDEX idx_candidato_id_exp ON experiencia (candidato_id);

-- Formação académica
CREATE TABLE formacao (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidato_id INT NOT NULL,
    instituicao VARCHAR(255) NOT NULL,
    curso VARCHAR(255) NOT NULL,
    grau VARCHAR(100),
    data_inicio DATE,
    data_fim DATE,
    FOREIGN KEY (candidato_id) REFERENCES candidato(id) ON DELETE CASCADE
);

CREATE INDEX idx_candidato_id_form ON formacao (candidato_id);

-- Perfil da empresa
CREATE TABLE empresa (
    id INT PRIMARY KEY,
    nome_empresa VARCHAR(255) NOT NULL,
    nuit VARCHAR(20),
    logotipo VARCHAR(255),
    website VARCHAR(255),
    descricao TEXT,
    localizacao VARCHAR(100),
    FOREIGN KEY (id) REFERENCES utilizador(id) ON DELETE CASCADE
);

-- Vagas de emprego
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
    FOREIGN KEY (empresa_id) REFERENCES empresa(id) ON DELETE CASCADE
);

-- Candidaturas
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
    UNIQUE KEY (vaga_id, candidato_id)
);

-- Administradores
CREATE TABLE admin (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    nome VARCHAR(255) NOT NULL
);

-- Inserir administrador padrão (senha: admin123)
INSERT INTO admin (email, senha, nome) VALUES (
    'admin@plataforma.co.mz', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- password: admin123
    'Administrador Principal'
);

-- Ou para criar um novo admin, use este comando PHP:
-- $senhaHash = password_hash('sua_senha', PASSWORD_BCRYPT);´
ALTER TABLE admin 
ADD COLUMN ultimo_login DATETIME NULL AFTER nome;