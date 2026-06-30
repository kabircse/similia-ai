# Similia AI

![CI](https://github.com/kabircse/similia-ai/actions/workflows/ci.yml/badge.svg)

**AI Clinical Workspace for Classical Homeopaths**

Similia AI is a production-style clinical workspace that helps a classical homeopathy practitioner move from raw patient symptoms to structured case-taking, rubric selection, repertorization, materia medica comparison, doctor-approved prescription, fee record, printable documents, and patient timeline.

This project is built as a senior full-stack portfolio project using Laravel, React, FastAPI, PostgreSQL, pgvector, Redis, and AI/RAG-style architecture.

---

## Core Principle

Similia AI does **not** automatically prescribe medicine.

The system supports the practitioner with:

- structured case-taking
- missing question suggestions
- red flag detection
- rubric selection
- repertorization analysis
- materia medica comparison
- prescription documentation

The final remedy, potency, repetition, and clinical decision are always made by the qualified practitioner.

---

## Product Workflow

```text
Doctor Login
    ->
Patient Profile
    ->
Visit / Case Taking
    ->
AI Case Structuring
    ->
Missing Questions + Red Flags
    ->
Rubric Selection
    ->
Weighted / Cross / Eliminative Repertorization
    ->
Materia Medica RAG Comparison
    ->
Doctor-Approved Prescription
    ->
Fee Record
    ->
Print Case Sheet / Prescription
    ->
Patient Timeline
```

---

## Completed Features

### Authentication

- Laravel Sanctum SPA authentication
- Doctor login/logout
- Protected API routes
- Demo doctor account

### Patient Management

- Patient CRUD
- Patient profile
- Search patients
- Patient-specific access control

### Visit and Case Taking

- Visit CRUD
- Raw case text
- Manual classical case-taking form
- Structured case sections
- Missing questions
- Red flags
- Doctor notes
- Follow-up date

### AI Case Structuring

- Laravel calls FastAPI AI service
- Raw case text converted into structured clinical sections
- Missing questions generated
- Red flags detected
- Doctor can edit and approve final case data

### Rubric Selection

- Search repertory rubrics
- Select rubrics for visit
- Assign symptom type
- Assign importance
- Set weight
- Mark essential rubric

### Repertorization

Implemented three methods:

1. **Weighted Repertorization**

   - Formula: rubric weight x remedy grade
   - Ranks remedies by total weighted score

2. **Cross Repertorization**

   - Finds remedies appearing across most selected rubrics
   - Ranks by rubric coverage first

3. **Eliminative Repertorization**

   - Uses essential rubrics as filters
   - Keeps only remedies covering all essential rubrics

### Materia Medica RAG Comparison

- Sample materia medica knowledge chunks
- PostgreSQL pgvector embedding storage
- Local deterministic embedding service
- Retrieval of relevant remedy chunks
- FastAPI comparison endpoint
- Source chunks displayed to the doctor

### Prescription

- Save final remedy
- Save potency
- Save repetition
- Save dose instruction
- Save reason for selection
- Save advice
- Save food/lifestyle note
- Save follow-up date
- Save draft/final status

### Fee Record

- Consultation fee
- Medicine fee
- Discount
- Paid amount
- Due amount
- Payment method
- Payment status
- Payment date

### Print / PDF

- Print doctor case sheet
- Print patient prescription
- Browser Save as PDF support
- Clean print layout without dashboard sidebar

### Patient Timeline

- Full visit history
- Case summary
- Rubrics summary
- Repertorization summary
- Prescription summary
- Fee summary
- Quick link to visit detail

---

## Tech Stack

### Backend

- Laravel 13
- PHP 8.4
- Laravel Sanctum
- PostgreSQL
- pgvector
- Redis
- REST API
- Eloquent Resources
- Form Requests
- Service classes

### Frontend

- React
- TypeScript
- Vite
- React Router
- TanStack Query
- Axios
- CSS
- Lucide React icons

### AI Service

- Python
- FastAPI
- Pydantic
- Local deterministic case structuring
- Local materia medica comparison endpoint

### Infrastructure

- Docker Compose for local and production deployment
- PostgreSQL with pgvector
- Redis
- Local multi-service development

---

## Project Structure

```text
similia-ai/
|-- apps/
|   |-- backend/
|   |   `-- Laravel API
|   |-- frontend/
|   |   `-- React TypeScript app
|   `-- ai-service/
|       `-- FastAPI AI service
|-- docs/
|   |-- ARCHITECTURE.md
|   |-- API_OVERVIEW.md
|   |-- DEPLOYMENT.md
|   |-- DEMO_SCRIPT.md
|   |-- PORTFOLIO_CASE_STUDY.md
|   `-- ROADMAP.md
|-- infrastructure/
|   |-- caddy/
|   `-- nginx/
|-- scripts/
|-- docker-compose.yml
|-- docker-compose.prod.yml
`-- README.md
```

---

## Local Setup

### 1. Start Docker Services

```bash
docker compose up -d
```

This starts:

- PostgreSQL with pgvector
- Redis

### 2. Backend Setup

```bash
cd apps/backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve --host=localhost --port=8000
```

### 3. AI Service Setup

```bash
cd apps/ai-service
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
python -m uvicorn app.main:app --reload --port 8001
```

### 4. Frontend Setup

```bash
cd apps/frontend
npm install
npm run dev
```

Open:

```text
http://localhost:5173
```

---

## Demo Login

```text
Email: doctor@similia.test
Password: password
```

---

## Demo Flow

1. Login as demo doctor.
2. Open Dashboard.
3. Open Patients.
4. Select **Demo Patient - Constitutional Case**.
5. Review patient timeline.
6. Open demo visit.
7. Review structured case sections.
8. Review selected rubrics.
9. Run weighted repertorization.
10. Run cross repertorization.
11. Run eliminative repertorization.
12. Run materia medica comparison.
13. Review doctor-approved prescription.
14. Review fee record.
15. Print case sheet.
16. Print prescription.

---

## Environment Variables

### Backend

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=similia_ai
DB_USERNAME=similia
DB_PASSWORD=secret

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

AI_SERVICE_URL=http://localhost:8001
AI_SERVICE_TIMEOUT=30

SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173,localhost:8000,127.0.0.1:8000
SESSION_DRIVER=database
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
```

### Frontend

```env
VITE_API_BASE_URL=http://localhost:8000
```

---

## Production Docker Deployment

Copy the production env example:

```bash
cp .env.production.example .env.production
```

Generate a production `APP_KEY`:

```bash
cd apps/backend
php artisan key:generate --show
```

Edit secrets and domain settings:

```bash
nano .env.production
```

Build and start production containers:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
```

Run migrations:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan migrate --force
```

Run demo seed only for a demo deployment:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan db:seed --force
```

Check containers:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml ps
```

After the first setup, deploy with:

```bash
./scripts/deploy-prod.sh
```

Create a PostgreSQL backup with:

```bash
./scripts/backup-postgres.sh
```

Production services:

- proxy
- frontend
- backend-nginx
- backend PHP-FPM
- backend-queue
- backend-scheduler
- FastAPI AI service
- PostgreSQL pgvector
- Redis

Full deployment notes are in [Deployment Guide](docs/DEPLOYMENT.md).

### HTTPS Reverse Proxy

Production uses Caddy as the HTTPS reverse proxy.

```text
https://your-domain.com
    |
    v
Caddy
    |
    v
React frontend / Laravel API
```

Caddy routes:

- `/` -> frontend
- `/api/*` -> Laravel API
- `/sanctum/*` -> Laravel Sanctum

### Queue Workers

Production includes dedicated Laravel services:

- `backend-queue`
- `backend-scheduler`

Queue worker logs:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f backend-queue
```

Scheduler logs:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f backend-scheduler
```

---

## Production Build Check

### Frontend

```bash
cd apps/frontend
npm run build
```

### Backend

```bash
cd apps/backend
php artisan route:list
php artisan config:clear
php artisan cache:clear
```

### AI Service

```bash
cd apps/ai-service
python -m compileall app
```

---

## Legacy Knowledge Import Foundation

Similia AI supports a remedy master table and legacy import foundation.

### Import Legacy Remedies CSV

Place the file here:

```text
apps/backend/storage/app/imports/legacy/remedies.csv
```

CSV format:

```csv
id,name,abbreviation
1,Abies Canadensis,Abies-c.
2,Abies Nigra,Abies-n.
3,Abrotanum,Abrot.
```

Dry run:

```bash
cd apps/backend
php artisan import:legacy-remedies storage/app/imports/legacy/remedies.csv --source=legacy_sql --dry-run
```

Import:

```bash
php artisan import:legacy-remedies storage/app/imports/legacy/remedies.csv --source=legacy_sql
```

Backfill current repertory, materia medica, and prescription rows:

```bash
php artisan remedies:backfill-existing
```

Search:

```text
GET /api/remedies?q=Abrot
```

Large CSV files under `apps/backend/database/imports/*.csv` are intentionally ignored and should not be committed.

---

## Import Legacy Repertory Data

Expected files:

```text
apps/backend/storage/app/imports/legacy/remedies.csv
apps/backend/storage/app/imports/legacy/repertories.csv
apps/backend/storage/app/imports/legacy/rubrics.csv
apps/backend/storage/app/imports/legacy/rubric_remedies.csv
```

The import commands accept any path relative to `apps/backend`, so local ignored files in `apps/backend/database/imports` can also be imported directly.

Import order:

```bash
cd apps/backend

php artisan import:legacy-remedies storage/app/imports/legacy/remedies.csv --source=legacy_sql

php artisan import:legacy-repertories storage/app/imports/legacy/repertories.csv --source=legacy_sql

php artisan import:legacy-rubrics storage/app/imports/legacy/rubrics.csv --source=legacy_sql

php artisan import:legacy-rubric-remedies storage/app/imports/legacy/rubric_remedies.csv --source=legacy_sql --remedy-source=legacy_sql
```

Root/chapter rubrics such as `Mind` are imported but marked as non-selectable. They are kept for hierarchy/search context, but hidden from normal rubric selection unless `include_non_selectable=true`.

---

## Import Legacy Materia Medica Data

Expected files:

```text
apps/backend/storage/app/imports/legacy/remedies.csv
apps/backend/storage/app/imports/legacy/materia_media.csv
apps/backend/storage/app/imports/legacy/materia_media_contents.csv
```

Import order:

```bash
cd apps/backend

php artisan import:legacy-remedies storage/app/imports/legacy/remedies.csv --source=legacy_sql

php artisan import:legacy-materia-medica-sources storage/app/imports/legacy/materia_media.csv --source=legacy_sql

php artisan import:legacy-materia-medica-contents storage/app/imports/legacy/materia_media_contents.csv --source=legacy_sql --remedy-source=legacy_sql
```

Test with a limited content import first:

```bash
php artisan import:legacy-materia-medica-contents storage/app/imports/legacy/materia_media_contents.csv --source=legacy_sql --remedy-source=legacy_sql --limit=10
```

Reimport and replace chunks for each legacy content row:

```bash
php artisan import:legacy-materia-medica-contents storage/app/imports/legacy/materia_media_contents.csv --source=legacy_sql --remedy-source=legacy_sql --reimport
```

The importer resolves remedies from the remedy master table, splits large materia medica entries into chunks, generates local deterministic pgvector embeddings, and keeps legacy row IDs in metadata for traceability.

---

## Import Organon, Potency, and Book Sections

Expected files:

```text
apps/backend/storage/app/imports/legacy/books.csv
apps/backend/storage/app/imports/legacy/book_sections.csv
```

Import books:

```bash
cd apps/backend

php artisan import:legacy-books storage/app/imports/legacy/books.csv --source=legacy_sql
```

Import book sections as embedded knowledge chunks:

```bash
php artisan import:legacy-book-sections storage/app/imports/legacy/book_sections.csv --source=legacy_sql
```

Test with a limited import:

```bash
php artisan import:legacy-book-sections storage/app/imports/legacy/book_sections.csv --source=legacy_sql --limit=10
```

Reimport and replace existing chunks:

```bash
php artisan import:legacy-book-sections storage/app/imports/legacy/book_sections.csv --source=legacy_sql --reimport
```

Knowledge source types:

- `organon`
- `philosophy`
- `potency`
- `relationship`
- `medical`
- `general`

Search API:

```text
GET /api/knowledge/search?q=highest ideal of cure&source_type=organon
GET /api/knowledge/search?q=repetition after improvement&source_type=potency
GET /api/knowledge/search?q=complementary antidote inimical&source_type=relationship
```

---

## AI Remedy Suggestion from Imported Knowledge

After repertory, materia medica, and book knowledge imports are available, Similia AI can generate doctor-facing remedy suggestions from the current visit evidence.

Manual flow:

```text
Open visit
Select rubrics
Run repertorization
Run materia medica comparison
Generate AI remedy suggestion
Review evidence matrix and source chunks
Doctor decides final remedy, potency, and repetition
```

API:

```text
GET /api/patients/{patient}/visits/{visit}/remedy-suggestions
POST /api/patients/{patient}/visits/{visit}/remedy-suggestions/generate
```

The suggestion system retrieves:

- selected rubric and repertorization evidence
- remedy-specific materia medica chunks
- Organon/philosophy, potency, relationship, and medical knowledge chunks

It stores each suggestion run and item with source evidence for review. It does not prescribe automatically.

---

## Documentation

- [Architecture](docs/ARCHITECTURE.md)
- [API Overview](docs/API_OVERVIEW.md)
- [Portfolio Case Study](docs/PORTFOLIO_CASE_STUDY.md)
- [Roadmap](docs/ROADMAP.md)
- [Demo Script](docs/DEMO_SCRIPT.md)

---

## Portfolio Value

This project demonstrates:

- Laravel API architecture
- React TypeScript frontend
- SPA authentication
- Domain-driven workflow design
- AI service integration
- RAG-style knowledge retrieval
- PostgreSQL pgvector usage
- Deterministic clinical scoring engines
- Print/PDF workflows
- Real-world CRUD + workflow + timeline product
- Multi-service monorepo architecture
- Senior-level product thinking

---

## Disclaimer

Similia AI is a clinical workflow and decision-support prototype for classical homeopathy practitioners.

It is not a replacement for qualified medical care. It does not automatically prescribe medicine. Final clinical decisions must be made by a qualified practitioner.
