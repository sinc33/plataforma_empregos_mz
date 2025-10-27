/**
 * ✨ EMPREGO MZ - MODERN FEATURES
 * JavaScript para funcionalidades modernas e interativas
 */

// ============================================
// 🎨 INICIALIZAÇÃO
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    initAnimations();
    initFavorites();
    initSalarySlider();
    initAutocomplete();
    initThemeToggle();
    initShareModal();
    initSkeletonLoading();
    initSmoothScroll();
    initFormValidation();
});

// ============================================
// ✨ ANIMAÇÕES DE ENTRADA
// ============================================
function initAnimations() {
    // Adicionar animações aos elementos conforme aparecem na viewport
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.classList.add('fade-in');
                }, index * 100);
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observar cards de vagas
    document.querySelectorAll('.vaga-card, .categoria-card, .solucao-card').forEach(card => {
        observer.observe(card);
    });
}

// ============================================
// ❤️ SISTEMA DE FAVORITOS
// ============================================
const FavoriteSystem = {
    storageKey: 'empregoMZ_favorites',

    init() {
        this.loadFavorites();
        this.addFavoriteButtons();
        this.updateFavoriteCounts();
    },

    loadFavorites() {
        const favorites = localStorage.getItem(this.storageKey);
        return favorites ? JSON.parse(favorites) : [];
    },

    saveFavorites(favorites) {
        localStorage.setItem(this.storageKey, JSON.stringify(favorites));
    },

    isFavorited(vagaId) {
        const favorites = this.loadFavorites();
        return favorites.includes(vagaId);
    },

    toggle(vagaId) {
        let favorites = this.loadFavorites();
        const index = favorites.indexOf(vagaId);
        
        if (index === -1) {
            favorites.push(vagaId);
            showToast('Vaga adicionada aos favoritos! ❤️', 'success');
        } else {
            favorites.splice(index, 1);
            showToast('Vaga removida dos favoritos', 'info');
        }
        
        this.saveFavorites(favorites);
        return index === -1; // Retorna true se foi favoritado
    },

    addFavoriteButtons() {
        // Adicionar botões de favorito em todos os cards de vaga
        document.querySelectorAll('.vaga-card').forEach(card => {
            const vagaId = this.getVagaIdFromCard(card);
            if (!vagaId) return;

            // Verificar se já existe botão de favorito
            if (card.querySelector('.btn-favorite')) return;

            const favoriteBtn = document.createElement('button');
            favoriteBtn.className = 'btn-favorite';
            favoriteBtn.setAttribute('aria-label', 'Favoritar vaga');
            favoriteBtn.innerHTML = '<i data-lucide="heart" style="width: 20px; height: 20px;"></i>';
            
            if (this.isFavorited(vagaId)) {
                favoriteBtn.classList.add('favorited');
                favoriteBtn.innerHTML = '<i data-lucide="heart" style="width: 20px; height: 20px; fill: currentColor;"></i>';
            }

            favoriteBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const wasFavorited = this.toggle(vagaId);
                
                if (wasFavorited) {
                    favoriteBtn.classList.add('favorited');
                    favoriteBtn.innerHTML = '<i data-lucide="heart" style="width: 20px; height: 20px; fill: currentColor;"></i>';
                } else {
                    favoriteBtn.classList.remove('favorited');
                    favoriteBtn.innerHTML = '<i data-lucide="heart" style="width: 20px; height: 20px;"></i>';
                }
                
                // Re-inicializar ícones Lucide
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });

            // Adicionar o botão ao card (no canto superior direito)
            const vagaTop = card.querySelector('.vaga-top') || card.querySelector('.vaga-header');
            if (vagaTop) {
                vagaTop.style.position = 'relative';
                favoriteBtn.style.position = 'absolute';
                favoriteBtn.style.top = '0';
                favoriteBtn.style.right = '0';
                vagaTop.appendChild(favoriteBtn);
            }
        });

        // Re-inicializar ícones Lucide
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    },

    getVagaIdFromCard(card) {
        // Tentar extrair ID de vários possíveis atributos/links
        const link = card.querySelector('a[href*="vaga_detalhe.php?id="]');
        if (link) {
            const match = link.href.match(/id=(\d+)/);
            return match ? match[1] : null;
        }
        
        // Alternativa: data-vaga-id
        return card.getAttribute('data-vaga-id');
    },

    updateFavoriteCounts() {
        const favorites = this.loadFavorites();
        document.querySelectorAll('.favorite-count').forEach(el => {
            el.textContent = favorites.length;
        });
    }
};

