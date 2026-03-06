FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

# ------------------------------------------------------
# Install Apache + PHP
# ------------------------------------------------------
RUN apt-get update && \
    apt-get install -y \
        apache2 \
        mariadb-client \
        php8.3 \
        php8.3-cli \
        php8.3-mysql \
        libapache2-mod-php8.3 && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# ------------------------------------------------------
# Apache modules
# ------------------------------------------------------
RUN a2enmod rewrite headers

# ------------------------------------------------------
# Copy Apache configs
# ------------------------------------------------------
COPY conf/apache/vhost.conf /etc/apache2/sites-available/cashcue.conf
COPY conf/apache/security.conf /etc/apache2/conf-available/cashcue-security.conf

RUN a2dissite 000-default.conf && \
    a2ensite cashcue.conf && \
    a2enconf cashcue-security 

#    systemctl restart apache2 || true

# ------------------------------------------------------
# Working directory
# ------------------------------------------------------
WORKDIR /var/www/html

# ------------------------------------------------------
# Copy docker entrypoint script into the container
# ------------------------------------------------------
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# ------------------------------------------------------
# Set the entrypoint to the custom script
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

EXPOSE 80

CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]