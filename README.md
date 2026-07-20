# Structra

Structra is a construction operations web application built from the PRD in
`/Users/eljakes/Documents/NAVKWA GROUP LTD/Contruction ERP/Structra_PRD.md`.

This repo is organized as a fullstack workspace:

- `backend/` — Laravel API, Sanctum token auth, PostgreSQL persistence
- `frontend/` — React/Vite web app
- `docker-compose.yml` — PostgreSQL and Redis services for local development

## Implemented Phase 1

- Tenant-aware company, branch, role, user, client, and supplier foundation
- Token authentication with seeded owner account
- Project register with tasks, budget lines, progress and cost rollups
- Procurement workflow: requisition, submit, approve/reject, convert to PO, issue, approve, deliver, close
- Document repository with branch/project scoping and real file upload storage
- Drawing library with disciplines, revisions, status transitions, and file uploads
- Executive dashboard, reports, and audit log API
- React workspace for dashboard, projects, procurement, documents, reports, and admin

## Local Setup

Backend:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Frontend:

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

Default local URLs:

- API: `http://127.0.0.1:8000/api/v1`
- Web: `http://127.0.0.1:5173`

If `8000` is already in use, run Laravel on another port, for example
`php artisan serve --host=127.0.0.1 --port=8010`, and set
`frontend/.env.local` to `VITE_API_URL=http://127.0.0.1:8010/api/v1`.

Seeded login:

- Email: `owner@structra.test`
- Password: `Structra2026`

## PostgreSQL

The current local `backend/.env` is configured for the PostgreSQL database
`structra` on `127.0.0.1:5432` with username `eljakes`.

For a portable Docker setup:

```bash
docker compose up -d postgres redis
```

Then set `backend/.env` database values to:

```bash
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=structra
DB_USERNAME=structra
DB_PASSWORD=structra_secret
```

## Verification

Backend:

```bash
cd backend
php artisan test
```

Frontend:

```bash
cd frontend
npm run lint
npm run build
```