function initFavorites() {
    FavoriteSystem.init();
}

// ============================================
// 🎚️ SLIDER DE SALÁRIO
// ============================================
function initSalarySlider() {
    const minInput = document.querySelector('input[name="salario_min"]');
    const maxInput = document.querySelector('input[name="salario_max"]');
    
    if (!minInput || !maxInput) return;

    // Criar container do slider
    const sliderContainer = document.createElement('div');
    sliderContainer.className = 'salary-slider-container';
    sliderContainer.innerHTML = `
        <div class="salary-slider">
            <input type="range" min="0" max="100000" step="1000" value="${minInput.value || 0}" id="salaryMin">
            <input type="range" min="0" max="100000" step="1000" value="${maxInput.value || 100000}" id="salaryMax">
        </div>
        <div class="salary-values">
            <span id="minValue">${formatCurrency(minInput.value || 0)}</span>
            <span id="maxValue">${formatCurrency(maxInput.value || 100000)}</span>
        </div>
    `;

    // Inserir antes dos inputs originais e escondê-los
    const parent = minInput.parentElement;
    parent.style.display = 'none';
    parent.parentElement.insertBefore(sliderContainer, parent);

    const salaryMin = document.getElementById('salaryMin');
    const salaryMax = document.getElementById('salaryMax');
    const minValue = document.getElementById('minValue');
    const maxValue = document.getElementById('maxValue');

    function updateValues() {
        let min = parseInt(salaryMin.value);
        let max = parseInt(salaryMax.value);

        // Garantir que min não ultrapasse max
        if (min > max - 5000) {
            min = max - 5000;
            salaryMin.value = min;
        }

        // Atualizar inputs originais
        minInput.value = min;
        maxInput.value = max;

        // Atualizar display
        minValue.textContent = formatCurrency(min);
        maxValue.textContent = formatCurrency(max);
    }

    salaryMin.addEventListener('input', updateValues);
    salaryMax.addEventListener('input', updateValues);

    updateValues();
}

function formatCurrency(value) {
    if (!value || value == 0) return '0 MT';
    return new Intl.NumberFormat('pt-MZ', {
        style: 'currency',
        currency: 'MZN',
        minimumFractionDigits: 0
    }).format(value).replace('MZN', 'MT');
}

// ============================================
// 🔔 SISTEMA DE NOTIFICAÇÕES TOAST
// ============================================
function showToast(message, type = 'info', duration = 3000) {
    // Criar container se não existir
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    // Criar toast
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icons = {
        success: 'check-circle',
        error: 'x-circle',
        warning: 'alert-triangle',
        info: 'info'
    };

    const titles = {
        success: 'Sucesso!',
        error: 'Erro!',
        warning: 'Atenção!',
        info: 'Informação'
    };

    toast.innerHTML = `
        <i data-lucide="${icons[type]}" class="toast-icon"></i>
        <div class="toast-content">
            <div class="toast-title">${titles[type]}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" aria-label="Fechar">
            <i data-lucide="x" style="width: 16px; height: 16px;"></i>
        </button>
        <div class="toast-progress"></div>
    `;

    container.appendChild(toast);

    // Re-inicializar ícones Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Botão de fechar
    toast.querySelector('.toast-close').addEventListener('click', () => {
        removeToast(toast);
    });

    // Auto-remover
    setTimeout(() => {
        removeToast(toast);
    }, duration);
}

