<?php
session_start();
require_once 'config/db.php';

 $pdo = getPDO();

// Buscar apenas 3 vagas recentes para a página inicial
 $sql_vagas_recentes = "SELECT v.*, e.nome_empresa, e.logotipo 
                       FROM vaga v 
                       JOIN empresa e ON v.empresa_id = e.id 
                       WHERE v.ativa = TRUE AND v.data_expiracao >= CURDATE()
                       ORDER BY v.data_publicacao DESC 
                       LIMIT 3";

 $stmt = $pdo->prepare($sql_vagas_recentes);
 $stmt->execute();
 $vagas_recentes = $stmt->fetchAll();

// Contar total de vagas ativas
 $sql_total_vagas = "SELECT COUNT(*) as total FROM vaga WHERE ativa = TRUE AND data_expiracao >= CURDATE()";
 $total_vagas = $pdo->query($sql_total_vagas)->fetch()['total'];

// Contar total de empresas
 $sql_total_empresas = "SELECT COUNT(*) as total FROM empresa";
 $total_empresas = $pdo->query($sql_total_empresas)->fetch()['total'];

// Áreas mais populares para quick links
 $areas_populares = [
    'TI e Tecnologia',
    'Agricultura e Pecuária',
    'Construção Civil', 
    'Educação e Formação',
    'Saúde e Medicina',
    'Comércio e Vendas',
    'Hotelaria e Turismo',
    'Administração e Secretariado',
    'Finanças e Contabilidade',
    'Recursos Humanos',
    'Marketing e Publicidade',
    'Logística e Transportes',
    'Mineração e Recursos Naturais',
    'Pesca e Aquicultura'
];

// Todas as províncias de Moçambique + Remoto
 $provincias_mocambique = [
    'Maputo Cidade',
    'Maputo Província',
    'Gaza',
    'Inhambane',
    'Sofala',
    'Manica',
    'Tete',
    'Zambézia',
    'Nampula',
    'Cabo Delgado',
    'Niassa',
    'Remoto'
];

