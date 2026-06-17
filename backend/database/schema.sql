-- ============================================
-- TC BATILLY - Base de données
-- ============================================

CREATE DATABASE IF NOT EXISTS tcbatilly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tcbatilly;

-- Table des utilisateurs admin
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor') DEFAULT 'editor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des événements
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    event_date DATE NOT NULL,
    day VARCHAR(10) NOT NULL,
    month VARCHAR(10) NOT NULL,
    category ENUM('tournoi', 'stage', 'evenement', 'autre') DEFAULT 'evenement',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_event_date (event_date),
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des actualités
CREATE TABLE IF NOT EXISTS news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    news_date DATE NOT NULL,
    emoji VARCHAR(10) DEFAULT '📰',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_news_date (news_date),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des tarifs
CREATE TABLE IF NOT EXISTS pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    badge VARCHAR(50),
    badge_type ENUM('default', 'popular') DEFAULT 'default',
    price DECIMAL(10,2) NOT NULL,
    period VARCHAR(20) DEFAULT '/ an',
    features JSON NOT NULL,
    note TEXT,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (display_order),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des entraîneurs/coachs
CREATE TABLE IF NOT EXISTS coaches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(150) NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    bio TEXT,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (display_order),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des services
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    icon VARCHAR(50),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (display_order),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des paramètres du site
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'textarea', 'number', 'boolean', 'json') DEFAULT 'text',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des logs d'activité
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DONNÉES INITIALES
-- ============================================

-- Utilisateur admin par défaut (mot de passe: admin123)
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@tcbatilly.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Événements initiaux (depuis le JSON existant)
INSERT INTO events (title, description, event_date, day, month, category, is_active) VALUES
('Tournoi Interne d''Automne', 'Compétition amicale ouverte à tous les membres. Inscription gratuite, lots à gagner !', '2024-11-14', '14', 'Nov', 'tournoi', TRUE),
('Stage Jeunes - Vacances de Noël', '2 semaines d''entraînement intensif pour progresser pendant les vacances (8-16 ans)', '2024-12-20', '20-31', 'Déc', 'stage', TRUE),
('Tournoi Inter-Clubs', 'Le TC Batilly reçoit 3 clubs de la région pour une journée de compétition conviviale', '2025-02-15', '15', 'Fév', 'tournoi', TRUE),
('Soirée de Clôture de Saison', 'Grande fête de fin d''année avec tournoi, barbecue et remise des prix. Ouvert à tous !', '2025-06-21', '21', 'Juin', 'evenement', TRUE);

-- Actualités initiales (depuis le HTML existant)
INSERT INTO news (title, content, news_date, emoji, is_active) VALUES
('Nouveau terrain couvert disponible !', 'Grande nouvelle pour nos membres ! Un nouveau court couvert de dernière génération sera disponible à partir de décembre 2024. Ce terrain en terre battue synthétique permettra de jouer toute l''année, quelles que soient les conditions météo. Les réservations sont déjà ouvertes via notre système en ligne.', '2024-10-15', '🎾', TRUE),
('Succès retentissant aux championnats régionaux', 'Nos équipes jeunes ont brillé lors des championnats régionaux de Lorraine ! Félicitations à Emma (13 ans) qui termine 2ème en catégorie Minimes, et à notre équipe 15-16 ans qui remporte le titre par équipes. Un grand bravo également à tous les participants pour leur fair-play et leur détermination !', '2024-10-08', '🏆', TRUE),
('Camp d''été 2025 - Inscriptions ouvertes', 'Ne manquez pas notre traditionnel camp d''été qui aura lieu du 7 au 18 juillet 2025. Au programme : entraînements techniques, préparation physique, tournois amicaux et animations. Ouvert aux jeunes de 8 à 16 ans, tous niveaux. Tarif : 280€ les 2 semaines (230€ pour les membres). Places limitées à 40 participants !', '2024-09-25', '☀️', TRUE),
('Assemblée Générale - Bilan de saison', 'L''assemblée générale du club s''est tenue le samedi 7 septembre en présence de 65 membres. Bilan très positif avec +15% d''adhérents cette année et de nombreux projets pour 2025 : rénovation des vestiaires, acquisition de nouveaux équipements et organisation d''un tournoi inter-clubs au printemps.', '2024-09-10', '👥', TRUE);

-- Tarifs initiaux
INSERT INTO pricing (name, badge, badge_type, price, period, features, note, display_order, is_active) VALUES
('Formule Junior', 'Jeunes', 'default', 120.00, '/ an', 
 '["Accès aux courts (hors heures de pointe)", "École de tennis incluse", "Participation aux tournois jeunes", "Prêt de matériel", "Stages vacances à tarif réduit"]',
 'Pour les moins de 18 ans', 1, TRUE),
('Formule Adulte', 'Populaire', 'popular', 180.00, '/ an',
 '["Accès illimité aux courts extérieurs", "2h/semaine sur court couvert", "Cours collectifs (1x/semaine)", "Participation à tous les tournois", "Réductions sur cours particuliers", "Vestiaires et douches"]',
 'Le meilleur rapport qualité-prix', 2, TRUE),
('Formule Famille', 'Famille', 'default', 320.00, '/ an',
 '["Jusqu''à 4 personnes", "Tous les avantages Adulte", "École de tennis pour les enfants", "Événements familiaux inclus", "-20% sur stages enfants", "Casiers privatifs"]',
 '2 adultes + 2 enfants maximum', 3, TRUE);

-- Entraîneurs initiaux
INSERT INTO coaches (name, role, image_url, display_order, is_active) VALUES
('Laura Martin', 'Entraîneuse principale - spécialité jeunes', 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=400&h=400&fit=crop', 1, TRUE),
('Marc Dupuis', 'Préparation physique & techniques avancées', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&h=400&fit=crop', 2, TRUE),
('Équipe de bénévoles', 'Organisation des événements et tournois locaux', 'https://images.unsplash.com/photo-1529626455594-4ff0802cfb7e?w=400&h=400&fit=crop', 3, TRUE);

-- Services initiaux
INSERT INTO services (title, description, display_order, is_active) VALUES
('Cours collectifs & privés', 'Des cours pour tous les âges et niveaux, dispensés par des entraîneurs qualifiés.', 1, TRUE),
('Stages et perfectionnement', 'Stages pendant les vacances scolaires, perfectionnement technique et préparation physique.', 2, TRUE),
('Location de terrain', 'Réservez un court en ligne ou sur place — utilisez le bouton RÉSERVER UN COURT dans le header.', 3, TRUE);

-- Paramètres du site
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'Tennis Club de Batilly', 'text', 'Nom du site'),
('contact_email', 'contact@tcbatilly.fr', 'text', 'Email de contact'),
('contact_phone', '+33383000000', 'text', 'Téléphone de contact'),
('address', 'Rue des Sports, 54980 Batilly, FRANCE', 'textarea', 'Adresse du club'),
('facebook_url', 'https://www.facebook.com', 'text', 'URL Facebook'),
('instagram_url', 'https://www.instagram.com', 'text', 'URL Instagram'),
('reservation_url', 'https://tenup.fft.fr/club/55540452/reservations', 'text', 'URL de réservation'),
('offers_url', 'https://tenup.fft.fr/club/55540452/offres', 'text', 'URL des offres');

-- ============================================
-- FIN DU SCRIPT
-- ============================================

