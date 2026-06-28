# Similia AI — Product Vision, Implementation Blueprint & Project Handoff

## 1. Project Identity

**Company:** Similia Labs
**Product:** Similia AI
**Product Type:** AI-assisted clinical workspace for classical homeopaths
**MVP Name:** Similia AI Doctor Workspace

## 2. One-Line Summary

Similia AI helps classical homeopaths move from partial patient symptoms to structured case-taking, missing questions, rubric selection, repertorization, materia medica comparison, doctor-approved prescription, printable documents, fee record, and patient timeline.

## 3. Core Positioning

Similia AI is not only a chatbot, not only a repertory, and not only a clinic management system.

It is a doctor-centered clinical workflow platform where:

* AI assists.
* Repertorization engine calculates.
* Doctor decides.
* System remembers.

The product is designed to become the clinical operating system for classical homeopathy.

---

# 4. Why This Project Exists

Classical homeopaths commonly work across many disconnected tools:

* Patient notebook
* Manual case-taking forms
* Repertory books/software
* Materia medica books
* Organon/philosophy references
* ChatGPT or general AI tools
* Prescription notes
* Fee records
* Follow-up reminders
* Old patient files

This creates scattered workflow, weak follow-up memory, and repeated manual work.

Similia AI aims to combine these into one professional workspace.

---

# 5. Target Users

## 5.1 Primary User

Classical homeopathy doctors who need a structured case-taking, repertorization, remedy comparison, prescription, and follow-up workflow.

## 5.2 Secondary Users

* Homeopathy students
* Clinics
* Teachers/trainers
* Institutions
* Research-focused practitioners

## 5.3 Future Users

Patients may later submit symptoms through a safe intake link, but patient-facing self-prescription is not part of the MVP.

---

# 6. Product Philosophy

Similia AI should never be designed as an AI that replaces the practitioner.

The correct philosophy:

> AI collects, structures, searches, compares, and explains.
> The doctor examines, judges, prescribes, and takes responsibility.

This makes the system safer, more professional, and more acceptable to serious practitioners.

---

# 7. Production MVP Boundary

The production MVP should focus on one complete doctor workflow.

## MVP Workflow

Doctor login
→ Create patient
→ Create visit
→ Enter partial raw case or manual case form
→ AI structures case
→ AI suggests missing questions
→ Doctor selects rubrics
→ Run repertorization
→ Compare remedies using materia medica RAG
→ Doctor selects final remedy, potency, repetition
→ Save fee/payment
→ Print prescription/case sheet
→ View patient timeline

This is the smallest serious version that proves the full product direction.

---

# 8. Showcase vs Revenue Boundary

## 8.1 Showcase MVP

Purpose:

* GitHub portfolio
* LinkedIn demo
* Job interview proof
* Senior developer positioning
* AI/RAG skill demonstration
* Product architecture demonstration

Showcase MVP includes:

1. Doctor authentication
2. Patient management
3. Visit and case-taking form
4. AI case structuring
5. Missing question generator
6. Rubric search and selection
7. Weighted repertorization
8. Cross repertorization
9. Eliminative repertorization
10. Materia medica RAG comparison
11. Doctor-approved prescription save
12. Potency and repetition save
13. Fee record
14. Print prescription/case sheet
15. Patient timeline

## 8.2 Revenue MVP

Revenue version starts after the showcase MVP works.

Revenue MVP should add:

1. Hosted doctor accounts
2. Subscription plans
3. AI credit limits
4. Patient intake link
5. Clinic branding on prescription
6. PDF export
7. Daily backup
8. Basic support system
9. Production deployment
10. Usage monitoring

## 8.3 Full SaaS Later

Future features:

* Patient login
* WhatsApp/SMS reminders
* Appointment module
* Medicine inventory
* Multi-doctor clinic
* Multi-branch clinic
* Advanced analytics
* AI agents
* Institution dashboard
* Mobile app
* Voice input
* Bengali patient intake
* Advanced previous-case search

---

# 9. MVP Feature Details

## 9.1 Authentication

Doctor and admin authentication using Laravel Sanctum.

Required:

* Login
* Logout
* Current user endpoint
* Protected API routes
* Doctor role
* Admin role

Sanctum cookie-based SPA authentication should be preferred for React + Laravel.

## 9.2 Patient Management

Patient fields:

