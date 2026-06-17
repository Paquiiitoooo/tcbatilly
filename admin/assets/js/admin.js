/**
 * TC BATILLY - Admin Panel JavaScript
 */

// ============================================
// SIDEBAR TOGGLE - FONCTION GLOBALE
// ============================================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (!sidebar) {
        console.error('Sidebar non trouvée');
        return;
    }
    
    const isOpen = sidebar.classList.contains('mobile-open');
    
    if (isOpen) {
        sidebar.classList.remove('mobile-open');
        if (sidebarOverlay) {
            sidebarOverlay.classList.remove('active');
        }
        document.body.style.overflow = '';
    } else {
        sidebar.classList.add('mobile-open');
        if (sidebarOverlay) {
            sidebarOverlay.classList.add('active');
        }
        document.body.style.overflow = 'hidden';
    }
}

// ============================================
// SIDEBAR TOGGLE - INITIALISATION
// ============================================
(function() {
    'use strict';
    
    function initSidebar() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if (!sidebar || !sidebarToggle) {
            return;
        }
        
        // Event listener sur le burger menu (backup)
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
        
        // Overlay click pour fermer
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
        
        // Fermer au clic sur nav items (mobile)
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    sidebar.classList.remove('mobile-open');
                    if (sidebarOverlay) {
                        sidebarOverlay.classList.remove('active');
                    }
                    document.body.style.overflow = '';
                }
            });
        });
        
        // Gérer le resize
        function handleResize() {
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('mobile-open');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('active');
                }
                document.body.style.overflow = '';
            }
        }
        
        handleResize();
        window.addEventListener('resize', handleResize);
    }
    
    // Lancer l'initialisation
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
    
    // Backup
    setTimeout(initSidebar, 100);
})();

// Confirmation de suppression
document.addEventListener('DOMContentLoaded', function() {
    // Gérer les confirmations de suppression
    const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm-delete') || 'Êtes-vous sûr de vouloir supprimer cet élément ?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Auto-hide des alertes après 5 secondes
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Gestion des onglets accessibles
    const tabGroups = document.querySelectorAll('.view-tabs');
    tabGroups.forEach(tablist => {
        const tabs = tablist.querySelectorAll('[role="tab"]');
        const panels = tablist.parentElement.querySelectorAll('.tab-panel');

        function setActiveTab(targetId, focus = false) {
            tabs.forEach(tab => {
                const isActive = tab.id === targetId;
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                tab.classList.toggle('is-active', isActive);
                if (isActive && focus) {
                    tab.focus();
                }
            });

            panels.forEach(panel => {
                const controlledBy = panel.getAttribute('aria-labelledby');
                panel.setAttribute('aria-hidden', controlledBy === targetId ? 'false' : 'true');
            });
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => setActiveTab(tab.id));
            tab.addEventListener('keydown', event => {
                const currentIndex = Array.from(tabs).indexOf(tab);
                let newIndex = null;
                if (event.key === 'ArrowRight') {
                    newIndex = (currentIndex + 1) % tabs.length;
                } else if (event.key === 'ArrowLeft') {
                    newIndex = (currentIndex - 1 + tabs.length) % tabs.length;
                }

                if (newIndex !== null) {
                    event.preventDefault();
                    const newTab = tabs[newIndex];
                    setActiveTab(newTab.id, true);
                }
            });
        });

        // Liens qui forcent le changement d'onglet
        const switchers = tablist.parentElement.querySelectorAll('[data-switch-tab]');
        switchers.forEach(el => {
            el.addEventListener('click', event => {
                const targetTab = el.getAttribute('data-switch-tab');
                const tabEl = tablist.querySelector(`#${targetTab}`);
                if (tabEl) {
                    setActiveTab(targetTab);
                }
            });
        });

        // Initialisation selon l'état présent dans le DOM
        const initiallyActive = tablist.querySelector('[aria-selected="true"]');
        if (initiallyActive) {
            setActiveTab(initiallyActive.id);
        } else if (tabs.length) {
            setActiveTab(tabs[0].id);
        }
    });
});

// Fonction utilitaire pour les requêtes API
async function apiRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };

    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(url, options);
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, error: error.message };
    }
}

// Preview d'image
function previewImage(input, targetId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(targetId).src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Validation de formulaire
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;

    const requiredInputs = form.querySelectorAll('[required]');
    let isValid = true;

    requiredInputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.style.borderColor = '#ef4444';
        } else {
            input.style.borderColor = '';
        }
    });

    return isValid;
}

// Notification toast
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
        font-weight: 600;
    `;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Animations CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

