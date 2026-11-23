CLAUDE.md — local_customreport

Plugin local de Moodle para el seguimiento de acciones formativas en teleformación que cumple los requisitos del SEPE y de los servicios públicos de empleo autonómicos (en especial el Servicio Canario de Empleo, SCE). Es un plugin genérico: la normativa española se resuelve con perfiles normativos configurables («SEPE/SCE — Orden TMS/369/2019», «Genérico», «Empresa»…), nunca con lógica fija repartida por el código.

Trabajas sin supervisión. Decide con criterio, documenta cada decisión en STATUS.md y sigue avanzando. Commits pequeños y push frecuente.

1. Marco normativo (fuente de verdad)

Antes de programar, lee lo que haya en docs/normativa/. Si falta algún documento, déjalo anotado en STATUS.md y sigue con lo que no dependa de él. No inventes campos oficiales: si un dato del modelo SEPE no está en los documentos del repo, crea el campo configurable y márcalo TODO-NORMATIVA en docs/REQUISITOS.md.

Orden TMS/369/2019, de 28 de marzo: Anexo II (plataforma), Anexo IV (verificación: usabilidad, accesibilidad, tutorización y seguimiento) y Anexo V (seguimiento: A. servicio web SOAP; B. visita virtual).
Modelo de datos para el seguimiento de acciones formativas en teleformación (SEPE, noviembre 2019) y el WSDL ProveedorCentroTFWS publicado por el SEPE.
Instrucciones de seguimiento del SEPE/FUNDAE y, si existen en el repo, instrucciones o resoluciones del SCE.
RGPD y LOPDGDD (LO 3/2018).

Mantén docs/REQUISITOS.md como matriz de trazabilidad: cada requisito con su referencia normativa (anexo y letra), dónde se implementa (clase/archivo), cómo se prueba (test) y su estado (✅ / 🟡 / ❌ / TODO-NORMATIVA).

2. Requisitos que debe cumplir el plugin
2.1 Informes de seguimiento para la visita virtual (Anexo V.B)

Los informes deben:

Generarse automáticamente, sin intervención manual.
Poder obtenerse por alumno, por grupo/curso, por módulo formativo y por unidad formativa, y por intervalo de fechas (inicio, desarrollo y final de la acción).
Cubrir todas las áreas y servicios del curso: recursos, actividades, foros, chat, mensajería, evaluaciones, etc.
Mostrar totales, medias y porcentajes.
Imprimirse (vista de impresión limpia) y exportarse a CSV y a XML. El XML es obligatorio y Report builder no lo ofrece: implementa un exportador XML propio con esquema documentado en docs/xml-schema.md.
Ser accesibles según el perfil: el alumno ve lo suyo, el tutor-formador ve sus grupos y la administración competente lo ve todo en solo lectura.
Incluir un registro y un informe estadístico y gráfico (core Chart API) por participante y por grupo con:
Accesos a la plataforma y a cada área (número y %), con áreas visitadas, fechas y horas.
Participación: aportaciones en foros, chat y mensajes enviados.
Tiempo invertido, por área, por módulo/UF y total.
Actividades de aprendizaje realizadas por módulo/UF y en total.
Tiempo medio por módulo/UF y para la acción completa.
Resultados de trabajos, actividades y pruebas (online y presenciales registradas manualmente) y puntuación media.
Resultados de la evaluación final por módulo/UF y especialidad: número y % de aprobados y puntuación.
Puntuación final de módulo cuando la prueba final es ≥ 5: 0,30 × media de evaluación de proceso + 0,70 × prueba final, indicando la convocatoria (1.ª o 2.ª). Los pesos y la nota mínima son configurables en el perfil normativo, con estos valores por defecto.
Número y % que superan cada módulo, todos los módulos y el módulo de formación práctica en centros de trabajo.
Ofrecer como mínimo estos datos:
Perfil del alumnado: sexo, edad, nivel formativo, situación laboral, provincia/CCAA, medio por el que conoció la acción, experiencia previa en cursos online, razones para elegir teleformación y valoración de la modalidad. Para personas ocupadas: categoría profesional, tamaño de empresa y horario.
Altas, modificaciones y bajas por módulo y acción, con fechas.
Número y % que inician, completan y abandonan, con causas de baja clasificadas (catálogo configurable).
Perfil de tutores-formadores: sexo, edad, nivel formativo, experiencia docente, experiencia docente online, experiencia en la unidad de competencia, competencia docente y situación contractual.
Identificación de las acciones: familia/área profesional, nivel de cualificación, CCAA, fechas, y módulos y UF impartidos.

Los perfiles se rellenan con campos de perfil personalizados del core. Un asistente del plugin los crea con nombres y opciones estándar. Algunos datos (medio de conocimiento, razones, valoración) pueden venir de una encuesta inicial: permite asignar su origen a un campo de perfil o a una respuesta de cuestionario (mod_feedback).

