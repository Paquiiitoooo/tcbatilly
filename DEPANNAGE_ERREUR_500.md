# 🔧 Dépannage Erreur 500 - TC Batilly

## ❌ Problème : Internal Server Error (500)

L'erreur 500 indique un problème de configuration serveur. Voici les solutions étape par étape.

---

## 🔍 Solution 1 : Vérifier le fichier .htaccess

Le fichier `.htaccess` peut causer une erreur 500 si certaines directives ne sont pas supportées par IONOS.

### Action immédiate :

1. **Renommez temporairement `.htaccess`** :
   - Via FileZilla, renommez `.htaccess` en `.htaccess.backup`
   - Testez si le site fonctionne maintenant
   
2. **Si ça fonctionne** : Le problème vient du `.htaccess`
   - Utilisez la version simplifiée fournie dans ce projet
   - Remplacez `.htaccess.backup` par le nouveau `.htaccess`

3. **Si ça ne fonctionne toujours pas** : Passez à la Solution 2

---

## 🔍 Solution 2 : Vérifier le fichier .env

Le fichier `.env` peut causer une erreur si :
- Il n'existe pas
- Il contient des caractères spéciaux mal échappés
- Le chemin est incorrect

### Vérifications :

1. **Le fichier `.env` existe-t-il ?**
   - Via FileZilla, vérifiez que le fichier `.env` est bien dans `tcb/`
   - ⚠️ Les fichiers commençant par `.` sont souvent cachés dans FileZilla
   - Activez l'affichage des fichiers cachés : **Serveur** → **Forcer l'affichage des fichiers cachés**

2. **Le contenu du `.env` est-il correct ?**
   - Ouvrez le fichier `.env` via FileZilla
   - Vérifiez qu'il n'y a pas d'espaces avant/après les `=`
   - Vérifiez qu'il n'y a pas de guillemets en trop

3. **Testez sans .env** :
   - Renommez temporairement `.env` en `.env.backup`
   - Le site devrait afficher une erreur de connexion DB (pas une 500)
   - Si c'est le cas, le problème vient du `.env`

---

## 🔍 Solution 3 : Vérifier les permissions

Les permissions incorrectes peuvent causer une erreur 500.

### Actions :

1. **Permissions des fichiers** :
   - Tous les fichiers PHP/HTML doivent être en **644**
   - Via FileZilla : Clic droit → **Permissions** → `644`

2. **Permissions des dossiers** :
   - Tous les dossiers doivent être en **755**
   - Dossiers à vérifier :
     - `tcb/` → **755**
     - `tcb/admin/` → **755**
     - `tcb/assets/` → **755**
     - `tcb/assets/uploads/` → **755**
     - `tcb/backend/` → **755**

3. **Permissions spéciales pour uploads** :
   - `tcb/assets/uploads/` → **755** ou **777** (si 755 ne fonctionne pas)
   - `tcb/assets/uploads/sponsors/` → **755** ou **777**

---

## 🔍 Solution 4 : Vérifier la configuration PHP

IONOS peut avoir des restrictions sur certaines directives PHP.

### Actions :

1. **Vérifiez la version PHP** :
   - Dans votre panneau IONOS, vérifiez que PHP 7.4+ est activé
   - Activez PHP 8.0 ou 8.1 si disponible

2. **Vérifiez les extensions PHP** :
   - Assurez-vous que `PDO` et `pdo_mysql` sont activées
   - Vérifiez dans le panneau IONOS → **PHP Settings**

3. **Créez un fichier `test.php`** dans `tcb/` :
   ```php
   <?php
   phpinfo();
   ?>
   ```
   - Visitez `votre-url/test.php`
   - Si ça fonctionne, PHP est OK
   - **Supprimez ce fichier après le test** (sécurité)

---

## 🔍 Solution 5 : Vérifier le RewriteBase (si sous-dossier)

Si votre projet est dans `tcb/` (sous-dossier), le `RewriteBase` doit être configuré.

### Actions :

1. **Ouvrez `.htaccess`** via FileZilla
2. **Si vous êtes dans un sous-dossier** (`votre-domaine.fr/tcb`) :
   - Décommentez : `RewriteBase /tcb/`
   - Commentez : `# RewriteBase /`

3. **Si vous êtes sur un sous-domaine** (`tcb.votre-domaine.fr`) :
   - Laissez : `RewriteBase /`

---

## 🔍 Solution 6 : Vérifier les logs d'erreur

Les logs d'erreur IONOS contiennent la cause exacte.

### Actions :

1. **Accédez aux logs** dans votre panneau IONOS :
   - Cherchez **"Logs"** ou **"Error Logs"**
   - Ouvrez le fichier de log le plus récent

2. **Cherchez les erreurs** :
   - Erreurs PHP (syntaxe, fonctions manquantes)
   - Erreurs de permissions
   - Erreurs de chemin de fichier

3. **Partagez l'erreur** pour obtenir de l'aide

---

## 🔍 Solution 7 : Test minimal

Testez avec un fichier PHP minimal pour isoler le problème.

### Actions :

1. **Créez `test_minimal.php`** dans `tcb/` :
   ```php
   <?php
   echo "PHP fonctionne !";
   ?>
   ```

2. **Visitez** `votre-url/test_minimal.php`
   - Si ça fonctionne : Le problème vient de votre code
   - Si ça ne fonctionne pas : Le problème vient de la configuration serveur

3. **Testez la connexion DB** avec `test_db.php` :
   ```php
   <?php
   require_once 'backend/config/config.php';
   require_once 'backend/config/database.php';
   
   try {
       $db = get_db();
       echo "Connexion DB OK !";
   } catch (Exception $e) {
       echo "Erreur DB : " . $e->getMessage();
   }
   ?>
   ```

---

## 📋 Checklist de dépannage rapide

- [ ] `.htaccess` renommé temporairement → Site fonctionne ?
- [ ] Fichier `.env` existe et est lisible
- [ ] Permissions fichiers : **644**
- [ ] Permissions dossiers : **755**
- [ ] `RewriteBase` correct selon sous-domaine/sous-dossier
- [ ] PHP 7.4+ activé
- [ ] Extensions `PDO` et `pdo_mysql` activées
- [ ] Logs d'erreur consultés
- [ ] Fichier `test.php` fonctionne

---

## 🆘 Solution d'urgence : .htaccess minimal

Si rien ne fonctionne, créez un `.htaccess` minimal :

```apache
# .htaccess minimal pour IONOS
RewriteEngine On
RewriteBase /

# Protection .env
<FilesMatch "^\.env$">
    Require all denied
</FilesMatch>
```

Testez avec ce fichier minimal, puis ajoutez les autres directives une par une.

---

## 📞 Informations à fournir pour obtenir de l'aide

Si le problème persiste, fournissez :
1. Le message d'erreur exact des logs IONOS
2. La structure de vos fichiers (sous-domaine ou sous-dossier ?)
3. La version PHP utilisée
4. Le résultat du fichier `test.php` (phpinfo)

---

**Bon dépannage ! 🔧**

