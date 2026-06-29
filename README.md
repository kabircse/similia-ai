# Similia AI

AI-assisted doctor workspace for classical homeopathy.

## Core Workflow

Partial case → AI structured case → missing questions → rubrics → repertorization → materia medica comparison → doctor-approved prescription → print/PDF → patient timeline.

## Tech Stack

- Laravel API

- React + TypeScript

- Python FastAPI AI service

- PostgreSQL + pgvector

- Redis

- Docker

## Local Demo

### Start services

```bash
docker compose up -d
```

### Backend

```bash
cd apps/backend
php artisan migrate
php artisan db:seed
php artisan serve --host=localhost --port=8000
```

### AI service

```bash
cd apps/ai-service
.venv\Scripts\Activate.ps1
uvicorn app.main:app --reload --port 8001
```

### Frontend

```bash
cd apps/frontend
npm run dev
```

### Demo login

Email: doctor@similia.test
Password: password

### Demo patient

Open Patients and select:

Demo Patient - Constitutional Case