* Name
* Age
* Gender
* Phone
* Address
* Occupation
* Marital status
* Notes

Doctor can view:

* Patient profile
* Visit history
* Prescription history
* Fee history
* Timeline

## 9.3 Visit and Case Taking

Each patient can have multiple visits.

Two case-taking modes should exist.

### Manual Classical Case Form

Fields:

* Chief complaint
* Location
* Sensation
* Modalities
* Concomitants
* Mentals
* Generals
* Thermal state
* Thirst
* Appetite
* Food desire/aversion
* Sleep
* Dreams
* Stool
* Urine
* Menses
* Past history
* Family history
* Current medicine
* Reports/tests note

### AI-Assisted Raw Case Input

Doctor can paste partial data.

Example:

Female, 26, chilly, low thirst, weight gain, likes sweets, fear of cancer, cracked fingers in winter, left breast discharge.

AI converts this into structured case-taking sections.

Important design rule:

Manual form and AI output must save into the same structured case model.

---

# 10. AI Case Structuring

The AI case structuring output should include:

* Chief complaint
* Mentals
* Generals
* Particulars
* Modalities
* Concomitants
* Causation
* Past history
* Family history
* Missing questions
* Red flags

Doctor must be able to edit, approve, or reject the AI output.

AI output should be treated as draft assistance, not final clinical truth.

---

# 11. Missing Question Generator

AI should suggest missing questions based on incomplete case data.

Examples:

* What makes the complaint better?
* What makes the complaint worse?
* Which time aggravates?
* Which side is affected?
* What is the patient’s thermal preference?
* How is thirst?
* How is appetite?
* Any food desire or aversion?
* How is sleep?
* Any dreams?
* Any stool or urine change?
* Any menstrual history?
* Any mental or emotional cause?
* Any past history?
* Any family history?

Doctor can mark questions as answered.

---

# 12. Rubric Search and Selection

The MVP should use a small curated repertory dataset first.

Doctor can:

* Search rubrics
* Select rubrics
* Add rubric weight
* Mark symptom type
* Mark essential rubric
* Add notes

Example rubrics:

* Mind > Fear > Cancer
* Generalities > Cold > Aggravates
* Stomach > Desires > Sweets
* Sleep > Dreams > Business
* Skin > Cracks > Fingers > Winter
* Female > Breast > Discharge

AI may suggest rubrics, but the doctor must confirm selected rubrics manually.

---

# 13. Repertorization Engine

Repertorization should be deterministic code, not AI.

AI may suggest rubrics and explain results, but the actual scoring should be transparent and reproducible.

## 13.1 Weighted Repertorization

Formula:

rubric weight × remedy grade = score

Purpose:

Gives more importance to characteristic symptoms.

## 13.2 Cross Repertorization

Purpose:

Find remedies appearing across multiple selected rubrics.

This helps identify remedies that cover the broader totality.

## 13.3 Eliminative Repertorization

Doctor marks some rubrics as essential.

If a remedy does not appear in essential rubrics, the system removes it or heavily reduces its score.

Purpose:

Useful when one or more symptoms are highly characteristic.

## 13.4 Later Guided Analysis

A guided case analysis inspired by expert clinical reasoning can be added later.

Do not copy any proprietary algorithm from existing software.

---

# 14. Repertorization Result Screen

For each remedy, show:

* Rank
* Total score
* Rubrics covered
* Essential rubrics covered
* Missing important rubrics
* Strong supporting rubrics
* Remedy grade per rubric

Example:

1. Calcarea carbonica
   Score: 86
   Covered: 8/10 rubrics
   Essential covered: 3/3
   Strong support: fear of cancer, chilly, sweets, weight gain

2. Graphites
   Score: 71
   Covered: 7/10 rubrics

3. Conium
   Score: 58
   Covered: 5/10 rubrics

This screen is important for both practitioner trust and technical showcase.

---

# 15. Materia Medica RAG

The MVP should not start with every book.

Start with a small curated dataset:

* Boericke sample
* Organon sample
* Kent repertory sample
* 20–50 remedies
* 100–300 rubrics

Doctor can compare top remedies.

Example:

Compare Calcarea carbonica vs Graphites vs Conium.

AI output should include:

* Remedy overview
* Mentals
* Generals
* Modalities
* Keynotes
* Confirmatory symptoms
* Missing differentiating symptoms
* Source references

