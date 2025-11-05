FROM php:8.3-cli

ENV TZ=Europe/Paris

# Configuration timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone \
    && echo "date.timezone=$TZ" > /usr/local/etc/php/conf.d/timezone.ini

# Installer gnupg, dirmngr, curl, ca-certificates pour gérer les clés GPG sans apt-key
RUN apt-get update && apt-get install -y gnupg2 dirmngr curl ca-certificates

# Ajout des clés GPG manuellement dans /usr/share/keyrings
RUN mkdir -p /usr/share/keyrings && \
    curl -fsSL "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x0E98404D386FA1D9" | gpg --dearmor -o /usr/share/keyrings/key1.gpg && \
    curl -fsSL "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x6ED0E7B82643E131" | gpg --dearmor -o /usr/share/keyrings/key2.gpg && \
    curl -fsSL "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xF8D2585B8783D481" | gpg --dearmor -o /usr/share/keyrings/key3.gpg && \
    curl -fsSL "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x54404762BBB6E853" | gpg --dearmor -o /usr/share/keyrings/key4.gpg && \
    curl -fsSL "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xBDE6D2B9216EC7A8" | gpg --dearmor -o /usr/share/keyrings/key5.gpg

# Installation des paquets et nettoyage apt
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    libxml2-dev \
    locales \
    curl \
    nodejs \
    npm \
    unzip \
    && npm install --global yarn \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Installer les extensions PHP nécessaires
RUN docker-php-ext-install intl pdo pdo_mysql pdo_pgsql zip calendar dom

# Configuration des locales
RUN echo "en_US.UTF-8 UTF-8" > /etc/locale.gen \
    && echo "fr_FR.UTF-8 UTF-8" >> /etc/locale.gen \
    && locale-gen

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    && mv composer.phar /usr/local/bin/composer

# Installer Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin

# Exposer port 8000
EXPOSE 8000

# Définir répertoire de travail
WORKDIR /var/www/html

# Commande par défaut pour lancer le serveur Symfony
CMD ["symfony", "server:start", "--no-tls", "--allow-http", "--port=8000", "--allow-all-ip"]
