FROM prestashop/prestashop:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends mariadb-server mariadb-client \
    && rm -rf /var/lib/apt/lists/*

COPY modules/junopay /var/www/html/modules/junopay
COPY scripts/entrypoint.sh /usr/local/bin/junopay-prestashop-entrypoint
COPY scripts/seed-demo.php /usr/local/bin/seed-demo.php

RUN chmod +x /usr/local/bin/junopay-prestashop-entrypoint \
    && chown -R www-data:www-data /var/www/html/modules/junopay /var/lib/mysql

EXPOSE 80

ENTRYPOINT ["junopay-prestashop-entrypoint"]