function removeToast(toast) {
    toast.style.animation = 'slideIn 0.4s reverse';
    setTimeout(() => {
        toast.remove();
    }, 400);
}

// ============================================
// 🔍 AUTOCOMPLETE NA BUSCA
// ============================================
function initAutocomplete() {
    const searchInputs = document.querySelectorAll('input[name="q"], .search-input');
    
    searchInputs.forEach(input => {
        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        wrapper.style.width = '100%';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const autocomplete = document.createElement('div');
        autocomplete.className = 'search-autocomplete';
        wrapper.appendChild(autocomplete);

        let timeout;
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            const query = this.value.trim();

            if (query.length < 2) {
                autocomplete.classList.remove('active');
                return;
            }

            timeout = setTimeout(() => {
                fetchSuggestions(query, autocomplete);
            }, 300);
        });

        // Fechar ao clicar fora
        document.addEventListener('click', (e) => {
            if (!wrapper.contains(e.target)) {
                autocomplete.classList.remove('active');
            }
        });
    });
}

function fetchSuggestions(query, autocomplete) {
    // Sugestões de exemplo (você pode substituir por uma chamada AJAX real)
    const suggestions = [
        { title: 'Desenvolvedor PHP', subtitle: 'Informática/TI', icon: 'code' },
        { title: 'Gerente de Vendas', subtitle: 'Comercial/Vendas', icon: 'briefcase' },
        { title: 'Assistente Administrativo', subtitle: 'Administrativa', icon: 'file-text' },
        { title: 'Designer Gráfico', subtitle: 'Marketing', icon: 'palette' },
        { title: 'Contador', subtitle: 'Finanças', icon: 'calculator' }
    ].filter(item => 
        item.title.toLowerCase().includes(query.toLowerCase()) ||
        item.subtitle.toLowerCase().includes(query.toLowerCase())
    );

    if (suggestions.length === 0) {
        autocomplete.classList.remove('active');
        return;
    }

    autocomplete.innerHTML = suggestions.map(item => `
        <div class="autocomplete-item" onclick="selectSuggestion('${item.title}')">
            <i data-lucide="${item.icon}" class="autocomplete-icon" style="width: 20px; height: 20px;"></i>
            <div class="autocomplete-text">
                <div class="autocomplete-title">${item.title}</div>
                <div class="autocomplete-subtitle">${item.subtitle}</div>
            </div>
        </div>
    `).join('');

    autocomplete.classList.add('active');

    // Re-inicializar ícones Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function selectSuggestion(value) {
    const input = document.querySelector('input[name="q"]');
    if (input) {
        input.value = value;
        document.querySelector('.search-autocomplete').classList.remove('active');
    }
}

