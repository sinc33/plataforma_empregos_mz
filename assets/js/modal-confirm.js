/**
 * ==========================================
 * 🎭 SISTEMA DE MODAL DE CONFIRMAÇÃO - VERSÃO SIMPLIFICADA
 * ==========================================
 * Modal customizado profissional para substituir confirm()
 */

(function() {
    'use strict';
    
    let modalElement = null;
    let currentCallback = null;
    
    // Criar o modal no DOM
    function createModal() {
        if (document.getElementById('globalConfirmModal')) {
            return; // Já existe
        }
        
        const modalHTML = `
            <div class="modal-confirm-overlay" id="globalConfirmModal">
                <div class="modal-confirm-container">
                    <div class="modal-confirm-header" id="modalHeader">
                        <div class="modal-confirm-icon" id="modalIcon">
                            <i data-lucide="alert-triangle"></i>
                        </div>
                        <div class="modal-confirm-title-wrapper">
                            <h3 id="modalTitle">Confirmar Ação</h3>
                            <p id="modalSubtitle"></p>
                        </div>
                    </div>

                    <div class="modal-confirm-body">
                        <p id="modalMessage">Tem certeza que deseja continuar?</p>
                        <div class="modal-confirm-highlight" id="modalHighlight" style="display: none;">
                            <strong id="modalHighlightTitle"></strong>
                            <p id="modalHighlightText"></p>
                        </div>
                    </div>

                    <div class="modal-confirm-footer">
                        <button class="modal-confirm-btn modal-confirm-btn-cancel" id="modalCancelBtn">
                            <i data-lucide="x"></i>
                            <span>Cancelar</span>
                        </button>
                        <button class="modal-confirm-btn modal-confirm-btn-confirm" id="modalConfirmBtn">
                            <i data-lucide="check"></i>
                            <span>Confirmar</span>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        modalElement = document.getElementById('globalConfirmModal');
        
        // Configurar event listeners
        setupEventListeners();
    }
    
    // Configurar event listeners
    function setupEventListeners() {
        const cancelBtn = document.getElementById('modalCancelBtn');
        const confirmBtn = document.getElementById('modalConfirmBtn');
        
        if (cancelBtn) {
            cancelBtn.onclick = closeModal;
        }
        
        if (confirmBtn) {
            confirmBtn.onclick = function() {
                console.log('✅ Botão CONFIRMAR clicado!');
                console.log('📌 Tipo do callback:', typeof currentCallback);
                console.log('📌 Callback atual:', currentCallback);
                
                // Guardar callback antes de fechar
                const callbackToExecute = currentCallback;
                
                // Fechar modal primeiro
                closeModal();
                
                // Executar callback depois
                if (callbackToExecute && typeof callbackToExecute === 'function') {
                    console.log('🚀 Executando ação confirmada...');
                    callbackToExecute();
                } else {
                    console.warn('⚠️ Nenhum callback definido');
                }
            };
        }
        
        // Fechar ao clicar fora
        if (modalElement) {
            modalElement.onclick = function(e) {
                if (e.target === modalElement) {
                    closeModal();
                }
            };
        }
        
        // Fechar com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modalElement && modalElement.classList.contains('active')) {
                closeModal();
            }
        });
    }
    
    // Abrir modal
    function openModal(options) {
        console.log('🎭 openModal chamado com:', options);
        
        // Garantir que o modal existe
        if (!modalElement) {
            console.log('⚠️ Modal não existe, criando...');
            createModal();
        }
        
        if (!modalElement) {
            console.error('❌ ERRO: Não foi possível criar o modal!');
            alert('Erro ao abrir modal de confirmação. Deseja continuar mesmo assim?');
            return;
        }
        
        console.log('✅ Modal element encontrado:', modalElement);
        
        const {
            type = 'warning',
            title = 'Confirmar Ação',
            message = 'Tem certeza?',
            confirmText = 'Confirmar',
            cancelText = 'Cancelar',
            confirmIcon = 'check',
            highlightTitle = '',
            highlightText = '',
            onConfirm = null
        } = options;
        
        currentCallback = onConfirm;
        console.log('📌 Callback configurado:', typeof onConfirm);
        
        // Atualizar conteúdo
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalConfirmBtn = document.getElementById('modalConfirmBtn');
        const modalCancelBtn = document.getElementById('modalCancelBtn');
        const modalHighlight = document.getElementById('modalHighlight');
        const modalHighlightTitle = document.getElementById('modalHighlightTitle');
        const modalHighlightText = document.getElementById('modalHighlightText');
        
        if (modalTitle) modalTitle.textContent = title;
        if (modalMessage) modalMessage.textContent = message;
        
        // Atualizar textos dos botões
        if (modalConfirmBtn) {
            const confirmSpan = modalConfirmBtn.querySelector('span');
            if (confirmSpan) confirmSpan.textContent = confirmText;
            
            const confirmIconEl = modalConfirmBtn.querySelector('i');
            if (confirmIconEl) confirmIconEl.setAttribute('data-lucide', confirmIcon);
        }
        
        if (modalCancelBtn) {
            const cancelSpan = modalCancelBtn.querySelector('span');
            if (cancelSpan) cancelSpan.textContent = cancelText;
        }
        
        // Highlight box (opcional)
        if (modalHighlight) {
            if (highlightTitle || highlightText) {
                if (modalHighlightTitle) modalHighlightTitle.textContent = highlightTitle;
                if (modalHighlightText) modalHighlightText.textContent = highlightText;
                modalHighlight.style.display = 'block';
            } else {
                modalHighlight.style.display = 'none';
            }
        }
        
        // Atualizar tipo (classe CSS)
        if (modalElement) {
            modalElement.className = 'modal-confirm-overlay active modal-confirm-' + type;
            document.body.style.overflow = 'hidden';
            console.log('✅ Modal classes aplicadas:', modalElement.className);
            console.log('✅ Modal display:', window.getComputedStyle(modalElement).display);
        } else {
            console.error('❌ modalElement não encontrado ao tentar abrir!');
        }
        
        // Atualizar ícones do Lucide
        if (typeof lucide !== 'undefined') {
            setTimeout(function() {
                lucide.createIcons();
            }, 10);
        }
        
        console.log('🎉 Modal deve estar visível agora!');
    }
    
    // Fechar modal
    function closeModal() {
        console.log('🚪 Fechando modal...');
        if (modalElement) {
            modalElement.classList.remove('active');
            document.body.style.overflow = '';
            console.log('✅ Modal fechado');
        }
        // NÃO limpar o callback aqui - será limpo após execução
        setTimeout(function() {
            currentCallback = null;
            console.log('🧹 Callback limpo após 500ms');
        }, 500);
    }
    
    // Exportar função global
    window.confirmModal = function(options) {
        openModal(options);
    };
    
    // Criar modal quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createModal);
    } else {
        createModal();
    }
    
    console.log('✅ Modal Confirm System Loaded');
    
})();
