# 🚀 Guía de Despliegue — ambiente de pruebas y producción

> **Vigencia:** 21-ago-2026. Este documento es **normativo** para desplegar la aplicación hoy.
> Reemplaza a la sección 5 del `README.md` (que solo cubría el ambiente de pruebas de forma manual) y **no** contradice a [`05-Analisis-Infraestructura-AWS.md`](05-Analisis-Infraestructura-AWS.md): ese documento describe la arquitectura **objetivo a futuro** (ECS Fargate + Aurora + ElastiCache), que no está construida. Lo que aquí se describe es lo que se va a operar realmente.

---

## 0. Decisiones que asume este documento

Estas decisiones se tomaron el 21-ago-2026. Si alguna cambia, este documento deja de ser válido en esa parte.

| Tema | Decisión | Por qué |
|---|---|---|
| Cómputo | **EC2 + Docker (Sail)**, una instancia por ambiente | Es lo único realista para el lanzamiento del 23-ago-2026. ECS Fargate queda como fase posterior. |
| Base de datos | **MySQL en contenedor ahora**, migración a **RDS después** | Prioriza llegar al lanzamiento. La migración a RDS está en la sección 11 y **no es opcional a mediano plazo** (ver sección 9). |
| Despliegue | **Automático a `test`, manual a producción** | Producción maneja datos personales y de salud; un merge equivocado no debe llegar solo. |
| Acceso de CI | **Runner autoalojado en cada instancia** | Evita abrir el puerto 22 a GitHub. ⚠️ **Requiere las mitigaciones de la sección 7.2 — el repositorio es público.** |
| DNS / TLS / WAF | **Cloudflare** | Ya disponible. Aporta TLS, WAF y es el mismo proveedor de Turnstile. |
| Correo | **Amazon SES** | Coherente con el resto de AWS y barato. Ojo con el sandbox (sección 6.4). |

**Nomenclatura:** este documento usa marcadores. Reemplázalos al ejecutar:

| Marcador | Significado |
|---|---|
| `<dominio-produccion>` | Dominio público de producción |
| `<dominio-pruebas>` | Subdominio del ambiente de pruebas |
| `<region-aws>` | Región de AWS (ej. `us-east-1`) |
| `<ip-elastica-prod>` / `<ip-elastica-test>` | IP elástica de cada instancia |
| `<tu-ip-administracion>` | IP desde la que administras por SSH |

---

## 1. Topología

```
                    Internet
                       │
              ┌────────▼────────┐
              │   Cloudflare    │  DNS · TLS · WAF · Turnstile
              └────┬───────┬────┘
                   │       │
     <dominio-pruebas>   <dominio-produccion>
                   │       │
        ┌──────────▼──┐ ┌──▼────────────┐
        │  EC2 TEST   │ │  EC2 PROD     │
        │             │ │               │
        │ app (Sail)  │ │ app (Sail)    │
        │ mysql 8.4   │ │ mysql 8.4     │
        │ redis       │ │ redis         │
        │ gh-runner   │ │ gh-runner     │
        └─────────────┘ └───────────────┘
```

Dos instancias **completamente separadas**: distinta base de datos, distinto `APP_KEY`, distintos secretos. Nunca apuntes el ambiente de pruebas a la base de producción — los seeders de demo borran y recrean datos.

---

## 2. Antes de empezar

Necesitas tener a mano:

- [ ] Cuenta de AWS con permiso para crear EC2, Elastic IP y Security Groups
- [ ] Dominio administrado en Cloudflare
- [ ] Par de llaves SSH para las instancias
- [ ] Acceso de administrador al repositorio en GitHub (para registrar runners y crear Environments)
- [ ] Un gestor de contraseñas del equipo para custodiar `APP_KEY` (ver sección 9.1)

---

## 3. Aprovisionar una instancia

Este procedimiento aplica igual a `test` y a producción. Hazlo **dos veces**.

### 3.1 Crear la instancia

