<?php
/**
 * ========================================
 * CONFIGURAÇÃO DE EMAIL - SMTP
 * ========================================
 * 
 * Configure suas credenciais SMTP aqui.
 * IMPORTANTE: NÃO commitar este arquivo com credenciais reais!
 */

// ========================================
// MODO DE OPERAÇÃO
// ========================================
// true = Envia emails reais via SMTP (requer configuração SMTP)
// false = Mostra link na tela (para desenvolvimento/teste)
define('EMAIL_ENABLED', false);

// ========================================
// CONFIGURAÇÕES SMTP
// ========================================

// Servidor SMTP
define('SMTP_HOST', 'smtp.gmail.com'); // Gmail, ou seu servidor SMTP

// Porta SMTP
define('SMTP_PORT', 587); // 587 para TLS, 465 para SSL

// Segurança
define('SMTP_SECURE', 'tls'); // 'tls' ou 'ssl'

// Autenticação
define('SMTP_USERNAME', 'yassinnormahomed482@gmail.com'); // Seu email
define('SMTP_PASSWORD', 'tqwvgzwgwraajdf'); // Senha de app do Gmail

// Remetente
define('EMAIL_FROM_ADDRESS', 'yassinnormahomed482@gmail.com');
define('EMAIL_FROM_NAME', 'Emprego MZ');

// ========================================
// INSTRUÇÕES PARA GMAIL
// ========================================
/*
Para usar Gmail:

1. Ativar Verificação em 2 Etapas:
   - Acesse: https://myaccount.google.com/security
   - Ative "Verificação em duas etapas"

2. Gerar Senha de App:
   - Acesse: https://myaccount.google.com/apppasswords
   - Selecione "Email" e "Outro (nome personalizado)"
   - Digite "Emprego MZ"
   - Copie a senha gerada (16 caracteres)
   - Cole em SMTP_PASSWORD acima

3. Configuração:
   SMTP_HOST = 'smtp.gmail.com'
   SMTP_PORT = 587
   SMTP_SECURE = 'tls'
   SMTP_USERNAME = 'seu-email@gmail.com'
   SMTP_PASSWORD = 'xxxx xxxx xxxx xxxx' (senha de 16 dígitos)

========================================
OUTRAS OPÇÕES DE SMTP
========================================

OUTLOOK/HOTMAIL:
- SMTP_HOST = 'smtp-mail.outlook.com'
- SMTP_PORT = 587
- SMTP_SECURE = 'tls'

YAHOO:
- SMTP_HOST = 'smtp.mail.yahoo.com'
- SMTP_PORT = 587
- SMTP_SECURE = 'tls'

SENDGRID (Recomendado para produção):
- SMTP_HOST = 'smtp.sendgrid.net'
- SMTP_PORT = 587
- SMTP_SECURE = 'tls'
- SMTP_USERNAME = 'apikey'
- SMTP_PASSWORD = 'sua-api-key'

MAILGUN:
- SMTP_HOST = 'smtp.mailgun.org'
- SMTP_PORT = 587
- SMTP_SECURE = 'tls'

========================================
SEGURANÇA
========================================

⚠️ IMPORTANTE:
- Nunca commite este arquivo com senhas reais no Git
- Adicione ao .gitignore: echo "config/email_config.php" >> .gitignore
- Em produção, use variáveis de ambiente
- Use Senhas de App, nunca a senha principal

*/

// ========================================
// CONFIGURAÇÕES DE EMAIL
// ========================================

// Debug (0 = desligado, 1 = erros, 2 = verbose)
define('SMTP_DEBUG', 0);

// Timeout (segundos)
define('SMTP_TIMEOUT', 10);

// Charset
define('EMAIL_CHARSET', 'UTF-8');

