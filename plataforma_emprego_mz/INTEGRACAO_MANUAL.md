# 🔧 Guia de Integração Manual

## ⚠️ IMPORTANTE

Se houver problemas com os arquivos PHP principais após a automação, siga este guia para integração manual.

## 📝 Passos de Integração

### 1. Adicionar no `<head>` de todos os arquivos PHP

Adicione estas linhas **ANTES** da tag `<style>`:

```html
<!-- Modern Enhancements CSS -->
<link rel="stylesheet" href="assets/css/modern-enhancements.css">
```

### 2. Adicionar antes do `</body>` de todos os arquivos PHP

Adicione estas linhas **ANTES** dos seus scripts existentes:

```html
<!-- Modern Features JavaScript -->
<script src="assets/js/modern-features.js"></script>
```

## 📁 Arquivos a Modificar

### index.php

**No `<head>` (após Lucide Icons):**
```html
<!-- Lucide Icons -->
<script src="https://unpkg.com/lucide@latest"></script>

<!-- Modern Enhancements CSS -->
<link rel="stylesheet" href="assets/css/modern-enhancements.css">
```

**Antes do `</body>` (antes dos scripts existentes):**
```html
<!-- Modern Features JavaScript -->
<script src="assets/js/modern-features.js"></script>

<script>
    // Seus scripts existentes...
    lucide.createIcons();
    
    // ADICIONAR: Notificação de boas-vindas (opcional)
    setTimeout(() => {
        <?php if (isset($_SESSION['user_id'])): ?>
            showToast('Bem-vindo de volta, <?php echo htmlspecialchars($_SESSION['nome'] ?? 'Usuário'); ?>! 👋', 'success');
        <?php endif; ?>
    }, 500);
</script>
```

---

### vagas.php

**No `<head>` (após Lucide Icons):**
```html
<!-- Lucide Icons -->
<script src="https://unpkg.com/lucide@latest"></script>

<!-- Modern Enhancements CSS -->
<link rel="stylesheet" href="assets/css/modern-enhancements.css">
```

**Modificar função `compartilharVaga`:**

Substitua a função `compartilharVaga` existente por:

```javascript
// Compartilhar vaga (atualizado para usar modal)
function compartilharVaga(vagaId) {
    const url = window.location.origin + '/plataforma_emprego_mz/vaga_detalhe.php?id=' + vagaId;
    const title = 'Vaga de Emprego - Emprego MZ';
    openShareModal(url, title);
}
```

**ADICIONAR antes do `</body>`:**
```html
<!-- Modern Features JavaScript -->
<script src="assets/js/modern-features.js"></script>

<script>
    // Seus scripts existentes (lucide, limparFiltros, ordenarVagas, etc.)
    lucide.createIcons();
    
    function limparFiltros() {
        window.location.href = 'vagas.php';
    }
    
    function ordenarVagas(ordem) {
        const url = new URL(window.location.href);
        url.searchParams.set('ordem', ordem);
        url.searchParams.set('pagina', '1');
        window.location.href = url.toString();
    }
    
    function compartilharVaga(vagaId) {
        const url = window.location.origin + '/plataforma_emprego_mz/vaga_detalhe.php?id=' + vagaId;
        const title = 'Vaga de Emprego - Emprego MZ';
        openShareModal(url, title);
    }
    
    // ADICIONAR: Toast com contagem (opcional)
    setTimeout(() => {
        showToast('<?php echo $total_vagas; ?> vagas encontradas!', 'info', 2000);
    }, 500);
</script>
```

---

### vaga_detalhe.php

**No `<head>` (após Lucide Icons):**
```html
<!-- Lucide Icons -->
<script src="https://unpkg.com/lucide@latest"></script>

<!-- Modern Enhancements CSS -->
<link rel="stylesheet" href="assets/css/modern-enhancements.css">
```

**Modificar função `copiarLink`:**

Substitua a função `copiarLink` existente por:

```javascript
function copiarLink() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(() => {
        showToast('Link copiado para a área de transferência!', 'success');
    }).catch(() => {
        showToast('Erro ao copiar link', 'error');
    });
}
```

**ADICIONAR antes do `</body>`:**
```html
<!-- Modern Features JavaScript -->
<script src="assets/js/modern-features.js"></script>

<script>
    lucide.createIcons();
    
    function copiarLink() {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            showToast('Link copiado para a área de transferência!', 'success');
        }).catch(() => {
            showToast('Erro ao copiar link', 'error');
        });
    }
    
    // ADICIONAR: Configuração de favoritos e notificações
    document.addEventListener('DOMContentLoaded', function() {
        const vagaId = <?php echo $vaga_id; ?>;
        const mainCard = document.querySelector('.vaga-header');
        if (mainCard && !mainCard.parentElement.classList.contains('vaga-card')) {
            mainCard.parentElement.classList.add('vaga-card');
            mainCard.parentElement.setAttribute('data-vaga-id', vagaId);
        }
        
        // Mostrar notificação de sucesso/erro se houver
        <?php if ($sucesso): ?>
            showToast('<?php echo addslashes($sucesso); ?>', 'success');
        <?php endif; ?>
        
        <?php if ($erro): ?>
            showToast('<?php echo addslashes($erro); ?>', 'error');
        <?php endif; ?>
    });
</script>
```

---

## 🎨 Adicionando Badges aos Cards

Para adicionar badges modernos aos cards de vaga, adicione estas classes:

