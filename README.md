# local_customreport

**ES** — Plugin local de Moodle 5.3 LTS para el seguimiento de acciones formativas en teleformación conforme a la Orden TMS/369/2019 (SEPE / Servicio Canario de Empleo). Es genérico: la normativa se resuelve mediante perfiles normativos configurables (`presets/`).

**EN** — Moodle 5.3 LTS local plugin for tracking e-learning training actions according to Spanish Order TMS/369/2019 (SEPE / Canary Employment Service). It is generic: regulations are handled through configurable regulatory profiles (`presets/`).

> Estado / Status: ALPHA 0.1.0 — see [STATUS.md](STATUS.md) and [docs/REQUISITOS.md](docs/REQUISITOS.md).

## Instalación / Installation

1. Copiar el repo en `public/local/customreport` / Copy the repo to `public/local/customreport`.
2. Administración del sitio → Notificaciones / Site administration → Notifications.
3. Ajustes: Administración del sitio → Plugins locales → *Informes de seguimiento de teleformación*.

Requisitos / Requirements: Moodle 5.3, PHP 8.3/8.4 (64 bit, `sodium`), `max_input_vars` ≥ 5000, PostgreSQL 17+ / MariaDB 11.4+ / MySQL 8.4+.

## Cálculo del tiempo / Time calculation

El tiempo dedicado es una **estimación** calculada a partir de `logstore_standard_log`: una sesión se corta cuando el hueco entre eventos supera un umbral configurable (30 min por defecto); al último evento de cada sesión se le suma una cola configurable (5 min por defecto) y el tiempo se atribuye al área o actividad del evento. Los segundos se asignan al día local (zona horaria del servidor, también en cambios de horario). Un hueco igual al umbral sigue siendo la misma sesión; un evento aislado cuenta solo la cola. Los datos se agregan de forma incremental con la tarea programada «Agregar el uso a partir del registro» (cada 15 min) y nunca se recorre el registro completo al cargar una página. Solo se guarda el uso de la plataforma y de los cursos con una acción formativa configurada.

Time spent is an **estimate** computed from `logstore_standard_log`: a session ends when the gap between events exceeds a configurable threshold (default 30 min); a configurable tail is added to the last event of each session and time is attributed to the event's area or activity. Seconds are assigned to the local day (server timezone, also on daylight saving changes). A gap equal to the threshold stays in the same session; a lone event counts only the tail. Data is aggregated incrementally by the scheduled task "Aggregate usage from the log" (every 15 min) and the full log is never scanned while a page loads. Only platform usage and courses with a configured training action are stored.

## Guía rápida del inspector / Inspector quick guide

Pendiente (hito 4) / Pending (milestone 4).

## Licencia / License

GPL v3 or later.
