// ============================================
// TC BATILLY - Enhanced Interactive JavaScript
// ============================================

// ===== 1. HEADER SCROLL EFFECT =====
const header = document.querySelector('header');
let lastScroll = 0;

function handleHeaderScroll() {
    const currentScroll = window.pageYOffset;
    
    if (currentScroll > 100) {
        header.classList.add('scrolled');
    } else {
        header.classList.remove('scrolled');
    }
    
    lastScroll = currentScroll;
}

window.addEventListener('scroll', handleHeaderScroll);

// ===== 2. HERO SLIDER =====
function initHeroSlider() {
    const slides = document.querySelectorAll('.hero-slide');
    if (slides.length === 0) return;
    
    let currentSlide = 0;
    
    function nextSlide() {
        slides[currentSlide].classList.remove('active');
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].classList.add('active');
    }
    
    // Change slide every 5 seconds
    setInterval(nextSlide, 5000);
}

// Initialize hero slider
initHeroSlider();

// ===== 3. ENHANCED MOBILE MENU WITH FULL ACCESSIBILITY =====
const menuToggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('nav');
const menuOverlay = document.querySelector('.menu-overlay');
const navLinks = document.querySelectorAll('nav a');
let lastFocusedElement = null;

// Get all focusable elements within the nav
function getFocusableElements() {
    return nav.querySelectorAll('a[href], button:not([disabled])');
}

// Open menu function
function openMenu() {
    // Store the last focused element to restore focus later
    lastFocusedElement = document.activeElement;
    
    // Store scroll position to prevent jump
    const scrollY = window.scrollY;
    
    // Activate menu with smooth transitions
    requestAnimationFrame(() => {
        menuToggle.classList.add('active');
        nav.classList.add('mobile-active');
        menuOverlay.classList.add('active');
        document.body.classList.add('menu-open');
        // Maintain scroll position when body becomes fixed
        document.body.style.top = `-${scrollY}px`;
    });
    
    // Update ARIA attributes
    menuToggle.setAttribute('aria-expanded', 'true');
    menuToggle.setAttribute('aria-label', 'Fermer le menu de navigation');
    menuOverlay.setAttribute('aria-hidden', 'false');
    
    // Enable tab navigation for nav links
    navLinks.forEach(link => {
        link.setAttribute('tabindex', '0');
    });
    
    // Set focus to first nav link after transition
    setTimeout(() => {
        const firstLink = nav.querySelector('a');
        if (firstLink) {
            firstLink.focus();
        }
    }, 150);
}

// Close menu function
function closeMenu() {
    // Get scroll position before removing fixed position
    const scrollY = document.body.style.top;
    
    menuToggle.classList.remove('active');
    nav.classList.remove('mobile-active');
    menuOverlay.classList.remove('active');
    document.body.classList.remove('menu-open');
    document.body.style.top = '';
    
    // Restore scroll position
    if (scrollY) {
        window.scrollTo(0, parseInt(scrollY || '0') * -1);
    }
    
    // Update ARIA attributes
    menuToggle.setAttribute('aria-expanded', 'false');
    menuToggle.setAttribute('aria-label', 'Ouvrir le menu de navigation');
    menuOverlay.setAttribute('aria-hidden', 'true');
    
    // Disable tab navigation for nav links on desktop
    if (window.innerWidth <= 900) {
        navLinks.forEach(link => {
            link.setAttribute('tabindex', '-1');
        });
    } else {
        navLinks.forEach(link => {
            link.removeAttribute('tabindex');
        });
    }
    
    // Restore focus to the menu toggle or last focused element
    if (lastFocusedElement) {
        lastFocusedElement.focus();
    } else {
        menuToggle.focus();
    }
}

// Toggle menu function
function toggleMenu() {
    const isOpen = nav.classList.contains('mobile-active');
    if (isOpen) {
        closeMenu();
    } else {
        openMenu();
    }
}