```html
<!-- Badge de área -->
<span class="badge badge-area">
    <i data-lucide="briefcase" style="width: 14px; height: 14px;"></i>
    <?php echo htmlspecialchars($vaga['area']); ?>
</span>

<!-- Badge de modalidade -->
<span class="badge badge-modalidade">
    <i data-lucide="monitor" style="width: 14px; height: 14px;"></i>
    <?php echo traduzirModalidade($vaga['modalidade']); ?>
</span>

<!-- Badge urgente (opcional) -->
<?php if ($vaga['urgente']): ?>
<span class="badge badge-urgente">
    <i data-lucide="zap" style="width: 14px; height: 14px;"></i>
    Urgente
</span>
<?php endif; ?>
```

---

## ✅ Verificação de Integração

Após fazer as modificações, verifique:

1. **Abra o DevTools (F12)**
   - Console não deve mostrar erros
   - Aba Network deve mostrar `modern-enhancements.css` e `modern-features.js` carregados

2. **Teste as funcionalidades:**
   - [ ] Botão de tema aparece no canto inferior direito
   - [ ] Botão de favorito aparece nos cards de vaga
   - [ ] Notificações toast aparecem
   - [ ] Autocomplete funciona na busca
   - [ ] Modal de compartilhar abre
   - [ ] Animações suaves nos cards ao passar mouse

3. **Console deve mostrar:**
   ```
   ✨ Emprego MZ - Modern Features carregado com sucesso!
   ```

---

## 🐛 Solução de Problemas

### CSS não carrega:
```html
<!-- Verifique o caminho -->
<link rel="stylesheet" href="assets/css/modern-enhancements.css">

<!-- Se o arquivo estiver em outra pasta: -->
<link rel="stylesheet" href="../assets/css/modern-enhancements.css">
```

### JavaScript não funciona:
```html
<!-- Certifique-se que está ANTES dos seus scripts -->
<script src="assets/js/modern-features.js"></script>
<script>
    // Seus scripts aqui
</script>
```

### Ícones não aparecem:
```html
<!-- Certifique-se que Lucide está carregado -->
<script src="https://unpkg.com/lucide@latest"></script>

<!-- E que você inicializa os ícones -->
<script>
    lucide.createIcons();
</script>
```

---

## 📦 Estrutura de Arquivos Final

```
plataforma_emprego_mz/
├── assets/
│   ├── css/
│   │   ├── style.css (existente)
│   │   └── modern-enhancements.css (NOVO)
│   ├── js/
│   │   └── modern-features.js (NOVO)
│   └── images/ (existente)
├── index.php (modificado)
├── vagas.php (modificado)
├── vaga_detalhe.php (modificado)
├── MODERN_FEATURES_README.md (documentação)
└── INTEGRACAO_MANUAL.md (este arquivo)
```

---

## 🚀 Testando Tudo

Depois de integrar, teste esta sequência:

1. **Página Inicial (`index.php`)**
   - [ ] Animações dos cards
   - [ ] Notificação de boas-vindas (se logado)
   - [ ] Botão de tema funciona

2. **Lista de Vagas (`vagas.php`)**
   - [ ] Botões de favorito aparecem
   - [ ] Slider de salário funciona
   - [ ] Autocomplete na busca
   - [ ] Toast com contagem de vagas
   - [ ] Botão compartilhar abre modal

3. **Detalhes da Vaga (`vaga_detalhe.php`)**
   - [ ] Botão de favorito
   - [ ] Copiar link mostra toast
   - [ ] Notificações de sucesso/erro
   - [ ] Modal de compartilhar

---

## 💡 Dicas

- **Ctrl + F5** para limpar cache do navegador
- **Modo anônimo** para testar sem extensões
- **DevTools** (F12) para verificar erros
- **Console** mostra mensagens de debug

---

## 📞 Exemplo Completo de Arquivo

### Exemplo: vagas.php completo

```php
<?php
session_start();
require_once 'config/db.php';
// ... seu código PHP ...
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vagas - Emprego MZ</title>
    
    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Ícones -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Modern Enhancements CSS -->
    <link rel="stylesheet" href="assets/css/modern-enhancements.css">
    
    <!-- Seus estilos -->
    <style>
        /* Seus estilos aqui */
    </style>
</head>
<body>
    
    <!-- Seu HTML aqui -->
    
    <!-- Modern Features JavaScript -->
    <script src="assets/js/modern-features.js"></script>
    
    <!-- Seus scripts -->
    <script>
        lucide.createIcons();
        
        function limparFiltros() {
            window.location.href = 'vagas.php';
        }
        
        function ordenarVagas(ordem) {
            const url = new URL(window.location.href);
            url.searchParams.set('ordem', ordem);
            url.searchParams.set('pagina', '1');
            window.location.href = url.toString();
        }
        
        function compartilharVaga(vagaId) {
            const url = window.location.origin + '/plataforma_emprego_mz/vaga_detalhe.php?id=' + vagaId;
            const title = 'Vaga de Emprego - Emprego MZ';
            openShareModal(url, title);
        }
        
        // Toast com contagem
        setTimeout(() => {
            showToast('<?php echo $total_vagas; ?> vagas encontradas!', 'info', 2000);
        }, 500);
    </script>
</body>
</html>
```

---

**Última atualização:** 27 de Outubro de 2025
**Versão:** 1.0.0
