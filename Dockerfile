FROM php:8.1-apache

# Paketler ve modüller
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && a2enmod rewrite \
    && apt-get clean

WORKDIR /var/www/html

# Proje dosyaları
COPY . /var/www/html/

# Klasörler ve izinler
RUN chown -R www-data:www-data /var/www/html \
    && mkdir -p /var/www/html/database /var/www/html/uploads/company-logos \
    && chmod -R 777 /var/www/html/database \
    && chmod -R 777 /var/www/html/uploads

# Apache: DocumentRoot = public (403'ü önler)
RUN printf '%s\n' \
  '<VirtualHost *:80>' \
  '  DocumentRoot /var/www/html/public' \
  '  <Directory /var/www/html/public>' \
  '    Options FollowSymLinks' \
  '    AllowOverride All' \
  '    Require all granted' \
  '    DirectoryIndex index.php index.html' \
  '  </Directory>' \
  '  ErrorLog ${APACHE_LOG_DIR}/error.log' \
  '  CustomLog ${APACHE_LOG_DIR}/access.log combined' \
  '</VirtualHost>' \
  > /etc/apache2/sites-available/000-default.conf

EXPOSE 80
CMD ["apache2-foreground"]