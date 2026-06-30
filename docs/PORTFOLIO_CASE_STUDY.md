# Similia AI Portfolio Case Study

## Project Name

Similia AI

## Category

AI Clinical Workspace / HealthTech Workflow System

## Target Users

Classical homeopathy practitioners who need a structured system for:

- case-taking
- repertory analysis
- materia medica comparison
- prescription documentation
- patient follow-up tracking

---

## Problem

Classical homeopathy case-taking is complex.

A practitioner often needs to manage:

- raw patient narratives
- mental symptoms
- physical generals
- particulars
- modalities
- concomitants
- repertory rubrics
- remedy comparison
- prescription history
- follow-up changes
- fees and receipts

Most simple clinic software handles appointments and billing, but does not support the deep clinical workflow of classical homeopathy.

---

## Solution

Similia AI provides a complete doctor-centered clinical workflow.

The practitioner can:

1. create a patient profile
2. create a visit
3. enter raw symptoms
4. structure the case with AI assistance
5. review missing questions and red flags
6. select rubrics
7. run repertorization
8. compare materia medica
9. save final prescription
10. record fees
11. print case sheet and prescription
12. review the patient timeline

---

## Key Technical Decisions

### Laravel as Core Backend

Laravel was used because it provides:

- strong API development speed
- authentication with Sanctum
- validation with Form Requests
- clean resources
- Eloquent relationships
- service-layer architecture
- production-friendly structure

### React TypeScript Frontend

React with TypeScript was used for:

- interactive clinical forms
- dashboard UI
- visit workflow screens
- print pages
- strong type safety
- reusable components

### FastAPI AI Service

FastAPI was separated from Laravel because AI logic is easier to evolve in Python.

This allows future integration with:

- local models
- embedding models
- LLM providers
- advanced NLP pipelines

### PostgreSQL and pgvector

PostgreSQL was selected for reliability.

pgvector was added for RAG-style knowledge retrieval from materia medica chunks.

### Deterministic Repertorization

Repertorization is not handled by AI.

It is implemented as deterministic backend logic:

- weighted method
- cross method
- eliminative method

This makes the clinical calculations explainable and testable.

---

## Architecture Strengths

This project demonstrates:

- monorepo architecture
- Laravel API design
- React SPA architecture
- cookie-based authentication
- role-based data access
- service classes
- AI service orchestration
- vector search
- clinical workflow modeling
- print-friendly pages
- production-minded documentation

---

## Senior Developer Highlights

### Domain Modeling

The system models real clinical objects:

- Patient
- Visit
- Case sections
- Rubric
- Remedy grade
- Repertorization run
- Repertorization result
- Materia medica chunk
- Prescription
- Fee
- Timeline

### Workflow Design

This is not just CRUD.

It implements a complete workflow:

```text
Case -> Rubrics -> Analysis -> Comparison -> Prescription -> Follow-up
```

### AI Boundary Design

AI is used carefully.

AI assists with:

- structuring case text
- generating missing questions
- comparing retrieved materia medica chunks

AI does not:

- automatically prescribe
- decide potency
- replace the doctor

### Explainability

The repertorization engines preserve:

- selected rubrics
- remedy grades
- weights
- scores
- coverage
- missing important rubrics

This makes the result reviewable.

---

## Demo Scenario

The demo patient is a 26-year-old constitutional case with:

- chilly tendency
- low thirst
- weight gain
- desire for sweets
- fear of cancer
- cracked fingers in winter
- breast discharge
- sleepiness
- dreams of daily work

The workflow shows:

- structured case-taking
- selected rubrics
- weighted repertorization
- cross repertorization
- eliminative repertorization
- materia medica comparison
- final prescription
- fee record
- printable documents
- timeline

---

## Future Business Potential

Similia AI can become a SaaS product for classical homeopaths.

Possible business features:

- clinic subscriptions
- branded prescriptions
- patient intake forms
- WhatsApp follow-up reminders
- AI credits
- multi-doctor clinic accounts
- appointment booking
- medicine inventory
- analytics
- mobile app

---

## Portfolio Summary

Similia AI is a strong senior full-stack portfolio project because it combines:

- real domain complexity
- Laravel backend architecture
- React frontend architecture
- Python AI service
- pgvector retrieval
- deterministic analysis engines
- workflow UX
- production documentation