// Principais cidades para quick links (mais procuradas)
 $cidades_principais = [
    'Maputo Cidade',
    'Matola',
    'Beira',
    'Nampula',
    'Chimoio',
    'Quelimane',
    'Tete',
    'Xai-Xai',
    'Inhambane',
    'Lichinga',
    'Pemba',
    'Remoto'
];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emprego MZ - Encontre Emprego em Moçambique</title>
    <meta name="description" content="A principal plataforma de empregos de Moçambique. Conectamos talentos com as melhores oportunidades em todas as províncias do país.">
    
    <!-- Google Fonts - Modern Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Marrabenta UI Styles -->
    <link rel="stylesheet" href="assets/css/marrabenta-ui.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body>
    <!-- 📱 HEADER - Navegação Principal -->
    <header class="main-header">
        <div class="header-container">
            <a href="index.php" class="header-logo">
                <i data-lucide="briefcase" style="width: 32px; height: 32px;"></i>
                Emprego MZ
            </a>
            <nav class="header-nav">
                <a href="index.php">
                    <i data-lucide="home" style="width: 18px; height: 18px;"></i>
                    Início
                </a>
                <a href="vagas.php">
                    <i data-lucide="briefcase" style="width: 18px; height: 18px;"></i>
                    Todas as Vagas
                </a>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['user_type'] === 'empresa'): ?>
                        <a href="empresa/dashboard.php">
                            <i data-lucide="bar-chart" style="width: 18px; height: 18px;"></i>
                            Dashboard
                        </a>
                        <a href="empresa/publicar_vaga.php">
                            <i data-lucide="plus-circle" style="width: 18px; height: 18px;"></i>
                            Publicar Vaga
                        </a>
                    <?php else: ?>
                        <a href="candidato/perfil.php">
                            <i data-lucide="user" style="width: 18px; height: 18px;"></i>
                            Meu Perfil
                        </a>
                        <a href="candidato/candidaturas.php">
                            <i data-lucide="file-text" style="width: 18px; height: 18px;"></i>
                            Minhas Candidaturas
                        </a>
                    <?php endif; ?>
                    
                    <span class="header-welcome">
                        <i data-lucide="smile" style="width: 18px; height: 18px;"></i>
                        Olá, 
                        <?php 
                        if ($_SESSION['user_type'] === 'empresa') {
                            $stmt_empresa = $pdo->prepare("SELECT nome_empresa FROM empresa WHERE id = ?");
                            $stmt_empresa->execute([$_SESSION['user_id']]);
                            $empresa = $stmt_empresa->fetch();
                            echo htmlspecialchars($empresa['nome_empresa'] ?? 'Empresa');
                        } else {
                            $stmt_candidato = $pdo->prepare("SELECT nome_completo FROM candidato WHERE id = ?");
                            $stmt_candidato->execute([$_SESSION['user_id']]);
                            $candidato = $stmt_candidato->fetch();
                            echo htmlspecialchars($candidato['nome_completo'] ?? 'Candidato');
                        }
                        ?>
                    </span>
                    <a href="auth/logout.php">
                        <i data-lucide="log-out" style="width: 18px; height: 18px;"></i>
                        Sair
                    </a>
                <?php else: ?>
                    <a href="auth/login.php">
                        <i data-lucide="log-in" style="width: 18px; height: 18px;"></i>
                        Login
                    </a>
                    <a href="auth/register.php">
                        <i data-lucide="user-plus" style="width: 18px; height: 18px;"></i>
                        Registar
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <!-- 🌅 HERO SECTION -->
        <div class="hero">
            <div class="hero-content">
                <h1 class="animate-slideDown">Encontre o Emprego dos Seus Sonhos em Moçambique</h1>
                <p class="animate-fadeIn">Conectamos talentos moçambicanos com as melhores oportunidades em todas as províncias</p>
                
                <!-- 🔍 BUSCA RÁPIDA -->
                <div class="search-box animate-fadeIn">
                    <form action="vagas.php" method="GET" class="search-form">
                        <input type="text" name="pesquisa" class="search-input"
                               placeholder="Cargo, palavra-chave ou empresa..." />
                        <button type="submit" class="btn-search">
                            <i data-lucide="search"></i>
                            Buscar Vagas
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- 📊 ESTATÍSTICAS -->
        <div class="stats">
            <div class="stat-item animate-popIn">
                <span class="stat-number"><?php echo $total_vagas; ?></span>
                <span class="stat-label">Vagas Ativas</span>
            </div>
            <div class="stat-item animate-popIn">
                <span class="stat-number"><?php echo $total_empresas; ?></span>
                <span class="stat-label">Empresas Parceiras</span>
            </div>
            <div class="stat-item animate-popIn">
                <span class="stat-number"><?php echo count($provincias_mocambique); ?></span>
                <span class="stat-label">Províncias</span>
            </div>
        </div>

        <!-- 🌊 OCEAN DIVIDER -->
        <div class="ocean-divider">
            <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="ocean-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:var(--azul-indico);stop-opacity:1" />
                        <stop offset="100%" style="stop-color:var(--verde-esperanca);stop-opacity:1" />
                    </linearGradient>
                </defs>
                <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                      fill="url(#ocean-gradient)" opacity="0.8"></path>
            </svg>
        </div>

        <!-- 🏷️ BUSCAR POR ÁREA -->
        <section class="section container">
            <h2 class="section-title">Buscar por Área</h2>
            <p class="section-subtitle">Encontre oportunidades no seu sector de actuação</p>
            <div class="quick-links">
                <?php foreach ($areas_populares as $area): ?>
                    <a href="vagas.php?area=<?php echo urlencode($area); ?>" class="quick-link">
                        <i data-lucide="briefcase"></i>
                        <?php echo $area; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 📍 CIDADES PRINCIPAIS -->
        <section class="section container">
            <h2 class="section-title">Cidades Mais Procuradas</h2>
            <p class="section-subtitle">Vagas nas principais cidades de Moçambique</p>
            <div class="quick-links">
                <?php foreach ($cidades_principais as $cidade): ?>
                    <a href="vagas.php?localizacao=<?php echo urlencode($cidade); ?>" class="quick-link">
                        <i data-lucide="<?php echo ($cidade === 'Remoto') ? 'globe' : 'map-pin'; ?>"></i>
                        <?php echo $cidade; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 🗺️ TODAS AS PROVÍNCIAS -->
        <section class="section container">
            <h2 class="section-title">Todas as Províncias de Moçambique</h2>
            <p class="section-subtitle">De Maputo ao Rovuma - oportunidades em todo o país</p>
            <div class="quick-links">
                <?php foreach ($provincias_mocambique as $provincia): ?>
                    <a href="vagas.php?localizacao=<?php echo urlencode($provincia); ?>" class="quick-link">
                        <i data-lucide="<?php echo ($provincia === 'Remoto') ? 'globe' : 'map-pin'; ?>"></i>
                        <?php echo $provincia; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 🌊 OCEAN DIVIDER -->
        <div class="ocean-divider">
            <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none" transform="rotate(180)">
                <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" 
                      fill="url(#ocean-gradient)" opacity="0.8"></path>
            </svg>
        </div>

        <!-- 🔥 VAGAS EM DESTAQUE -->
        <section class="section container">
            <div class="flex-between flex-mobile-column" style="margin-bottom: var(--space-lg);">
                <div>
                    <h2 class="section-title" style="text-align: left; margin-bottom: var(--space-xs);">Vagas em Destaque</h2>
                    <p style="color: var(--cinza-baobab);">As oportunidades mais recentes para você</p>
                </div>
                <a href="vagas.php" class="btn btn-primary">
                    <i data-lucide="arrow-right"></i>
                    Ver todas as <?php echo $total_vagas; ?> vagas
                </a>
            </div>

            <?php if (empty($vagas_recentes)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i data-lucide="inbox"></i>
                    </div>
                    <h3>Nenhuma vaga disponível no momento</h3>
                    <p>Volte mais tarde para conferir novas oportunidades!</p>
                </div>
            <?php else: ?>
                <div class="vaga-grid">
                    <?php foreach ($vagas_recentes as $index => $vaga): ?>
                        <?php 
                        // Simulação: primeira vaga é urgente
                        $is_urgent = ($index === 0);
                        ?>
                        <div class="xima-card <?php echo $is_urgent ? 'xima-card--urgent' : ''; ?>">
                            <?php if ($is_urgent): ?>
                                <div class="card-urgent-badge">
                                    <i data-lucide="zap" style="width: 14px; height: 14px;"></i>
                                    Urgente
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-header">
                                <div class="card-logo">
                                    <?php if ($vaga['logotipo']): ?>
                                        <img src="<?php echo htmlspecialchars($vaga['logotipo']); ?>" 
                                             alt="<?php echo htmlspecialchars($vaga['nome_empresa']); ?>"
                                             style="width: 100%; height: 100%; object-fit: cover; border-radius: var(--radius-md);">
                                    <?php else: ?>
                                        <i data-lucide="building" style="width: 30px; height: 30px; color: var(--azul-indico);"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="card-title-section">
                                    <div class="card-title">
                                        <a href="vaga_detalhe.php?id=<?php echo $vaga['id']; ?>">
                                            <?php echo htmlspecialchars($vaga['titulo']); ?>
                                        </a>
                                    </div>
                                    <div class="card-empresa">
                                        <i data-lucide="building" style="width: 16px; height: 16px;"></i>
                                        <?php echo htmlspecialchars($vaga['nome_empresa']); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-meta-group">
                                <div class="card-meta">
                                    <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i>
                                    <?php echo htmlspecialchars($vaga['localizacao']); ?>
                                </div>
                                <div class="card-meta">
                                    <i data-lucide="tag" style="width: 16px; height: 16px;"></i>
                                    <?php echo htmlspecialchars($vaga['area']); ?>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap" style="gap: var(--space-xs); margin-bottom: var(--space-md);">
                                <span class="badge badge-primary">
                                    <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                    <?php 
                                        echo [
                                            'tempo_inteiro' => 'Tempo Inteiro',
                                            'tempo_parcial' => 'Tempo Parcial', 
                                            'estagio' => 'Estágio',
                                            'freelance' => 'Freelance'
                                        ][$vaga['tipo_contrato']] ?? $vaga['tipo_contrato'];
                                    ?>
                                </span>
                                <span class="badge badge-success">
                                    <i data-lucide="monitor" style="width: 14px; height: 14px;"></i>
                                    <?php 
                                        echo [
                                            'presencial' => 'Presencial',
                                            'hibrido' => 'Híbrido',
                                            'remoto' => 'Remoto'
                                        ][$vaga['modalidade']] ?? $vaga['modalidade'];
                                    ?>
                                </span>
                            </div>
                            
                            <?php if ($vaga['salario_estimado']): ?>
                                <div class="card-salary">
                                    <i data-lucide="dollar-sign" style="width: 20px; height: 20px;"></i>
                                    <?php echo number_format($vaga['salario_estimado'], 0, ',', ' '); ?> MT
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-footer">
                                <span class="flex" style="align-items: center; gap: var(--space-xs);">
                                    <i data-lucide="calendar" style="width: 14px; height: 14px;"></i>
                                    <?php echo date('d/m/Y', strtotime($vaga['data_publicacao'])); ?>
                                </span>
                                <a href="vaga_detalhe.php?id=<?php echo $vaga['id']; ?>">
                                    Ver detalhes
                                    <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- 🎯 CALL TO ACTION -->
        <div class="cta-section">
            <div class="cta-content">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['user_type'] === 'empresa'): ?>
                        <h2>Precisa de Talentos para Sua Empresa?</h2>
                        <p>Publique suas vagas e encontre os melhores profissionais em todas as províncias de Moçambique</p>
                        <div class="cta-buttons">
                            <a href="empresa/dashboard.php" class="btn btn-primary">
                                <i data-lucide="bar-chart"></i>
                                Acessar Dashboard
                            </a>
                            <a href="empresa/publicar_vaga.php" class="btn btn-ghost">
                                <i data-lucide="plus-circle"></i>
                                Publicar Nova Vaga
                            </a>
                        </div>
                    <?php else: ?>
                        <h2>Procura por Oportunidades?</h2>
                        <p>Complete seu perfil, adicione suas experiências e formações para se destacar para as empresas</p>
                        <div class="cta-buttons">
                            <a href="candidato/perfil.php" class="btn btn-primary">
                                <i data-lucide="user"></i>
                                Completar Perfil
                            </a>
                            <a href="vagas.php" class="btn btn-ghost">
                                <i data-lucide="search"></i>
                                Ver Todas as Vagas
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <h2>Comece Sua Jornada Profissional Hoje</h2>
                    <p>Junte-se a milhares de profissionais e empresas que já encontraram sucesso na nossa plataforma</p>
                    <div class="cta-buttons">
                        <a href="auth/register.php?tipo=candidato" class="btn btn-primary">
                            <i data-lucide="user-plus"></i>
                            Criar Conta Candidato
                        </a>
                        <a href="auth/register.php?tipo=empresa" class="btn btn-ghost">
                            <i data-lucide="building"></i>
                            Criar Conta Empresa
                        </a>
                    </div>
                    <div style="margin-top: var(--space-md);">
                        <a href="auth/login.php" style="color: var(--branco-puro); text-decoration: underline; opacity: 0.9;" class="flex-center" style="gap: var(--space-xs);">
                            <i data-lucide="log-in" style="width: 16px; height: 16px;"></i>
                            Já tem conta? Faça login aqui
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- 📱 FOOTER - Rodapé Completo -->
    <footer class="main-footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3>Sobre a Emprego MZ</h3>
                    <p>Somos a principal plataforma de conexão de talentos e oportunidades em Moçambique, dedicada a impulsionar carreiras e fortalecer empresas em todo o país.</p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><i data-lucide="facebook"></i></a>
                        <a href="#" aria-label="LinkedIn"><i data-lucide="linkedin"></i></a>
                        <a href="#" aria-label="Twitter"><i data-lucide="twitter"></i></a>
                        <a href="#" aria-label="Instagram"><i data-lucide="instagram"></i></a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>Links Rápidos</h3>
                    <ul>
                        <li><a href="vagas.php"><i data-lucide="briefcase" style="width: 16px; height: 16px;"></i> Todas as Vagas</a></li>
                        <li><a href="#"><i data-lucide="help-circle" style="width: 16px; height: 16px;"></i> Como Funciona</a></li>
                        <li><a href="#"><i data-lucide="building" style="width: 16px; height: 16px;"></i> Para Empresas</a></li>
                        <li><a href="#"><i data-lucide="user" style="width: 16px; height: 16px;"></i> Para Candidatos</a></li>
                        <li><a href="auth/register.php"><i data-lucide="user-plus" style="width: 16px; height: 16px;"></i> Criar Conta</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Suporte</h3>
                    <ul>
                        <li><a href="#"><i data-lucide="help-circle" style="width: 16px; height: 16px;"></i> Central de Ajuda</a></li>
                        <li><a href="#"><i data-lucide="file-text" style="width: 16px; height: 16px;"></i> Termos de Uso</a></li>
                        <li><a href="#"><i data-lucide="shield" style="width: 16px; height: 16px;"></i> Política de Privacidade</a></li>
                        <li><a href="#"><i data-lucide="mail" style="width: 16px; height: 16px;"></i> Fale Conosco</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contacto</h3>
                    <p><i data-lucide="mail" style="width: 16px; height: 16px; margin-right: 8px;"></i> geral@empregomz.co.mz</p>
                    <p><i data-lucide="phone" style="width: 16px; height: 16px; margin-right: 8px;"></i> +258 21 123 456</p>
                    <p><i data-lucide="map-pin" style="width: 16px; height: 16px; margin-right: 8px;"></i> Maputo, Moçambique</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Emprego MZ - Todos os direitos reservados.</p>
                <p style="margin-top: 5px;">De Maputo ao Rovuma, construindo o futuro juntos. 🇲🇿</p>
            </div>
        </div>
    </footer>

    <!-- ✨ MARRABENTA UI JAVASCRIPT -->
    <script src="assets/js/marrabenta-ui.js"></script>
</body>
</html>