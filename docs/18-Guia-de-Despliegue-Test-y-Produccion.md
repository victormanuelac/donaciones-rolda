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
| Acceso de CI | **AWS SSM con OIDC**, sobre runners de GitHub | Sin credenciales de larga vida y sin puertos de entrada. El repositorio es público, lo que descarta los runners autoalojados (sección 7.2). |
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
        │ agente SSM  │ │ agente SSM    │
        └──────▲──────┘ └──────▲────────┘
               │               │
               └───── AWS SSM ─┘   ← saliente; sin puertos de entrada
                        ▲
                 GitHub Actions (OIDC)
```

Dos instancias **completamente separadas**: distinta base de datos, distinto `APP_KEY`, distintos secretos. Nunca apuntes el ambiente de pruebas a la base de producción — los seeders de demo borran y recrean datos.

---

## 2. Antes de empezar

Necesitas tener a mano:

- [ ] Cuenta de AWS con permiso para crear EC2, Elastic IP, Security Groups y **roles de IAM** (para OIDC, sección 7.4)
- [ ] Dominio administrado en Cloudflare
- [ ] Par de llaves SSH para las instancias
- [ ] Acceso de administrador al repositorio en GitHub (para crear Environments y sus variables)
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

> La instancia corre app + MySQL + Redis en la misma máquina. `t3.micro` (1 GB) **no alcanza**: `npm run build` agota la memoria. Si usas `t3.small` en producción, la sección 3.3 (swap) deja de ser opcional.

### 3.2 Security Group

| Puerto | Origen | Motivo |
|---|---|---|
| 80 (HTTP) | [Rangos de IP de Cloudflare](https://www.cloudflare.com/ips/) | Solo Cloudflare debe alcanzar el origen |
| 22 (SSH) | `<tu-ip-administracion>/32`, **temporal** | Solo hasta que SSM funcione (sección 7.3); después se cierra |

Restringir el puerto 80 a los rangos de Cloudflare es lo que impide que alguien encuentre la IP del origen y esquive el WAF. Sin eso, Cloudflare es decorativo.

El puerto 22 es transitorio: en cuanto el agente de SSM esté registrado, ciérralo. `aws ssm start-session` da consola interactiva sin exponer nada y deja rastro en CloudTrail. Mientras siga abierto, jamás lo dejes en `0.0.0.0/0`.

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

### 7.2 Por qué NO se usan runners autoalojados

**`donaciones-rolda` es un repositorio público**, y esa sola condición descarta los runners autoalojados. GitHub lo desaconseja explícitamente: cualquiera puede abrir un PR desde un fork y, si un workflow corre en una máquina tuya, **ejecuta código arbitrario dentro del servidor de producción** — con la base de datos y la `APP_KEY` ahí mismo. El runner además persiste entre jobs, así que un atacante puede dejar algo instalado.

Conviene entender la distinción, porque se confunde con facilidad: **GitHub nunca entrega secretos ni tokens OIDC a un workflow disparado desde un fork.** Ese vector no existe cuando el job corre en un runner efímero de GitHub. El problema no es "CI que despliega desde un repo público", es específicamente "CI que corre sobre hardware propio".

| Opción | Credencial | Puertos abiertos | Riesgo principal |
|---|---|---|---|
| **SSM + OIDC** ← elegida | Token efímero (~15 min) | **Ninguno** | Un rol de IAM mal acotado |
| SSH con clave en secrets | Clave de larga vida | 22 expuesto a GitHub | Si la clave se filtra, acceso total y permanente |
| Runner autoalojado | — | Ninguno | **Ejecución de código arbitrario en el servidor** |

SSM gana en las dos dimensiones que importan: no hay credencial que robar porque expira, y no hay puerto que atacar porque el agente sale hacia AWS — nadie entra.

### 7.3 Preparar SSM en la instancia

Las AMI de Ubuntu publicadas en AWS ya traen el agente. Verifica:

```bash
sudo snap services amazon-ssm-agent
# si no está: sudo snap install amazon-ssm-agent --classic
```

Adjunta a la instancia un **IAM Instance Profile** con la política administrada `AmazonSSMManagedInstanceCore`. Sin eso el agente no se registra.

Confirma desde tu equipo que la instancia aparece:

```bash
aws ssm describe-instance-information \
  --query "InstanceInformationList[].{Id:InstanceId,Estado:PingStatus}" --output table
