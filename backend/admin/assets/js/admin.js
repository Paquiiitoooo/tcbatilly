/**
 * TC BATILLY - Admin Panel JavaScript
 */

// ============================================
// SIDEBAR TOGGLE - SOLUTION ULTRA SIMPLE
// ============================================
(function() {
    'use strict';
    
    // Fonction d'initialisation
    function initSidebar() {
        console.log('=== INIT SIDEBAR ===');
        
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        console.log('Toggle:', sidebarToggle);
        console.log('Sidebar:', sidebar);
        console.log('Overlay:', sidebarOverlay);
        
        if (!sidebarToggle) {
            console.error('❌ BURGER MENU NON TROUVÉ!');
            return;
        }
        
        if (!sidebar) {
            console.error('❌ SIDEBAR NON TROUVÉE!');
            return;
        }
        
        console.log('✅ Éléments trouvés');
        
        // Fonctions d'ouverture/fermeture
        function openSidebar() {
            console.log('Ouverture sidebar');
            sidebar.classList.add('mobile-open');
            if (sidebarOverlay) {
                sidebarOverlay.classList.add('active');
            }
            document.body.style.overflow = 'hidden';
            sidebarToggle.classList.add('active');
        }
        
        function closeSidebar() {
            console.log('Fermeture sidebar');
            sidebar.classList.remove('mobile-open');
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('active');
            }
            document.body.style.overflow = '';
            sidebarToggle.classList.remove('active');
        }
        
        // Event listener sur le burger menu
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('CLICK SUR BURGER MENU!');
            
            if (sidebar.classList.contains('mobile-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
        
        // Overlay click
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                closeSidebar();
            });
        }
        
        // Fermer au clic sur nav items (mobile)
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    closeSidebar();
                }
            });
        });
        
        // Gérer le resize - le CSS gère l'affichage
        function handleResize() {
            if (window.innerWidth >= 768) {
                closeSidebar();
            }
        }
        
        handleResize();
        window.addEventListener('resize', handleResize);
        
        console.log('✅ Sidebar initialisée');
    }
    
    // Lancer l'initialisation
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
    
    // Backup après 100ms
    setTimeout(initSidebar, 100);
    setTimeout(initSidebar, 500);
})();

// ============================================
// CONFIRMATION DE SUPPRESSION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm-delete') || 'Êtes-vous sûr de vouloir supprimer cet élément ?';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
});

// ============================================
// FORMULAIRE DE CONNEXION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            
            if (!username.value.trim() || !password.value.trim()) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs');
                return false;
            }
        });
    }
});
