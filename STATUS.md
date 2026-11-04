# STATUS

## Hito actual
Hito 3 (Motor de datos) — CERRADO. Siguiente: hito 4 (Informes de visita virtual).

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
- Hito 3:
  - Tablas: `_usage`, `_cursor`, `_progress`, `_enrol`, `_cause`, `_deferred`.
  - `session_calculator` (algoritmo puro: hueco ≤ umbral = misma sesión, cola al cerrar, duplicados, desorden, días locales con cambio de hora), `usage_aggregator` (marca de agua del log + cursor por usuario, accesos, participación, eventos de matrícula), `progress_calculator` + `api\progress` (con comprobación de capacidades), `cause_manager` (catálogo configurable), `retention` y privacy provider completo con borrado aplazado (`purge_deferred`).
  - Tareas programadas: `aggregate_usage` (*/15), `update_progress` (horaria), `purge_deferred` (diaria).
- Validación hito 2: 17 tests PHPUnit en verde (PostgreSQL 16 local), phpcs (estándar moodle) sin avisos, instalación y actualización limpias.

## Decisiones
- Privacy provider sigue siendo `null_provider`: las tablas del hito 2 guardan configuración, no datos personales de alumnos. Se sustituirá en el hito 3 (datos agregados por usuario).
- Acción/módulos/roles en tablas propias en lugar de `core_customfield`: necesitamos varias acciones por curso (grupos), una estructura MF/UF y claves únicas; customfield no lo permite sin duplicar datos.
- Capacidad de gestión (`local/customreport:manage`) comprobada en contexto de sistema (config. administrativa).
- Pesos del perfil «Empresa» 0,50/0,50 y 1 convocatoria: provisionales (TODO-NORMATIVA R-TN-2).
- Las opciones de campos de perfil sin catálogo oficial en el repo son configurables (R-TN-1).
- Solo se almacena el uso de la plataforma (courseid 0) y de cursos con acción configurada (minimización RGPD). Limitación: no hay relleno retroactivo; el uso previo a configurar una acción no se recupera (TODO: comando CLI de backfill por curso).
- Un evento de «vista» (crud r, action viewed) o el inicio de sesión cuenta como acceso; el tiempo entre eventos se atribuye al área del evento anterior; los eventos de matrícula no cuentan como actividad del administrador.
- Borrado RGPD: dentro del periodo de control (hasta fin + 90 días, o sin fecha de fin) la solicitud se registra en `local_customreport_deferred` y se ejecuta al terminar; el cursor técnico sí se borra siempre.
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
Hito 4: calculadora 30/70, datasources de Report builder, páginas de estadísticas/gráficos, impresión/CSV/XML, informe del alumno, panel del tutor, rol y comprobación del inspector.
