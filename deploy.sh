#!/usr/bin/env bash
#
# Despliegue de Donaciones Rolda. Se ejecuta EN la instancia EC2, no en CI.
#
#   ./deploy.sh test     → ambiente de pruebas
#   ./deploy.sh main     → producción
#
# Lo invoca .github/workflows/deploy.yml a través de AWS SSM, pero es
# ejecutable a mano si CI no está disponible:
#
#   aws ssm start-session --target <instance-id>
#   sudo runuser -l ubuntu -c "cd /home/ubuntu/donaciones-rolda && ./deploy.sh main"
#
# Ver docs/18-Guia-de-Despliegue-Test-y-Produccion.md, sección 7.
#
set -euo pipefail

RAMA="${1:?Falta la rama a desplegar (test o main)}"
cd "$(dirname "$0")"

ANTERIOR=$(git rev-parse HEAD)
echo "==> Commit actual: $ANTERIOR"

revertir() {
    echo "==> FALLÓ el despliegue — revirtiendo el código a $ANTERIOR"
    git reset --hard "$ANTERIOR"
    ./vendor/bin/sail up -d
    ./vendor/bin/sail artisan optimize
    echo "==> Código revertido."
    echo "    Si alcanzaron a correr migraciones, revísalas a mano antes de reintentar."
    echo "    Ver docs/18-Guia-de-Despliegue-Test-y-Produccion.md, sección 8.3."
    exit 1
}
trap revertir ERR

echo "==> Actualizando a origin/$RAMA"
git fetch origin "$RAMA"
git checkout "$RAMA"
git reset --hard "origin/$RAMA"

echo "==> Levantando contenedores"
./vendor/bin/sail up -d

echo "==> Dependencias PHP"
./vendor/bin/sail composer install --no-dev --optimize-autoloader

echo "==> Migraciones"
./vendor/bin/sail artisan migrate --force

echo "==> Compilando assets"
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build

echo "==> Cachés de producción"
./vendor/bin/sail artisan optimize

echo "==> Verificando que la aplicación responde"
for _ in $(seq 1 30); do
    if curl -sf http://localhost/up > /dev/null; then
        trap - ERR
        echo "==> Despliegue verificado en $(git rev-parse --short HEAD)"
        exit 0
    fi
    sleep 2
done

echo "==> La aplicación no respondió en /up tras 60 segundos"
exit 1