if (menuToggle && nav) {
    // Menu toggle click
    menuToggle.addEventListener('click', toggleMenu);
    
    // Close menu when clicking on overlay
    if (menuOverlay) {
        menuOverlay.addEventListener('click', closeMenu);
    }
    
    // Close menu when clicking on a link
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            closeMenu();
        });
    });
    
    // Close menu on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && nav.classList.contains('mobile-active')) {
            closeMenu();
        }
    });
    
    // Focus trap - keep focus within menu when open
    document.addEventListener('keydown', (e) => {
        if (!nav.classList.contains('mobile-active')) return;
        if (e.key !== 'Tab') return;
        
        const focusableElements = Array.from(getFocusableElements());
        const menuToggleIndex = focusableElements.indexOf(menuToggle);
        
        // Add menu toggle to the focusable elements if not already there
        if (menuToggleIndex === -1) {
            focusableElements.unshift(menuToggle);
        }
        
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        // If shift + tab on first element, go to last
        if (e.shiftKey && document.activeElement === firstElement) {
            e.preventDefault();
            lastElement.focus();
        }
        // If tab on last element, go to first
        else if (!e.shiftKey && document.activeElement === lastElement) {
            e.preventDefault();
            firstElement.focus();
        }
    });
    
    // Handle window resize - close menu and update tabindex
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (window.innerWidth > 900) {
                // Desktop view - close menu and enable tab navigation
                if (nav.classList.contains('mobile-active')) {
                    closeMenu();
                }
                navLinks.forEach(link => {
                    link.removeAttribute('tabindex');
                });
            } else {
                // Mobile view - disable tab navigation when menu is closed
                if (!nav.classList.contains('mobile-active')) {
                    navLinks.forEach(link => {
                        link.setAttribute('tabindex', '-1');
                    });
                }
            }
        }, 250);
    });
    
    // Initialize proper tabindex on page load
    if (window.innerWidth <= 900) {
        navLinks.forEach(link => {
            link.setAttribute('tabindex', '-1');
        });
    }
}

// ===== 4. ACTIVE NAVIGATION HIGHLIGHT =====
const sections = document.querySelectorAll('section[id]');
const navLinksAll = document.querySelectorAll('nav a');

function highlightNavigation() {
    const scrollPosition = window.pageYOffset + 150;

    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.offsetHeight;
        const sectionId = section.getAttribute('id');
        
        if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
            navLinksAll.forEach(link => {
                link.classList.remove('active');
                // Match href without # to section id
                if (link.getAttribute('href') === `#${sectionId}` || 
                    (sectionId === 'accueil' && link.getAttribute('href') === '#')) {
                    link.classList.add('active');
                }
            });
        }
    });
}

window.addEventListener('scroll', highlightNavigation);

// ===== 5. SCROLL ANIMATIONS VARIÉES (Intersection Observer) =====
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -80px 0px'
};

// Définir les types d'animations pour chaque section
const sectionAnimations = {
    'qui-sommes-nous': 'fadeInLeft',
    'rejoindre': 'fadeInRight',
    'tarifs': 'fadeInUp',
    'services': 'fadeInLeft',
    'coachs': 'scaleIn',
    'agenda': 'fadeInRight',
    'actualites': 'fadeInLeft',
    'galerie': 'fadeInUp',
    'temoignages': 'fadeInRight',
    'contact': 'fadeInUp'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            
            // Add stagger animation to children
            const children = entry.target.querySelectorAll('.service, .plan, .coach, .news-item, .pricing-card, .event-item, .schedule-item');
            children.forEach((child, index) => {
                setTimeout(() => {
                    child.style.opacity = '1';
                    child.style.transform = 'translateY(0) translateX(0) scale(1)';
                }, index * 120);
            });
            
            // Special animations for pricing cards
            const pricingCards = entry.target.querySelectorAll('.pricing-card');
            pricingCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0) scale(1)';
                }, index * 150);
            });
        }
    });
}, observerOptions);

// Observe all sections with varied animations
sections.forEach(section => {
    if (!section.classList.contains('hero')) {
        const sectionId = section.getAttribute('id');
        const animationType = sectionAnimations[sectionId] || 'fadeInUp';
        
        section.classList.add('animate-on-scroll', animationType);
        observer.observe(section);
    }
});

// Set initial state for staggered elements with varied starts
document.querySelectorAll('.service').forEach((item, index) => {
    item.style.opacity = '0';
    item.style.transform = index % 2 === 0 ? 'translateX(-50px)' : 'translateX(50px)';
    item.style.transition = 'all 0.7s cubic-bezier(0.4, 0, 0.2, 1)';
});

document.querySelectorAll('.plan, .coach').forEach(item => {
    item.style.opacity = '0';
    item.style.transform = 'scale(0.8) translateY(30px)';
    item.style.transition = 'all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1)';
});

