#!/usr/bin/env bash
set -euo pipefail

: "${PS_DOMAIN:=localhost}"
PS_INSTALL_AUTO=1
PS_INSTALL_DB=0
DB_SERVER=127.0.0.1
DB_PORT=3306
DB_NAME=prestashop
DB_USER=prestashop
DB_PASSWD=prestashop
DB_PREFIX=ps_
: "${ADMIN_MAIL:=demo@junopayserver.com}"
: "${ADMIN_PASSWD:=DemoPassword123!}"
: "${PS_LANGUAGE:=en}"
: "${PS_COUNTRY:=US}"
: "${PS_ENABLE_SSL:=1}"
: "${JUNOPAY_BASE_URL:=}"
: "${JUNOPAY_MERCHANT_API_KEY:=}"
: "${JUNOPAY_WEBHOOK_SECRET:=demo-webhook-secret}"

mkdir -p /var/run/mysqld
chown -R mysql:mysql /var/run/mysqld /var/lib/mysql

if [[ ! -f /var/lib/mysql/junopay-demo-init ]]; then
  rm -rf /var/lib/mysql/*
  mariadb-install-db --user=mysql --datadir=/var/lib/mysql --auth-root-authentication-method=normal >/dev/null
  touch /var/lib/mysql/junopay-demo-init
  chown -R mysql:mysql /var/lib/mysql
fi

mysqld_safe --datadir=/var/lib/mysql --skip-networking=0 --bind-address=127.0.0.1 &

for _ in $(seq 1 60); do
  if mysqladmin ping -h 127.0.0.1 --silent; then
    break
  fi
  sleep 1
done

mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASSWD}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

chown -R www-data:www-data /var/www/html
export PS_DOMAIN PS_INSTALL_AUTO PS_INSTALL_DB DB_SERVER DB_PORT DB_NAME DB_USER DB_PASSWD DB_PREFIX ADMIN_MAIL ADMIN_PASSWD PS_LANGUAGE PS_COUNTRY PS_ENABLE_SSL
mkdir -p /tmp/init-scripts
cp /usr/local/bin/seed-demo.php /tmp/init-scripts/seed-demo.php
chmod +x /tmp/init-scripts/seed-demo.php
cat > /tmp/init-scripts/zz-fix-permissions.sh <<'SH'
#!/usr/bin/env bash
set -euo pipefail
mkdir -p /var/www/html/var/cache/prod/smarty/compile /var/www/html/var/cache/prod/smarty/cache
chown -R www-data:www-data /var/www/html/var /var/www/html/modules/junopay
SH
chmod +x /tmp/init-scripts/zz-fix-permissions.sh

if [[ -z "${JUNOPAY_MERCHANT_API_KEY}" ]]; then
  echo "warning: JUNOPAY_MERCHANT_API_KEY is not set; checkout invoice creation will fail until configured" >&2
fi

exec /tmp/docker_run.sh