Rule:

No book-based answer should appear without source reference.

---

# 16. Prescription Module

Doctor selects final prescription.

Fields:

* Remedy
* Potency
* Repetition
* Dose instruction
* Reason for selection
* Advice
* Food/lifestyle note
* Follow-up date

AI may prepare a draft note only from doctor-approved data.

AI must not invent remedy, potency, repetition, or dose.

---

# 17. Fee Module

Keep the MVP fee system simple.

Fields:

* Consultation fee
* Medicine fee
* Discount
* Paid amount
* Due amount
* Payment method
* Payment date

Do not build full accounting in the MVP.

---

# 18. Print and PDF Module

The MVP should include printable documents because this makes the app feel real and usable in clinics.

## 18.1 Doctor Case Sheet

Private document for doctor.

Contains:

* Patient info
* Full case
* Selected rubrics
* Repertorization result
* Doctor notes
* Final prescription

## 18.2 Patient Prescription

Patient-facing document.

Contains only:

* Patient name
* Date
* Doctor-approved remedy
* Potency
* Dose instruction
* Advice
* Follow-up date
* Clinic info

It should not show repertorization details.

## 18.3 Fee Receipt

Contains:

* Fee
* Paid
* Due
* Date
* Payment method

## 18.4 Follow-up Summary

Contains:

* Previous remedy
* Previous potency
* Follow-up date
* Changes
* Current plan

---

# 19. Patient Timeline

Every visit should create a timeline item.

Timeline shows:

* Date
* Chief complaint
* Case summary
* Selected rubrics
* Repertorization result
* Final remedy
* Potency
* Fee
* Follow-up date

Future AI can search this timeline.

Example future query:

Show all patients where Calcarea carbonica 200C was prescribed for chilly, low thirst, weight gain cases.

---

# 20. Safety Rules

Patient-facing remedy suggestion is not included in the MVP.

Doctor-facing AI must always be framed as decision support.

Required safety message:

Possible remedy considerations are based on selected rubrics and book references. Final prescription, potency, repetition, and clinical decision must be made by the qualified practitioner.

Red flags should be highlighted:

* Severe chest pain
* Severe breathing difficulty
* Unconsciousness
* Severe dehydration
* Stroke signs
* Severe infection
* Severe bleeding
* Pregnancy emergency
* Suicidal thoughts
* Suspicious lump/discharge/rapid weight loss

AI should suggest referral or medical evaluation when red flags appear.

---

# 21. Technology Stack

## Frontend

* React
* TypeScript
* Vite
* Tailwind CSS
* shadcn/ui
* TanStack Query
* React Router
* Axios

## Backend

* Laravel REST API
* Laravel Sanctum
* Laravel Queue
* Laravel Scheduler
* Policies/Gates
* OpenAPI/Swagger

## AI Service

* Python FastAPI
* RAG pipeline
* Embedding service
* AI provider interface
* Ollama for local development
* OpenRouter or other providers for production MVP later

## Database

* PostgreSQL
* pgvector

## Queue and Cache

* Redis

## Infrastructure

* Docker
* Docker Compose
* Nginx later
* GitHub Actions later
* DigitalOcean VPS later

---

# 22. Development Architecture

Current development setup:

Laravel backend runs locally with PHP/Laragon.
React frontend runs locally with npm.
FastAPI AI service runs locally with Python virtual environment.
PostgreSQL with pgvector runs in Docker.
Redis runs in Docker.

PostgreSQL server does not need to be installed locally.

Laravel only needs local PHP PostgreSQL extensions:

* pdo_pgsql
* pgsql

If Laravel runs locally:

DB_HOST=127.0.0.1
REDIS_HOST=127.0.0.1

If Laravel later runs inside Docker:

DB_HOST=postgres
REDIS_HOST=redis

---

# 23. Recommended Repository Structure

similia-ai/
├── apps/
│   ├── backend/
│   ├── frontend/
│   └── ai-service/
├── docs/
├── infrastructure/
│   └── nginx/
├── docker-compose.yml
├── README.md
└── .gitignore

---

# 24. Docker Infrastructure

Current Docker services:

* PostgreSQL with pgvector
* Redis

Recommended `docker-compose.yml`:

