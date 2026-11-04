# STATUS

## Hito actual
Hito 1 (Base) — en curso: ficheros base creados; pendiente validar instalación y phpcs.

## Hecho
- `claude.md` renombrado a `CLAUDE.md`.
- version.php (0.1.0 ALPHA, requires 2026100500 = Moodle 5.3), db/access.php, settings.php, lang en/es, privacy provider, README bilingüe, CI (moodle-plugin-ci, PHP 8.3/8.4, pgsql/mariadb), docs/REQUISITOS.md inicial.

## Decisiones
- Privacy provider = `null_provider` en el hito 1 porque aún no hay tablas; se sustituirá al añadir datos (hito 2/3).
- `requires` tomado de `public/version.php` del checkout MOODLE_503_STABLE.
- CI con moodle-plugin-ci ^4 y MariaDB 11.4 / PostgreSQL 17.

## Bloqueos
- Ninguno. (El primer push falló por permisos de GitHub; resuelto por el usuario.)

## TODO-NORMATIVA
- Pendiente de revisar a fondo `docs/normativa/` (PDF del modelo de datos, WSDL) al empezar los hitos 2 y 5.

## Siguiente paso
Validar instalación en Moodle 5.3, ejecutar phpcs; después hito 2 (configuración).
