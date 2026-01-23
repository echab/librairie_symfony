
# Configuration pour le dévelopement en local

- Dézipper le fichier `site_librairie-xxx.zip` dans `Mes documents` pour obtenir la hiérarchie suivante:

- Double-cliquer sur `install_outils.cmd` pour installer les outils VSCode, PHP, Composer et Symfony.

Organisation des fichiers:
```
📁Mes documents
   📁site_librairie
      📄.env              La configuration par défaut
      📄.env.local        La configuration locale (mots de passe, mailer)
      📁backup            Les zip de sauvegardes et d'installation
      📁posts             Tout le contenu du site (fichiers au format Markdown)
      📁templates         Tout le look des pages du site (syntaxe Twig)
      📁assets\styles  
         📄app.css        La feuille de styles du site
      📁public  
         📁images         Les images utilisées par les parties statiques du site
         📁uploads  
            📁images      Les images et photos utilisés par les pages
            📁stock       Les fichiers de stock envoyé par le PC de caisse

      📄.gitignore
      📄composer.json
      📄composer.lock
      📄importmap.php
      📄index.php
      📄deno.jsonc
      📁.vscode
      📁.git
      📁bin
      📁config            Les fichiers de configuration Symfony
      📁src               La logique du site (Controllers, Services)
      📁tests
      📁translations
      📁var
         📁log            Les log d'erreurs
         📁cache
      📁vendor
   📁outils
      📄install_outils.cmd
      📁php               PHP
      📁VSCode            L'éditeur de code source
```

- Lancer VSCode (outils\VSCode\code.exe) et installer les extensions VSCode suivantes:
    - Composer (🟐Devsense)
    - PHP (🟐Devsense)
    - PHP Debug (🟐Xdebug)
    - YAML (🟐Red Hat)
    - Twig Language 2 (mblode)
    - Tasks (actboy168)

- Installer les dépendances (dans vendor):
    - Dans la bar de status de VSCode, cliquer sur `🠋local install`

# Lancer le site en local

- Dans la barre de status de VSCode, cliquer sur `⏵local start` puis 'localhost:8001' pour ouvrir le navigateur.

# Arrêter le serveur local

- Dans la barre de status de VSCode, cliquer sur `▣local stop`.


# Debug

- Dans VSCode, View-Run (Ctrl+Maj+D), `Debug en lançant le serveur intégré`


# Deploiement

- Mise à jour de l'application:
    - Dans le fichier `compose.json`, s'assurer que le block `"archive"` correspond bien à `"name": "librairie_symfony_app"`
    - cliquer sur `📦Zip site` pour créer un nouveau fichier `librairie_symfony_app-*.zip`.
    - Dans la page d'admin https://ma-librairie.fr/admin, cliquer sur `Uploader un fichier zip`, selectionner le fichier zip précédent et cliquez sur `Upload`.
    - Puis sélectionnez le fichier uploadé et cliquez sur `🔺Unzip`.

- Mise à jour des dépendances:
    - Dans le fichier `compose.json`,
         - renommer la section `"archive"` de `librairie_symfony_app` en `"archive_A"`,
         - renommer la section `"archive_V"` en `"archive"` pour `librairie_symfony_vendor`,
    - cliquer sur `📦Zip site` pour créer un nouveau fichier `librairie_symfony_vendor-*.zip`.
    - restaurer les noms initiaux dans `compose.json` en `"archive_V"` pour `librairie_symfony_vendor` et `"archive"` pour `librairie_symfony_app`.

- `composer dump-env prod` will create a file `.env.local.php` to be copied/edited on the prod server.

- `composer install --no-dev --optimize-autoloader --classmap-authoritative`


# Déploiement sur Windows en local

1. installer [PHP](https://windows.php.net/download/)

2. installer [Composer](https://getcomposer.org/download/)

3. unzip les fichiers `librairie_symfony_app-*.zip` et `librairie_symfony_vendor-*.zip` dans un répertoire,

4. Ouvrir une console `cmd` dans le répertoire

```cmd
mklink /D /J assets  public\assets
mklink /D /J images  public\images
mklink /D /J uploads public\uploads
```

5. Créer le fichier `.env.local` en duplicant le fichier `.env` et en complétant les variables suivantes:

| variable          | contenu                      | example
|-------------------|------------------------------|---------------------------------------
| APP_SECRET        | 32 caractères aléatoires     | 'bhfbMt0WwGOKm2gKfvkqnCieDdcMrdeS'
| MAILER_DSN        | url pour l'envoi de mail     | 'smtp://libraire:DyPQrj0f@gmail.com:465'
| MAIL_CONTACT      | votre mail pour les tests    | john.doe@free.fr
| EAN_WEB_URL       | url où %s sera un code EAN   | 'https://www.grandelibrairie.fr/livres/%s.html'
| ADMIN_PASSWORD    | mot de passe encodé          | '$2y$13$bbR6VQtYz5xoXFYvjOajyo1.wCRJVQ/k59I'
| STOCK_PASSWORD    | mot de passe encodé          |
| *_PASSWORD        | mot de passe encodé          |

6. Lancer le serveur:

```cmd
php -S localhost:8008
```

et ouvrir la page `http://localhost:8008` dans un navigateur.

```mermaid
flowchart LR
route([Route _url_])
controller[Controller<br>_*.php_]
template[template<br>_*.twig_]
service[Service / Repository<br>_*.php_]
form[Form<br>_*.php_]
%% entity[Entity<br>_*.php_]
service[Service / Repository<br>_*.php_]

route --> controller
controller --> service
controller ---> template
%% controller -..-> entity
controller -.-> form --> template
```
