# Matriz de trazabilidad de requisitos

Estados: ✅ hecho · 🟡 parcial · ❌ pendiente · TODO-NORMATIVA (dato oficial no confirmado en `docs/normativa/`).
Referencias: OTMS = Orden TMS/369/2019; MD = Modelo de datos SEPE (nov. 2019); WSDL = ProveedorCentroTFWS.

| ID | Requisito | Referencia | Implementación | Prueba | Estado |
|----|-----------|------------|----------------|--------|--------|
| R-2.1.1 | Informes generados automáticamente | OTMS Anexo V.B | (hito 4) | — | ❌ |
| R-2.1.2 | Filtros por alumno, grupo, módulo, UF e intervalo de fechas | OTMS Anexo V.B | (hito 4) | — | ❌ |
| R-2.1.3 | Cobertura de todas las áreas (recursos, foros, chat, mensajería, evaluaciones) | OTMS Anexo V.B | Uso por área (curso/actividad/componente) en `local_customreport_usage`; informes en hito 4 | `usage_aggregator_test` | 🟡 |
| R-2.1.4 | Totales, medias y porcentajes | OTMS Anexo V.B | (hito 4) | — | ❌ |
| R-2.1.5 | Impresión, CSV y XML (`docs/xml-schema.md`) | OTMS Anexo V.B | (hito 4) | — | ❌ |
| R-2.1.6 | Acceso por perfil: alumno / tutor / administración | OTMS Anexo V.B | `db/access.php` (capacidades) | — | 🟡 |
| R-2.1.7 | Estadística y gráficos (Chart API): accesos, participación, tiempos, actividades, resultados | OTMS Anexo V.B | Datos de accesos, participación y tiempo agregados; gráficos en hito 4 | `usage_aggregator_test` | 🟡 |
| R-2.1.8 | Puntuación final 0,30·proceso + 0,70·prueba final (≥ 5), convocatoria 1.ª/2.ª, configurable | OTMS Anexo V.B | `classes/local/profile_manager.php`, presets/*.json (pesos 0,30/0,70, nota mínima 5, convocatorias); cálculo en hito 4 | `profile_manager_test` | 🟡 |
| R-2.1.9 | % que superan cada módulo, todos y FCT | OTMS Anexo V.B | (hito 4) | — | ❌ |
| R-2.1.10 | Perfil del alumnado y de tutores (campos de perfil + asistente + encuesta mod_feedback) | OTMS Anexo V.B / MD | `classes/local/profile_field_wizard.php`, `profilefields.php`; origen desde mod_feedback pendiente (hito 4) | `cmrole_and_wizard_test` | 🟡 |
| R-2.1.11 | Altas, modificaciones, bajas con causas (catálogo configurable) | OTMS Anexo V.B | `usage_aggregator` (eventos de matrícula), `cause_manager` (catálogo configurable de causas); UI de asignación de causa en hito 4 | `usage_aggregator_test` | 🟡 |
| R-2.1.12 | Identificación de acciones (familia, nivel, CCAA, fechas, módulos y UF) | OTMS Anexo V.B | `classes/local/action_manager.php`, `manage.php` (datos de acción); informes en hito 4 | `action_manager_test` | 🟡 |
| R-2.2.1 | Monitorización de entrada, duración y frecuencia | OTMS Anexo IV | `usage_aggregator` + tabla `local_customreport_usage` (primer/último acceso, accesos y tiempo por día); vista del tutor en hito 4 | `usage_aggregator_test` | 🟡 |
| R-2.2.2 | Registro continuo del progreso | OTMS Anexo IV | `progress_calculator` + `local_customreport_progress` (completadas, media de proceso, pruebas finales 1.ª/2.ª) | `progress_test` | ✅ |
| R-2.2.3 | Traza de participación en foros/debates/chats | OTMS Anexo IV | Participaciones (foros, chat, mensajes) en `local_customreport_usage.participations` | `usage_aggregator_test` | 🟡 |
| R-2.2.4 | Informe del alumno por módulo/UF con descarga e impresión | OTMS Anexo IV | (hito 4) | — | ❌ |
| R-2.2.5 | API pública `\local_customreport\api\progress` | Diseño | `classes/api/progress.php` (`get_user_progress`, con comprobación de capacidades) | `progress_test` | ✅ |
| R-2.2.6 | Indicadores y avisos al tutor | OTMS Anexo IV | (hito 4); ajuste `inactivitydays` | — | 🟡 |
| R-2.3.1 | Rol «Seguimiento Administración competente» solo lectura | OTMS Anexo V.B | (hito 4) | — | ❌ |
| R-2.3.2 | Comprobación de que el rol no tiene capacidades de escritura | OTMS Anexo V.B | (hito 4) | — | ❌ |
| R-2.3.3 | Página de inicio del inspector | OTMS Anexo V.B | (hito 4) | — | ❌ |
| R-2.4.1 | SOAP 1.1 document/literal conforme al WSDL | OTMS Anexo V.A / WSDL | (hito 5) | — | ❌ |
| R-2.4.2 | WS-Security UsernameToken PasswordText | OTMS Anexo V.A | (hito 5) | — | ❌ |
| R-2.4.3 | Operaciones crearCentro…eliminarAccion y códigos de retorno -2…2 | OTMS Anexo V.A / WSDL | (hito 5) | — | ❌ |
| R-2.4.4 | Reglas de ciclo de vida (30 días disponibilidad, 90 días seguimiento) | OTMS Anexo V.A | (hito 5) | — | ❌ |
| R-2.4.5 | Registro de peticiones/respuestas con retención | OTMS Anexo V.A | ajuste `logretentiondays`; (hito 5) | — | 🟡 |
| R-2.5.1 | Metadatos de acción formativa | MD | `db/install.xml` (local_customreport_action), `classes/form/action_form.php`, `manage.php`; tablas propias (decisión en STATUS) | `action_manager_test` | ✅ |
| R-2.5.2 | Módulos formativos y UF con horas | MD | `classes/local/structure_manager.php`, `structure.php` | `structure_manager_test` | ✅ |
| R-2.5.3 | Rol evaluativo de actividades | OTMS Anexo V.B | `classes/local/cmrole_manager.php`, `roles.php`; sugerencia desde format_customtiles solo como aviso/mapeo | `cmrole_and_wizard_test` | 🟡 |
| R-2.5.4 | Perfiles normativos JSON (`presets/`) con importación/exportación | Diseño | `classes/local/profile_manager.php`, `presets/`, `presets.php` (importar/exportar con validación) | `profile_manager_test` | ✅ |
| R-2.5.5 | Imagen institucional configurable | Diseño | (hito 2/4) | — | ❌ |
| R-2.6.1 | Sesiones y tiempos desde logstore (umbral y cola configurables) | OTMS Anexo V.B | `session_calculator`, `usage_aggregator`; ajustes `sessiongap`, `sessiontail` | `session_calculator_test`, `usage_aggregator_test` | ✅ |
| R-2.6.2 | Agregación incremental por tarea programada | Diseño | Tareas `aggregate_usage` (cada 15 min), `update_progress` (horaria); marca de agua + cursor por usuario | `usage_aggregator_test` | ✅ |
| R-2.6.3 | Documentación del método de estimación | Diseño | README.md | — | ✅ |
| R-2.6.4 | Comprobación de `sessiontimeout` | OTMS Anexo II | (hito 6) | — | ❌ |
| R-2.7.1 | Privacy API completa (export/borrado aplazado) | RGPD / LOPDGDD | `classes/privacy/provider.php`: metadatos, export y borrado con aplazamiento (`retention`, `purge_deferred`) | `privacy_provider_test` | ✅ |
| R-2.7.2 | Consentimiento informado integrable con tool_policy | RGPD | (hito 6) | — | ❌ |
| R-2.7.3 | Documentos de identidad en tablas propias, solo vista inspector | RGPD | capacidad `viewidentitydocuments`; (hito 5) | — | 🟡 |
| R-4.1 | Cumple phpcs, Mustache lint, GPL en cabeceras, lang en/es | Reglas de código | CI `.github/workflows/ci.yml` | CI | 🟡 |
| R-TN-1 | Catálogos oficiales de opciones (nivel formativo, situación laboral, tamaño de empresa, valoración, formación en teleformación del tutor) | MD (no hay catálogo cerrado en `docs/normativa/`) | opciones configurables en el asistente de campos de perfil | — | TODO-NORMATIVA |
| R-TN-2 | Pesos del perfil «Empresa» (0,50/0,50 provisional) | Sin norma: criterio propio | `presets/empresa.json` | `profile_manager_test` | TODO-NORMATIVA |
| R-TN-3 | Texto exacto de consentimiento y destinatario por administración | RGPD / MD punto 4 | `legaltexts` en presets | `profile_manager_test` | TODO-NORMATIVA |
| R-TN-4 | Atribución del tiempo y de la semántica de «acceso» (vista = acceso) y conjunto de eventos de participación | Método propio, explicado en README | `usage_aggregator` | `usage_aggregator_test` | TODO-NORMATIVA |
