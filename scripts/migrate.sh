#!/usr/bin/env bash
# Aplica en orden cada archivo .sql de database/migrations/ dentro del contenedor.
set -euo pipefail

MIGRATIONS_DIR="$(dirname "$0")/../database/migrations"

shopt -s nullglob
files=("$MIGRATIONS_DIR"/*.sql)

if [ ${#files[@]} -eq 0 ]; then
    echo "No hay migraciones pendientes."
    exit 0
fi

for f in "${files[@]}"; do
    echo "Aplicando $(basename "$f")..."
    docker compose exec departamentos-db \
        sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < "$f"
done

echo "Migraciones completadas."
