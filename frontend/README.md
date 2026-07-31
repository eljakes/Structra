# Navkwa Build Frontend

React/Vite workspace for the Navkwa Build ERP and Navkwa Build Cloud Console.

## Environment

Copy the example file and adjust only what differs from your environment:

```bash
cp .env.example .env.local
```

- `VITE_API_URL`: leave blank for same-origin hosting through `/api/v1`; set it for a separate API domain.
- `VITE_API_PROXY_TARGET`: Laravel origin used by the local Vite dev proxy.
- `VITE_LIVE_REFRESH_MS`: background refresh interval for authenticated ERP data. Set `0` to disable polling.

## Commands

```bash
npm install
npm run dev
npm run build
```

The frontend stores only the Sanctum bearer token locally. All ERP data is loaded from the Laravel API and PostgreSQL-backed endpoints.
