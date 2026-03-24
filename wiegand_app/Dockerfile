# On utilise une image officielle
FROM php:8.2-apache

# 1. On détruit le fichier index.html par défaut d'Apache
RUN rm -f /var/www/html/index.html

# 2. On copie tous vos fichiers PHP
COPY . /var/www/html/

# 3. On donne les droits sur les fichiers web
RUN chown -R www-data:www-data /var/www/html/

# 4. On expose le port
EXPOSE 80

# 🌟 L'ASTUCE ANTI-AMNÉSIE EST ICI 🌟
# Au démarrage du conteneur, on donne à Apache les droits sur le dossier persistant de HA
# Puis on lance le serveur web normalement
CMD chown -R www-data:www-data /data && apache2-foreground