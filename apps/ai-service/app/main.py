from typing import Any, Dict, List, Optional

from fastapi import FastAPI
from pydantic import BaseModel, Field

app = FastAPI(title="Similia AI Service")


CASE_SECTION_KEYS = [
    "location",
    "sensation",
    "modalities",
    "concomitants",
    "mentals",
    "generals",
    "thermal_state",
    "thirst",
    "appetite",
    "food_desires",
    "food_aversions",
    "sleep",
    "dreams",
    "stool",
    "urine",
    "menses",
    "past_history",
    "family_history",
    "current_medicine",
    "reports_note",
]


class CaseStructureRequest(BaseModel):
    raw_text: Optional[str] = Field(default="", max_length=20000)
    chief_complaint: Optional[str] = Field(default=None, max_length=5000)
    existing_case_sections: Any = Field(default_factory=dict)


class CaseStructureData(BaseModel):
    chief_complaint: Optional[str]
    case_sections: Dict[str, str]
    missing_questions: List[str]
    red_flags: List[str]
    confidence: str
    engine: str


def append_text(current: str, value: str) -> str:
    if not value:
        return current

    if not current:
        return value

    if value.lower() in current.lower():
        return current

    return f"{current}; {value}"


def contains_any(text: str, keywords: List[str]) -> bool:
    return any(keyword in text for keyword in keywords)


def build_missing_questions(sections: Dict[str, str], chief_complaint: Optional[str]) -> List[str]:
    questions: List[str] = []

    if not chief_complaint:
        questions.append("What is the chief complaint and duration?")

    if not sections.get("modalities"):
        questions.append("What makes the complaint better or worse?")

    if not sections.get("thermal_state"):
        questions.append("Is the patient chilly, hot, or thermally balanced?")

    if not sections.get("thirst"):
        questions.append("How is thirst: low, normal, high, or thirstless?")

    if not sections.get("sleep"):
        questions.append("How is sleep and sleep position?")

    if not sections.get("dreams"):
        questions.append("Any repeated dreams or characteristic dreams?")

    if not sections.get("stool"):
        questions.append("Any stool habit or bowel change?")

    if not sections.get("urine"):
        questions.append("Any urinary symptoms?")

    if not sections.get("mentals"):
        questions.append("Any fears, anxiety, grief, anger, or emotional causation?")

    if not sections.get("past_history"):
        questions.append("Any important past medical history?")

    if not sections.get("family_history"):
        questions.append("Any important family history?")

    return questions


def detect_red_flags(text: str) -> List[str]:
    red_flags: List[str] = []

    if contains_any(text, ["chest pain", "severe chest", "heart attack"]):
        red_flags.append("Chest pain may require urgent medical evaluation.")

    if contains_any(text, ["breathing difficulty", "shortness of breath", "severe breathlessness"]):
        red_flags.append("Severe breathing difficulty may require urgent medical evaluation.")

    if contains_any(text, ["unconscious", "fainting", "loss of consciousness"]):
        red_flags.append("Unconsciousness or fainting needs urgent medical evaluation.")

    if contains_any(text, ["suicidal", "self harm", "self-harm"]):
        red_flags.append("Suicidal or self-harm thoughts require urgent mental health support.")

    if contains_any(text, ["breast discharge", "nipple discharge", "breast lump", "bloody discharge"]):
        red_flags.append("Breast discharge or lump should be medically evaluated, especially if persistent, bloody, or unilateral.")

    if contains_any(text, ["rapid weight loss", "unexplained weight loss"]):
        red_flags.append("Unexplained rapid weight loss should be medically evaluated.")

    return red_flags


    def structure_case(payload: CaseStructureRequest) -> CaseStructureData:
        raw_text = str(payload.raw_text or "")
        text = raw_text.lower()

        existing_sections = payload.existing_case_sections

    if not isinstance(existing_sections, dict):
        existing_sections = {}

    sections = {
        key: str(existing_sections.get(key, "") or "")
        for key in CASE_SECTION_KEYS
    }

    chief_complaint = payload.chief_complaint or ""

    if not chief_complaint and raw_text.strip():
        chief_complaint = raw_text.strip().split(".")[0][:500]

    if contains_any(text, ["fear of cancer", "cancer fear", "afraid of cancer"]):
        sections["mentals"] = append_text(sections["mentals"], "Fear of cancer")

    if contains_any(text, ["anxiety", "fear", "worry", "restless"]):
        sections["mentals"] = append_text(sections["mentals"], "Anxiety/fear tendency mentioned")

    if contains_any(text, ["chilly", "cold patient", "cold aggravates", "feels cold"]):
        sections["thermal_state"] = append_text(sections["thermal_state"], "Chilly patient")

    if contains_any(text, ["hot patient", "heat aggravates", "feels hot", "hot body"]):
        sections["thermal_state"] = append_text(sections["thermal_state"], "Hot patient / heat tendency")

    if contains_any(text, ["low thirst", "no thirst", "thirstless", "less thirst"]):
        sections["thirst"] = append_text(sections["thirst"], "Low thirst / thirstless tendency")

    if contains_any(text, ["thirsty", "much thirst", "high thirst"]):
        sections["thirst"] = append_text(sections["thirst"], "Increased thirst")

    if contains_any(text, ["weight gain", "obesity", "overweight", "gaining weight"]):
        sections["generals"] = append_text(sections["generals"], "Weight gain tendency")

    if contains_any(text, ["weakness", "tired", "fatigue", "easily tired"]):
        sections["generals"] = append_text(sections["generals"], "Weakness / fatigue")

    if contains_any(text, ["likes sweets", "desire sweets", "sweet desire", "sweets"]):
        sections["food_desires"] = append_text(sections["food_desires"], "Desire for sweets")

    if contains_any(text, ["spicy dislike", "dislikes spicy", "spicy aggravates", "cannot tolerate spicy"]):
        sections["food_aversions"] = append_text(sections["food_aversions"], "Aversion/intolerance to spicy food")

    if contains_any(text, ["cracked fingers", "finger cracks", "cracks in fingers"]):
        sections["location"] = append_text(sections["location"], "Fingers")
        sections["sensation"] = append_text(sections["sensation"], "Cracks in fingers")

    if contains_any(text, ["winter", "in winter", "cold weather"]):
        sections["modalities"] = append_text(sections["modalities"], "Worse in winter/cold weather")

    if contains_any(text, ["breast discharge", "nipple discharge", "left breast discharge", "yellow discharge"]):
        sections["location"] = append_text(sections["location"], "Breast")
        sections["sensation"] = append_text(sections["sensation"], "Breast/nipple discharge mentioned")

    if contains_any(text, ["sleepy", "sleep more", "excess sleep", "sleep much"]):
        sections["sleep"] = append_text(sections["sleep"], "Sleepiness / increased sleep")

    if contains_any(text, ["dreams of work", "daily work dreams", "work dreams"]):
        sections["dreams"] = append_text(sections["dreams"], "Dreams of daily work")

    missing_questions = build_missing_questions(sections, chief_complaint)
    red_flags = detect_red_flags(text)

    return CaseStructureData(
        chief_complaint=chief_complaint or None,
        case_sections=sections,
        missing_questions=missing_questions,
        red_flags=red_flags,
        confidence="draft",
        engine="local_case_structurer_v1",
    )

