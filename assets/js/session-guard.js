/**
 * Session Guard - DESABILITADO TEMPORARIAMENTE
 * Plataforma Emprego MZ
 * 
 * Sistema desabilitado para permitir sessões persistentes.
 * Se precisar reativar, descomentar o código abaixo.
 */

// Sistema de encerramento automático de sessão DESABILITADO
// A sessão agora persiste por 7 dias ou 24h de inatividade
console.log('Session Guard: Sistema de encerramento ao fechar aba está DESABILITADO');

/*
(function() {
    'use strict';
    
    // Flag para detectar navegação interna vs fechar aba
    let isInternalNavigation = false;
    
    // Detectar cliques em links internos
    document.addEventListener('click', function(event) {
        const link = event.target.closest('a');
        if (link && link.href && link.href.includes(window.location.host)) {
            isInternalNavigation = true;
            
            // Resetar flag após navegação
            setTimeout(function() {
                isInternalNavigation = false;
            }, 100);
        }
    });
    
    // Detectar submissão de formulários
    document.addEventListener('submit', function() {
        isInternalNavigation = true;
        
        setTimeout(function() {
            isInternalNavigation = false;
        }, 100);
    });
    
    // Detectar refresh (F5, Ctrl+R)
    window.addEventListener('keydown', function(event) {
        // F5 ou Ctrl+R
        if (event.key === 'F5' || (event.ctrlKey && event.key === 'r')) {
            isInternalNavigation = true;
        }
    });
    
    // Detectar quando a aba está sendo fechada
    window.addEventListener('beforeunload', function(event) {
        // Não encerrar sessão se for navegação interna ou refresh
        if (isInternalNavigation) {
            return;
        }
        
        // Encerrar sessão apenas se a aba estiver sendo fechada
        const url = window.location.origin + '/plataforma_emprego_mz/api/encerrar_sessao.php';
        
        // sendBeacon envia uma requisição POST assíncrona que não é cancelada
        // quando a página é fechada
        if (navigator.sendBeacon) {
            navigator.sendBeacon(url, new Blob([], { type: 'application/json' }));
        } else {
            // Fallback para navegadores antigos (requisição síncrona)
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url, false); // false = síncrono
            try {
                xhr.send();
            } catch(e) {
                // Ignorar erros silenciosamente
            }
        }
    });
    
})();
*/