document.querySelectorAll('.news-item').forEach((item, index) => {
    item.style.opacity = '0';
    item.style.transform = 'translateX(-40px)';
    item.style.transition = 'all 0.6s ease';
});

document.querySelectorAll('.pricing-card').forEach((item, index) => {
    item.style.opacity = '0';
    item.style.transform = 'translateY(50px) scale(0.9)';
    item.style.transition = 'all 0.7s cubic-bezier(0.34, 1.56, 0.64, 1)';
});

document.querySelectorAll('.event-item, .schedule-item').forEach((item, index) => {
    item.style.opacity = '0';
    item.style.transform = 'translateX(40px)';
    item.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
});

// ===== 6. SCROLL TO TOP BUTTON =====
const scrollTopBtn = document.querySelector('.scroll-top');

if (scrollTopBtn) {
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 500) {
            scrollTopBtn.classList.add('visible');
        } else {
            scrollTopBtn.classList.remove('visible');
        }
    });

    scrollTopBtn.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// ===== 7. FORM VALIDATION & SUBMISSION =====
const contactForm = document.querySelector('.contact-form');

if (contactForm) {
    contactForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        // Get form values
        const nom = document.getElementById('nom').value.trim();
        const email = document.getElementById('email').value.trim();
        const message = document.getElementById('message').value.trim();
        
        // Simple validation
        if (nom === '' || email === '' || message === '') {
            showNotification('Veuillez remplir tous les champs', 'error');
            return;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showNotification('Veuillez entrer une adresse email valide', 'error');
            return;
        }
        
        // Simulate form submission
        const submitBtn = contactForm.querySelector('.btn-submit');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Envoi en cours...';
        submitBtn.disabled = true;
        
        setTimeout(() => {
            submitBtn.textContent = '✓ Message envoyé !';
            submitBtn.style.background = '#4CAF50';
            showNotification('Votre message a été envoyé avec succès !', 'success');
            contactForm.reset();
            
            setTimeout(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                submitBtn.style.background = '';
            }, 3000);
        }, 1500);
    });
}

// Notification helper
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: ${type === 'error' ? '#f44336' : type === 'success' ? '#4CAF50' : '#2196F3'};
        color: white;
        padding: 16px 24px;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideInRight 0.4s ease-out;
        font-weight: 600;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.4s ease-out';
        setTimeout(() => notification.remove(), 400);
    }, 3000);
}