```

> Una vez que SSM funciona, **cierra el puerto 22 del Security Group**. `aws ssm start-session --target <instance-id>` te da consola interactiva sin puerto expuesto y con todo el acceso auditado en CloudTrail. Es una mejora de seguridad por encima de lo que había, no solo un cambio de método de despliegue.

### 7.4 Configurar OIDC entre GitHub y AWS

**Paso 1 — Registra el proveedor OIDC** (una sola vez por cuenta de AWS):

```bash
aws iam create-open-id-connect-provider \
  --url https://token.actions.githubusercontent.com \
  --client-id-list sts.amazonaws.com
```

**Paso 2 — Crea un rol por ambiente.** Son dos roles separados, `deploy-pruebas` y `deploy-produccion`, cada uno con permiso solo sobre su instancia. La política de confianza es donde está el seguro real:

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": {
      "Federated": "arn:aws:iam::<cuenta>:oidc-provider/token.actions.githubusercontent.com"
    },
    "Action": "sts:AssumeRoleWithWebIdentity",
    "Condition": {
      "StringEquals": {
        "token.actions.githubusercontent.com:aud": "sts.amazonaws.com",
        "token.actions.githubusercontent.com:sub": "repo:victormanuelac/donaciones-rolda:environment:production"
      }
    }
  }]
}
```

La condición `sub` es lo que hace segura toda la configuración: ata el rol de producción al Environment `production`, que a su vez exige aprobación humana (7.5). Un fork no puede pedir ese token y una rama arbitraria tampoco. Para el rol de pruebas, usa `repo:victormanuelac/donaciones-rolda:environment:test`.

> ⚠️ **Usa `StringEquals`, nunca `StringLike` con comodines.** Un `sub` como `repo:victormanuelac/donaciones-rolda:*` deja que cualquier rama del repositorio asuma el rol de producción y anula el control de aprobación.

**Paso 3 — Política de permisos del rol**, acotada a una sola instancia y a un solo documento de SSM:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": "ssm:SendCommand",
      "Resource": [
        "arn:aws:ec2:<region-aws>:<cuenta>:instance/<instance-id-prod>",
        "arn:aws:ssm:<region-aws>::document/AWS-RunShellScript"
      ]
    },
    {
      "Effect": "Allow",
      "Action": ["ssm:GetCommandInvocation", "ssm:ListCommandInvocations"],
      "Resource": "*"
    }
  ]
}
```

### 7.5 GitHub Environments

Crea dos Environments en Settings → Environments:

| Environment | Ramas permitidas | Revisores requeridos |
|---|---|---|
| `test` | `test` | ninguno |
| `production` | `main` | **al menos 1** |

El revisor requerido en `production` es lo que convierte el despliegue en manual: el workflow se queda esperando aprobación antes de tocar el servidor. Además, esa aprobación es lo que la condición `sub` del rol de IAM está exigiendo — sin ella no se emite el token.

En cada Environment define estas variables (Environment variables, no secrets — no son sensibles):

| Variable | `test` | `production` |
|---|---|---|
| `AWS_ROLE_ARN` | `arn:aws:iam::<cuenta>:role/deploy-pruebas` | `arn:aws:iam::<cuenta>:role/deploy-produccion` |
| `INSTANCE_ID` | `<instance-id-test>` | `<instance-id-prod>` |
| `RAMA` | `test` | `main` |

### 7.6 `deploy.sh` — los pasos del despliegue

Los pasos viven en [`deploy.sh`](../deploy.sh), versionado en la raíz del repositorio y no incrustado en el YAML: así se pueden probar, revisar en un PR y ejecutar a mano si CI no está disponible.

El script actualiza el código a la rama indicada, reinstala dependencias, corre migraciones, compila assets, regenera cachés y verifica `/up`. Si cualquier paso falla, un `trap` revierte el código al commit anterior y termina con error.

```bash
./deploy.sh test     # ambiente de pruebas
./deploy.sh main     # producción
```

> El `trap` revierte el **código**, no la base de datos. Si el despliegue falló después de una migración destructiva, lee la sección 8.3 antes de tocar nada.

### 7.7 `deploy.yml`

```yaml
name: deploy

