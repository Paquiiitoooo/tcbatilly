# TC Batilly

Site web du Tennis Club de Batilly avec frontend statique, backend PHP, panel d'administration et base MySQL/MariaDB.

## CI/CD GitHub Actions

Le workflow se trouve dans `.github/workflows/ci-cd.yml`.

Il lance automatiquement :

- sur `push` et `pull_request` vers `main` : validation de la syntaxe PHP, validation JSON et présence des fichiers essentiels ;
- sur `push` vers `main` uniquement : déploiement FTPS vers l'hébergement IONOS.

## Secrets GitHub requis

Dans le repo GitHub, aller dans `Settings` -> `Secrets and variables` -> `Actions` -> `New repository secret`.

Créer ces secrets :

- `FTP_SERVER` : hote FTP/FTPS IONOS ;
- `FTP_USERNAME` : utilisateur FTP/FTPS ;
- `FTP_PASSWORD` : mot de passe FTP/FTPS ;
- `FTP_PORT` : port FTPS, souvent `21` en FTPS explicite ;
- `FTP_SERVER_DIR` : dossier distant de publication, par exemple `./` ou le dossier du sous-domaine.

Le fichier `.env` de production doit rester sur le serveur et ne doit pas être commité.

## Premier push

```bash
git init
git add .
git commit -m "Initial commit"
git branch -M main
git remote add origin git@github.com:Paquiiitoooo/tcbatilly.git
git push -u origin main
```