// Add notification animations to CSS dynamically
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOutRight {
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

// ===== 8. SMOOTH REVEAL ON LOAD =====
window.addEventListener('load', () => {
    // Ensure all content is visible on load
    document.body.style.opacity = '1';
});

// ===== 9. ENHANCED HOVER EFFECTS =====
// Add ripple effect to buttons
document.querySelectorAll('button, .btn-join').forEach(button => {
    button.addEventListener('click', function(e) {
        const ripple = document.createElement('span');
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            top: ${y}px;
            left: ${x}px;
            pointer-events: none;
            animation: ripple 0.6s ease-out;
        `;
        
        this.style.position = 'relative';
        this.style.overflow = 'hidden';
        this.appendChild(ripple);
        
        setTimeout(() => ripple.remove(), 600);
    });
});

// Add ripple animation
const rippleStyle = document.createElement('style');
rippleStyle.textContent = `
    @keyframes ripple {
        from {
            transform: scale(0);
            opacity: 1;
        }
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
`;
document.head.appendChild(rippleStyle);

// ===== 10. PHOTO CAROUSEL =====
function initCarousel() {
    const track = document.querySelector('.carousel-track');
    const images = document.querySelectorAll('.carousel-img');
    const dotsContainer = document.querySelector('.carousel-dots');
    
    if (!track || images.length === 0) return;
    
    let currentIndex = 0;
    const totalImages = images.length;
    
    // Create dots
    for (let i = 0; i < totalImages; i++) {
        const dot = document.createElement('div');
        dot.classList.add('carousel-dot');
        if (i === 0) dot.classList.add('active');
        dot.addEventListener('click', () => goToSlide(i));
        dotsContainer.appendChild(dot);
    }
    
    const dots = document.querySelectorAll('.carousel-dot');
    
    function goToSlide(index) {
        currentIndex = index;
        track.style.transform = `translateX(-${currentIndex * 100}%)`;
        
        // Update dots
        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === currentIndex);
        });
    }
    
    function nextSlide() {
        currentIndex = (currentIndex + 1) % totalImages;
        goToSlide(currentIndex);
    }
    
    // Auto-advance every 4 seconds
    setInterval(nextSlide, 4000);
}

// Initialize carousel when page loads
initCarousel();

// ===== 11. LOAD EVENTS FROM API & DISPLAY IN CALENDAR =====
let calendarEventsFront = [];
let currentDateFront = new Date();
let currentFilterFront = 'all';

// Mois en français
const monthsFront = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
const daysOfWeekFront = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

async function loadEvents() {
    try {
        const response = await fetch('./backend/api/events.php?active=1');
        const data = await response.json();
        
        if (data.success && data.events) {
            // Préparer les événements pour le calendrier
            calendarEventsFront = data.events.map(event => {
                const eventDate = new Date(event.event_date);
                return {
                    id: event.id,
                    title: event.title,
                    description: event.description,
                    date: event.event_date,
                    event_time: event.event_time || '(journée)',
                    day: eventDate.getDate(),
                    month: eventDate.getMonth(),
                    year: eventDate.getFullYear(),
                    category: event.category,
                    is_active: event.is_active
                };
            });
            
            // Store events globally for calendar download
            window.clubEvents = data.events || [];
            
            // Initialiser le calendrier
            initCalendarFront();
        }
    } catch (error) {
        console.error('Erreur lors du chargement des événements:', error);
    }
}

// Filtrer les événements
function filterEventsFront(filter) {
    currentFilterFront = filter;
    renderCalendarFront();
    
    // Mettre à jour les tabs
    document.querySelectorAll('.calendar-tab-front').forEach(tab => {
        tab.classList.remove('active');
        if (tab.dataset.tab === filter) {
            tab.classList.add('active');
        }
    });
}

// Obtenir les événements filtrés
function getFilteredEventsFront() {
    if (currentFilterFront === 'all') {
        return calendarEventsFront.filter(e => e.is_active);
    }
    
    // Les tournois et stages = sportif, les événements et autres = associatif
    const sportifCategories = ['tournoi', 'stage'];
    const associatifCategories = ['evenement', 'autre'];
    
    if (currentFilterFront === 'sportif') {
        return calendarEventsFront.filter(e => e.is_active && sportifCategories.includes(e.category));
    } else if (currentFilterFront === 'associatif') {
        return calendarEventsFront.filter(e => e.is_active && associatifCategories.includes(e.category));
    }
    
    return calendarEventsFront.filter(e => e.is_active);
}

// Initialiser le calendrier
function initCalendarFront() {
    renderCalendarFront();
    updateMonthYearFront();
}

// Rendre le calendrier
function renderCalendarFront() {
    const grid = document.getElementById('calendarGridFront');
    if (!grid) return;
    
    grid.innerHTML = '';
    
    // En-têtes des jours
    daysOfWeekFront.forEach(day => {
        const header = document.createElement('div');
        header.className = 'calendar-day-header-front';
        header.textContent = day;
        grid.appendChild(header);
    });
    
    const year = currentDateFront.getFullYear();
    const month = currentDateFront.getMonth();
    
    // Premier jour du mois
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay();
    
    // Jours du mois précédent
    const prevMonth = new Date(year, month, 0);
    const daysInPrevMonth = prevMonth.getDate();
    
    // Remplir les jours du mois précédent
    for (let i = startingDayOfWeek - 1; i >= 0; i--) {
        const day = document.createElement('div');
        day.className = 'calendar-day-front other-month';
        day.innerHTML = `<div class="calendar-day-number-front">${daysInPrevMonth - i}</div>`;
        grid.appendChild(day);
    }
    
    // Jours du mois actuel
    const filteredEvents = getFilteredEventsFront();
    const today = new Date();
    
    for (let day = 1; day <= daysInMonth; day++) {
        const dayDate = new Date(year, month, day);
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day-front';
        
        // Vérifier si c'est aujourd'hui
        if (dayDate.toDateString() === today.toDateString()) {
            dayElement.classList.add('today');
        }
        
        // Numéro du jour
        const dayNumber = document.createElement('div');
        dayNumber.className = 'calendar-day-number-front';
        dayNumber.textContent = day;
        dayElement.appendChild(dayNumber);
        
        // Événements du jour
        const eventsContainer = document.createElement('div');
        eventsContainer.className = 'calendar-day-events-front';
        
        const dayEvents = filteredEvents.filter(event => {
            const eventDate = new Date(event.date);
            return eventDate.getDate() === day && 
                   eventDate.getMonth() === month && 
                   eventDate.getFullYear() === year;
        });
        
        dayEvents.slice(0, 2).forEach(event => {
            const eventItem = document.createElement('div');
            eventItem.className = `calendar-event-item-front ${event.category}`;
            eventItem.textContent = event.title;
            eventItem.title = `${event.title} - ${event.event_time}`;
            eventItem.dataset.eventId = event.id;
            eventItem.style.cursor = 'pointer';
            eventsContainer.appendChild(eventItem);
        });
        
        if (dayEvents.length > 2) {
            const more = document.createElement('div');
            more.className = 'calendar-event-item-front calendar-event-more';
            more.textContent = `+${dayEvents.length - 2} autre${dayEvents.length > 3 ? 's' : ''}`;
            more.style.background = '#6b7280';
            more.style.cursor = 'pointer';
            more.dataset.dayEvents = JSON.stringify(dayEvents.slice(2));
            eventsContainer.appendChild(more);
        }
        
        dayElement.appendChild(eventsContainer);
        grid.appendChild(dayElement);
    }
    
    // Attacher les event listeners après le rendu
    setTimeout(() => {
        attachEventListeners();
    }, 100);
    
    // Remplir les jours restants
    const totalCells = grid.children.length;
    const remainingCells = 42 - totalCells; // 6 semaines * 7 jours
    for (let i = 1; i <= remainingCells; i++) {
        const day = document.createElement('div');
        day.className = 'calendar-day-front other-month';
        day.innerHTML = `<div class="calendar-day-number-front">${i}</div>`;
        grid.appendChild(day);
    }
}


// Mettre à jour l'affichage du mois/année
function updateMonthYearFront() {
    const monthYear = document.getElementById('currentMonthYearFront');
    if (monthYear) {
        monthYear.textContent = `${monthsFront[currentDateFront.getMonth()]} ${currentDateFront.getFullYear()}`;
    }
}

// Navigation du calendrier
document.addEventListener('DOMContentLoaded', () => {
    // Mettre à jour l'année dans le footer
    const currentYearEl = document.getElementById('currentYear');
    if (currentYearEl) {
        currentYearEl.textContent = new Date().getFullYear();
    }
    
    const prevBtn = document.getElementById('prevMonthFront');
    const nextBtn = document.getElementById('nextMonthFront');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            currentDateFront.setMonth(currentDateFront.getMonth() - 1);
            initCalendarFront();
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            currentDateFront.setMonth(currentDateFront.getMonth() + 1);
            initCalendarFront();
        });
    }
    
    // Tabs
    document.querySelectorAll('.calendar-tab-front').forEach(tab => {
        tab.addEventListener('click', () => {
            filterEventsFront(tab.dataset.tab);
        });
    });
});

// Load events on page load
loadEvents();

// ===== LOAD NEWS FROM API =====
async function loadNews() {
    try {
        const response = await fetch('./backend/api/news.php?active=1&limit=10');
        const data = await response.json();
        
        const newsContainer = document.getElementById('news-container');
        if (newsContainer && data.success && data.news) {
            // Remove loading placeholder
            newsContainer.innerHTML = '';
            
            // Add news items from API
            data.news.forEach(news => {
                const article = document.createElement('article');
                article.className = 'news-item';
                
                const newsDate = new Date(news.news_date);
                const formattedDate = newsDate.toLocaleDateString('fr-FR', { 
                    day: 'numeric', 
                    month: 'short', 
                    year: 'numeric' 
                });
                
                article.innerHTML = `
                    <div class="news-date">${formattedDate}</div>
                    <h4>${news.emoji || '📰'} ${news.title}</h4>
                    <p>${news.content}</p>
                `;
                
                newsContainer.appendChild(article);
            });
        } else if (newsContainer) {
            newsContainer.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">Aucune actualité disponible</p>';
        }
    } catch (error) {
        console.error('Erreur lors du chargement des actualités:', error);
        const newsContainer = document.getElementById('news-container');
        if (newsContainer) {
            newsContainer.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">Erreur lors du chargement des actualités</p>';
        }
    }
}

// Load news on page load  
loadNews();

// ===== LOAD SPONSORS FROM API =====
async function loadSponsors() {
    try {
        const response = await fetch('./backend/api/sponsors.php?active=1');
        const data = await response.json();
        
        const sponsorsTrack = document.getElementById('sponsors-track');
        if (!sponsorsTrack) return;
        
        if (data.success && data.sponsors && data.sponsors.length > 0) {
            // Dupliquer les sponsors plusieurs fois pour un défilement infini plus fluide
            const duplicatedSponsors = [
                ...data.sponsors,
                ...data.sponsors,
                ...data.sponsors,
                ...data.sponsors,
                ...data.sponsors
            ];
            
            sponsorsTrack.innerHTML = duplicatedSponsors.map(sponsor => {
                const logoUrl = sponsor.logo_path || 'https://via.placeholder.com/120x60?text=' + encodeURIComponent(sponsor.name);
                const sponsorLink = sponsor.website ? `<a href="${sponsor.website}" target="_blank" rel="noopener noreferrer" class="sponsor-link">` : '';
                const sponsorLinkClose = sponsor.website ? '</a>' : '';
                
                return `
                    ${sponsorLink}
                    <div class="sponsor-item">
                        <img src="${logoUrl}" alt="${sponsor.name}" loading="lazy" onerror="this.src='https://via.placeholder.com/120x60?text=${encodeURIComponent(sponsor.name)}'">
                    </div>
                    ${sponsorLinkClose}
                `;
            }).join('');
        } else {
            // Masquer la bannière si aucun sponsor
            const sponsorsRibbon = document.getElementById('sponsors-ribbon');
            if (sponsorsRibbon) {
                sponsorsRibbon.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Erreur lors du chargement des sponsors:', error);
        // Masquer la bannière en cas d'erreur
        const sponsorsRibbon = document.getElementById('sponsors-ribbon');
        if (sponsorsRibbon) {
            sponsorsRibbon.style.display = 'none';
        }
    }
}

// Load sponsors on page load
loadSponsors();

// ===== LOAD PRICING FROM API =====
async function loadPricing() {
    try {
        const response = await fetch('./backend/api/pricing.php?active=1');
        const data = await response.json();
        
        const pricingContainer = document.getElementById('pricing-container');
        if (pricingContainer && data.success && data.pricing) {
            pricingContainer.innerHTML = data.pricing.map(price => {
                const featuresList = Array.isArray(price.features) 
                    ? price.features.map(f => `<li>✓ ${f}</li>`).join('')
                    : '';
                
                const badgeClass = price.badge_type === 'popular' ? 'popular' : '';
                const badgeHtml = price.badge ? `<div class="pricing-badge ${price.badge_type === 'popular' ? 'popular' : ''}">${price.badge}</div>` : '';
                
                return `
                    <div class="pricing-card ${badgeClass}">
                        ${badgeHtml}
                        <h3>${price.name}</h3>
                        <div class="price">
                            <span class="amount">${parseFloat(price.price).toFixed(0)}€</span>
                            <span class="period">${price.period || '/ an'}</span>
                        </div>
                        <ul class="features">
                            ${featuresList}
                        </ul>
                        ${price.note ? `<p class="pricing-note">${price.note}</p>` : ''}
                        <a href="https://tenup.fft.fr/club/55540452/offres" target="_blank" class="btn-pricing">S'inscrire</a>
                    </div>
                `;
            }).join('');
        }
    } catch (error) {
        console.error('Erreur lors du chargement des tarifs:', error);
        const pricingContainer = document.getElementById('pricing-container');
        if (pricingContainer) {
            pricingContainer.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">Erreur lors du chargement des tarifs</p>';
        }
    }
}

// ===== LOAD SERVICES FROM API =====
async function loadServices() {
    try {
        const response = await fetch('./backend/api/services.php?active=1');
        const data = await response.json();
        
        const servicesContainer = document.getElementById('services-container');
        if (servicesContainer && data.success && data.services) {
            servicesContainer.innerHTML = data.services.map(service => `
                <div class="service">
                    <h3>${service.title}</h3>
                    <p>${service.description}</p>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Erreur lors du chargement des services:', error);
        const servicesContainer = document.getElementById('services-container');
        if (servicesContainer) {
            servicesContainer.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">Erreur lors du chargement des services</p>';
        }
    }
}

// ===== LOAD COACHES FROM API =====
async function loadCoaches() {
    try {
        const response = await fetch('./backend/api/coaches.php?active=1');
        const data = await response.json();
        
        const coachesContainer = document.getElementById('coaches-container');
        if (coachesContainer && data.success && data.coaches) {
            coachesContainer.innerHTML = data.coaches.map(coach => `
                <div class="coach">
                    <img src="${coach.image_url}" alt="${coach.name} - ${coach.role}" loading="lazy">
                    <h4>${coach.name}</h4>
                    <p>${coach.role}</p>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Erreur lors du chargement des entraîneurs:', error);
        const coachesContainer = document.getElementById('coaches-container');
        if (coachesContainer) {
            coachesContainer.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">Erreur lors du chargement des entraîneurs</p>';
        }
    }
}

// Event Modal
function showEventModal(event) {
    const modal = document.getElementById('eventModal');
    if (!modal) return;
    
    const modalTitle = document.getElementById('eventModalTitle');
    const modalDate = document.getElementById('eventModalDate');
    const modalTime = document.getElementById('eventModalTime');
    const modalDescription = document.getElementById('eventModalDescription');
    const modalCategory = document.getElementById('eventModalCategory');
    
    if (modalTitle) modalTitle.textContent = event.title;
    if (modalDate) {
        const eventDate = new Date(event.date);
        modalDate.textContent = `${eventDate.getDate()} ${monthsFront[eventDate.getMonth()]} ${eventDate.getFullYear()}`;
    }
    if (modalTime) modalTime.textContent = event.event_time || '(journée)';
    if (modalDescription) modalDescription.textContent = event.description;
    if (modalCategory) {
        modalCategory.textContent = event.category.charAt(0).toUpperCase() + event.category.slice(1);
        modalCategory.className = `event-modal-category ${event.category}`;
    }
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeEventModal() {
    const modal = document.getElementById('eventModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Add event listeners for calendar events
function attachEventListeners() {
    // Click on event items
    document.querySelectorAll('.calendar-event-item-front').forEach(item => {
        item.addEventListener('click', function() {
            const eventId = this.dataset.eventId;
            if (eventId) {
                const event = calendarEventsFront.find(e => e.id == eventId);
                if (event) {
                    showEventModal(event);
                }
            } else if (this.dataset.dayEvents) {
                // Multiple events - show first one or create a list
                const events = JSON.parse(this.dataset.dayEvents);
                if (events.length > 0) {
                    showEventModal(events[0]);
                }
            }
        });
    });
}

// Load all data on page load
document.addEventListener('DOMContentLoaded', () => {
    loadPricing();
    loadServices();
    loadCoaches();
    
    // Close modal on overlay click
    const eventModal = document.getElementById('eventModal');
    if (eventModal) {
        eventModal.addEventListener('click', (e) => {
            if (e.target === eventModal) {
                closeEventModal();
            }
        });
    }
    
    // Close modal on close button
    const eventModalClose = document.getElementById('eventModalClose');
    if (eventModalClose) {
        eventModalClose.addEventListener('click', closeEventModal);
    }
    
    // Close modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeEventModal();
        }
    });
});

// ===== 12. DOWNLOAD CALENDAR (.ics) =====
function downloadCalendar() {
    if (!window.clubEvents || window.clubEvents.length === 0) {
        alert('Aucun événement disponible pour le moment');
        return;
    }
    
    // Create .ics file content
    let icsContent = 'BEGIN:VCALENDAR\n';
    icsContent += 'VERSION:2.0\n';
    icsContent += 'PRODID:-//TC Batilly//Events Calendar//FR\n';
    icsContent += 'CALSCALE:GREGORIAN\n';
    icsContent += 'METHOD:PUBLISH\n';
    icsContent += 'X-WR-CALNAME:TC Batilly - Calendrier\n';
    icsContent += 'X-WR-TIMEZONE:Europe/Paris\n';
    
    window.clubEvents.forEach(event => {
        const eventDate = new Date(event.date);
        const dateStr = eventDate.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
        
        icsContent += 'BEGIN:VEVENT\n';
        icsContent += `UID:${event.id}@tcbatilly.fr\n`;
        icsContent += `DTSTAMP:${dateStr}\n`;
        icsContent += `DTSTART:${dateStr}\n`;
        icsContent += `SUMMARY:${event.title}\n`;
        icsContent += `DESCRIPTION:${event.description}\n`;
        icsContent += `LOCATION:Tennis Club Batilly, 54980 Batilly\n`;
        icsContent += 'STATUS:CONFIRMED\n';
        icsContent += 'END:VEVENT\n';
    });
    
    icsContent += 'END:VCALENDAR';
    
    // Download the file
    const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'TC_Batilly_Calendrier.ics';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Show confirmation
    showNotification('Calendrier téléchargé avec succès !', 'success');
}

// ===== 13. COOKIE MANAGEMENT SYSTEM =====
const cookieBtn = document.getElementById('cookieBtn');
const cookieModal = document.getElementById('cookieModal');
const cookieModalClose = document.querySelector('.cookie-modal-close');
const cookieModalOverlay = document.querySelector('.cookie-modal-overlay');
const acceptAllBtn = document.getElementById('acceptAll');
const rejectAllBtn = document.getElementById('rejectAll');
const savePreferencesBtn = document.getElementById('savePreferences');

// Cookie preferences
const cookiePreferences = {
    necessary: true, // Always true
    analytics: false,
    marketing: false,
    preferences: false
};

// Load saved preferences
function loadCookiePreferences() {
    const saved = localStorage.getItem('cookiePreferences');
    if (saved) {
        const parsed = JSON.parse(saved);
        Object.assign(cookiePreferences, parsed);
        
        // Update UI
        document.getElementById('analyticsCookies').checked = cookiePreferences.analytics;
        document.getElementById('marketingCookies').checked = cookiePreferences.marketing;
        document.getElementById('preferencesCookies').checked = cookiePreferences.preferences;
    }
}

// Save preferences
function saveCookiePreferences() {
    localStorage.setItem('cookiePreferences', JSON.stringify(cookiePreferences));
    showNotification('Vos préférences ont été enregistrées', 'success');
}

// Open modal
function openCookieModal() {
    cookieModal.classList.add('active');
    cookieModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

// Close modal
function closeCookieModal() {
    cookieModal.classList.remove('active');
    cookieModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

// Event listeners
if (cookieBtn) {
    cookieBtn.addEventListener('click', openCookieModal);
}

if (cookieModalClose) {
    cookieModalClose.addEventListener('click', closeCookieModal);
}

if (cookieModalOverlay) {
    cookieModalOverlay.addEventListener('click', closeCookieModal);
}

// Close on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && cookieModal.classList.contains('active')) {
        closeCookieModal();
    }
});

// Accept all cookies
if (acceptAllBtn) {
    acceptAllBtn.addEventListener('click', () => {
        cookiePreferences.analytics = true;
        cookiePreferences.marketing = true;
        cookiePreferences.preferences = true;
        
        document.getElementById('analyticsCookies').checked = true;
        document.getElementById('marketingCookies').checked = true;
        document.getElementById('preferencesCookies').checked = true;
        
        saveCookiePreferences();
        closeCookieModal();
    });
}

// Reject all cookies (except necessary)
if (rejectAllBtn) {
    rejectAllBtn.addEventListener('click', () => {
        cookiePreferences.analytics = false;
        cookiePreferences.marketing = false;
        cookiePreferences.preferences = false;
        
        document.getElementById('analyticsCookies').checked = false;
        document.getElementById('marketingCookies').checked = false;
        document.getElementById('preferencesCookies').checked = false;
        
        saveCookiePreferences();
        closeCookieModal();
    });
}

// Save custom preferences
if (savePreferencesBtn) {
    savePreferencesBtn.addEventListener('click', () => {
        cookiePreferences.analytics = document.getElementById('analyticsCookies').checked;
        cookiePreferences.marketing = document.getElementById('marketingCookies').checked;
        cookiePreferences.preferences = document.getElementById('preferencesCookies').checked;
        
        saveCookiePreferences();
        closeCookieModal();
    });
}

// Load preferences on page load
loadCookiePreferences();

// ===== 14. LOADING INDICATOR =====
console.log('✓ TC Batilly - Tous les scripts chargés avec succès !');
console.log('✓ Hero slider activé (transition 5s)');
console.log('✓ Animations activées');
console.log('✓ Interactions configurées');
console.log('✓ Événements chargés depuis JSON');
console.log('✓ Carousel de photos initialisé (auto-rotation 4s)');
console.log('✓ Bouton cookies activé');

