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

El tiempo dedicado es una **estimación** calculada a partir de `logstore_standard_log`: una sesión se corta cuando el hueco entre eventos supera un umbral configurable (30 min por defecto); al último evento de cada sesión se le suma una cola configurable (5 min por defecto) y el tiempo se atribuye al área o actividad del evento. (Implementación en hitos posteriores.)

Time spent is an **estimate** computed from `logstore_standard_log`: a session ends when the gap between events exceeds a configurable threshold (default 30 min); a configurable tail is added to the last event of each session and time is attributed to the event's area or activity. (Implemented in later milestones.)

## Guía rápida del inspector / Inspector quick guide

Pendiente (hito 4) / Pending (milestone 4).

## Licencia / License

GPL v3 or later.
