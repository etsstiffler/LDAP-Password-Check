FROM php:8.2.8-cli
RUN apt-get update && apt-get install -y \
    cron \
    zip \
    unzip\
    libldap2-dev\
    logrotate

RUN apt-get clean

# Install LDAP Extension
RUN docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install ldap

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Add logrotate conf
ADD log.conf /etc/logrotate.d/log.conf

# Set file rights 
RUN chmod 0644 /etc/logrotate.d/log.conf

# Add Crontab File
ADD crontab /etc/cron.d/cron
# Give execution rights on the cron job
RUN chmod 0644 /etc/cron.d/cron

# Running crontab
RUN crontab /etc/cron.d/cron

ADD ./ /app
WORKDIR /app

# Install PHPMailer
RUN composer install
RUN composer require phpmailer/phpmailer

# Create the log file to be able to run tail
RUN touch /var/log/cron.log
 
# Run the command on container startup
# Run the cron service in the foreground
CMD ["cron"]
