# file2sharepoint

FROM php:8.2-cli
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && install-php-extensions gettext intl zip
COPY src /usr/src/file2sharepoint/src
RUN sed -i -e 's/..\/.env//' /usr/src/file2sharepoint/src/*.php
COPY composer.json /usr/src/file2sharepoint
WORKDIR /usr/src/file2sharepoint
RUN curl -s https://getcomposer.org/installer | php
RUN ./composer.phar install
WORKDIR /usr/src/file2sharepoint/src
CMD [ "php", "./file2sharepoint.php" ]
