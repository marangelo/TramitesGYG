# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

"ANRS Trámites" — a plain PHP (no framework, no Composer, no build step, no tests) web app for managing regulatory license requests (*solicitudes*) filed on behalf of *titulares* against the ANRS agency, plus invoicing (*facturas*) for approved requests. UI and all code identifiers are in Spanish; keep new code in Spanish to match.

Frontend: Bootstrap 5.3 + Font Awesome 6 from CDN, inline `<style>`/`<script>` in pages; charts on `dashboard.php`.

## Running

- Local: served by WAMP from `E:\Wamp\www\TramitesGYG` (PHP files are executed directly; there is no router).
- Docker: `docker compose up -d` → `kooldev/php:8.4-nginx-prod` on port `8196`, nginx config from `default.tmpl`, 150M upload limits.
- Syntax check a file: `php -l path/to/file.php`.
- `config.php` parses `.env` in the project root (`BASE_URL`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`) and creates the global `$pdo`. `.env` is git-ignored; `.env.example` is the template.
- There is no schema/migration file in the repo; the DB schema is only discoverable from the SQL in `functions.php`.

## Architecture

- **`functions.php`** (~3200 lines) is the entire data/business layer: every DB query, permission check, state machine, and reporting aggregate lives here as global functions using `global $pdo`. Pages should call these instead of writing SQL inline. It's organized in commented sections (security/permissions, catalogs, per-entity CRUD/search, solicitud state changes, requisitos, producto propuesto, dashboard stats, facturas).
- **Module folders** (`distribuidores/`, `titulares/`, `personas/`, `productos/`, `fabricantes/`, `tipos_licencia/`, `tipo_modificacion/`, `tipo_documento/`, `requisitos/`, `usuarios/`, `solicitudes/`, `facturas/`, `permisos/`) follow the same pattern:
  - `index.php` — list with search + pagination via `buscarX($busqueda, $pagina, $limite)` which returns `['datos' => [...], 'total' => n]`.
  - `crear.php` / `editar.php` — forms, both POST to `guardar.php` (insert when no id, update otherwise).
  - `guardar.php` / `eliminar.php` — perform the write, then `setMensaje()` + redirect; errors are passed back via `?error=` query param. `index.php` calls `mostrarMensaje()` to render the flash.
  - Page bootstrap: `session_start()` → `require_once '../includes/auth_check.php'` → optional `require_role(n)` → `config.php` + `functions.php`, set `$titulo`, then `include '../includes/header.php'` … `include '../includes/footer.php'`.
- **`includes/header.php`** renders the sidebar/nav (determines active section from the current directory name) and also requires config/functions.
- **Root `ajax_*.php`** endpoints return JSON for cascading selects (municipios by departamento, tipos de licencia by dirección ANRS, requisitos by combination, distribuidores, producto search).
- Output escaping helper: `h()`. Other helpers: `formatearFecha()`, `formatearMoneda()`.

### Auth & roles

`login.php` stores `usuario_id`, `id_persona`, `roles[]`, and `titulares[]` (from table `permiso`) in the session. Role IDs are hardcoded:

| id_rol | Role | Access |
|---|---|---|
| 1 | Solicitante | Own solicitudes (where they're `id_representante_legal`) for their titulares; edit only in `Nueva` |
| 2 | Evaluador | All solicitudes of their titulares; reviews, approval, facturas |
| 3 | Admin | Everything; catalog modules use `require_role(3)` |
| 4 | (view-only role) | Can view solicitudes, no actions |

Data scoping by titular: `getTitularesUsuario()` returns `null` for admin (meaning "all") or the allowed IDs. Authorization for solicitudes goes through `puedeAccederSolicitud($solicitud, 'view'|'edit'|'action')`.

### Solicitud state machine

States are rows in `estado_solicitud`, referenced **by name** (`obtenerIdEstado('Nueva')`): `Nueva → Solicitud_ingresado → Solicitud_evaluacion → Solicitud_aprobado | Solicitud_rechazada`, `Solicitud_rechazada → Solicitud_evaluacion`, any non-terminal → `Solicitud_cancelado`; after invoicing, `marcarFacturaComoPagada()` moves requests to `Solicitud_pagada`. Transitions are defined in two places that must stay consistent: `transicionValida()` (action-based, used by `cambiarEstadoSolicitud`) and `obtenerEstadosPermitidosParaRevision()` (used by `agregarRevisionSolicitud`, the manual review flow that records `solicitud_revision` rows).

On approval, the request's *producto propuesto* is applied to the real `producto` table via `aplicarProductoPropuesto()`, which detects an already-open transaction and defers commit/rollback to the caller — preserve that pattern when composing transactional functions (`$pdo->inTransaction()`).

Required documents per request come from `requisito_documento`, keyed by (tipo_licencia, tipo_tramite, tipo_modificacion). Uploaded files go to `uploads/documentos/doc_<timestamp>_<rand>.pdf` with a relative path stored in the DB (upload files are currently tracked in git).