on:
  push:
    branches: [test]          # despliegue automático a pruebas
  workflow_dispatch:          # despliegue manual a producción

permissions:
  contents: read
  id-token: write             # habilita OIDC — sin esto no hay token

concurrency:
  group: deploy-${{ github.ref }}
  cancel-in-progress: false   # nunca canceles un despliegue a medias

jobs:
  deploy:
    runs-on: ubuntu-latest    # runner efímero de GitHub, nunca self-hosted
    environment: ${{ github.event_name == 'push' && 'test' || 'production' }}

    steps:
      - name: Autenticarse en AWS vía OIDC
        uses: aws-actions/configure-aws-credentials@v4
        with:
          role-to-assume: ${{ vars.AWS_ROLE_ARN }}
          aws-region: <region-aws>

      - name: Desplegar por SSM
        run: |
          set -euo pipefail

          ID=$(aws ssm send-command \
            --instance-ids "${{ vars.INSTANCE_ID }}" \
            --document-name AWS-RunShellScript \
            --comment "deploy ${{ github.sha }}" \
            --parameters commands='["runuser -l ubuntu -c \"cd /home/ubuntu/donaciones-rolda && ./deploy.sh ${{ vars.RAMA }}\""]' \
            --query Command.CommandId --output text)

          echo "Comando SSM: $ID"

          aws ssm wait command-executed \
            --command-id "$ID" --instance-id "${{ vars.INSTANCE_ID }}" || true

          aws ssm get-command-invocation \
            --command-id "$ID" --instance-id "${{ vars.INSTANCE_ID }}" \
            --query StandardOutputContent --output text

          ESTADO=$(aws ssm get-command-invocation \
            --command-id "$ID" --instance-id "${{ vars.INSTANCE_ID }}" \
            --query Status --output text)

          if [ "$ESTADO" != "Success" ]; then
            echo "::error::El despliegue falló ($ESTADO)"
            aws ssm get-command-invocation \
              --command-id "$ID" --instance-id "${{ vars.INSTANCE_ID }}" \
              --query StandardErrorContent --output text
            exit 1
          fi
```

El `|| true` tras el `wait` es deliberado: el waiter falla cuando el comando falla, y en ese caso queremos leer la salida del script antes de terminar el job. El estado real se evalúa después.

**No hay ningún secreto en este workflow.** El único permiso sensible es `id-token: write`, y GitHub no lo concede a workflows disparados desde forks.

### 7.8 Despliegue a producción, paso a paso

1. PR de `test` a `main`, aprobado y con CI en verde.
2. Squash-merge (el ruleset no permite otra cosa).
3. Actions → `deploy` → Run workflow → rama `main`.
4. El workflow queda **esperando aprobación**. Un revisor la otorga; recién ahí AWS emite el token.
5. Verifica manualmente: entrar, buscar en el portal público, registrar un movimiento en el Kardex.

### 7.9 Si el despliegue automático no está disponible

Mientras `deploy.yml` no exista, o si CI está caído, el despliegue manual es el mismo script:

```bash
aws ssm start-session --target <instance-id>
sudo runuser -l ubuntu -c "cd /home/ubuntu/donaciones-rolda && ./deploy.sh main"
```

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
- [ ] **Puerto 22 cerrado** (acceso por `aws ssm start-session`)
- [ ] Agente SSM registrado y respondiendo
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
- [ ] Environment `production` con revisor requerido
- [ ] Rol de IAM de producción con `sub` en **`StringEquals`**, apuntando al Environment `production` (nunca comodines)
- [ ] Rol acotado a `ssm:SendCommand` sobre una sola instancia
- [ ] Ningún runner autoalojado registrado en el repositorio
- [ ] Ningún secreto de larga vida de AWS en GitHub Secrets
- [ ] Despliegue probado primero en `test`

**Operación**
- [ ] Respaldo diario corriendo hacia S3
- [ ] **Restauración probada al menos una vez**
- [ ] Alguien de guardia identificado para el día del lanzamiento
