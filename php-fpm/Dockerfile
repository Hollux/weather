FROM php:8.3-fpm

ENV TZ=Europe/Paris
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone \
    && echo "date.timezone=${TZ}" > /usr/local/etc/php/conf.d/timezone.ini

# Installer unixODBC et ses dépendances
RUN apt-get update && apt-get install -y \
    unixodbc \
    unixodbc-dev \
    libodbc2 \
    && rm -rf /var/lib/apt/lists/*


# Spécifier le flavour 'unixODBC' lors de l'installation de pdo_odbc
RUN docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr \
    && docker-php-ext-install pdo_odbc

RUN docker-php-ext-install pdo pdo_mysql


# Copier le pilote ODBC IBM dans le conteneur
#COPY ibm_odbc_driver/ /opt/ibm/iaccess/lib/

# Copier le fichier php.ini personnalisé dans le conteneur
COPY php.ini /usr/local/etc/php/conf.d/odbc_custom.ini


# Configuration d'odbcinst.ini
#RUN echo "[IBM i Access ODBC Driver]" > /etc/odbcinst.ini && \
#    echo "Description=IBM i Access for Linux ODBC Driver" >> /etc/odbcinst.ini && \
#    echo "Driver=/opt/ibm/iaccess/lib/libcwbodbc.so" >> /etc/odbcinst.ini && \
#    echo "Setup=/opt/ibm/iaccess/lib/libcwbodbcs.so" >> /etc/odbcinst.ini && \
#    echo "[IBM i Access ODBC Driver 64-bit]" >> /etc/odbcinst.ini && \
#    echo "Description=IBM i Access for Linux 64-bit ODBC Driver" >> /etc/odbcinst.ini && \
#    echo "Driver=/opt/ibm/iaccess/lib64/libcwbodbc.so" >> /etc/odbcinst.ini

# Configuration d'odbc.ini avec des valeurs par défaut
#RUN echo "[MyDSN]" > /etc/odbc.ini && \
#    echo "Description=IBM i Access ODBC Driver" >> /etc/odbc.ini && \
#    echo "Driver=IBM i Access ODBC Driver" >> /etc/odbc.ini && \
#    echo "System=${ODBC_SERVER:-your_default_server}" >> /etc/odbc.ini && \
#    echo "UserID=${ODBC_USER:-your_default_user}" >> /etc/odbc.ini && \
#    echo "Password=${ODBC_PASSWORD:-your_default_password}" >> /etc/odbc.ini

# Copier votre application PHP dans le conteneur
COPY . /var/www/html

# Configurer la bibliothèque partagée
ENV LD_LIBRARY_PATH=/opt/ibm/iaccess/lib:$LD_LIBRARY_PATH

# Exposer le port 9000 pour PHP-FPM
EXPOSE 9000
