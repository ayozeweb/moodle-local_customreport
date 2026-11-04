# STATUS

## Hito actual
Hito 2 (Configuración) — CERRADO. Siguiente: hito 3 (Motor de datos).

## Hecho
- Hito 1: base del plugin (version, capabilities, ajustes, cadenas en/es, privacy, CI, README, REQUISITOS).
- Hito 2:
  - Tablas: `local_customreport_profile`, `_action`, `_module`, `_cmrole` (`db/install.xml`, `db/upgrade.php`, `db/install.php`).
  - `profile_manager`: perfiles JSON versionados (schema 1) con validación, importación (rechaza versiones anteriores) y exportación; presets `sepe_sce_tms369`, `generico`, `empresa` cargados al instalar/actualizar.
  - `action_manager`: metadatos de acción formativa (identificadores, situación 10–50, modalidad TF/PR/PE, financiación, fechas previstas/reales, centros presenciales), regla de fecha real/prevista (7 días, MD punto 16).
  - `structure_manager`: módulos (MF), unidades (UF), formación práctica (MP) con horas y secciones/subsecciones; detecta secciones duplicadas.
  - `cmrole_manager`: rol evaluativo (proceso / prueba final 1.ª / 2.ª / FCT / no evaluable); aviso y mapeo de sugerencia AF/AE/PP/PO si hay format_customtiles (sin dependencia).
  - `profile_field_wizard`: campos de perfil de alumnado y tutores (competencia docente 01–06 del MD).
  - Páginas: `manage.php`, `structure.php`, `roles.php`, admin `presets.php` y `profilefields.php`, enlace en navegación de curso.
- Validación: 17 tests PHPUnit en verde (PostgreSQL 16 local), phpcs (estándar moodle) sin avisos, instalación y actualización limpias.

## Decisiones
- Privacy provider sigue siendo `null_provider`: las tablas del hito 2 guardan configuración, no datos personales de alumnos. Se sustituirá en el hito 3 (datos agregados por usuario).
- Acción/módulos/roles en tablas propias en lugar de `core_customfield`: necesitamos varias acciones por curso (grupos), una estructura MF/UF y claves únicas; customfield no lo permite sin duplicar datos.
- Capacidad de gestión (`local/customreport:manage`) comprobada en contexto de sistema (config. administrativa).
- Pesos del perfil «Empresa» 0,50/0,50 y 1 convocatoria: provisionales (TODO-NORMATIVA R-TN-2).
- Las opciones de campos de perfil sin catálogo oficial en el repo son configurables (R-TN-1).
- Tests con atributos `#[CoversClass]` (PHPUnit 11) para evitar avisos de deprecación con `--fail-on-warning`.

## Bloqueos / limitaciones del entorno
- Sandbox sin PostgreSQL 17 ni MariaDB ≥ 11.4 y sin Docker: pruebas locales con PostgreSQL 16 y versión mínima rebajada solo en `/tmp/moodle`. La CI de GitHub usa las versiones oficiales; todavía no se ha visto su resultado.
- Las páginas (`manage.php`, `structure.php`, `roles.php`, `presets.php`, `profilefields.php`) pasan lint y phpcs pero no se han recorrido en un navegador; la lógica que usan sí está cubierta por tests. Pendiente de smoke test (Behat/Playwright) más adelante.
- Sin `ext-soap` local: el hito 5 usará el respaldo con DOMDocument.
- Mustache lint no ejecutado (aún no hay plantillas).

## TODO-NORMATIVA
- R-TN-1, R-TN-2, R-TN-3 (ver docs/REQUISITOS.md).
- El PDF del modelo de datos tiene los esquemas de petición/respuesta como imágenes: se confirmarán contra el WSDL en el hito 5.

## Siguiente paso
Hito 3: sesiones y tiempos (umbral/cola), accesos por área, participación, progreso, altas y bajas con causas; tareas programadas y tablas agregadas; API `\local_customreport\api\progress`.
