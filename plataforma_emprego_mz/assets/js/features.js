/**
 * 🎯 FEATURES.JS - Sistema de Funcionalidades
 * Favoritos, Toast e Compartilhar
 * Não requer modificações no HTML
 */

(function() {
    'use strict';

    // ============================================
    // ❤️ SISTEMA DE FAVORITOS
    // ============================================
    
    const FavoritesSystem = {
        storageKey: 'empregoMZ_favorites',
        
        init() {
            this.addFavoriteButtons();
            this.loadFavorites();
        },
        
        // Carregar favoritos do localStorage
        loadFavorites() {
            const favorites = localStorage.getItem(this.storageKey);
            return favorites ? JSON.parse(favorites) : [];
        },
        
        // Salvar favoritos no localStorage
        saveFavorites(favorites) {
            localStorage.setItem(this.storageKey, JSON.stringify(favorites));
        },
        
        // Verificar se vaga está favoritada
        isFavorited(vagaId) {
            const favorites = this.loadFavorites();
            return favorites.includes(String(vagaId));
        },
        
        // Toggle favorito
        toggle(vagaId) {
            let favorites = this.loadFavorites();
            const id = String(vagaId);
            const index = favorites.indexOf(id);
            
            if (index === -1) {
                // Adicionar aos favoritos
                favorites.push(id);
                showToast('Vaga adicionada aos favoritos! ❤️', 'success');
                return true;
            } else {
                // Remover dos favoritos
                favorites.splice(index, 1);
                showToast('Vaga removida dos favoritos', 'info');
                return false;
            }
            
            this.saveFavorites(favorites);
        },
        
        // Adicionar botões de favorito em todos os cards
        addFavoriteButtons() {
            document.querySelectorAll('.vaga-card').forEach(card => {
                // Verificar se já tem botão
                if (card.querySelector('.btn-favorite')) return;
                
                // Extrair ID da vaga
                const vagaId = this.getVagaId(card);
                if (!vagaId) return;
                
                // Criar botão
                const button = document.createElement('button');
                button.className = 'btn-favorite';
                button.setAttribute('aria-label', 'Favoritar vaga');
                button.setAttribute('data-vaga-id', vagaId);
                
                // SVG do coração
                button.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                `;
                
                // Verificar se já está favoritado
                if (this.isFavorited(vagaId)) {
                    button.classList.add('favorited');
                }
                
                // Event listener
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const isFavorited = this.toggle(vagaId);
                    
                    if (isFavorited) {
                        button.classList.add('favorited');
                    } else {
                        button.classList.remove('favorited');
                    }
                    
                    this.saveFavorites(this.loadFavorites());
                });
                
                // Adicionar ao card
                const header = card.querySelector('.vaga-header') || card.querySelector('.vaga-top') || card;
                if (header.style.position !== 'relative' && header.style.position !== 'absolute') {
                    header.style.position = 'relative';
                }
                header.appendChild(button);
            });
        },
        
        // Extrair ID da vaga do card
        getVagaId(card) {
            // Tentar pegar de data-vaga-id
            let id = card.getAttribute('data-vaga-id');
            if (id) return id;
            
            // Tentar extrair de links
            const link = card.querySelector('a[href*="vaga_detalhe.php?id="]') || 
                         card.querySelector('a[href*="id="]');
            if (link) {
                const match = link.href.match(/id=(\d+)/);
                if (match) return match[1];
            }
            
            return null;
        },
        
        // Contar favoritos
        count() {
            return this.loadFavorites().length;
        }
    };

    // ============================================
    // 🔔 SISTEMA DE NOTIFICAÇÕES TOAST
    // ============================================
    
    const ToastSystem = {
        container: null,
        
        init() {
            // Criar container se não existir
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.className = 'toast-container';
                document.body.appendChild(this.container);
            }
        },
        
        show(message, type = 'info', duration = 3000) {
            this.init();
            
            // Criar toast
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            // Ícones por tipo
            const icons = {
                success: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>',
                error: '<circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line>',
                warning: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>',
                info: '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>'
            };
            
            const titles = {
                success: 'Sucesso!',
                error: 'Erro!',
                warning: 'Atenção!',
                info: 'Informação'
            };
            
            toast.innerHTML = `
                <div class="toast-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        ${icons[type]}
                    </svg>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${titles[type]}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" aria-label="Fechar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <div class="toast-progress animating"></div>
            `;
            
            // Adicionar ao container
            this.container.appendChild(toast);
            
            // Botão de fechar
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.addEventListener('click', () => {
                this.remove(toast);
            });
            
            // Auto-remover
            setTimeout(() => {
                this.remove(toast);
            }, duration);
            
            return toast;
        },
        
        remove(toast) {
            toast.classList.add('hiding');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 400);
        }
    };
    
    // Função global para usar no HTML
    window.showToast = function(message, type = 'info', duration = 3000) {
        return ToastSystem.show(message, type, duration);
    };

    // ============================================
    // 📱 SISTEMA DE COMPARTILHAR
    // ============================================
    
    const ShareSystem = {
        init() {
            // Adicionar menus de compartilhar
            document.querySelectorAll('.btn-compartilhar').forEach(btn => {
                this.setupShareButton(btn);
            });
        },
        
        setupShareButton(button) {
            // Verificar se já tem menu
            if (button.nextElementSibling?.classList.contains('share-menu')) return;
            
            // Criar menu
            const menu = this.createMenu();
            
            // Adicionar após o botão
            button.parentNode.style.position = 'relative';
            button.parentNode.insertBefore(menu, button.nextSibling);
            
            // Toggle menu
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const isActive = menu.classList.contains('active');
                
                // Fechar todos os menus
                document.querySelectorAll('.share-menu').forEach(m => {
                    m.classList.remove('active');
                });
                document.querySelectorAll('.btn-compartilhar').forEach(b => {
                    b.classList.remove('active');
                });
                
                // Toggle este menu
                if (!isActive) {
                    menu.classList.add('active');
                    button.classList.add('active');
                }
            });
            
            // Fechar ao clicar fora
            document.addEventListener('click', (e) => {
                if (!button.contains(e.target) && !menu.contains(e.target)) {
                    menu.classList.remove('active');
                    button.classList.remove('active');
                }
            });
            
            // Setup ações
            this.setupMenuActions(menu, button);
        },
        
        createMenu() {
            const menu = document.createElement('div');
            menu.className = 'share-menu';
            menu.innerHTML = `
                <a href="#" class="share-menu-item whatsapp" data-action="whatsapp">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                    <span class="share-menu-text">WhatsApp</span>
                </a>
                <a href="#" class="share-menu-item linkedin" data-action="linkedin">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path>
                        <rect x="2" y="9" width="4" height="12"></rect>
                        <circle cx="4" cy="4" r="2"></circle>
                    </svg>
                    <span class="share-menu-text">LinkedIn</span>
                </a>
                <a href="#" class="share-menu-item email" data-action="email">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <span class="share-menu-text">Email</span>
                </a>
                <div class="share-menu-divider"></div>
                <a href="#" class="share-menu-item copy" data-action="copy">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                    </svg>
                    <span class="share-menu-text">Copiar link</span>
                </a>
            `;
            return menu;
        },
        
        setupMenuActions(menu, button) {
            menu.querySelectorAll('.share-menu-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    const action = item.getAttribute('data-action');
                    const url = this.getShareUrl(button);
                    const title = this.getShareTitle(button);
                    
                    this.share(action, url, title, item);
                    
                    // Fechar menu
                    setTimeout(() => {
                        menu.classList.remove('active');
                        button.classList.remove('active');
                    }, 300);
                });
            });
        },
        
        getShareUrl(button) {
            // Tentar pegar URL do card mais próximo
            const card = button.closest('.vaga-card');
            if (card) {
                const link = card.querySelector('a[href*="vaga_detalhe.php"]');
                if (link) {
                    return link.href;
                }
            }
            return window.location.href;
        },
        
        getShareTitle(button) {
            const card = button.closest('.vaga-card');
            if (card) {
                const title = card.querySelector('.vaga-title');
                if (title) {
                    return title.textContent.trim();
                }
            }
            return document.title;
        },
        
        share(action, url, title, item) {
            const encodedUrl = encodeURIComponent(url);
            const encodedTitle = encodeURIComponent(title);
            
            switch(action) {
                case 'whatsapp':
                    window.open(`https://wa.me/?text=${encodedTitle}%20${encodedUrl}`, '_blank');
                    showToast('Abrindo WhatsApp...', 'info', 2000);
                    break;
                    
                case 'linkedin':
                    window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`, '_blank');
                    showToast('Abrindo LinkedIn...', 'info', 2000);
                    break;
                    
                case 'email':
                    window.location.href = `mailto:?subject=${encodedTitle}&body=${encodedUrl}`;
                    showToast('Abrindo seu cliente de email...', 'info', 2000);
                    break;
                    
                case 'copy':
                    this.copyToClipboard(url, item);
                    break;
            }
        },
        
        copyToClipboard(text, item) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast('Link copiado para a área de transferência!', 'success');
                    
                    // Feedback visual
                    item.classList.add('copied');
                    setTimeout(() => {
                        item.classList.remove('copied');
                    }, 2000);
                }).catch(() => {
                    this.fallbackCopy(text);
                });
            } else {
                this.fallbackCopy(text);
            }
        },
        
        fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            
            try {
                document.execCommand('copy');
                showToast('Link copiado!', 'success');
            } catch (err) {
                showToast('Erro ao copiar link', 'error');
            }
            
            document.body.removeChild(textarea);
        }
    };

    // ============================================
    // 🚀 INICIALIZAÇÃO
    // ============================================
    
    function init() {
        // Aguardar DOM carregar
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initFeatures);
        } else {
            initFeatures();
        }
    }
    
    function initFeatures() {
        FavoritesSystem.init();
        ToastSystem.init();
        ShareSystem.init();
        
        console.log('✨ Features carregadas: Favoritos, Toast e Compartilhar');
    }
    
    // Iniciar
    init();
    
    // Expor para uso global
    window.FavoritesSystem = FavoritesSystem;
    window.ToastSystem = ToastSystem;
    window.ShareSystem = ShareSystem;

})();