// ============================================
// 🌓 MODO ESCURO/CLARO
// ============================================
function initThemeToggle() {
    // Criar botão de toggle se não existir
    if (!document.querySelector('.theme-toggle')) {
        const toggle = document.createElement('button');
        toggle.className = 'theme-toggle';
        toggle.setAttribute('aria-label', 'Alternar tema');
        toggle.innerHTML = '<i data-lucide="moon" style="width: 24px; height: 24px;"></i>';
        document.body.appendChild(toggle);

        // Re-inicializar ícones Lucide
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    const toggle = document.querySelector('.theme-toggle');
    const currentTheme = localStorage.getItem('theme') || 'light';

    // Aplicar tema salvo
    if (currentTheme === 'dark') {
        document.body.classList.add('dark-mode');
        toggle.innerHTML = '<i data-lucide="sun" style="width: 24px; height: 24px;"></i>';
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    toggle.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        
        const isDark = document.body.classList.contains('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        
        toggle.innerHTML = isDark 
            ? '<i data-lucide="sun" style="width: 24px; height: 24px;"></i>'
            : '<i data-lucide="moon" style="width: 24px; height: 24px;"></i>';

        // Re-inicializar ícones Lucide
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        showToast(`Modo ${isDark ? 'escuro' : 'claro'} ativado`, 'success');
    });
}

// ============================================
// 📱 MODAL DE COMPARTILHAR
// ============================================
function initShareModal() {
    // Criar modal se não existir
    if (!document.querySelector('.share-modal')) {
        const modal = document.createElement('div');
        modal.className = 'share-modal';
        modal.innerHTML = `
            <div class="share-content">
                <div class="share-header">
                    <h3 class="share-title">Compartilhar Vaga</h3>
                    <button class="share-close" aria-label="Fechar">&times;</button>
                </div>
                <div class="share-buttons">
                    <a href="#" class="share-btn whatsapp" data-share="whatsapp">
                        <i data-lucide="message-circle" style="width: 32px; height: 32px;"></i>
                        <span class="share-btn-label">WhatsApp</span>
                    </a>
                    <a href="#" class="share-btn linkedin" data-share="linkedin">
                        <i data-lucide="linkedin" style="width: 32px; height: 32px;"></i>
                        <span class="share-btn-label">LinkedIn</span>
                    </a>
                    <a href="#" class="share-btn facebook" data-share="facebook">
                        <i data-lucide="facebook" style="width: 32px; height: 32px;"></i>
                        <span class="share-btn-label">Facebook</span>
                    </a>
                    <a href="#" class="share-btn twitter" data-share="twitter">
                        <i data-lucide="twitter" style="width: 32px; height: 32px;"></i>
                        <span class="share-btn-label">X (Twitter)</span>
                    </a>
                    <a href="#" class="share-btn" data-share="email">
                        <i data-lucide="mail" style="width: 32px; height: 32px;"></i>
                        <span class="share-btn-label">E-mail</span>
                    </a>
                    <button class="share-btn" data-share="copy">
                        <i data-lucide="link" style="width: 32px; height: 32px;"></i>
                        <span class="share-btn-label">Copiar Link</span>
                    </button>
                </div>
                <div class="share-link-container">
                    <input type="text" class="share-link-input" readonly>
                    <button class="share-copy-btn">Copiar</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Re-inicializar ícones Lucide
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Event listeners
        modal.querySelector('.share-close').addEventListener('click', () => {
            modal.classList.remove('active');
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });

        // Compartilhar
        modal.querySelectorAll('[data-share]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const type = btn.getAttribute('data-share');
                const url = modal.querySelector('.share-link-input').value;
                const title = document.title;
                
                shareVaga(type, url, title);
            });
        });

        // Copiar link
        modal.querySelector('.share-copy-btn').addEventListener('click', function() {
            const input = modal.querySelector('.share-link-input');
            input.select();
            document.execCommand('copy');
            
            this.classList.add('copied');
            this.textContent = '✓ Copiado!';
            
            showToast('Link copiado para a área de transferência!', 'success');
            
            setTimeout(() => {
                this.classList.remove('copied');
                this.textContent = 'Copiar';
            }, 2000);
        });
    }
}

function openShareModal(url, title) {
    const modal = document.querySelector('.share-modal');
    const input = modal.querySelector('.share-link-input');
    
    input.value = url || window.location.href;
    modal.classList.add('active');
}

function shareVaga(type, url, title) {
    const encodedUrl = encodeURIComponent(url);
    const encodedTitle = encodeURIComponent(title);
    
    const shareUrls = {
        whatsapp: `https://wa.me/?text=${encodedTitle}%20${encodedUrl}`,
        linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
        facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`,
        twitter: `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}`,
        email: `mailto:?subject=${encodedTitle}&body=${encodedUrl}`,
        copy: null
    };

    if (type === 'copy') {
        navigator.clipboard.writeText(url).then(() => {
            showToast('Link copiado!', 'success');
        });
    } else if (shareUrls[type]) {
        window.open(shareUrls[type], '_blank', 'width=600,height=400');
    }
}

// Adicionar aos botões de compartilhar existentes
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-compartilhar')) {
        e.preventDefault();
        openShareModal(window.location.href, document.title);
    }
});

// ============================================
// 💀 SKELETON LOADING
// ============================================
function initSkeletonLoading() {
    // Adicionar skeleton ao carregar mais conteúdo
    window.showSkeletonLoading = function(count = 3) {
        const container = document.querySelector('.vagas-area') || document.querySelector('main');
        if (!container) return;

        for (let i = 0; i < count; i++) {
            const skeleton = document.createElement('div');
            skeleton.className = 'skeleton-card';
            skeleton.innerHTML = `
                <div class="skeleton-header">
                    <div class="skeleton skeleton-avatar"></div>
                    <div style="flex: 1;">
                        <div class="skeleton skeleton-title"></div>
                        <div class="skeleton skeleton-subtitle"></div>
                    </div>
                </div>
                <div class="skeleton skeleton-text"></div>
                <div class="skeleton skeleton-text"></div>
                <div class="skeleton skeleton-text"></div>
                <div class="skeleton skeleton-button"></div>
            `;
            container.appendChild(skeleton);
        }

        return () => {
            document.querySelectorAll('.skeleton-card').forEach(el => el.remove());
        };
    };
}

// ============================================
// 🎯 SCROLL SUAVE
// ============================================
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#') return;
            
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// ============================================
// ✅ VALIDAÇÃO DE FORMULÁRIOS
// ============================================
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validar campos obrigatórios
            this.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    showFieldError(field, 'Este campo é obrigatório');
                } else {
                    field.classList.remove('error');
                    hideFieldError(field);
                }
            });
            
            // Validar e-mail
            this.querySelectorAll('[type="email"]').forEach(field => {
                if (field.value && !isValidEmail(field.value)) {
                    isValid = false;
                    field.classList.add('error');
                    showFieldError(field, 'E-mail inválido');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showToast('Por favor, corrija os erros no formulário', 'error');
            }
        });
        
        // Remover erro ao digitar
        form.querySelectorAll('input, textarea, select').forEach(field => {
            field.addEventListener('input', function() {
                this.classList.remove('error');
                hideFieldError(this);
            });
        });
    });
}

function showFieldError(field, message) {
    let errorEl = field.parentElement.querySelector('.form-error');
    if (!errorEl) {
        errorEl = document.createElement('div');
        errorEl.className = 'form-error';
        field.parentElement.appendChild(errorEl);
    }
    errorEl.innerHTML = `<i data-lucide="alert-circle" style="width: 14px; height: 14px;"></i> ${message}`;
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function hideFieldError(field) {
    const errorEl = field.parentElement.querySelector('.form-error');
    if (errorEl) {
        errorEl.remove();
    }
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// ============================================
// 🎁 FUNÇÕES UTILITÁRIAS GLOBAIS
// ============================================

// Expor funções globalmente
window.showToast = showToast;
window.openShareModal = openShareModal;
window.FavoriteSystem = FavoriteSystem;

// ============================================
// 📊 ANALYTICS (Opcional)
// ============================================
function trackEvent(category, action, label) {
    // Integração com Google Analytics ou similar
    if (typeof gtag !== 'undefined') {
        gtag('event', action, {
            'event_category': category,
            'event_label': label
        });
    }
}

// ============================================
// 🚀 PERFORMANCE - Lazy Loading de Imagens
// ============================================
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });

    document.querySelectorAll('img[data-src]').forEach(img => {
        imageObserver.observe(img);
    });
}

// ============================================
// 📱 PWA - Service Worker (Opcional)
// ============================================
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        // Descomente para ativar PWA
        // navigator.serviceWorker.register('/sw.js');
    });
}

console.log('✨ Emprego MZ - Modern Features carregado com sucesso!');
