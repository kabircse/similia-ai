# Similia AI API Overview

Base URL:

```text
http://localhost:8000/api
```

Authentication uses Laravel Sanctum SPA cookie authentication.

---

## Health

```text
GET /health
```

---

## Auth

```text
POST /login
GET  /me
POST /logout
```

---

## Dashboard

```text
GET /dashboard/overview
GET /dashboard
```

Returns:

- doctor info
- total patients
- today visits
- pending follow-ups
- prescriptions saved
- recent activity

---

## Patients

```text
GET    /patients
POST   /patients
GET    /patients/{patient}
PUT    /patients/{patient}
PATCH  /patients/{patient}
DELETE /patients/{patient}
```

---

## Visits

```text
GET    /patients/{patient}/visits
POST   /patients/{patient}/visits
GET    /patients/{patient}/visits/{visit}
PUT    /patients/{patient}/visits/{visit}
PATCH  /patients/{patient}/visits/{visit}
DELETE /patients/{patient}/visits/{visit}
```

---

## AI Case Structuring

```text
POST /patients/{patient}/visits/{visit}/structure-case
```

Purpose:

- convert raw case text into structured case sections
- generate missing questions
- detect red flags

---

## Repertory Rubrics

```text
GET /repertory/rubrics
GET /repertory/rubrics/{rubric}
```

---

## Selected Case Rubrics

```text
GET    /patients/{patient}/visits/{visit}/rubrics
POST   /patients/{patient}/visits/{visit}/rubrics
PATCH  /patients/{patient}/visits/{visit}/rubrics/{caseRubric}
DELETE /patients/{patient}/visits/{visit}/rubrics/{caseRubric}
```

---

## Repertorization

```text
GET  /patients/{patient}/visits/{visit}/repertorization-runs
GET  /patients/{patient}/visits/{visit}/repertorization-runs/{run}
POST /patients/{patient}/visits/{visit}/repertorize/weighted
POST /patients/{patient}/visits/{visit}/repertorize/cross
POST /patients/{patient}/visits/{visit}/repertorize/eliminative
```

Supported methods:

- weighted
- cross
- eliminative

---

## Materia Medica Comparison

```text
POST /patients/{patient}/visits/{visit}/materia-medica/compare
```

Purpose:

- resolve repertorization run
- retrieve materia medica chunks
- send context to FastAPI
- return comparison with source chunks

---

## Prescription

```text
GET    /patients/{patient}/visits/{visit}/prescription
PUT    /patients/{patient}/visits/{visit}/prescription
DELETE /patients/{patient}/visits/{visit}/prescription
```

Stores:

- remedy
- potency
- repetition
- dose instruction
- reason
- advice
- food/lifestyle note
- follow-up date
- status

---

## Fee

```text
GET    /patients/{patient}/visits/{visit}/fee
PUT    /patients/{patient}/visits/{visit}/fee
DELETE /patients/{patient}/visits/{visit}/fee
```

Stores:

- consultation fee
- medicine fee
- discount
- paid amount
- due amount
- payment method
- payment status
- payment date

---

## Print

```text
GET /patients/{patient}/visits/{visit}/print/case-sheet
GET /patients/{patient}/visits/{visit}/print/prescription
```

These endpoints return print-ready JSON used by React print pages.

---

## Patient Timeline

```text
GET /patients/{patient}/timeline
```

Returns complete patient history:

- visits
- case summary
- selected rubrics
- repertorization summary
- prescription
- fee
