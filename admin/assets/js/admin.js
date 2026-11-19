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

// Image cropper for admin uploads
document.addEventListener('DOMContentLoaded', function() {
    const cropInputs = document.querySelectorAll('[data-crop-input]');
    if (!cropInputs.length) return;

    const modal = document.createElement('div');
    modal.className = 'crop-modal';
    modal.innerHTML = `
        <div class="crop-dialog" role="dialog" aria-modal="true" aria-label="Recadrer l image">
            <div class="crop-dialog-header">
                <h2>Recadrer l image</h2>
                <button type="button" class="btn-ghost crop-close">Fermer</button>
            </div>
            <div class="crop-stage">
                <div class="crop-frame">
                    <img class="crop-image" alt="">
                </div>
            </div>
            <div class="crop-controls">
                <label for="cropZoom">Zoom</label>
                <input type="range" id="cropZoom" min="1" max="3" step="0.01" value="1">
            </div>
            <div class="form-actions crop-actions">
                <button type="button" class="btn-primary crop-apply">Utiliser cette image</button>
                <button type="button" class="btn-secondary crop-cancel">Annuler</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    const frame = modal.querySelector('.crop-frame');
    const img = modal.querySelector('.crop-image');
    const zoomInput = modal.querySelector('#cropZoom');
    const applyBtn = modal.querySelector('.crop-apply');
    const cancelBtns = modal.querySelectorAll('.crop-close, .crop-cancel');

    let activeInput = null;
    let targetInput = null;
    let preview = null;
    let outputWidth = 800;
    let outputHeight = 800;
    let outputType = 'image/jpeg';
    let baseScale = 1;
    let zoom = 1;
    let offsetX = 0;
    let offsetY = 0;
    let dragging = false;
    let lastX = 0;
    let lastY = 0;

    function constrainOffsets() {
        const frameRect = frame.getBoundingClientRect();
        const scale = baseScale * zoom;
        const imageWidth = img.naturalWidth * scale;
        const imageHeight = img.naturalHeight * scale;
        const maxX = Math.max(0, (imageWidth - frameRect.width) / 2);
        const maxY = Math.max(0, (imageHeight - frameRect.height) / 2);
        offsetX = Math.max(-maxX, Math.min(maxX, offsetX));
        offsetY = Math.max(-maxY, Math.min(maxY, offsetY));
    }

    function renderCropImage() {
        const scale = baseScale * zoom;
        const frameRect = frame.getBoundingClientRect();
        const imageWidth = img.naturalWidth * scale;
        const imageHeight = img.naturalHeight * scale;
        constrainOffsets();
        img.style.width = `${imageWidth}px`;
        img.style.height = `${imageHeight}px`;
        img.style.left = `${frameRect.width / 2 + offsetX - imageWidth / 2}px`;
        img.style.top = `${frameRect.height / 2 + offsetY - imageHeight / 2}px`;
    }

    function openCropper(input, dataUrl) {
        activeInput = input;
        targetInput = document.getElementById(input.dataset.cropTarget);
        preview = document.getElementById(input.dataset.cropPreview);
        outputWidth = parseInt(input.dataset.cropWidth || '800', 10);
        outputHeight = parseInt(input.dataset.cropHeight || '800', 10);
        outputType = input.dataset.cropOutput || 'image/jpeg';

        const aspect = parseFloat(input.dataset.cropAspect || '1');
        frame.style.aspectRatio = String(aspect);
        img.onload = function() {
            const frameRect = frame.getBoundingClientRect();
            baseScale = Math.max(frameRect.width / img.naturalWidth, frameRect.height / img.naturalHeight);
            zoom = 1;
            offsetX = 0;
            offsetY = 0;
            zoomInput.value = '1';
            renderCropImage();
        };
        img.src = dataUrl;
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeCropper(clearInput) {
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
        if (clearInput && activeInput) {
            activeInput.value = '';
        }
        activeInput = null;
    }

    cropInputs.forEach(input => {
        input.addEventListener('change', function() {
            const file = input.files && input.files[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                showToast('Merci de choisir une image.', 'error');
                input.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = event => openCropper(input, event.target.result);
            reader.readAsDataURL(file);
        });
    });

    zoomInput.addEventListener('input', function() {
        zoom = parseFloat(zoomInput.value);
        renderCropImage();
    });

    frame.addEventListener('pointerdown', function(event) {
        dragging = true;
        lastX = event.clientX;
        lastY = event.clientY;
        frame.setPointerCapture(event.pointerId);
    });

    frame.addEventListener('pointermove', function(event) {
        if (!dragging) return;
        offsetX += event.clientX - lastX;
        offsetY += event.clientY - lastY;
        lastX = event.clientX;
        lastY = event.clientY;
        renderCropImage();
    });

    frame.addEventListener('pointerup', function(event) {
        dragging = false;
        frame.releasePointerCapture(event.pointerId);
    });

    frame.addEventListener('pointercancel', function() {
        dragging = false;
    });

    applyBtn.addEventListener('click', function() {
        if (!targetInput || !img.naturalWidth) return;
        const frameRect = frame.getBoundingClientRect();
        const scale = baseScale * zoom;
        const imageWidth = img.naturalWidth * scale;
        const imageHeight = img.naturalHeight * scale;
        const imageLeft = frameRect.width / 2 + offsetX - imageWidth / 2;
        const imageTop = frameRect.height / 2 + offsetY - imageHeight / 2;
        const sourceX = Math.max(0, (0 - imageLeft) / scale);
        const sourceY = Math.max(0, (0 - imageTop) / scale);
        const sourceWidth = Math.min(img.naturalWidth - sourceX, frameRect.width / scale);
        const sourceHeight = Math.min(img.naturalHeight - sourceY, frameRect.height / scale);

        const canvas = document.createElement('canvas');
        canvas.width = outputWidth;
        canvas.height = outputHeight;
        const context = canvas.getContext('2d');
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.drawImage(img, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, outputWidth, outputHeight);
        const dataUrl = canvas.toDataURL(outputType, 0.92);
        targetInput.value = dataUrl;

        if (preview) {
            preview.src = dataUrl;
            preview.hidden = false;
        }

        closeCropper(false);
    });

    cancelBtns.forEach(button => {
        button.addEventListener('click', () => closeCropper(true));
    });

    window.addEventListener('resize', function() {
        if (modal.classList.contains('is-open') && img.naturalWidth) {
            const frameRect = frame.getBoundingClientRect();
            baseScale = Math.max(frameRect.width / img.naturalWidth, frameRect.height / img.naturalHeight);
            renderCropImage();
        }
    });
});

