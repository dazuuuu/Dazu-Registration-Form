# React frontend (Dazu Hub public site + student/tutor portals)

## Development

```bash
cd frontend
npm install
npm run dev
```

Proxy expects PHP backend on port 8080, or configure Ampps Apache to serve `/workspace/public`.

## Production build

```bash
cd frontend
npm run build
```

Output goes to `../public/` (keeps `api/`, `admin/`, `uploads/`, `tutor/` intact).

## Routes

| Path | Description |
|------|-------------|
| `/` | Homepage |
| `/courses` | Course catalog (from PHP API) |
| `/courses/:id` | Course detail + apply |
| `/testimonials` | Video + text testimonials |
| `/apply` | Standalone application form |
| `/student` | Student portal |
| `/tutor` | Tutor dashboard (links to PHP curriculum tools) |
| `/admin/*` | PHP admin panel (unchanged) |
| `/api/*` | JSON API |

## Database

Run migration: `databases/migrations/023_video_testimonials.sql`

## Legacy PHP pages

Archived under `legacy-public/` during migration.
