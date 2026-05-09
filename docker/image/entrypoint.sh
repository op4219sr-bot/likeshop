#!/bin/bash
set -e

# 默认值（可通过环境变量覆盖）
: "${DB_HOST:=mysql}"
: "${DB_PORT:=3306}"
: "${DB_USER:=likeshop}"
: "${DB_PASSWORD:=likeshop123}"
: "${DB_NAME:=likeshop}"
: "${DB_PREFIX:=ls_}"
: "${ADMIN_USER:=admin}"
: "${ADMIN_PASSWORD:=123456}"
: "${APP_DEBUG:=false}"

export DB_HOST DB_PORT DB_USER DB_PASSWORD DB_NAME DB_PREFIX ADMIN_USER ADMIN_PASSWORD APP_DEBUG

LOCK_FILE=/server/config/install.lock
ENV_FILE=/server/.env

echo "[entrypoint] waiting for MySQL at ${DB_HOST}:${DB_PORT} ..."
for i in $(seq 1 60); do
    if mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" --silent 2>/dev/null; then
        echo "[entrypoint] MySQL is ready"
        break
    fi
    if [ "$i" = "60" ]; then
        echo "[entrypoint] MySQL not ready after 60s, exiting" >&2
        exit 1
    fi
    sleep 1
done

# 写 .env（每次启动都重写,以便环境变量改了后重启即可生效）
cat > "${ENV_FILE}" <<EOF
[app]
app_debug = ${APP_DEBUG}
app_trace = false

[database]
hostname = ${DB_HOST}
database = ${DB_NAME}
username = ${DB_USER}
password = ${DB_PASSWORD}
hostport = ${DB_PORT}

[project]
env_name =
file_domain =
EOF
chown www-data:www-data "${ENV_FILE}"

# 首次启动：跑自动安装
if [ ! -f "${LOCK_FILE}" ]; then
    echo "[entrypoint] no install.lock, running auto-install ..."
    php /usr/local/bin/auto_install.php
    if [ -f "${LOCK_FILE}" ]; then
        echo "[entrypoint] auto-install completed; admin user = ${ADMIN_USER}, password = ${ADMIN_PASSWORD}"
    else
        echo "[entrypoint] auto-install failed" >&2
        exit 1
    fi
else
    echo "[entrypoint] install.lock present, skipping install"
fi

# 修复挂载卷的权限（volume 第一次创建时是 root）
chown -R www-data:www-data /server/runtime /server/public/uploads /server/config 2>/dev/null || true

exec "$@"
