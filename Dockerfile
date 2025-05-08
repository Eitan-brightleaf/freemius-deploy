FROM php:8.2-cli

# Install git and clean up in one step to reduce image size
RUN apt-get update && apt-get install -y git && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY deploy.php /deploy.php
COPY ${file_name} /${file_name}
RUN git clone https://github.com/Freemius/freemius-php-sdk.git /freemius-php-api

CMD php /deploy.php