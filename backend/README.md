# 🎾 TC Batilly - Backend & Panel d'Administration

Backend PHP complet avec interface d'administration moderne pour gérer dynamiquement le site du Tennis Club de Batilly.

## 📋 Table des matières

- [Fonctionnalités](#fonctionnalités)
- [Technologies utilisées](#technologies-utilisées)
- [Installation](#installation)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
- [Structure du projet](#structure-du-projet)
- [API Endpoints](#api-endpoints)
- [Sécurité](#sécurité)

## ✨ Fonctionnalités

### Panel d'administration
- ✅ **Dashboard** avec statistiques et activité récente
- 📅 **Gestion des événements** - Calendrier du club
- 📰 **Gestion des actualités** - News et annonces
- 💰 **Gestion des tarifs** - Formules d'adhésion
- 👥 **Gestion des entraîneurs** - Profils et photos
- ⚡ **Gestion des services** - Services proposés
- 🔐 **Authentification sécurisée**
- 📊 **Logs d'activité** - Traçabilité complète
- 🎨 **Interface moderne** - Design épuré et responsive

### API REST
- 🔌 API RESTful pour toutes les entités
- 📡 Format JSON
- 🛡️ Protection CSRF
- ⚡ Performance optimisée

## 🛠️ Technologies utilisées

- **PHP 7.4+** - Backend
- **MySQL/MariaDB** - Base de données
- **PDO** - Accès sécurisé à la base de données
- **HTML5/CSS3** - Interface utilisateur
- **JavaScript ES6+** - Interactivité
- **Inter Font** - Typographie moderne

## 📦 Installation

### Prérequis

- PHP 7.4 ou supérieur
- MySQL 5.7+ ou MariaDB 10.3+
- Serveur web (Apache/Nginx)
- Extension PHP PDO_MYSQL activée

### Étapes d'installation

#### 1. Cloner le projet

```bash
git clone <votre-repo>
cd TCBatilly
```

#### 2. Créer la base de données

Connectez-vous à MySQL :

```bash
mysql -u root -p
```

Exécutez le script SQL :

```sql
SOURCE backend/database/schema.sql;
```

Ou importez via phpMyAdmin en utilisant le fichier `backend/database/schema.sql`.

#### 3. Configurer les paramètres

1. Copiez le fichier d'exemple : `cp backend/config/env.dist .env`
2. Éditez `.env` et renseignez vos valeurs :
   ```
   SITE_URL=https://tc-batilly.fr
   ADMIN_URL=https://tc-batilly.fr/admin

   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=tcb_bdd_site
   DB_USER=tcb_admin_site
   DB_PASS=motdepasse
   ```
   > Un modèle prêt à l'emploi pour la production se trouve dans `env_IONOS.txt` (racine).
   > Astuce : vous pouvez également utiliser une variable unique `DATABASE_URL=mysql://utilisateur:motdepasse@hote:port/base?charset=utf8mb4`
3. Ajustez `ENVIRONMENT` (`development` ou `production`) et `DEBUG_MODE` selon votre contexte.

⚠️ Le fichier `backend/config/config.php` lit automatiquement ces valeurs, évitez d'éditer le fichier PHP directement.

#### 4. Permissions (Linux/Mac)

```bash
chmod -R 755 backend/
chmod -R 777 assets/uploads/
```

#### 5. Configurer le serveur web

**Apache (.htaccess)**

Un fichier `.htaccess` est déjà inclus pour Apache.

**Nginx**

```nginx
location /backend/api/ {
    try_files $uri $uri/ /backend/api/index.php?$query_string;
}
```

## 🔧 Configuration

- Contrôlez l'environnement depuis `.env` (`ENVIRONMENT` + `DEBUG_MODE`)
- Mettez à jour `PASSWORD_SALT` avec une chaîne unique (toujours via `.env`)
- Les paramètres base de données acceptent `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET` ou un `DATABASE_URL`
- Les limites d'upload sont configurables (`MAX_UPLOAD_SIZE`, `ALLOWED_EXTENSIONS`, `UPLOAD_URL`)
- `APP_TIMEZONE` permet d'ajuster le fuseau horaire sans modifier le code

## 🚀 Déploiement sur IONOS (Plesk)

1. **Préparer l'environnement**
   - Pointez le domaine `tc-batilly.fr` vers le dossier web et activez HTTPS (Let's Encrypt)
   - Créez la base de données MySQL/MariaDB et un utilisateur dédié
2. **Uploader l'application**
   - Déposez l'intégralité du projet dans le dossier web du sous-domaine (le `DocumentRoot` doit contenir `index.html`)
   - Copiez `backend/config/env.dist` en `.env` et complétez les variables (URL, base de données, sécurité, uploads)
   - Vérifiez les droits d'écriture sur `assets/uploads/` (via Plesk ou `chmod 775`)
3. **Initialiser la base**
   - Importez `backend/database/schema.sql` via phpMyAdmin ou la CLI
   - Connectez-vous au panel d'administration (`/admin`) avec `admin` / `admin123` et changez immédiatement le mot de passe
4. **Tests & durcissement**
   - Testez le frontend et les endpoints API (ex : `/backend/api/events.php?active=1`)
   - Gardez `DEBUG_MODE=false` en production
   - Optionnel : restreignez l'accès direct au répertoire `backend/config/` via `.htaccess` ou le pare-feu Plesk

## 👨‍💻 Utilisation

### Accès au panel d'administration

1. Ouvrez votre navigateur : `http://localhost/TCBatilly/admin/login.php`
2. Connectez-vous avec les identifiants par défaut :
   - **Username** : `admin`
   - **Password** : `admin123`

⚠️ **Changez immédiatement le mot de passe en production !**

### Gestion des contenus

#### Événements
- Accédez à **Événements** dans le menu
- Cliquez sur "Ajouter un événement"
- Remplissez le formulaire (titre, description, date, catégorie)
- Le calendrier sur le site se met à jour automatiquement

#### Actualités
- Accédez à **Actualités** dans le menu
- Créez une nouvelle actualité avec un emoji personnalisé
- L'actualité apparaît instantanément sur le site

#### Tarifs
- Gérez les formules d'adhésion
- Ajoutez plusieurs caractéristiques par formule
- Définissez les badges (Populaire, Famille, etc.)

#### Entraîneurs
- Ajoutez les profils des entraîneurs
- Uploadez des photos via URL
- Définissez les spécialités

#### Services
- Listez tous les services du club
- Personnalisez avec des emojis

## 📁 Structure du projet

```
TCBatilly/
│
├── backend/
│   ├── config/
│   │   ├── config.php           # Configuration principale
│   │   └── database.php         # Connexion DB (Singleton)
│   │
│   ├── database/
│   │   ├── schema.sql           # Structure de la base de données
│   │   ├── install_complete.sql # Installation complète (schéma + données)
│   │   └── migration_*.sql      # Migrations à appliquer sur une base existante
│   │
│   ├── api/
│   │   ├── events.php           # API Événements
│   │   ├── news.php             # API Actualités
│   │   ├── pricing.php          # API Tarifs
│   │   ├── coaches.php          # API Entraîneurs
│   │   ├── services.php         # API Services
│   │   └── sponsors.php         # API Sponsors
│   │
│   └── README.md                # Ce fichier
│
├── admin/                       # Panel d'administration (unique)
│   ├── index.php               # Dashboard
│   ├── login.php               # Connexion (2FA email + reset password)
│   ├── logout.php              # Déconnexion
│   ├── forgot_password.php     # Mot de passe oublié
│   ├── reset_password.php      # Réinitialisation du mot de passe
│   ├── events.php              # Gestion événements
│   ├── calendar.php            # Vue calendrier des événements
│   ├── news.php                # Gestion actualités
│   ├── pricing.php             # Gestion tarifs
│   ├── coaches.php             # Gestion entraîneurs
│   ├── services.php            # Gestion services
│   ├── sponsors.php            # Gestion sponsors
│   ├── settings.php            # Paramètres & utilisateurs
│   ├── logs.php                # Historique des actions
│   │
│   ├── includes/
│   │   ├── header.php          # Header admin
│   │   └── footer.php          # Footer admin
│   │
│   └── assets/
│       ├── css/
│       │   └── admin.css       # Styles admin
│       └── js/
│           └── admin.js        # Scripts admin
│
├── assets/
│   ├── css/
│   │   └── style.css            # Styles frontend
│   ├── js/
│   │   └── script.js            # Scripts frontend (modifié pour API)
│   ├── data/
│   │   └── events.json          # [Deprecated] Données statiques
│   └── uploads/                 # Uploads (créer ce dossier)
│
└── index.html                   # Page d'accueil du site
```

## 🔌 API Endpoints

Toutes les API retournent du JSON avec le format :

```json
{
  "success": true,
  "data": [...],
  "error": null
}
```

### Événements

```
GET  /backend/api/events.php?active=1&limit=100
POST /backend/api/events.php [Auth required]
PUT  /backend/api/events.php [Auth required]
DELETE /backend/api/events.php [Auth required]
```

### Actualités

```
GET  /backend/api/news.php?active=1&limit=10
POST /backend/api/news.php [Auth required]
PUT  /backend/api/news.php [Auth required]
DELETE /backend/api/news.php [Auth required]
```

### Tarifs

```
GET  /backend/api/pricing.php?active=1
POST /backend/api/pricing.php [Auth required]
PUT  /backend/api/pricing.php [Auth required]
DELETE /backend/api/pricing.php [Auth required]
```

### Entraîneurs

```
GET  /backend/api/coaches.php?active=1
POST /backend/api/coaches.php [Auth required]
PUT  /backend/api/coaches.php [Auth required]
DELETE /backend/api/coaches.php [Auth required]
```

### Services

```
GET  /backend/api/services.php?active=1
POST /backend/api/services.php [Auth required]
PUT  /backend/api/services.php [Auth required]
DELETE /backend/api/services.php [Auth required]
```

### Exemple de requête

**GET - Récupérer les événements actifs**

```javascript
fetch('http://localhost/TCBatilly/backend/api/events.php?active=1')
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      console.log(data.events);
    }
  });
```

**POST - Créer un événement (authentifié)**

```javascript
fetch('http://localhost/TCBatilly/backend/api/events.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    title: 'Tournoi d\'été',
    description: 'Grand tournoi annuel',
    event_date: '2024-07-15',
    day: '15',
    month: 'Juil',
    category: 'tournoi',
    is_active: true
  })
})
.then(res => res.json())
.then(data => console.log(data));
```

## 🛡️ Sécurité

### Mesures implémentées

- ✅ **Prepared Statements** - Protection contre les injections SQL
- ✅ **Password Hashing** - Bcrypt avec salt
- ✅ **Session Management** - Sessions sécurisées avec timeout
- ✅ **Input Sanitization** - Validation et nettoyage des entrées
- ✅ **CSRF Protection** - Headers de sécurité
- ✅ **XSS Protection** - htmlspecialchars() sur toutes les sorties
- ✅ **Activity Logging** - Traçabilité complète des actions

### Recommandations de sécurité

1. **Changez le mot de passe admin** immédiatement après installation
2. **Modifiez le PASSWORD_SALT** dans `.env`
3. **Activez HTTPS** en production
4. **Limitez l'accès** au dossier `/backend/` via IP (optionnel)
5. **Sauvegardez régulièrement** la base de données
6. **Mettez à jour** PHP et MySQL régulièrement

### Fichier .htaccess (Apache)

```apache
# Protéger les fichiers sensibles
<FilesMatch "^(config\.php|database\.php)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Bloquer l'accès direct aux fichiers d'inclusion
<Files "header.php">
    Order allow,deny
    Deny from all
</Files>
<Files "footer.php">
    Order allow,deny
    Deny from all
</Files>
```

## 🐛 Dépannage

### Erreur de connexion à la base de données

Vérifiez :
- Les identifiants dans `config.php`
- Que MySQL est démarré
- Que la base `tcbatilly` existe

### Page blanche

Activez le mode debug dans `config.php` :

```php
define('DEBUG_MODE', true);
```

### Permissions refusées

```bash
chmod -R 755 backend/
chown -R www-data:www-data backend/
```

### API ne retourne rien

Vérifiez les logs d'erreur PHP :
- Linux : `/var/log/apache2/error.log`
- Windows : `C:\xampp\apache\logs\error.log`

## 📝 Changelog

### Version 1.0.0 (2024)

- ✨ Interface d'administration complète
- 🔌 API REST pour toutes les entités
- 📊 Dashboard avec statistiques
- 🔐 Système d'authentification
- 📝 Logs d'activité
- 🎨 Design moderne et responsive

## 👨‍💻 Développeur

Créé avec ❤️ pour le TC Batilly

## 📄 Licence

Tous droits réservés © 2024 TC Batilly

---

**Questions ou problèmes ?** Contactez l'administrateur système.

