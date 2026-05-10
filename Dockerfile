FROM prestashop/prestashop:8.2-apache

ARG JUNOPAY_PRESTASHOP_PLUGIN_REF=44c117268084428efe5a45a09135f3b2bc59eca2

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl mariadb-server mariadb-client \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL -o /tmp/junopay-prestashop-plugin.tar.gz "https://github.com/JunoPayServer/junopay-prestashop-plugin/archive/${JUNOPAY_PRESTASHOP_PLUGIN_REF}.tar.gz" \
    && mkdir -p /tmp/junopay-prestashop-plugin \
    && tar -xzf /tmp/junopay-prestashop-plugin.tar.gz -C /tmp/junopay-prestashop-plugin --strip-components=1 \
    && cp -a /tmp/junopay-prestashop-plugin/modules/junopay /var/www/html/modules/junopay \
    && rm -rf /tmp/junopay-prestashop-plugin /tmp/junopay-prestashop-plugin.tar.gz
COPY scripts/entrypoint.sh /usr/local/bin/junopay-prestashop-entrypoint
COPY scripts/seed-demo.php /usr/local/bin/seed-demo.php

RUN chmod +x /usr/local/bin/junopay-prestashop-entrypoint \
    && chown -R www-data:www-data /var/www/html/modules/junopay /var/lib/mysql

EXPOSE 80

ENTRYPOINT ["junopay-prestashop-entrypoint"]
