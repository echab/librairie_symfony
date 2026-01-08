# Reste à faire

✅ Format: date en français
✅ Banner image, responsive
✅ Login/Logout to edit post et coups-de-coeur
✅ enable SECURITY and access_control: config\packages\security.yaml

✅ Intl: cleanup unused translations files at build time, creating the vendor.zip
🟦 Intl: optimize with `vendor\symfony\intl\Resources\bin\update-data.php` to keep only fr locale as source

✅ Stock: test upload stock*.json.gz
❌ Stock: /wp-login.json instead of /wp-login. Ok with sym server, KO with php builtin web server & xdebug https://bugs.php.net/bug.php?id=61286
❌ Stock: GET and POST: /wp-json/lp/v1/livres.json instead of /wp-json/lp/v1/livres
✅ Stock: tag recent
✅ Stock: selection d'un livre, zoom sur les couvertures
✅ Stock: tag and link vers coup-de-coeur
✅ Stock: sort by: titre, auteur, date de sortie, prix
✅ Stock: display "date du stock"
✅ Stock: search multi mots
✅ Stock: compteurs sur chaque rayon
✅ Stock: cherche dans les autres rayons si pas de résultat "Livres trouvés dans les autres rayons:"
✅ Stock: optimisation: search local end javascript
✅ Stock: Search sans mot, un rayon selectionné:  ne pas afficher "trouver dans les autres rayons"
✅ Stock: the "Nouveauté" tag is missing for very recent books
✅ Stock: Date du stock, réaffichée si plus de 24h. Affiché l'âge.
✅ Stock: Pagination, search en php (SEO)
🟦 #TODO Stock: Navigation dans les sous-rayon
🟦 #TODO Stock: Delete/archive old stock*.json.gz files

❌ Coeur: ean dans le slug?
✅ Coeur: intégrer image
✅ Coeur: optimiser image
✅ Coeur: sélection du rayon dans la liste
✅ Import WordPress xml file for coups de cœur
✅ Coeur: get resume from the web
✅ Coeur: Eviter les doublons
✅ Coeur: Afficher Rayon post->rayon plutôt que le slug: Rayon {{post.category}}

🟦 #TODO Post: ctrl+S pour sauver
✅ Post: aide pour insérer des images
✅ Post: image vignettes: ajouter #vignette à la fin de l'url
✅ Post: picture gallery des uploads
🟦 #TODO Post: supprimer un post (à la poubelle, lister/sortir de la poubelle)
🟦 #TODO Post: archive
🟦 #TODO Post: couleurs pour bold et italic
🟦 #TODO Post: Tronquer le texte en mode list, une seule images, en vignette
✅ Post: preview
✅ Post: markdown, lien vers doc externe
✅ Post: toolbar de boutons de style markdown pour les images
🟦 #TODO Post: toolbar de boutons de style markdown
✅ Post: BUG enlever la confirmation "leave the page" quand on clique sur "Publier"
✅ Post: image upload, enlever le uuid
✅ Image upload, convertir en jpg si 2 fois plus petit que le png
✅ BUG PostRepository should not mix rayon level 1 and 2, example: litteratre/polar
✅ sur le serveur, déplacer les posts dans des sous-folder par sous-rayon (renommer folder to posts_, unzip dans posts)
🟦 #TODO Post: drag&drop image https://picnicss.com/documentation#dropimage

🟦 #TODO Agenda: à paufiner

✅ Contact: tester envoi d'email

✅ Optimize: PostRepository, cache the posts using Symfony Cache

✅ Photos: post category photo
✅ Photos: 2 tailles d'images, "thumb" utilisé dans les liste et la gallerie photos
🟦 #TODO Global search
✅ Convert existing coups-de-coeurs
✅ Panier

🟦 #TODO Version mobile: dépend nombre pixels et taille physique

✅ Admin: Users review roles
✅ Admin: Users: change password (saved into .env.local.php)
🟦 #TODO Admin: Users: Add user (saved into security.yaml and .env.local.php)
🟦 #TODO Admin: Backup incremental (post, uploads), base on file dates, into local zip file
✅ Admin: Restore incremental (post, uploads), from a local zip file
✅ Admin: Update app, vendors, loading and unzippng a zip
✅ Admin: Backup/restore posts to/from a zip
🟦 Admin: Delete a Backup

❌ Déployer: install composer ?
✅ Déployer: build for prod ?
✅ Déployer: Apache: .htaccess
✅ Déployer: install.php a simple form to get a password and target folder, for first unzip
✅ protect all the non-public folder on the server: .htaccess DENY FROM ALL
✅ allow wp-content/uploads content, and the new implemented wp-login and wp-json pages
✅ unzip, force overwrite of public/assets/manifest.json and public/assets/importmap.json, using mtime

✅ enable some Symfony logs into /var/log/prod.log
🟦 #TODO understand why https://ma-librairie.fr/ returns error 500 if not 'APP_DEBUG' => true,
    ✅ deploy dev bundles to test on the server
    ✅ customize error.html.twig to render some error 500 details when authenticated. https://symfony.com/doc/6.4/controller/error_pages.html#overriding-the-default-errorcontroller
    ✅ customize APP-DEBUG error_renderer to show only "Error 500" and some details only if connected
    ✅ run locally with APP_ENV=prod, reinstall the symfony/messenger bundle with empty config

✅ Editor: Tasks to install, start, stop and build
✅ Editor: stop php to kill "php-cgi.exe" processes
🟦 Editor: Simpler zip sources (a php script in bin) instead of composer archive

🟦 Admin: Run composer command
🟦 Admin: Check all vendors: composer show -l
🟦 Admin: Check outdated vendors: composer outdated
🟦 Admin: Update vendors: composer update
🟦 Migrate to PHP 8.4 and Symfony 7.4 (novembre 2025)
🟦 Fix headers for security: Strict-Transport-Security, Content-Security-Policy, X-Frame-Options, X-Content-Type-Options https://securityheaders.com/?q=https%3A%2F%2Flibrairie-lespassantes.fr%2F see: https://symfony.com/bundles/NelmioSecurityBundle

✅ Lien instagram
✅ Panier couleur clair
✅ Add Panier dans coup de cœur
✅ Contact ok
✅ Panier avec commande
✅ Contact Nom et prénom
✅ Page de garde, accueil, fil rouge, etc.
🟦 Naviguer vers les vieux coups de cœur
✅ Importer les coeurs: C:\Users\chabaud\Documents\perso\Librairie\data\Philippe-Bouquet-2021php@gmail.com-WordPress.2025-08-19.xml
✅ Ajouter "#gauche" (-200x303-1.jpg#gauche) à toutes les images des coups de coeur (zip, en local, upload)
