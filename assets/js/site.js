(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

    const TENUP = {
        reservation: 'https://tenup.fft.fr/club/55540452/reservations',
        offers: 'https://tenup.fft.fr/club/55540452/offres',
    };
    const links = { ...TENUP };
    let clubAddress = '';

    const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    const MONTHS_SHORT = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    const WEEKDAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    const CATEGORY = {
        tournoi: 'Compétition',
        stage: 'Stage',
        evenement: 'Vie du club',
        autre: 'Autre',
    };
    const FILTERS = {
        all: null,
        sportif: ['tournoi', 'stage'],
        associatif: ['evenement', 'autre'],
    };

    // Valeurs d'exemple livrées avec le site : on ne les affiche pas tant qu'elles n'ont pas été remplacées.
    const PLACEHOLDER_PHONES = ['+33383000000'];
    const PLACEHOLDER_EMAILS = ['contact@tcbatilly.fr'];

    /* ---------- utilitaires ---------- */

    // Les textes saisis dans l'admin sont enregistrés échappés (&amp;, &#039;) : on les ramène en texte brut.
    const parser = new DOMParser();
    const plain = (value) => (value == null ? '' : parser.parseFromString(String(value), 'text/html').documentElement.textContent);

    const safeUrl = (value) => {
        const url = plain(value).trim();
        return /^https?:\/\//i.test(url) ? url : '';
    };

    const safeImage = (value) => {
        const src = plain(value).trim();
        return /^(https?:\/\/|\/|\.\/)/i.test(src) ? src : '';
    };

    function h(tag, props = {}, ...children) {
        const node = document.createElement(tag);
        for (const [key, value] of Object.entries(props)) {
            if (value == null || value === false) continue;
            if (key === 'text') node.textContent = value;
            else if (key === 'class') node.className = value;
            else node.setAttribute(key, value);
        }
        children.flat().forEach((child) => {
            if (child != null && child !== false) node.append(child.nodeType ? child : document.createTextNode(child));
        });
        return node;
    }

    async function getJSON(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(url + ' : ' + response.status);
        return response.json();
    }

    const toDate = (value) => {
        const [y, m, d] = String(value).slice(0, 10).split('-').map(Number);
        return new Date(y, m - 1, d);
    };
    const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    const dayNumber = (date) => (date.getDate() === 1 ? '1er' : String(date.getDate()));
    const dayLabel = (date, withYear) => `${WEEKDAYS[date.getDay()]} ${dayNumber(date)} ${MONTHS[date.getMonth()]}${withYear ? ' ' + date.getFullYear() : ''}`;

    const euro = (amount) => new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
    }).format(amount);

    const loadFailed = () => h('p', { class: 'empty', text: 'Impossible de charger cette section pour le moment.' });

    /* ---------- menu ---------- */

    function initMenu() {
        const toggle = $('#menu-toggle');
        const nav = $('#menu');
        if (!toggle || !nav) return;

        const set = (open) => {
            nav.classList.toggle('open', open);
            toggle.setAttribute('aria-expanded', String(open));
            toggle.textContent = open ? 'Fermer' : 'Menu';
            document.body.classList.toggle('menu-open', open);
        };

        toggle.addEventListener('click', () => set(!nav.classList.contains('open')));
        nav.addEventListener('click', (event) => {
            if (event.target.closest('a')) set(false);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && nav.classList.contains('open')) {
                set(false);
                toggle.focus();
            }
        });
        window.matchMedia('(min-width: 1021px)').addEventListener('change', (event) => {
            if (event.matches) set(false);
        });
    }

    /* ---------- réglages du club ---------- */

    const socialUrl = (value) => {
        const url = safeUrl(value);
        if (!url) return '';
        try {
            const parsed = new URL(url);
            return parsed.pathname.replace(/\/+$/, '') || parsed.search ? url : '';
        } catch (error) {
            return '';
        }
    };

    /* ---------- données structurées (JSON-LD) ---------- */

    function patchClubLd(patch) {
        const node = $('#ld-club');
        if (!node) return;
        try {
            const data = JSON.parse(node.textContent);
            const club = (data['@graph'] || []).find((item) => item['@type'] === 'SportsClub');
            if (!club) return;
            Object.assign(club, patch.club || {});
            if (patch.address) club.address = { ...club.address, ...patch.address };
            if (patch.sameAs) club.sameAs = [...new Set([...(club.sameAs || []), ...patch.sameAs])];
            node.textContent = JSON.stringify(data);
        } catch (error) {
            // les données statiques du HTML restent valables
        }
    }

    function addLd(id, data) {
        let node = document.getElementById(id);
        if (!node) {
            node = document.createElement('script');
            node.type = 'application/ld+json';
            node.id = id;
            document.head.append(node);
        }
        node.textContent = JSON.stringify(data);
    }

    async function loadSettings() {
        let raw = {};
        try {
            const data = await getJSON('./backend/api/settings.php');
            if (data.success) raw = data.settings || {};
        } catch (error) {
            // les valeurs par défaut du HTML restent en place
        }
        const s = Object.fromEntries(Object.entries(raw).map(([key, value]) => [key, plain(value).trim()]));

        links.reservation = safeUrl(s.reservation_url) || TENUP.reservation;
        links.offers = safeUrl(s.offers_url) || TENUP.offers;
        $$('[data-link]').forEach((anchor) => {
            anchor.href = links[anchor.dataset.link] || anchor.href;
        });

        const parts = (s.address || '').split(/\n|,\s*/).map((part) => part.trim()).filter(Boolean);
        if (parts.length) {
            clubAddress = parts.join(', ');
            const lines = [];
            parts.forEach((part, index) => {
                if (index) lines.push(h('br'));
                lines.push(part);
            });
            $('#contact-address')?.replaceChildren(...lines);
            $('#practical-address')?.replaceChildren(parts.slice(0, 2).join(', '));
            $('#legal-address')?.replaceChildren(clubAddress);
            const routeUrl = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent('Tennis Club de Batilly, ' + clubAddress);
            $('#route-link')?.setAttribute('href', routeUrl);
            $('#footer-route')?.setAttribute('href', routeUrl);
            $('#footer-address')?.replaceChildren(parts.slice(0, 2).join(', '));
            if (parts.length > 1 && !/\d{5}/.test(parts[0])) patchClubLd({ address: { streetAddress: parts[0] } });
        }

        const rows = [];
        const phone = s.contact_phone;
        if (phone && !PLACEHOLDER_PHONES.includes(phone.replace(/\s/g, ''))) {
            rows.push(['Téléphone', h('a', { href: 'tel:' + phone.replace(/[^\d+]/g, ''), text: phone })]);
        }
        const email = s.contact_email;
        const hasEmail = email && !PLACEHOLDER_EMAILS.includes(email.toLowerCase());
        if (hasEmail) {
            rows.push(['E-mail', h('a', { href: 'mailto:' + email, text: email })]);
            $$('[data-fill="email"]').forEach((slot) => {
                slot.replaceChildren(' : ', h('a', { href: 'mailto:' + email, text: email }));
            });
        }
        const clubPatch = {};
        if (phone && !PLACEHOLDER_PHONES.includes(phone.replace(/\s/g, ''))) clubPatch.telephone = phone;
        if (hasEmail) clubPatch.email = email;
        const facebook = socialUrl(s.facebook_url);
        if (facebook) rows.push(['Facebook', h('a', { href: facebook, target: '_blank', rel: 'noopener', text: 'Page du club' })]);
        const instagram = socialUrl(s.instagram_url);
        if (instagram) rows.push(['Instagram', h('a', { href: instagram, target: '_blank', rel: 'noopener', text: 'Compte du club' })]);

        const sameAs = [facebook, instagram].filter(Boolean);
        patchClubLd({ club: clubPatch, sameAs });

        const footerList = $('#footer-contact');
        rows.forEach(([, content]) => footerList?.append(h('li', {}, content)));

        const target = $('#contact-details') || $('#legal-contact');
        const legalOnly = !$('#contact-details');
        rows
            .filter(([label]) => !legalOnly || label === 'Téléphone' || label === 'E-mail')
            .forEach(([label, content]) => target?.append(h('div', {}, h('dt', { text: label }), h('dd', {}, content))));
    }

    /* ---------- listes alimentées par l'API ---------- */

    const hideSection = (name) => {
        $$(`[data-section="${name}"]`).forEach((section) => { section.hidden = true; });
    };

    async function fill(selector, url, key, render, emptyAction, after) {
        const box = $(selector);
        if (!box) return;
        const limit = parseInt(box.dataset.limit || '0', 10);
        try {
            const data = await getJSON(url);
            let items = (data && data[key]) || [];
            if (limit) items = items.slice(0, limit);
            if (!items.length) {
                emptyAction(box);
                return;
            }
            box.replaceChildren(...items.map((item, index) => render(item, index)));
            if (after) after(items);
        } catch (error) {
            box.replaceChildren(loadFailed());
        }
    }

    function offerCard(item, index) {
        const price = parseFloat(item.price);
        const period = plain(item.period).trim();
        const suffix = period.startsWith('/');

        const priceBlock = h('div', { class: 'offer-price' });
        if (price > 0) {
            if (period && !suffix) priceBlock.append(h('span', { class: 'offer-prefix', text: period }));
            priceBlock.append(h('span', { class: 'offer-amount', text: euro(price) }));
            if (suffix) priceBlock.append(' ', h('span', { class: 'offer-prefix', text: period }));
        } else {
            priceBlock.append(h('span', { class: 'offer-ask', text: 'Prix sur Ten’Up' }));
        }

        const details = Array.isArray(item.features) ? item.features.map((line) => h('li', { text: plain(line) })) : [];

        return h('article', { class: `offer-card tone-${(index % 3) + 1}` },
            h('h3', { text: plain(item.name) }),
            details.length ? h('ul', { class: 'offer-details' }, details) : null,
            item.note ? h('p', { class: 'offer-details', text: plain(item.note) }) : null,
            h('div', { class: 'offer-foot' },
                priceBlock,
                h('a', { class: 'offer-link', href: links.offers, target: '_blank', rel: 'noopener', text: 'Voir sur Ten’Up' })));
    }

    function newsCard(item, index, clamp) {
        const date = toDate(item.news_date);
        const featured = !clamp && index === 0;
        return h('article', { class: 'news-card' + (clamp ? ' clamp' : '') + (featured ? ' featured' : '') },
            h('time', { datetime: String(item.news_date).slice(0, 10), text: `${dayNumber(date)} ${MONTHS[date.getMonth()]} ${date.getFullYear()}` }),
            h('h3', { text: plain(item.title) }),
            h('p', { text: plain(item.content) }));
    }

    function initLists() {
        const noOffers = (box) => box.replaceChildren(h('p', { class: 'empty', text: 'Les offres de la saison arrivent bientôt. Elles seront aussi sur Ten’Up.' }));
        fill('#offers', './backend/api/pricing.php?active=1', 'pricing', offerCard, noOffers, (items) => {
            const offers = items.filter((item) => parseFloat(item.price) > 0).map((item) => ({
                '@type': 'Offer',
                name: plain(item.name),
                price: String(parseFloat(item.price)),
                priceCurrency: 'EUR',
                url: links.offers,
                seller: { '@id': 'https://tc-batilly.fr/#club' },
            }));
            if (offers.length) {
                addLd('ld-offers', {
                    '@context': 'https://schema.org',
                    '@type': 'OfferCatalog',
                    name: 'Offres d’adhésion du Tennis Club de Batilly',
                    itemListElement: offers,
                });
            }
        });
        fill('#offers-teaser', './backend/api/pricing.php?active=1', 'pricing', offerCard, noOffers);

        const noNews = (box) => box.replaceChildren(h('p', { class: 'empty', text: 'Pas d’actualité pour le moment.' }));
        fill('#news-teaser', './backend/api/news.php?active=1&limit=3', 'news', (item, index) => newsCard(item, index, true), noNews);
        fill('#news-list', './backend/api/news.php?active=1&limit=20', 'news', (item, index) => newsCard(item, index, false), noNews);

        fill('#services-list', './backend/api/services.php?active=1', 'services',
            (item) => h('article', { class: 'service' }, h('h3', { text: plain(item.title) }), h('p', { text: plain(item.description) })),
            () => hideSection('services'));

        fill('#coaches-list', './backend/api/coaches.php?active=1', 'coaches',
            (item) => {
                const src = safeImage(item.image_url);
                return h('article', { class: 'coach' },
                    src ? h('img', { src, alt: plain(item.name), loading: 'lazy', width: 460, height: 460 }) : null,
                    h('h3', { text: plain(item.name) }),
                    h('p', { text: plain(item.role) }),
                    item.diploma ? h('p', { class: 'diploma', text: plain(item.diploma) }) : null);
            },
            () => hideSection('coaches'));

        fill('#partners-list', './backend/api/sponsors.php?active=1', 'sponsors',
            (item) => {
                const src = safeImage(item.logo_path);
                const href = safeUrl(item.website);
                const inner = src ? h('img', { src, alt: plain(item.name), loading: 'lazy' }) : h('span', { text: plain(item.name) });
                return href ? h('a', { class: 'partner', href, target: '_blank', rel: 'noopener' }, inner) : h('div', { class: 'partner' }, inner);
            },
            () => hideSection('sponsors'),
            () => { const section = $('#partenaires'); if (section) section.hidden = false; });
    }

    /* ---------- événements ---------- */

    let eventsPromise = null;
    function loadEvents() {
        if (!eventsPromise) {
            eventsPromise = getJSON('./backend/api/events.php?active=1').then((data) => (data.events || []).map((event) => {
                const start = toDate(event.event_date);
                let end = event.event_end_date ? toDate(event.event_end_date) : start;
                if (end < start) end = start;
                return {
                    id: event.id,
                    title: plain(event.title),
                    description: plain(event.description),
                    start,
                    end,
                    time: plain(event.event_time),
                    category: event.category,
                };
            }));
        }
        return eventsPromise;
    }

    const whenText = (event) => {
        if (sameDay(event.start, event.end)) return dayLabel(event.start, true);
        const sameYear = event.start.getFullYear() === event.end.getFullYear();
        return `Du ${dayLabel(event.start, !sameYear)} au ${dayLabel(event.end, true)}`;
    };

    const startOfToday = () => {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), now.getDate());
    };

    async function initNextEvents() {
        const box = $('#next-events');
        if (!box) return;
        try {
            const events = await loadEvents();
            const seen = new Set();
            const upcoming = events
                .filter((event) => event.end >= startOfToday())
                .sort((a, b) => a.start - b.start)
                .filter((event) => (seen.has(event.title) ? false : seen.add(event.title)))
                .slice(0, 3);

            if (!upcoming.length) {
                box.replaceChildren(h('p', { class: 'empty', text: 'Aucune date à venir pour le moment.' }));
                return;
            }
            box.replaceChildren(...upcoming.map((event) => h('a', { class: 'tile', href: 'calendrier.html' },
                h('div', { class: 'tile-date' }, h('strong', { text: dayNumber(event.start) }), h('span', { text: MONTHS_SHORT[event.start.getMonth()] })),
                h('h3', { text: event.title }),
                h('p', { text: whenText(event) }),
                h('span', { class: 'tag ' + event.category, text: CATEGORY[event.category] || 'Autre' }))));
        } catch (error) {
            box.replaceChildren(loadFailed());
        }
    }

    async function initFooterNext() {
        const slot = $('#footer-next');
        if (!slot) return;
        try {
            const events = await loadEvents();
            const next = events.filter((event) => event.end >= startOfToday()).sort((a, b) => a.start - b.start)[0];
            slot.textContent = next ? `${next.title} · ${whenText(next)}` : 'Les prochaines dates seront publiées bientôt.';
        } catch (error) {
            slot.textContent = 'Le calendrier est sur la page dédiée.';
        }
    }

    /* ---------- calendrier ---------- */

    const calendar = {
        events: [],
        filter: 'all',
        view: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
    };

    const visibleEvents = () => {
        const allowed = FILTERS[calendar.filter];
        return calendar.events.filter((event) => !allowed || allowed.includes(event.category));
    };

    const occursOn = (event, day) => event.start <= day && day <= event.end;

    function renderSeason() {
        const strip = $('#season');
        if (!strip) return;

        const now = new Date();
        const startYear = now.getMonth() >= 8 ? now.getFullYear() : now.getFullYear() - 1;
        const events = visibleEvents();
        const cells = [];

        for (let i = 0; i < 12; i++) {
            const monthDate = new Date(startYear, 8 + i, 1);
            const monthEnd = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0);
            const inMonth = events.filter((event) => event.start <= monthEnd && event.end >= monthDate);
            const categories = ['tournoi', 'stage', 'evenement', 'autre'].filter((category) => inMonth.some((event) => event.category === category));
            const current = monthDate.getFullYear() === calendar.view.getFullYear() && monthDate.getMonth() === calendar.view.getMonth();

            const button = h('button', {
                type: 'button',
                class: 'season-month',
                'data-year': monthDate.getFullYear(),
                'data-month': monthDate.getMonth(),
                'aria-current': current ? 'true' : 'false',
                'aria-label': `${MONTHS[monthDate.getMonth()]} ${monthDate.getFullYear()}, ${inMonth.length} événement${inMonth.length > 1 ? 's' : ''}`,
            },
            h('span', { class: 'm', text: MONTHS_SHORT[monthDate.getMonth()] }),
            h('span', { class: 'y', text: String(monthDate.getFullYear()) }),
            h('span', { class: 'bars' }, categories.map((category) => h('i', { class: 'c-' + category })), inMonth.length ? ' ' + inMonth.length : ''));
            cells.push(h('li', {}, button));
        }
        strip.replaceChildren(...cells);
    }

    function renderCalendar() {
        const grid = $('#cal-grid');
        const list = $('#cal-list');
        if (!grid || !list) return;

        const year = calendar.view.getFullYear();
        const month = calendar.view.getMonth();
        $('#cal-title').textContent = `${MONTHS[month]} ${year}`;
        $('#cal-list-title').textContent = `En ${MONTHS[month]} ${year}`;

        const events = visibleEvents();
        const first = new Date(year, month, 1);
        const offset = (first.getDay() + 6) % 7;
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const cells = Math.ceil((offset + daysInMonth) / 7) * 7;
        const today = new Date();

        const nodes = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'].map((name) => h('div', { class: 'cal-dow', text: name }));

        for (let i = 0; i < cells; i++) {
            const day = new Date(year, month, 1 - offset + i);
            const inMonth = day.getMonth() === month;
            const cell = h('div', { class: 'cal-day' + (inMonth ? '' : ' other') + (sameDay(day, today) ? ' today' : '') },
                h('span', { class: 'cal-num', text: String(day.getDate()) }));

            if (inMonth) {
                const todays = events.filter((event) => occursOn(event, day));
                todays.slice(0, 2).forEach((event) => {
                    const title = sameDay(day, event.start) || day.getDay() === 1 ? event.title : '';
                    cell.append(h('button', {
                        type: 'button',
                        class: 'cal-evt ' + event.category,
                        'data-id': event.id,
                        'aria-label': `${event.title}, ${whenText(event)}`,
                        text: title,
                    }));
                });
                if (todays.length > 2) cell.append(h('span', { class: 'cal-more', text: `+${todays.length - 2}` }));
            }
            nodes.push(cell);
        }
        grid.replaceChildren(...nodes);

        const monthStart = new Date(year, month, 1);
        const monthEnd = new Date(year, month + 1, 0);
        const inView = events
            .filter((event) => event.start <= monthEnd && event.end >= monthStart)
            .sort((a, b) => a.start - b.start || a.title.localeCompare(b.title, 'fr'));

        if (!inView.length) {
            list.replaceChildren(h('li', { class: 'empty', text: 'Rien de prévu ce mois-ci.' }));
        } else {
            list.replaceChildren(...inView.map((event) => h('li', { class: 'cal-item' },
                h('div', { class: 'cal-date' }, h('strong', { text: dayNumber(event.start) }), h('span', { text: MONTHS_SHORT[event.start.getMonth()] })),
                h('div', {}, h('h4', { text: event.title }), h('p', { text: whenText(event) + (event.description ? '. ' + event.description : '') })),
                h('span', { class: 'tag ' + event.category, text: CATEGORY[event.category] || 'Autre' }))));
        }
        renderSeason();
    }

    function openEvent(id) {
        const event = calendar.events.find((item) => String(item.id) === String(id));
        const dialog = $('#evt-dialog');
        if (!event || !dialog) return;
        $('#evt-title').textContent = event.title;
        $('#evt-when').textContent = whenText(event) + (event.time && event.time !== '(journée)' ? ', ' + event.time : '');
        $('#evt-text').textContent = event.description;
        const tag = $('#evt-tag');
        tag.textContent = CATEGORY[event.category] || 'Autre';
        tag.className = 'tag ' + event.category;
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
    }

    function downloadCalendar() {
        if (!calendar.events.length) return;
        const ymd = (date) => `${date.getFullYear()}${String(date.getMonth() + 1).padStart(2, '0')}${String(date.getDate()).padStart(2, '0')}`;
        const escape = (value) => String(value).replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\r?\n/g, '\\n');
        const fold = (line) => {
            const out = [];
            while (line.length > 60) {
                out.push(line.slice(0, 60));
                line = ' ' + line.slice(60);
            }
            out.push(line);
            return out;
        };
        const stamp = new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');

        const lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Tennis Club de Batilly//Calendrier//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:Tennis Club de Batilly'];
        calendar.events.forEach((event) => {
            const endExclusive = new Date(event.end.getFullYear(), event.end.getMonth(), event.end.getDate() + 1);
            lines.push('BEGIN:VEVENT', `UID:event-${event.id}@tc-batilly.fr`, `DTSTAMP:${stamp}`,
                `DTSTART;VALUE=DATE:${ymd(event.start)}`, `DTEND;VALUE=DATE:${ymd(endExclusive)}`,
                `SUMMARY:${escape(event.title)}`, `DESCRIPTION:${escape(event.description)}`);
            if (clubAddress) lines.push(`LOCATION:${escape('Tennis Club de Batilly, ' + clubAddress)}`);
            lines.push('END:VEVENT');
        });
        lines.push('END:VCALENDAR');

        const blob = new Blob([lines.flatMap(fold).join('\r\n') + '\r\n'], { type: 'text/calendar;charset=utf-8' });
        const anchor = h('a', { href: URL.createObjectURL(blob), download: 'TC_Batilly_Calendrier.ics' });
        document.body.append(anchor);
        anchor.click();
        anchor.remove();
        setTimeout(() => URL.revokeObjectURL(anchor.href), 1000);
    }

    async function initCalendar() {
        const grid = $('#cal-grid');
        if (!grid) return;

        try {
            calendar.events = await loadEvents();
        } catch (error) {
            grid.replaceChildren(loadFailed());
            return;
        }

        const today = startOfToday();
        const isoDay = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        const upcomingEvents = calendar.events.filter((event) => event.end >= today).sort((a, b) => a.start - b.start).slice(0, 25);
        if (upcomingEvents.length) {
            addLd('ld-events', upcomingEvents.map((event) => ({
                '@context': 'https://schema.org',
                '@type': 'SportsEvent',
                name: event.title,
                description: event.description || undefined,
                startDate: isoDay(event.start),
                endDate: isoDay(event.end),
                eventStatus: 'https://schema.org/EventScheduled',
                eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
                location: { '@type': 'Place', name: 'Tennis Club de Batilly', address: { '@type': 'PostalAddress', addressLocality: 'Batilly', postalCode: '54980', addressCountry: 'FR' } },
                organizer: { '@id': 'https://tc-batilly.fr/#club' },
            })));
        }
        const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
        const monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        if (!calendar.events.some((event) => event.start <= monthEnd && event.end >= monthStart)) {
            const next = calendar.events.filter((event) => event.end >= today).sort((a, b) => a.start - b.start)[0];
            if (next) calendar.view = new Date(next.start.getFullYear(), next.start.getMonth(), 1);
        }

        renderCalendar();

        const move = (delta) => {
            calendar.view = new Date(calendar.view.getFullYear(), calendar.view.getMonth() + delta, 1);
            renderCalendar();
        };
        $('#cal-prev').addEventListener('click', () => move(-1));
        $('#cal-next').addEventListener('click', () => move(1));
        $$('.tab').forEach((tab) => tab.addEventListener('click', () => {
            calendar.filter = tab.dataset.filter;
            $$('.tab').forEach((other) => other.setAttribute('aria-selected', String(other === tab)));
            renderCalendar();
        }));
        $('#season').addEventListener('click', (event) => {
            const button = event.target.closest('.season-month');
            if (!button) return;
            calendar.view = new Date(Number(button.dataset.year), Number(button.dataset.month), 1);
            renderCalendar();
            $('#cal-anchor').scrollIntoView({ block: 'start' });
        });
        grid.addEventListener('click', (event) => {
            const button = event.target.closest('.cal-evt');
            if (button) openEvent(button.dataset.id);
        });
        $('#ics-download').addEventListener('click', downloadCalendar);

        const dialog = $('#evt-dialog');
        $('#evt-close').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    }

    /* ---------- démarrage ---------- */

    $('#year').textContent = new Date().getFullYear();
    initMenu();
    loadSettings().finally(initLists);
    initNextEvents();
    initFooterNext();
    initCalendar();
})();