```yaml
services:
  postgres:
    image: pgvector/pgvector:pg16
    container_name: similia_postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: similia_ai
      POSTGRES_USER: similia
      POSTGRES_PASSWORD: secret
    ports:
      - "5432:5432"
    volumes:
      - similia_postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U similia -d similia_ai"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    container_name: similia_redis
    restart: unless-stopped
    command: redis-server --appendonly yes
    ports:
      - "6379:6379"
    volumes:
      - similia_redis_data:/data

volumes:
  similia_postgres_data:
  similia_redis_data:
```

Useful commands:

```bash
docker compose up -d
docker compose ps
docker exec -it similia_postgres psql -U similia -d similia_ai
docker exec -it similia_redis redis-cli ping
```

Expected Redis result:

```text
PONG
```

---

# 25. pgvector Migration

Enable pgvector through Laravel migration, not manually every time.

Command:

```bash
php artisan make:migration enable_pgvector_extension
```

Migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
    }

    public function down(): void
    {
        // Do not drop vector extension automatically.
    }
};
```

Run:

```bash
php artisan migrate
```

---

# 26. GitHub Project Management

Use GitHub Issues and Milestones to show professional workflow.

## Milestones

1. Foundation
2. Doctor Workspace
3. AI Case Workflow
4. Repertorization Engine
5. Knowledge & Prescription
6. Production Polish

## Issues

1. Setup monorepo with Laravel, React, FastAPI
2. Configure Docker PostgreSQL pgvector and Redis
3. Add Laravel Sanctum authentication
4. Build doctor dashboard
5. Build patient CRUD
6. Build visit/case-taking form
7. Add AI case-structuring endpoint
8. Add rubric search and selection
9. Build weighted repertorization engine
10. Build cross repertorization engine
11. Build eliminative repertorization engine
12. Add materia medica RAG comparison
13. Save prescription/remedy/potency
14. Add fee record
15. Add print/PDF case sheet and prescription
16. Add patient timeline

Issue means one task.
Milestone means one phase or group of related tasks.

---

# 27. Branch Naming Convention

Use issue-based branches:

```text
feature/issue-1-monorepo-setup
feature/issue-2-docker-postgres-redis
feature/issue-3-sanctum-authentication
feature/issue-4-doctor-dashboard
feature/issue-5-patient-crud
feature/issue-6-visit-case-taking-form
feature/issue-7-ai-case-structuring
feature/issue-8-rubric-search-selection
feature/issue-9-weighted-repertorization
feature/issue-10-cross-repertorization
feature/issue-11-eliminative-repertorization
feature/issue-12-materia-medica-rag
feature/issue-13-prescription-remedy-potency
feature/issue-14-fee-record
feature/issue-15-print-pdf
feature/issue-16-patient-timeline
```

Workflow:

Create issue
→ Create branch
→ Code
→ Commit
→ Push
→ Pull request
→ Merge
→ Close issue

Pull request description should include:

```text
Closes #issue_number
```

Example:

```text
Closes #3
```

---

# 28. Current Implementation Status

## Issue #1: Monorepo Setup

Expected structure:

similia-ai/
├── apps/
│   ├── backend/
│   ├── frontend/
│   └── ai-service/
├── docs/
├── infrastructure/
│   └── nginx/
├── docker-compose.yml
├── README.md
└── .gitignore

Backend:

Laravel installed in `apps/backend`.

Frontend:

React + TypeScript + Vite installed in `apps/frontend`.

AI service:

FastAPI installed in `apps/ai-service`.

Basic AI endpoints:

* GET /health
* POST /case/structure

## Issue #2: Docker PostgreSQL pgvector and Redis

Expected outcome:

* docker-compose.yml exists
* PostgreSQL container runs
* Redis container runs
* Laravel connects to PostgreSQL
* Laravel migration works
* pgvector extension is enabled
* Redis ping returns PONG

## Issue #3: Laravel Sanctum Authentication

Current or next active issue.

Goal:

Doctor can login, logout, and protected routes work.

Expected backend:

* Sanctum installed
* CORS supports credentials
* Stateful API middleware enabled
* `/api/login`
* `/api/me`
* `/api/logout`
* Demo doctor user
* Demo admin user
* Protected dashboard test route

Important backend env:

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
SESSION_DRIVER=database
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173,localhost:8000,127.0.0.1:8000
```

Expected React:

* Axios installed
* Axios configured with credentials
* React calls `/sanctum/csrf-cookie`
* Then React calls `/api/login`
* `/api/me` returns logged-in user
* `/api/logout` logs out

Demo users:

```text
doctor@similia.test / password
admin@similia.test / password
```

Branch:

```text
feature/issue-3-sanctum-authentication
```

Commit message:

```text
Add Laravel Sanctum authentication
```

Pull request description:

```text
Closes #3
```

---

# 29. 8-Week MVP Roadmap

## Week 1: Foundation

* Monorepo setup
* Laravel install
* React install
* FastAPI install
* PostgreSQL + pgvector
* Redis
* Docker Compose
* Basic README

## Week 2: Auth + Patient

* Sanctum authentication
* Doctor login
* Patient CRUD
* Visit CRUD
* React dashboard

## Week 3: Case Taking

* Manual case form
* Raw case input
* Structured case save
* Edit case structure

## Week 4: AI Case Structuring

* FastAPI case structuring endpoint
* Missing question generator
* AI output save
* Doctor edit/approve flow

## Week 5: Rubrics

* Seed small repertory dataset
* Rubric search
* Rubric selection
* Weight/importance/essential flag
* Basic AI rubric suggestion

## Week 6: Repertorization

* Weighted strategy
* Cross strategy
* Eliminative strategy
* Result ranking
* Result explanation
* Unit tests for scoring

## Week 7: Materia Medica RAG

* Book chunking
* Embeddings
* pgvector search
* Remedy comparison
* Citation display

## Week 8: Prescription, Fee, Print, Timeline

* Final remedy save
* Potency/repetition save
* Fee save
* Follow-up save
* Print case sheet
* Print prescription
* Print receipt
* Timeline screen
* Demo data
* README
* Architecture diagram
* Demo video

---

# 30. MVP Demo Case

Use this case for product demo:

Female, 26
Chilly
Low thirst
Weight gain
Likes sweets
Fear of cancer
Cracked fingers in winter
Left breast discharge
Sleepy
Dreams of daily work

Demo flow:

1. Doctor enters partial case.
2. AI structures it.
3. AI asks missing questions.
4. Doctor selects rubrics.
5. Weighted method ranks remedies.
6. Cross method confirms common remedies.
7. AI compares Calcarea, Graphites, Conium.
8. Doctor selects remedy and potency.
9. Fee and follow-up saved.
10. Prescription printed.
11. Timeline created.

---

# 31. What Not To Build Now

Do not build these in the MVP:

* Full patient portal
* WhatsApp integration
* SMS integration
* Medicine inventory
* Full accounting
* Multi-branch clinic
* Institution dashboard
* Mobile app
* Complex AI agents
* Full book database
* Full repertory clone
* Patient self-prescription

These will slow down the MVP.

---

# 32. Why This Project Is Strong for Recruiters

Similia AI proves:

* Senior Laravel architecture
* React + TypeScript frontend skill
* FastAPI microservice skill
* PostgreSQL and Redis experience
* Docker-based development
* AI/RAG product implementation
* Domain-driven design
* SaaS thinking
* Clinical workflow modeling
* Deterministic algorithm design
* Secure authentication
* Human-in-the-loop AI safety
* Product roadmap planning
* Clean GitHub workflow with issues, milestones, branches, and pull requests

This is stronger than a generic CRUD portfolio because it combines engineering, AI, domain expertise, and real business potential.

---

# 33. Core Technical Principle

Build the deterministic workflow first.

Correct order:

Case data
→ Rubric selection
→ Repertorization engine
→ Materia medica comparison
→ Prescription save
→ Print/timeline
→ AI assistance

Do not make AI the center of the architecture.

Make the doctor workflow the center.

---

# 34. Next Recommended Step

Continue Issue #3:

1. Finish Laravel Sanctum setup.
2. Configure CORS and stateful SPA authentication.
3. Create demo doctor/admin users.
4. Add React Axios setup.
5. Test login, current user, and logout.
6. Commit and push branch.
7. Open PR with `Closes #3`.
8. Merge to main.
9. Start Issue #4: Build doctor dashboard.

---

# 35. Final Product Rule

Similia AI should help the doctor think better, document better, compare better, and remember better.

It should not replace the doctor.

Final principle:

AI assists.
Repertorization calculates.
Doctor decides.
System remembers.