2.2 Tutorización y seguimiento (Anexo IV)
Monitorización de entrada, duración y frecuencia de conexión, visible para el tutor.
Registro continuo del progreso: contenidos completados, actividades y evaluaciones realizadas y resultados.
Traza de participación en foros, debates y chats.
Informe de seguimiento para el alumno al terminar cada módulo/UF (o apartado), con descarga e impresión.
Datos de progreso por módulo/UF expuestos mediante una API pública del plugin (\local_customreport\api\progress), para que formatos de curso como format_customtiles dibujen las barras sin recalcular nada.
Indicadores para el tutor: alumnos sin acceso en N días, ritmo por debajo del previsto y actividades vencidas sin entregar. Opcional: aviso automático al tutor (Message API).
2.3 Perfil de la administración competente (Anexo V.B)
Crea un rol «Seguimiento Administración competente» (SEPE, SCE…) con permisos de solo lectura sobre cursos, informes, calificaciones y registros, y sin ningún permiso de escritura.
Incluye una comprobación automática que verifica que ese rol no tiene capacidades de riesgo de escritura (RISK_DATALOSS, RISK_CONFIG, RISK_PERSONAL con escritura, etc.) y alerta si alguien las añade.
Página de inicio del inspector: lista de acciones formativas con acceso directo a cada informe.
2.4 Servicio web de seguimiento SOAP (Anexo V.A)
SOAP 1.1 sobre HTTPS, document/literal, conforme al WSDL oficial ProveedorCentroTFWS que haya en docs/normativa/. Si el WSDL no está, implementa todo lo demás y deja este punto bloqueado en STATUS.md.
Autenticación WS-Security UsernameToken Profile 1.0, wsse:PasswordText, con credenciales configurables por administración competente.
Operaciones: crearCentro, obtenerDatosCentro, crearAccion, obtenerListaAcciones, obtenerAccion y eliminarAccion.
La respuesta repite el mensaje de entrada y añade <codigoRetorno>: -2 WS no disponible, -1 error inesperado, 0 correcto, 1 centro con acciones / acción existente / acción inexistente (según la operación), 2 error en parámetro con ETIQUETA_ERROR.
Reglas del ciclo de vida:
La acción debe estar disponible desde la fecha de inicio prevista hasta 30 días naturales después del fin, y no puede borrarse antes.
Seguimiento al inicio, durante el desarrollo y 90 días naturales después del fin.
Las fechas son previstas mientras no han ocurrido y reales cuando ya han pasado.
Los datos que se envían en cada fase son los que marca el Anexo V: autorización (identificación, especialidades, centros presenciales); inicio (tutores, datos de uso, participantes); avance (tutorías presenciales, evaluación final, resultados).
Endpoint propio dentro del plugin (por ejemplo ws/seguimiento.php), con SoapServer si ext-soap está disponible y, si no, procesando el XML con DOMDocument. Debe registrar cada petición y respuesta en un log con retención configurable.
Existe un plugin GPL comparable (studiolxd/moodle-local_sepe). Puedes estudiarlo para interpretar la norma. Si reutilizas código, respeta su licencia y deja la atribución en las cabeceras y en el README.
2.5 Modelo de configuración
Acción formativa = un curso o un grupo dentro de un curso (configurable). Metadatos: código de acción, especialidad (código del Catálogo), código de centro, administración competente, fechas previstas y reales, centros de sesiones presenciales y modalidad. Usa campos personalizados de curso (core_customfield) cuando encaje y tablas propias cuando no.
Estructura: asignación de secciones (y subsecciones) a módulos formativos y unidades formativas, con sus horas.
Rol evaluativo de cada actividad: proceso, prueba final 1.ª convocatoria, prueba final 2.ª convocatoria, FCT o no evaluable. Si format_customtiles está instalado, ofrece importar sus categorías (AF/AE/PP/PO) como sugerencia, sin depender de él.
Perfiles normativos en JSON versionado (presets/): sepe_sce_tms369, generico y empresa. Definen pesos, nota mínima, informes activos, columnas, campos obligatorios y textos legales. Se importan y exportan desde la administración y se validan al importar.
Imagen institucional configurable en las cabeceras de informes e impresiones: logotipos de SEPE, SCE/Gobierno de Canarias, FSE+, etc. Los archivos los sube el administrador; el plugin no incluye logotipos oficiales.
2.6 Cálculo del tiempo
Fuente: logstore_standard_log. Una sesión se corta cuando el hueco entre eventos supera un umbral configurable (30 min por defecto). Al último evento de cada sesión se le suma una cola configurable. El tiempo se atribuye al área o actividad del evento.
Agregación incremental con tarea programada en tablas propias indexadas. Nunca se recorre el log completo al cargar una página.
Documenta en el README que es una estimación y explica el método, que un inspector puede preguntar.
Comprobación de cumplimiento: la caducidad de sesión por inactividad (sessiontimeout) está configurada.
2.7 Privacidad (RGPD / Anexo V.A)
Provider completo de Privacy API: export y borrado, respetando las retenciones legales. Los datos de seguimiento deben conservarse durante el periodo de control, así que el borrado se aplaza y se documenta.
Texto de consentimiento informado para el tratamiento de datos de seguimiento, cuyo destinatario es la administración laboral competente, integrable con tool_policy.
Guarda solo los datos personales necesarios. Los documentos de identidad recibidos por el servicio web van en tablas propias, no se muestran fuera de la vista de inspector y aparecen en el registro de privacidad.
3. Entorno técnico
Moodle 5.3 LTS (MOODLE_503_STABLE). PHP 8.3/8.4 de 64 bits, extensión sodium, max_input_vars ≥ 5000. PostgreSQL 17+, MariaDB 11.4+ o MySQL 8.4+.
Componente local_customreport. La raíz del repo es la raíz del plugin. En un checkout de Moodle va en public/local/customreport (desde 5.1 el webroot es public/).
Clona Moodle fuera del repo (git clone --depth 1 --branch MOODLE_503_STABLE https://github.com/moodle/moodle.git) y enlaza el plugin. Prueba primero moodle-docker; si Docker no funciona en el sandbox, instala PHP y la base de datos directamente. Si una vía falla por red o permisos, cambia de estrategia en lugar de reintentar en bucle.
Antes de usar APIs, lee los UPGRADING.md de 5.3 de public/lib, public/reportbuilder y public/course.
Ejecuta solo los tests de este plugin, nunca la suite completa de Moodle. Explora el core con grep/glob y no leas archivos enormes enteros.
4. Reglas de código
Estilo de Moodle (phpcs con el estándar moodle, sin avisos). Clases con autoload en classes/ y nada de funciones globales nuevas salvo los callbacks obligatorios de lib.php.
Informes tabulares sobre core_reportbuilder (datasources, entidades, columnas, filtros). Estadísticas y gráficos en páginas propias con core Chart API y plantillas Mustache.
Comprobación de capacidades en cada página, export y endpoint. Nunca se muestran datos de otro usuario sin permiso.
Todo texto visible va en cadenas de idioma: lang/en obligatorio y lang/es completo y revisado.
Cálculos pesados en tareas programadas o adhoc, nunca al cargar la página.
Nada específico del SEPE fuera de presets/, de la capa SOAP y de docs/.
Accesibilidad WCAG 2.1 AA en todas las páginas del plugin.
Sin librerías desde CDN. Licencia GPL v3 con cabeceras en todos los archivos.
5. Hitos (en este orden)

Al cerrar cada hito: tests en verde, STATUS.md y docs/REQUISITOS.md actualizados, commit y push. Si el presupuesto se acaba, lo que quede hecho debe instalarse y funcionar.

Base: version.php (requires tomado del checkout de 5.3, madurez ALPHA, 0.1.0), capabilities, ajustes, cadenas en/es, Privacy provider, README, workflow de GitHub Actions con moodle-plugin-ci (PHP 8.3/8.4, pgsql/mariadb), docs/REQUISITOS.md inicial con todos los requisitos de la sección 2.
Configuración: metadatos de acción formativa, asignación de módulos/UF, rol evaluativo de actividades, perfiles normativos JSON y asistente de campos de perfil de alumnado y tutores.
Motor de datos: sesiones y tiempos, accesos por área, participación, progreso, altas y bajas con causas. Tareas programadas y tablas agregadas.
Informes de visita virtual: datasources de Report builder, estadísticas y gráficos, filtros por fechas y módulo/UF, totales, medias y porcentajes, cálculo 30/70 con convocatorias, impresión, CSV y XML, informe del alumno por módulo/UF, panel del tutor y rol de inspector de solo lectura con su comprobación.
Servicio web SOAP del Anexo V.A con tests de cada operación y código de retorno, con XML de ejemplo en tests/fixtures/.
Panel de cumplimiento: página de administración que revisa de forma automática los requisitos verificables (sessiontimeout, rol de inspector sin escritura, endpoint SOAP operativo, campos de perfil presentes, consentimiento configurado, imagen institucional cargada…) y enlaza cada uno con docs/REQUISITOS.md.
6. Definición de terminado
El plugin se instala y se actualiza sin errores ni avisos en 5.3.
PHPUnit verde. Casos límite del algoritmo de sesiones: un único evento, hueco exacto en el umbral, eventos duplicados, cambio de horario, usuario sin accesos.
phpcs sin avisos y Mustache lint correcto.
docs/REQUISITOS.md sin requisitos ❌ que no estén justificados.
README en español e inglés con instalación, configuración del perfil SEPE/SCE y guía rápida para el inspector.
7. STATUS.md

Mantenlo siempre al día con: hito actual, hecho, decisiones (con su porqué), bloqueos, TODO-NORMATIVA y siguiente paso.
