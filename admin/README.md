# 🎾 Panel d'Administration - TC Batilly

Le panel d'administration est maintenant accessible directement via l'URL simplifiée :

**🔗 http://localhost/TCBatilly/admin**

## 📍 Nouvelle Structure

```
admin/
├── login.php          # Page de connexion
├── logout.php         # Déconnexion
├── index.php          # Dashboard principal
├── events.php         # Gestion des événements
├── news.php           # Gestion des actualités
├── pricing.php        # Gestion des tarifs
├── coaches.php        # Gestion des entraîneurs
├── services.php       # Gestion des services
│
├── includes/
│   ├── header.php     # Header commun
│   └── footer.php     # Footer commun
│
└── assets/
    ├── css/
    │   └── admin.css  # Styles du panel
    └── js/
        └── admin.js   # Scripts du panel
```

## 🚀 Accès Rapide

- **Page de connexion** : `/admin/login.php` ou `/admin`
- **Dashboard** : `/admin/index.php`
- **Identifiants par défaut** :
  - Username : `admin`
  - Password : `admin123`

## ⚙️ Configuration

L'URL est définie dans `backend/config/config.php` :

```php
define('ADMIN_URL', SITE_URL . '/admin');
```

## 📝 Notes

- Les fichiers du backend (config, database, API) restent dans `/backend/`
- Seul le panel admin a été déplacé pour simplifier l'URL d'accès
- Les chemins relatifs ont été ajustés automatiquement dans tous les fichiers

## 🔒 Sécurité

Pour protéger l'accès au dossier admin en production, ajoutez dans `/admin/.htaccess` :

```apache
# Bloquer l'accès par IP (optionnel)
Order Deny,Allow
Deny from all
Allow from 192.168.1.0/24
Allow from xxx.xxx.xxx.xxx

# Ou protection par mot de passe
AuthType Basic
AuthName "Zone Restreinte"
AuthUserFile /chemin/absolu/.htpasswd
Require valid-user
```

---

**Développé avec ❤️ pour le TC Batilly**

