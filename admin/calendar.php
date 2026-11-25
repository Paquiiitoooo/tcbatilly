<?php
/**
 * TC BATILLY - Calendrier Admin
 */
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';

require_login();

$db = get_db();
$page_title = 'Calendrier';
$page_description = 'Visualiser le calendrier des événements du club';

// Récupérer tous les événements
$stmt = $db->query("SELECT * FROM events ORDER BY event_date ASC");
$events = $stmt->fetchAll();

// Préparer les événements pour le calendrier
$calendar_events = [];
foreach ($events as $event) {
    $date = new DateTime($event['event_date']);
    $calendar_events[] = [
        'id' => $event['id'],
        'title' => $event['title'],
        'description' => $event['description'],
        'date' => $event['event_date'],
        'day' => $date->format('d'),
        'month' => $date->format('m'),
        'year' => $date->format('Y'),
        'category' => $event['category'],
        'is_active' => (bool)$event['is_active']
    ];
}

include 'includes/header.php';
?>

<div class="content-section">
    <!-- Tabs pour Sportif / Associatif -->
    <div class="calendar-tabs">
        <button class="calendar-tab active" data-tab="all">Tous les événements</button>
        <button class="calendar-tab" data-tab="sportif">Sportif</button>
        <button class="calendar-tab" data-tab="associatif">Associatif</button>
    </div>

    <!-- Calendrier -->
    <div class="calendar-container">
        <div class="calendar-header">
            <button class="calendar-nav-btn" id="prevMonth" aria-label="Mois précédent">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <h3 id="currentMonthYear"></h3>
            <button class="calendar-nav-btn" id="nextMonth" aria-label="Mois suivant">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
        </div>
        <div class="calendar-grid" id="calendarGrid"></div>
    </div>

    <!-- Liste des événements du mois -->
    <div class="calendar-events-list" id="calendarEventsList"></div>
</div>

<script>
// Données des événements depuis PHP
const calendarEvents = <?php echo json_encode($calendar_events, JSON_UNESCAPED_UNICODE); ?>;
let currentDate = new Date();
let currentFilter = 'all';

// Mois en français
const months = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
const daysOfWeek = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

// Échapper le HTML pour éviter toute injection dans le rendu
function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Initialiser le calendrier
function initCalendar() {
    renderCalendar();
    renderEventsList();
    updateMonthYear();
}

// Filtrer les événements
function filterEvents(filter) {
    currentFilter = filter;
    renderCalendar();
    renderEventsList();

    // Mettre à jour les tabs
    document.querySelectorAll('.calendar-tab').forEach(tab => {
        tab.classList.remove('active');
        if (tab.dataset.tab === filter) {
            tab.classList.add('active');
        }
    });
}

// Obtenir les événements filtrés
function getFilteredEvents() {
    if (currentFilter === 'all') {
        return calendarEvents.filter(e => e.is_active);
    }

    // Les tournois et stages = sportif, les événements et autres = associatif
    const sportifCategories = ['tournoi', 'stage'];
    const associatifCategories = ['evenement', 'autre'];

    if (currentFilter === 'sportif') {
        return calendarEvents.filter(e => e.is_active && sportifCategories.includes(e.category));
    } else if (currentFilter === 'associatif') {
        return calendarEvents.filter(e => e.is_active && associatifCategories.includes(e.category));
    }

    return calendarEvents.filter(e => e.is_active);
}

