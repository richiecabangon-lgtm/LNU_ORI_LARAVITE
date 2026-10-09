# LNU ORI System

Office of Research and Innovation System for **Leyte Normal University**.

A scalable Laravel + Inertia + Vue platform that manages faculty research involvement through a clear RBAC workflow:

**Faculty → Coordinator evaluation → Admin approval**

## Stack

- Laravel 13
- Inertia.js + Vue 3 + TypeScript
- Tailwind CSS (White / Blue / Yellow design system)
- SQLite by default (switchable to MySQL for production)

## Roles

| Role | Capabilities |
|------|--------------|
| **Faculty** | Create, revise, and submit research involvement records with evidence |
| **Coordinator** | Evaluate submissions, score quality, recommend approval / rejection / revision |
| **Admin** | Approve or reject evaluated submissions; manage users |

## Architecture highlights

- Enum-driven statuses and roles for safe domain modeling
- `ResearchWorkflowService` centralizes status transitions and audit history
- Policies + role middleware for authorization
- Soft deletes, UUID route keys, and status history for sustainable records
- Organizational units (colleges, departments, academic years) for growth

## Workflow statuses

`draft → submitted → under_evaluation → evaluated → approved | rejected`

Revision path: coordinator may return a submission to `revision_requested`.

## Quick start

```bash
cd LNU_ORI_LARAVITE
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install
npm run dev
```

In another terminal:

```bash
php artisan serve
```

Open `http://localhost:8000`.

## Demo accounts

Password for all seeded users: `password`

| Role | Email |
|------|-------|
| Admin | admin@lnu.edu.ph |
| Coordinator | coordinator@lnu.edu.ph |
| Faculty | faculty@lnu.edu.ph |

## Color palette

- **Primary:** White (`#FFFFFF`)
- **Secondary:** Blue (`#1E4D8C`)
- **Accent:** Yellow (`#F5C518`)

## Production notes

1. Use MySQL/PostgreSQL instead of SQLite
2. Set strong `APP_KEY`, disable debug, configure mail/queue
3. Run `npm run build` and serve Vite assets
4. Put file storage behind durable object storage if needed
5. Keep role provisioning admin-only (public registration disabled)
