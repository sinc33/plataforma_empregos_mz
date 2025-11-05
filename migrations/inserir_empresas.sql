-- ==========================================
-- INSERIR EMPRESAS MOÇAMBICANAS - DADOS REALISTAS
-- ==========================================
USE plataforma_emprego_mz;

-- Senha padrão para todas as empresas: empresa123
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

-- ==========================================
-- EMPRESA 1: Vodacom Moçambique
-- ==========================================
INSERT INTO utilizador (email, senha, tipo, ativo) VALUES 
('rh@vodacom.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empresa', TRUE);

INSERT INTO empresa (id, nome_empresa, nuit, website, descricao, localizacao) VALUES 
(LAST_INSERT_ID(), 
 'Vodacom Moçambique', 
 '400123456',
 'https://www.vodacom.co.mz',
 'Líder em telecomunicações em Moçambique, oferecendo serviços de voz, dados e soluções empresariais. Parte do grupo Vodafone, comprometida com a transformação digital do país.',
 'Maputo');

-- ==========================================
-- EMPRESA 2: Mozal (Mineração)
-- ==========================================
INSERT INTO utilizador (email, senha, tipo, ativo) VALUES 
('recrutamento@mozal.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empresa', TRUE);

INSERT INTO empresa (id, nome_empresa, nuit, website, descricao, localizacao) VALUES 
(LAST_INSERT_ID(), 
 'Mozal - Mozambique Aluminium', 
 '400234567',
 'https://www.mozal.co.mz',
 'Fundição de alumínio de classe mundial. Uma das maiores exportadoras de Moçambique, comprometida com o desenvolvimento sustentável e criação de empregos.',
 'Matola');

-- ==========================================
-- EMPRESA 3: Banco Standard Bank
-- ==========================================
INSERT INTO utilizador (email, senha, tipo, ativo) VALUES 
('carreiras@standardbank.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empresa', TRUE);

INSERT INTO empresa (id, nome_empresa, nuit, website, descricao, localizacao) VALUES 
(LAST_INSERT_ID(), 
 'Standard Bank Moçambique', 
 '400345678',
 'https://www.standardbank.co.mz',
 'Instituição financeira líder em Moçambique, oferecendo soluções bancárias inovadoras para pessoas e empresas. Parte do grupo Standard Bank África.',
 'Maputo');

-- ==========================================
-- EMPRESA 4: Cervejas de Moçambique (CDM)
-- ==========================================
INSERT INTO utilizador (email, senha, tipo, ativo) VALUES 
('rh@cdm.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empresa', TRUE);

INSERT INTO empresa (id, nome_empresa, nuit, website, descricao, localizacao) VALUES 
(LAST_INSERT_ID(), 
 'Cervejas de Moçambique (CDM)', 
 '400456789',
 'https://www.cdm.co.mz',
 'Maior cervejeira de Moçambique, produtora das marcas 2M, Laurentina e Impala. Líder no setor de bebidas, comprometida com a qualidade e inovação.',
 'Maputo');

-- ==========================================
-- EMPRESA 5: Shoprite Moçambique
-- ==========================================
INSERT INTO utilizador (email, senha, tipo, ativo) VALUES 
('recrutamento@shoprite.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empresa', TRUE);

INSERT INTO empresa (id, nome_empresa, nuit, website, descricao, localizacao) VALUES 
(LAST_INSERT_ID(), 
 'Shoprite Moçambique', 
 '400567890',
 'https://www.shoprite.co.mz',
 'Maior rede de supermercados de Moçambique, oferecendo produtos de qualidade a preços acessíveis. Presente em várias províncias, criando milhares de empregos.',
 'Maputo');

-- ==========================================
-- EMPRESA 6: Moza Banco
-- ==========================================
INSERT INTO utilizador (email, senha, tipo, ativo) VALUES 
('talento@mozabanco.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empresa', TRUE);

INSERT INTO empresa (id, nome_empresa, nuit, website, descricao, localizacao) VALUES 
(LAST_INSERT_ID(), 
 'Moza Banco', 
 '400678901',
 'https://www.mozabanco.co.mz',
 'Banco comercial 100% moçambicano, focado em soluções digitais e inclusão financeira. Oferecemos serviços bancários modernos e acessíveis.',
 'Maputo');

-- ==========================================
-- EMPRESA 7: Sasol Moçambique (Energia)
-- ==========================================
INSERT INTO utilizador (email, senha, tipo, ativo) VALUES 
('rh@sasol.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empresa', TRUE);

INSERT INTO empresa (id, nome_empresa, nuit, website, descricao, localizacao) VALUES 
(LAST_INSERT_ID(), 
 'Sasol Petroleum Moçambique', 
 '400789012',
 'https://www.sasol.co.mz',
 'Líder no setor de energia e gás natural em Moçambique. Operando o projeto de gás natural de Temane, contribuindo para o desenvolvimento energético do país.',
 'Inhambane');

-- ==========================================
-- EMPRESA 8: TIM Moçambique (Telecomunicações)
-- ==========================================
INSERT INTO utilizador (email, senha, tipo, ativo) VALUES 
('carreiras@tim.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empresa', TRUE);

INSERT INTO empresa (id, nome_empresa, nuit, website, descricao, localizacao) VALUES 
(LAST_INSERT_ID(), 
 'TIM Moçambique', 
 '400890123',
 'https://www.tim.co.mz',
 'Operadora de telecomunicações oferecendo serviços móveis inovadores. Focada em conectar Moçambique através de tecnologia 4G e soluções digitais.',
 'Beira');

-- ==========================================
-- EMPRESA 9: KUDUMBA (Supermercados)
-- ==========================================
INSERT INTO utilizador (email, senha, tipo, ativo) VALUES 
('rh@kudumba.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empresa', TRUE);

INSERT INTO empresa (id, nome_empresa, nuit, website, descricao, localizacao) VALUES 
(LAST_INSERT_ID(), 
 'KUDUMBA Supermercados', 
 '400901234',
 'https://www.kudumba.co.mz',
 'Rede moçambicana de supermercados com forte presença no norte do país. Comprometida com produtos locais e desenvolvimento comunitário.',
 'Nampula');

-- ==========================================
-- EMPRESA 10: CETA - Centro de Estudos e Tecnologias Aplicadas
-- ==========================================
INSERT INTO utilizador (email, senha, tipo, ativo) VALUES 
('talento@ceta.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empresa', TRUE);

INSERT INTO empresa (id, nome_empresa, nuit, website, descricao, localizacao) VALUES 
(LAST_INSERT_ID(), 
 'CETA - Tecnologias de Informação', 
 '401012345',
 'https://www.ceta.co.mz',
 'Empresa moçambicana de tecnologia, especializada em desenvolvimento de software, consultoria em TI e transformação digital para empresas e governo.',
 'Maputo');

-- ==========================================
-- VERIFICAÇÃO
-- ==========================================
SELECT 
    u.id,
    u.email,
    e.nome_empresa,
    e.localizacao,
    e.nuit
FROM utilizador u
JOIN empresa e ON u.id = e.id
WHERE u.tipo = 'empresa'
ORDER BY u.id DESC
LIMIT 10;

-- ==========================================
-- RESUMO
-- ==========================================
SELECT 
    COUNT(*) as 'Total de Empresas',
    GROUP_CONCAT(DISTINCT e.localizacao) as 'Localizações'
FROM empresa e
JOIN utilizador u ON e.id = u.id
WHERE u.tipo = 'empresa';