| Parámetro | Pruebas | Producción |
|---|---|---|
| AMI | Ubuntu Server 24.04 LTS | Ubuntu Server 24.04 LTS |
| Tipo | `t3.small` (2 vCPU / 2 GB) | `t3.medium` (2 vCPU / 4 GB) |
| Disco | 30 GB gp3 | 50 GB gp3 |
| IP elástica | Sí | Sí |

> La instancia corre app + MySQL + Redis + runner de CI en la misma máquina. `t3.micro` (1 GB) **no alcanza**: `npm run build` agota la memoria. Si usas `t3.small` en producción, la sección 3.3 (swap) deja de ser opcional.

### 3.2 Security Group

| Puerto | Origen | Motivo |
|---|---|---|
| 22 (SSH) | `<tu-ip-administracion>/32` | Administración. **Nunca `0.0.0.0/0`.** |
| 80 (HTTP) | [Rangos de IP de Cloudflare](https://www.cloudflare.com/ips/) | Solo Cloudflare debe alcanzar el origen |

Restringir el puerto 80 a los rangos de Cloudflare es lo que impide que alguien encuentre la IP del origen y esquive el WAF. Sin eso, Cloudflare es decorativo.

No se abre el 3306 ni el 6379: MySQL y Redis solo se hablan dentro de la red de Docker.

### 3.3 Preparar el sistema

```bash
sudo apt update && sudo apt install -y docker.io docker-compose-plugin git
sudo usermod -aG docker $USER
# cierra sesión y vuelve a entrar para que el grupo docker tome efecto
```

Swap — obligatorio en `t3.small`, recomendado siempre. Sin esto, compilar los assets mata el proceso:

```bash
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

Zona horaria y reinicio automático por seguridad:

```bash
sudo timedatectl set-timezone America/Bogota
sudo apt install -y unattended-upgrades
```

### 3.4 Clonar el repositorio

```bash
git clone https://github.com/victormanuelac/donaciones-rolda.git
cd donaciones-rolda
git checkout test     # en producción: git checkout main
cp .env.example .env
```

---

## 4. Configurar `.env` por ambiente

Esta es la parte donde se cometen los errores caros. Revisa **cada** fila.

| Variable | Local | Pruebas | Producción |
|---|---|---|---|
| `APP_ENV` | `local` | `staging` | `production` |
| `APP_DEBUG` | `true` | `false` | `false` ⚠️ |
| `APP_URL` | `http://localhost` | `https://<dominio-pruebas>` | `https://<dominio-produccion>` |
| `APP_KEY` | generada | generada, **distinta** | generada, **distinta y respaldada** |
| `DB_PASSWORD` | `password` | contraseña real | contraseña real, **distinta** |
| `SESSION_ENCRYPT` | `false` | `true` | `true` |
| `SESSION_SECURE_COOKIE` | — | `true` | `true` |
| `SESSION_DRIVER` | `database` | `redis` | `redis` |
| `CACHE_STORE` | `database` | `redis` | `redis` |
| `QUEUE_CONNECTION` | `database` | `database` | `database` (ver 10.3) |
| `MAIL_MAILER` | `log` | `ses` | `ses` |
| `TURNSTILE_SITE_KEY` | clave de prueba | **clave real** | **clave real** |
| `TURNSTILE_SECRET_KEY` | clave de prueba | **clave real** | **clave real** |

> `APP_DEBUG=true` en producción expone variables de entorno y trazas completas en cualquier error. Con datos de salud de por medio, es una brecha notificable bajo la Ley 1581 (72 horas). Verifícalo antes de cada despliegue.

> Las claves de Turnstile que trae `.env.example` (`1x00000000000000000000AA`) son las de prueba de Cloudflare: **aprueban todo**. Si llegan a producción, el antibot del portal público no existe.

### 4.1 Confiar en el proxy de Cloudflare — paso obligatorio

**Hoy `bootstrap/app.php` no configura `trustProxies`.** Detrás de Cloudflare, sin eso:

- Laravel genera URLs `http://` en vez de `https://`, y el **Service Worker deja de registrarse** — la PWA offline del censo en campo no funciona.
- `audit_logs` registra la IP de Cloudflare en vez de la del usuario real, lo que degrada la trazabilidad que exige la matriz de cumplimiento.

Antes de poner cualquier ambiente detrás de Cloudflare, agrega en `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO);

    // ... el resto de la configuración existente
});
```

`at: '*'` es aceptable **solo porque** el Security Group ya limita el puerto 80 a los rangos de Cloudflare (sección 3.2). Si esa restricción no está, `'*'` permite falsificar la IP de origen.

---

## 5. Primer despliegue

```bash
# 1. Dependencias PHP, sin las de desarrollo
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs --no-dev --optimize-autoloader

# 2. Levantar contenedores
./vendor/bin/sail up -d

# 3. Clave de aplicación — GENERA UNA SOLA VEZ (ver 9.1)
./vendor/bin/sail artisan key:generate

# 4. Esquema
./vendor/bin/sail artisan migrate --force

# 5. Assets
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build

# 6. Cachés de producción
./vendor/bin/sail artisan optimize
```

Verifica:

```bash
curl -sf http://localhost/up && echo "OK"
```

### 5.1 Datos iniciales

**En pruebas** puedes sembrar datos de demo:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

**En producción, nunca.** `migrate:fresh` borra la base completa, y los seeders (`KardexDemoSeeder`, `DeliveriesDemoSeeder`, `BeneficiariesDemoSeeder`) crean usuarios con contraseñas conocidas y publicadas en el `README.md`. En producción el primer administrador se crea a mano:

```bash
./vendor/bin/sail artisan tinker
```

> Los 3 protocolos de recomendación que siembra `BeneficiariesDemoSeeder` son **ejemplos ilustrativos, no una librería médica curada**. Si se van a usar en producción, deben ser revisados por alguien con criterio clínico.

---

## 6. Cloudflare

### 6.1 DNS

| Tipo | Nombre | Contenido | Proxy |
|---|---|---|---|
| A | `<dominio-produccion>` | `<ip-elastica-prod>` | Proxied (naranja) |
| A | `<dominio-pruebas>` | `<ip-elastica-test>` | Proxied (naranja) |

El proxy **debe** estar activo. En gris, el tráfico va directo al origen sin TLS ni WAF.

### 6.2 TLS

- SSL/TLS mode: **Full (strict)**
- Genera un **Origin Certificate** en Cloudflare e instálalo en la instancia, o termina TLS en Cloudflare con el origen en HTTP restringido por Security Group (aceptable dado 3.2).
- Activa **Always Use HTTPS** y **Automatic HTTPS Rewrites**.
- HSTS: actívalo solo cuando confirmes que todo funciona por HTTPS. Es difícil de revertir.

### 6.3 Turnstile

Crea **dos** widgets, uno por dominio, y pon sus claves en el `.env` correspondiente. Las claves de prueba nunca salen de local.

### 6.4 Amazon SES

1. Verifica el dominio en SES (`<region-aws>`) y publica los registros DKIM en Cloudflare.
2. **Solicita salir del sandbox.** En sandbox, SES solo entrega a direcciones verificadas: la recuperación de contraseña no le llegará a los usuarios reales. La aprobación puede tardar **más de 24 horas**, así que inícialo antes del lanzamiento.
3. Configura SPF y DMARC.

---

## 7. CI/CD

### 7.1 Lo que ya existe

`.github/workflows/tests.yml` corre en cada PR y en push a `main`/`test`: Pint, PHPStan y Pest sobre runners de GitHub. No lo cambies; es la barrera de calidad.

### 7.2 ⚠️ Runner autoalojado en un repositorio público

**`donaciones-rolda` es un repositorio público.** GitHub desaconseja explícitamente usar runners autoalojados en repos públicos: cualquiera puede abrir un PR desde un fork y, si un workflow con `pull_request` corre en tu runner, **ejecuta código arbitrario dentro de tu servidor de producción**.

Se puede hacer de forma segura, pero **las tres mitigaciones siguientes son obligatorias**, no recomendaciones:

1. **Ningún workflow que corra en el runner autoalojado puede dispararse por `pull_request`.** Solo `push` a ramas protegidas y `workflow_dispatch`. `tests.yml` (que sí usa `pull_request`) debe seguir en `ubuntu-latest`.
2. **Settings → Actions → General → Fork pull request workflows:** exigir aprobación para **todos** los colaboradores externos.
3. **El runner de producción corre solo bajo un GitHub Environment con revisores requeridos** (sección 7.4).

Si estas mitigaciones no se pueden sostener operativamente, la alternativa correcta es despliegue por SSH con clave en secrets, o AWS SSM con OIDC. **Es preferible cambiar de método que dejar el runner mal configurado.**

### 7.3 Instalar el runner

En cada instancia, con un usuario **sin** privilegios de sudo:

```bash
mkdir ~/actions-runner && cd ~/actions-runner
curl -o actions-runner-linux-x64.tar.gz -L \
  https://github.com/actions/runner/releases/latest/download/actions-runner-linux-x64.tar.gz
tar xzf actions-runner-linux-x64.tar.gz

# El token se obtiene en Settings → Actions → Runners → New self-hosted runner
./config.sh --url https://github.com/victormanuelac/donaciones-rolda --token <TOKEN> \
            --labels donaciones-test        # en producción: donaciones-prod
sudo ./svc.sh install && sudo ./svc.sh start
```

Las etiquetas `donaciones-test` y `donaciones-prod` son lo que dirige cada despliegue a su instancia. No las repitas entre máquinas.

### 7.4 GitHub Environments

Crea dos Environments en Settings → Environments:

| Environment | Ramas permitidas | Revisores requeridos |
|---|---|---|
| `test` | `test` | ninguno |
| `production` | `main` | **al menos 1** |

El revisor requerido en `production` es lo que convierte el despliegue en manual: el workflow se queda esperando aprobación antes de tocar el servidor.

### 7.5 `deploy.yml`

```yaml
name: deploy

on:
  push:
    branches: [test]          # despliegue automático a pruebas
  workflow_dispatch:          # despliegue manual a producción
    inputs:
      ambiente:
        description: Ambiente a desplegar
        required: true
        default: production
        type: choice
        options: [production]

permissions:
  contents: read

concurrency:
  group: deploy-${{ github.ref }}
  cancel-in-progress: false   # nunca canceles un despliegue a medias

jobs:
  deploy:
    runs-on:
      - self-hosted
      - ${{ github.event_name == 'push' && 'donaciones-test' || 'donaciones-prod' }}
    environment: ${{ github.event_name == 'push' && 'test' || 'production' }}

    steps:
      - name: Desplegar
        working-directory: /home/ubuntu/donaciones-rolda
        run: |
          set -euo pipefail

          RAMA="${{ github.event_name == 'push' && 'test' || 'main' }}"

          git fetch origin "$RAMA"
          ANTERIOR=$(git rev-parse HEAD)
          echo "ANTERIOR=$ANTERIOR" >> "$GITHUB_ENV"
          git checkout "$RAMA"
          git reset --hard "origin/$RAMA"

          ./vendor/bin/sail down --remove-orphans || true
          ./vendor/bin/sail up -d
          ./vendor/bin/sail composer install --no-dev --optimize-autoloader
          ./vendor/bin/sail artisan migrate --force
          ./vendor/bin/sail npm ci
          ./vendor/bin/sail npm run build
          ./vendor/bin/sail artisan optimize

      - name: Verificar que responde
        run: |
          for i in $(seq 1 30); do
            if curl -sf http://localhost/up > /dev/null; then
              echo "Despliegue verificado"; exit 0
            fi
            sleep 2
          done
          echo "La aplicación no respondió en /up"; exit 1

      - name: Revertir si falló
        if: failure()
        working-directory: /home/ubuntu/donaciones-rolda
        run: |
          git reset --hard "$ANTERIOR"
          ./vendor/bin/sail up -d
          ./vendor/bin/sail artisan optimize
          echo "Revertido a $ANTERIOR — revisa las migraciones a mano (ver 8.3)"
```

> El paso de reversión devuelve el **código**, no la base de datos. Si el despliegue falló después de una migración destructiva, lee la sección 8.3 antes de tocar nada.

### 7.6 Despliegue a producción, paso a paso

1. PR de `test` a `main`, aprobado y con CI en verde.
2. Squash-merge (el ruleset no permite otra cosa).
3. Actions → `deploy` → Run workflow → rama `main`, ambiente `production`.
4. El workflow queda **esperando aprobación**. Un revisor la otorga.
5. Verifica manualmente: entrar, buscar en el portal público, registrar un movimiento en el Kardex.

---

## 8. Operación

### 8.1 Respaldos

Con MySQL en contenedor, **los respaldos son responsabilidad tuya**. Esto es lo que RDS resolvería solo. Cron diario en producción:

```bash
#!/usr/bin/env bash
# /home/ubuntu/respaldo.sh
set -euo pipefail
cd /home/ubuntu/donaciones-rolda
FECHA=$(date +%Y-%m-%d-%H%M)
./vendor/bin/sail exec -T mysql mysqldump -usail -p"$DB_PASSWORD" \
    --single-transaction --routines laravel | gzip > "/tmp/db-$FECHA.sql.gz"
aws s3 cp "/tmp/db-$FECHA.sql.gz" "s3://<bucket-respaldos>/produccion/"
rm "/tmp/db-$FECHA.sql.gz"
```

```
0 3 * * * /home/ubuntu/respaldo.sh >> /var/log/respaldo.log 2>&1
```

Requisitos que no son negociables:

- El bucket S3 debe tener **cifrado en reposo** y **versionado** activados.
- La instancia usa un **IAM Role** con permiso solo de `PutObject` sobre ese prefijo. No pongas credenciales de AWS en el `.env`.
- **Prueba la restauración al menos una vez.** Un respaldo que nunca se restauró no es un respaldo.

### 8.2 Migraciones

`deploy.yml` corre `migrate --force` automáticamente. Para una migración que reescribe o borra datos, haz un respaldo manual **antes** y considera desplegarla por separado del código que la usa.

### 8.3 Reversión

| Situación | Qué hacer |
|---|---|
| Falló el código, sin migraciones nuevas | El paso automático de reversión basta |
| Falló con migraciones **aditivas** (columnas o tablas nuevas) | Revertir el código basta; el esquema extra no molesta |
| Falló con migraciones **destructivas** | Restaura el respaldo. `migrate:rollback` **no** recupera datos borrados |

### 8.4 Logs

```bash
./vendor/bin/sail logs -f laravel.test          # contenedor
./vendor/bin/sail artisan pail                  # log de la aplicación
```

Rota `storage/logs` para que no llene el disco.

---

## 9. Cumplimiento (Ley 1581/2012)

Ver [`08-Matriz-Compliance-Privacy-LSPP.md`](08-Matriz-Compliance-Privacy-LSPP.md) para el detalle legal. Lo que afecta directamente al despliegue:

### 9.1 Custodia de `APP_KEY` — lo más crítico de este documento

Los campos PII y de salud (`beneficiaries.document_number`, `medical_notes`, `chronic_conditions`, `current_symptoms`, `families.head_document_number`, `families.phone`, `users.document_id`, entre otros) están cifrados **a nivel de aplicación** con `APP_KEY`.

**Si se pierde `APP_KEY`, esos datos son irrecuperables.** No los recupera ningún respaldo de base de datos, porque lo que está respaldado es el texto cifrado.

- Genera `APP_KEY` **una sola vez** por ambiente y guárdala en el gestor de contraseñas del equipo, **antes** del primer despliegue con datos reales.
- Nunca vuelvas a correr `key:generate` en un ambiente que ya tenga datos.
- Rotarla requiere descifrar y volver a cifrar todo con ambas llaves disponibles. No es un cambio de variable.

### 9.2 Lo que este despliegue todavía no cumple

| Requisito | Estado |
|---|---|
| Cifrado en tránsito (TLS 1.3) | ✅ vía Cloudflare |
| Cifrado de PII a nivel de aplicación | ✅ implementado |
| Cifrado en reposo del volumen | ⚠️ activa cifrado en el volumen EBS al crear la instancia |
| Retención y auto-anonimización | ❌ `DataRetentionCleanupJob` **no existe** (ver 10.2) |
| Trazabilidad con IP real | ❌ requiere `trustProxies` (ver 4.1) |
| Respaldos con recuperación puntual | ⚠️ solo diarios; se resuelve con RDS (sección 11) |

---

## 10. Deuda técnica conocida

Se documenta para que nadie asuma que ya está resuelto.

### 10.1 `trustProxies` sin configurar
Rompe la PWA offline y la trazabilidad de IP. **Bloqueante** — ver 4.1.

### 10.2 Retención de datos sin implementar
La matriz de cumplimiento exige un job diario a las 02:00 UTC (anonimizar usuarios inactivos 12 meses, archivar `audit_logs`, purgar búsquedas anónimas a los 90 días). Hoy no existe **ni el job ni un scheduler configurado**. Es una obligación legal pendiente, no una mejora.

### 10.3 Sin worker de colas ni scheduler
`QUEUE_CONNECTION=database`, pero hoy no se encola nada, así que no hace falta un worker. En cuanto se agregue el primer `ShouldQueue` (o el job de 10.2), habrá que levantar `queue:work` y `schedule:work` como servicios y este documento debe actualizarse.

### 10.4 Sin tiempo real
La spec menciona Laravel Reverb, pero **no está instalado** (`BROADCAST_CONNECTION=log`). El dashboard en tiempo real del Módulo 12 no está disponible.

### 10.5 Otros pendientes de producto
Doble verificación (maker-checker) en salidas grandes y reportes exportables CSV/Excel siguen sin implementar — ver `CLAUDE.md`.

---

## 11. Migración a RDS (fase posterior)

MySQL en contenedor es una decisión de lanzamiento, no un destino. Migra cuando se cumpla cualquiera de estas: hay datos reales de beneficiarios en producción, el equipo no está probando la restauración cada mes, o se necesita recuperación a un punto en el tiempo.

Procedimiento resumido:

1. Crea la instancia RDS MySQL 8.4 en subred privada, Multi-AZ, con cifrado en reposo y respaldos automáticos a 7 días.
2. Security Group de RDS que solo acepte al Security Group de la EC2.
3. Ventana de mantenimiento: pon la app en mantenimiento (`sail artisan down`).
4. `mysqldump` del contenedor → importar a RDS.
5. Actualiza `DB_HOST` en `.env` y elimina el servicio `mysql` de `compose.yaml`.
6. `sail artisan up` y verifica.
7. Conserva el contenedor apagado unos días como red de seguridad.

El costo adicional debe contrastarse con el presupuesto vigente de $8,256 USD ([`ANALISIS-COSTOS-REALES.md`](ANALISIS-COSTOS-REALES.md)).

---

## 12. Checklist de salida a producción

Antes de que entren datos reales:

**Infraestructura**
- [ ] Cifrado del volumen EBS activado
- [ ] Puerto 80 restringido a los rangos de Cloudflare
- [ ] Puerto 22 restringido a IP de administración
- [ ] Swap configurado

**Aplicación**
- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] `APP_KEY` generada **y respaldada en el gestor de contraseñas**
- [ ] `trustProxies` configurado (sección 4.1)
- [ ] `SESSION_ENCRYPT=true` y `SESSION_SECURE_COOKIE=true`
- [ ] Claves reales de Turnstile
- [ ] Contraseña de base de datos distinta a la de pruebas
- [ ] Ningún seeder de demo ejecutado

**Cloudflare**
- [ ] DNS en modo proxied
- [ ] SSL/TLS en Full (strict)
- [ ] Always Use HTTPS activo

**Correo**
- [ ] Dominio verificado en SES con DKIM
- [ ] **Fuera del sandbox de SES**
- [ ] Recuperación de contraseña probada de extremo a extremo

**CI/CD**
- [ ] Runner de producción con la etiqueta `donaciones-prod`
- [ ] Environment `production` con revisor requerido
- [ ] Ningún workflow con `pull_request` corriendo en runners autoalojados
- [ ] Aprobación obligatoria para PRs de forks

**Operación**
- [ ] Respaldo diario corriendo hacia S3
- [ ] **Restauración probada al menos una vez**
- [ ] Alguien de guardia identificado para el día del lanzamiento