// Rendre le calendrier
function renderCalendar() {
    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';

    // En-têtes des jours
    daysOfWeek.forEach(day => {
        const header = document.createElement('div');
        header.className = 'calendar-day-header';
        header.textContent = day;
        grid.appendChild(header);
    });

    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

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
        day.className = 'calendar-day other-month';
        day.innerHTML = `<div class="calendar-day-number">${daysInPrevMonth - i}</div>`;
        grid.appendChild(day);
    }

    // Jours du mois actuel
    const filteredEvents = getFilteredEvents();
    const today = new Date();

    for (let day = 1; day <= daysInMonth; day++) {
        const dayDate = new Date(year, month, day);
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';

        // Vérifier si c'est aujourd'hui
        if (dayDate.toDateString() === today.toDateString()) {
            dayElement.classList.add('today');
        }

        // Numéro du jour
        const dayNumber = document.createElement('div');
        dayNumber.className = 'calendar-day-number';
        dayNumber.textContent = day;
        dayElement.appendChild(dayNumber);

        // Événements du jour
        const eventsContainer = document.createElement('div');
        eventsContainer.className = 'calendar-day-events';

        const dayEvents = filteredEvents.filter(event => {
            const eventDate = new Date(event.date);
            return eventDate.getDate() === day &&
                   eventDate.getMonth() === month &&
                   eventDate.getFullYear() === year;
        });

        dayEvents.slice(0, 3).forEach(event => {
            const dot = document.createElement('div');
            dot.className = `calendar-event-dot ${event.category}`;
            dot.title = event.title;
            eventsContainer.appendChild(dot);
        });

        if (dayEvents.length > 3) {
            const more = document.createElement('div');
            more.className = 'calendar-event-dot';
            more.textContent = `+${dayEvents.length - 3}`;
            more.style.background = 'var(--ink-muted)';
            eventsContainer.appendChild(more);
        }

        dayElement.appendChild(eventsContainer);
        grid.appendChild(dayElement);
    }

    // Remplir les jours restants
    const totalCells = grid.children.length;
    const remainingCells = 42 - totalCells; // 6 semaines * 7 jours
    for (let i = 1; i <= remainingCells; i++) {
        const day = document.createElement('div');
        day.className = 'calendar-day other-month';
        day.innerHTML = `<div class="calendar-day-number">${i}</div>`;
        grid.appendChild(day);
    }
}

// Rendre la liste des événements
function renderEventsList() {
    const container = document.getElementById('calendarEventsList');
    const filteredEvents = getFilteredEvents();
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

    // Filtrer les événements du mois actuel
    const monthEvents = filteredEvents.filter(event => {
        const eventDate = new Date(event.date);
        return eventDate.getMonth() === month && eventDate.getFullYear() === year;
    }).sort((a, b) => new Date(a.date) - new Date(b.date));

    if (monthEvents.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: var(--ink-muted); padding: 40px;">Aucun événement ce mois-ci</p>';
        return;
    }

    container.innerHTML = '<h4>Événements du mois</h4>';

    monthEvents.forEach(event => {
        const eventDate = new Date(event.date);
        const item = document.createElement('div');
        item.className = 'event-list-item';

        const category = escapeHtml(event.category);
        const categoryLabel = category.charAt(0).toUpperCase() + category.slice(1);

        item.innerHTML = `
            <div class="event-list-date">
                <div class="day">${eventDate.getDate()}</div>
                <div class="month">${months[eventDate.getMonth()].substring(0, 3)}</div>
            </div>
            <div class="event-list-content">
                <h5>${escapeHtml(event.title)}</h5>
                <p>${escapeHtml(event.description)}</p>
            </div>
            <span class="event-list-badge ${category}">${categoryLabel}</span>
        `;

        container.appendChild(item);
    });
}

// Mettre à jour l'affichage du mois/année
function updateMonthYear() {
    const monthYear = document.getElementById('currentMonthYear');
    monthYear.textContent = `${months[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
}

// Navigation du calendrier
document.getElementById('prevMonth').addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() - 1);
    initCalendar();
});

document.getElementById('nextMonth').addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() + 1);
    initCalendar();
});

// Tabs
document.querySelectorAll('.calendar-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        filterEvents(tab.dataset.tab);
    });
});

// Initialiser au chargement
initCalendar();
</script>

<?php include 'includes/footer.php'; ?>
