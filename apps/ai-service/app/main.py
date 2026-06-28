from fastapi import FastAPI
from pydantic import BaseModel

app = FastAPI(title="Similia AI Service")

class CaseInput(BaseModel):
    raw_text: str

@app.get("/health")
def health():
    return {"status": "ok", "service": "ai-service"}

@app.post("/case/structure")
def structure_case(payload: CaseInput):
    return {
        "chief_complaint": "",
        "mentals": [],
        "generals": [],
        "particulars": [],
        "modalities": [],
        "missing_questions": [
            "What makes the complaint better or worse?",
            "What is the patient's thermal preference?",
            "How is thirst, appetite, sleep, stool and urine?"
        ],
        "red_flags": []
    }