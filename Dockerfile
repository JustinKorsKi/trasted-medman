FROM php:8.2-apache

# Install mysqli and other extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy all project files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/

# Create startup script
RUN echo '#!/bin/bash\nsed -i "s/80/${PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-enabled/000-default.conf\napache2-foreground' > /start.sh
RUN chmod +x /start.sh

CMD ["/bin/bash", "/start.sh"]