def structure_case(payload: CaseStructureRequest) -> CaseStructureData:
    raw_text = str(payload.raw_text or "")
    text = raw_text.lower()

    existing_sections = payload.existing_case_sections

    if not isinstance(existing_sections, dict):
        existing_sections = {}

    sections = {
        key: str(existing_sections.get(key, "") or "")
        for key in CASE_SECTION_KEYS
    }

    chief_complaint = payload.chief_complaint or ""

    if not chief_complaint and raw_text.strip():
        chief_complaint = raw_text.strip().split(".")[0][:500]

    if contains_any(text, ["fear of cancer", "cancer fear", "afraid of cancer"]):
        sections["mentals"] = append_text(sections["mentals"], "Fear of cancer")

    if contains_any(text, ["anxiety", "fear", "worry", "restless"]):
        sections["mentals"] = append_text(sections["mentals"], "Anxiety/fear tendency mentioned")

    if contains_any(text, ["chilly", "cold patient", "cold aggravates", "feels cold"]):
        sections["thermal_state"] = append_text(sections["thermal_state"], "Chilly patient")

    if contains_any(text, ["low thirst", "no thirst", "thirstless", "less thirst"]):
        sections["thirst"] = append_text(sections["thirst"], "Low thirst / thirstless tendency")

    if contains_any(text, ["weight gain", "obesity", "overweight", "gaining weight"]):
        sections["generals"] = append_text(sections["generals"], "Weight gain tendency")

    if contains_any(text, ["likes sweets", "desire sweets", "sweet desire", "sweets"]):
        sections["food_desires"] = append_text(sections["food_desires"], "Desire for sweets")

    if contains_any(text, ["cracked fingers", "finger cracks", "cracks in fingers"]):
        sections["location"] = append_text(sections["location"], "Fingers")
        sections["sensation"] = append_text(sections["sensation"], "Cracks in fingers")

    if contains_any(text, ["winter", "in winter", "cold weather"]):
        sections["modalities"] = append_text(sections["modalities"], "Worse in winter/cold weather")

    if contains_any(text, ["breast discharge", "nipple discharge", "left breast discharge", "yellow discharge"]):
        sections["location"] = append_text(sections["location"], "Breast")
        sections["sensation"] = append_text(sections["sensation"], "Breast/nipple discharge mentioned")

    if contains_any(text, ["sleepy", "sleep more", "excess sleep", "sleep much"]):
        sections["sleep"] = append_text(sections["sleep"], "Sleepiness / increased sleep")

    if contains_any(text, ["dreams of work", "daily work dreams", "work dreams"]):
        sections["dreams"] = append_text(sections["dreams"], "Dreams of daily work")

    missing_questions = build_missing_questions(sections, chief_complaint)
    red_flags = detect_red_flags(text)

    return CaseStructureData(
        chief_complaint=chief_complaint or None,
        case_sections=sections,
        missing_questions=missing_questions,
        red_flags=red_flags,
        confidence="draft",
        engine="local_case_structurer_v1",
    )    


@app.get("/health")
def health():
    return {"status": "ok", "service": "ai-service"}


@app.post("/case/structure")
def structure_case_endpoint(payload: CaseStructureRequest):
    return {"data": structure_case(payload)}