FROM php:8.4-apache

# Install library PostgreSQL untuk koneksi Supabase & aktifkan mod_rewrite Apache
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Izinkan override konfigurasi & hilangkan peringatan ServerName Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Salin seluruh file aplikasi dari folder Backend ke root web Apache
COPY . /var/www/html/

# Atur hak akses folder agar tidak bermasalah saat membaca file/session
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

# Jalankan Apache di port bawaan 80 (atau sesuaikan otomatis jika Railway menyuntikkan variabel $PORT)
CMD ["sh", "-c", "sed -i \"s/80/\${PORT:-80}/g\" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf && apache2-foreground"]