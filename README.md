# Donaciones Rolda

Donaciones Rolda es una plataforma liviana, de alta velocidad y resiliente a fallas de red, diseñada para rastrear y gestionar la disponibilidad de medicamentos, insumos médicos, alimentos y herramientas durante emergencias locales o catástrofes en el municipio de Roldanillo (Colombia).

La documentación de diseño, negocio y arquitectura vive en [`docs/`](docs/00-INDICE.md) — empieza por ese índice. Las convenciones de código y el detalle técnico del stack están en [`CLAUDE.md`](CLAUDE.md).

## Requisitos

Solo necesitas **Docker** — el proyecto corre 100% en contenedores vía [Laravel Sail](https://laravel.com/docs/sail), no hace falta PHP, Composer, MySQL ni Node instalados en tu máquina.

- Docker Desktop (Mac/Windows, con integración WSL si usas WSL2) o Docker Engine + plugin `docker compose` (Linux/EC2)
- Git

## 1. Clonar el repositorio

```bash
git clone git@github.com:victormanuelac/donaciones-rolda.git
cd donaciones-rolda
```

## 2. Primer arranque local con Docker (Laravel Sail)

El repo no trae `vendor/` ni `node_modules/` (van ignorados en git), así que el primer `composer install` se hace con un contenedor temporal — no necesitas PHP local para este paso:

```bash
cp .env.example .env

docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

> Se usa la imagen `php84-composer` solo para este paso porque Docker Hub todavía no publica un `php85-composer` (PHP 8.5 es muy reciente). `--ignore-platform-reqs` evita que Composer bloquee por el desfase de versión menor; el contenedor de la app sí corre PHP 8.5 (se construye desde `vendor/laravel/sail/runtimes/8.5` al hacer `sail up`).

Con `vendor/` ya instalado, `./vendor/bin/sail` queda disponible y se usa para todo lo demás:

```bash
./vendor/bin/sail up -d          # levanta app (puerto 80), MySQL 8.4 y Redis
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run build  # o `sail npm run dev` para hot-reload mientras desarrollas
```

Abre **http://localhost**.

Las credenciales de base de datos ya vienen configuradas en `.env.example` para apuntar a los contenedores de Sail (`DB_HOST=mysql`, `DB_DATABASE=laravel`, `DB_USERNAME=sail`, `DB_PASSWORD=password`) — no hace falta tocarlas para desarrollo local.

### Alias `sail` (opcional, pero recomendado)

Para no escribir `./vendor/bin/sail` cada vez:

```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Agrégalo a tu `.bashrc`/`.zshrc` y usa simplemente `sail up -d`, `sail artisan ...`, etc.

## 3. Comandos del día a día

```bash
sail up -d              # levantar contenedores en background
sail down                # detenerlos
sail artisan migrate      # migraciones
sail artisan tinker
sail composer <comando>
sail npm run dev          # vite con hot-reload
sail test                 # suite de Pest
sail pint                 # formateo de código
sail mysql                 # abre un cliente mysql dentro del contenedor de base de datos
```

## 4. Flujo de ramas y despliegue

```
feature/xxx  →  PR (squash-merge, firmado)  →  test  →  (CI/CD a EC2 de pruebas)  →  PR aprobado  →  main
```

- Todo cambio empieza en una rama nueva desde `main` (`feature/...`, `fix/...`, `docs/...`).
- Se abre PR contra la rama **`test`**, nunca push directo. Al mergear, ese push dispara el pipeline de CI/CD hacia el **ambiente de pruebas en EC2** (sección 5).
- Una vez validado en el ambiente de pruebas y con el PR aprobado, se abre PR de `test` a **`main`** — esa es la única vía hacia producción.
- **`main` y `test` están protegidas en GitHub** (branch protection rules) con tres reglas que aplican de verdad, no solo de palabra:
  1. **Solo vía Pull Request** — push directo rechazado (salvo el owner del repo, que tiene bypass; no lo uses para saltarte el flujo).
  2. **Sin commits de merge** — el merge del PR debe ser **squash** (o rebase), no "merge commit". Configúralo así al mergear en GitHub, o con `gh pr merge --squash`.
  3. **Commits firmados** — cada commit necesita firma verificada (GPG o SSH signing). Configúralo una vez por máquina:
     ```bash
     # opción SSH (más simple si ya usas una key SSH para GitHub)
     git config --global gpg.format ssh
     git config --global user.signingkey ~/.ssh/id_ed25519.pub
     git config --global commit.gpgsign true
     ```
     o sigue la [guía de GitHub para firma GPG](https://docs.github.com/en/authentication/managing-commit-signature-verification) si prefieres esa vía. Sin esto, GitHub rechaza el push a `test`/`main` si no eres el owner con bypass.

## 5. Ambiente de pruebas en la instancia EC2

Mientras no exista un pipeline de despliegue automatizado (ECS/Terraform, ver `docs/05-Analisis-Infraestructura-AWS.md` para el diseño objetivo a futuro), el ambiente de pruebas en EC2 se levanta con el **mismo flujo de Docker/Sail que en local** — es la vía más simple hoy para tener un ambiente compartido y probado por el equipo. Corresponde a la rama `test` del flujo descrito arriba.

### 5.1 Preparar la instancia (una sola vez)

Conéctate por SSH a la instancia EC2 (Ubuntu 24.04 LTS recomendado, consistente con el runtime de Sail) e instala Docker:

```bash
sudo apt update && sudo apt install -y docker.io docker-compose-plugin git
sudo usermod -aG docker $USER
# cierra sesión y vuelve a entrar para que el grupo docker tome efecto
```

Verifica el Security Group de la instancia: debe permitir entrada por el puerto `22` (SSH, restringido a tu IP) y por el puerto que uses para la app (`80` si usas el `APP_PORT` por defecto de Sail, o el que definas en `.env`).

### 5.2 Desplegar/actualizar

```bash
git clone git@github.com:victormanuelac/donaciones-rolda.git
cd donaciones-rolda
git checkout test
cp .env.example .env
```

Antes de levantar, ajusta `.env` para el ambiente de pruebas (no dejes los valores de desarrollo local):

| Variable | Valor local | Valor en EC2 (pruebas) |
|---|---|---|
| `APP_ENV` | `local` | `staging` |
| `APP_DEBUG` | `true` | `false` |
| `APP_URL` | `http://localhost` | `http://<IP-pública-o-dominio-de-la-instancia>` |
| `DB_PASSWORD` | `password` | una contraseña real, no el default |

Luego, igual que en local:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest composer install --ignore-platform-reqs --no-dev --optimize-autoloader

./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --force
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Para actualizar el ambiente de pruebas con cambios nuevos:

```bash
git checkout test && git pull
./vendor/bin/sail composer install --no-dev --optimize-autoloader
./vendor/bin/sail artisan migrate --force
./vendor/bin/sail npm run build
./vendor/bin/sail artisan optimize
```

> ⚠️ Esto es un ambiente de **pruebas**, no la arquitectura de producción objetivo. `docs/05-Analisis-Infraestructura-AWS.md` y `docs/13-Diagramas-Arquitectura.md` documentan el diseño con ECS Fargate + RDS Aurora + ElastiCache + Cloudflare para producción — cuando ese pipeline exista, este apartado se actualiza para reflejarlo.

## Más información

- [`docs/00-INDICE.md`](docs/00-INDICE.md) — índice completo de documentación de diseño y negocio
- [`docs/15-Sistema-de-Diseno-Visual.md`](docs/15-Sistema-de-Diseno-Visual.md) — estándar de diseño visual de la aplicación
- [`CLAUDE.md`](CLAUDE.md) — arquitectura técnica, stack, convenciones de código
