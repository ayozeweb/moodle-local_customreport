# STATUS

## Hito actual
Hito 5 (Servicio web SOAP) — CERRADO. Siguiente: hito 6 (Panel de cumplimiento).

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
- Hito 4:
  - `score_calculator` (0,30 proceso + 0,70 prueba final con nota mínima 5 y 1.ª/2.ª convocatoria, configurable por perfil).
  - `report\followup` (datos del informe desde las tablas agregadas), `report\presenter` (plantillas + Chart API), `export\csv`, `export\xml` + `docs/followup.xsd` + `docs/xml-schema.md`.
  - Páginas: `report.php` (filtros, gráficos, impresión, CSV, XML), `student.php` (informe del alumno por módulo/UF), `tutor.php` (sin acceso, ritmo, actividades vencidas), `inspector.php`.
  - Report builder: datasources `progress` y `usage`.
  - Rol `customreportinspector` solo lectura (creado en install/upgrade) y status check `inspector_readonly`.
  - Logotipos institucionales configurables (4 ranuras) mostrados en informes e impresiones.
  - Smoke test real con servidor web (login admin y alumno): 14 páginas responden 200; CSV y XML válidos contra el XSD; el alumno es redirigido a su informe y se le deniega tutor/inspector/gestión.
- Hito 5:
  - WSDL real recuperado de la página HTML guardada en `docs/normativa/ProveedorCentroTFWS.wsdl` (ver `docs/ws.md`); esquemas en `ws/schema/`.
  - `ws/seguimiento.php` + `classes/sepe/` (service, security, wsdl, store, builder, lifecycle, identity, result_calculator): 6 operaciones, WS-Security PasswordText, validación contra XSD, ciclo de vida, log sin contraseñas con retención, DOMDocument (sin ext-soap).
  - `wsadmin.php`: credenciales, centro, acciones recibidas y vinculación, importación de documentos de identidad, últimas peticiones.
  - Tablas: `wscenter`, `wsaction`, `wscred`, `wslog`, `idoc`, `slot` (franjas horarias).
  - Corrección: `settings.php` del hito 4 no incluía los ajustes de logotipos (un reemplazo falló en silencio); reescrito y verificado en navegador.
  - Smoke test real por HTTP con las fixtures: códigos 0/1/2/-1, fallos de autenticación como SOAP Fault, WSDL por GET, HTTPS obligatorio.
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
- El informe XML es un esquema propio (no hay esquema oficial para la visita virtual en `docs/normativa/`); queda documentado en `docs/xml-schema.md`.
- «Han iniciado» = con accesos, tiempo o actividades hechas en el intervalo; «bajas» = último evento de matrícula es una baja. «Superan el módulo» = puntuación final ≥ nota de aprobado del perfil; el módulo de prácticas (MP) se supera con todas sus actividades completadas.
- Aviso automático al tutor (Message API): opcional, no implementado.
- El servicio usa DOMDocument y no SoapServer: control total del orden/espacios de nombres, mismos resultados con o sin ext-soap y testeable sin servidor.
- `centercode` de la acción pasa a 16 caracteres (el WSDL define `codigo_centro` de longitud 16; el hito 2 validaba 10 por error).
- Sin protección contra fuerza bruta en el endpoint (TODO: limitar intentos por IP/usuario).
- Tests con atributos `#[CoversClass]` (PHPUnit 11) para evitar avisos de deprecación con `--fail-on-warning`.

## Bloqueos / limitaciones del entorno
- Sandbox sin PostgreSQL 17 ni MariaDB ≥ 11.4 y sin Docker: pruebas locales con PostgreSQL 16 y versión mínima rebajada solo en `/tmp/moodle`. La CI de GitHub usa las versiones oficiales; todavía no se ha visto su resultado.
- Las páginas (`manage.php`, `structure.php`, `roles.php`, `presets.php`, `profilefields.php`) pasan lint y phpcs pero no se han recorrido en un navegador; la lógica que usan sí está cubierta por tests. Pendiente de smoke test (Behat/Playwright) más adelante.
- Mustache lint (grunt) no ejecutado localmente; las plantillas se validan renderizándolas en PHPUnit y el JSON de ejemplo se comprobó a mano.
- Sin `ext-soap` local: el hito 5 usará el respaldo con DOMDocument.
- Mustache lint no ejecutado (aún no hay plantillas).

## TODO-NORMATIVA
- R-TN-1, R-TN-2, R-TN-3 (ver docs/REQUISITOS.md).
- El PDF del modelo de datos tiene los esquemas de petición/respuesta como imágenes: se confirmarán contra el WSDL en el hito 5.

## Siguiente paso
Hito 6: panel de cumplimiento (sessiontimeout, rol inspector, endpoint SOAP, campos de perfil, consentimiento/tool_policy, logotipos) enlazado con docs/REQUISITOS.md; revisión final de README y requisitos ❌.
