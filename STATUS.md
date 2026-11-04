# STATUS

## Hito actual
Hito 1 (Base) — CERRADO. Siguiente: hito 2 (Configuración).

## Hecho
- `claude.md` renombrado a `CLAUDE.md`.
- version.php (0.1.0 ALPHA, requires 2026100500 = Moodle 5.3), db/access.php, settings.php, lang en/es, privacy provider, README bilingüe, CI (moodle-plugin-ci, PHP 8.3/8.4, pgsql/mariadb), docs/REQUISITOS.md inicial.

- Validado: instalación limpia en Moodle 5.3+ (MOODLE_503_STABLE) con PostgreSQL 16; phpcs (estándar moodle) sin avisos.

## Decisiones
- Privacy provider = `null_provider` en el hito 1 porque aún no hay tablas; se sustituirá al añadir datos (hito 2/3).
- `requires` tomado de `public/version.php` del checkout MOODLE_503_STABLE.
- CI con moodle-plugin-ci ^4 y MariaDB 11.4 / PostgreSQL 17.

## Bloqueos
- Entorno del sandbox: no hay PostgreSQL 17 ni MariaDB ≥ 11.4 y el demonio Docker no arranca. Para probar localmente se usa PostgreSQL 16 y se rebajó la versión mínima solo en el checkout de pruebas (`/tmp/moodle`, fuera del repo). La CI de GitHub Actions usa las versiones oficiales (pgsql 17, mariadb 11.4). Falta `ext-soap` en PHP local: el hito 5 usará el respaldo con DOMDocument.
- No se ha podido ejecutar moodle-plugin-ci completo (Mustache lint, PHPUnit con BD de test) en local; se confía en la CI remota.
- Ninguno. (El primer push falló por permisos de GitHub; resuelto por el usuario.)

## TODO-NORMATIVA
- Pendiente de revisar a fondo `docs/normativa/` (PDF del modelo de datos, WSDL) al empezar los hitos 2 y 5.

## Siguiente paso
Hito 2: metadatos de acción formativa, módulos/UF, rol evaluativo, perfiles JSON y asistente de campos de perfil.
