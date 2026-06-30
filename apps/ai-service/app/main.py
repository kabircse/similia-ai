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


class RemedyCandidate(BaseModel):
    remedy_code: str
    remedy_name: str
    rank: int
    total_score: int
    rubric_coverage: int
    essential_coverage: int


class KnowledgeChunk(BaseModel):
    remedy_code: str
    remedy_name: str
    section: Optional[str] = None
    content: str
    source_title: Optional[str] = None
    distance: Optional[float] = None


class MateriaMedicaCompareRequest(BaseModel):
    case_summary: str
    candidates: List[RemedyCandidate]
    chunks: List[KnowledgeChunk]


class RemedyComparisonItem(BaseModel):
    remedy_code: str
    remedy_name: str
    rank: int
    total_score: int
    matching_points: List[str]
    differentiating_points: List[str]
    missing_questions: List[str]
    source_chunks: List[Dict[str, Any]]


class MateriaMedicaCompareResponse(BaseModel):
    summary: str
    remedies: List[RemedyComparisonItem]
    safety_note: str
    engine: str


class RemedySuggestRequest(BaseModel):
    repertorization_run: Dict[str, Any] = Field(default_factory=dict)
    case_snapshot: Dict[str, Any] = Field(default_factory=dict)
    selected_rubrics: List[Dict[str, Any]] = Field(default_factory=list)
    candidates: List[Dict[str, Any]] = Field(default_factory=list)
    knowledge_chunks: List[Dict[str, Any]] = Field(default_factory=list)
    retrieved_sources: Dict[str, Any] = Field(default_factory=dict)


class RemedySuggestItem(BaseModel):
    remedy_code: Optional[str] = None
    remedy_name: str
    rank: int
    confidence_score: float = 0
    repertory_score: float = 0
    materia_medica_score: float = 0
    knowledge_score: float = 0
    summary: str
    matching_points: List[str] = Field(default_factory=list)
    differentiating_points: List[str] = Field(default_factory=list)
    missing_questions: List[str] = Field(default_factory=list)
    evidence_matrix: List[Dict[str, Any]] = Field(default_factory=list)
    repertory_evidence: Dict[str, Any] = Field(default_factory=dict)
    materia_medica_evidence: List[Dict[str, Any]] = Field(default_factory=list)
    potency_considerations: List[Dict[str, Any]] = Field(default_factory=list)
    relationship_notes: List[Dict[str, Any]] = Field(default_factory=list)
    medical_safety_notes: List[Dict[str, Any]] = Field(default_factory=list)
    source_chunks: List[Dict[str, Any]] = Field(default_factory=list)
    metadata: Dict[str, Any] = Field(default_factory=dict)


class RemedySuggestResponse(BaseModel):
    safety_note: str
    suggestions: List[RemedySuggestItem]
    engine: str


class MissingQuestionConversationStartRequest(BaseModel):
    language: str = "bn-BD"
    max_questions: int = 10
    raw_case_text: Optional[str] = None
    chief_complaint: Optional[str] = None
    case_sections: Dict[str, Any] = Field(default_factory=dict)
    missing_questions: List[str] = Field(default_factory=list)
    red_flags: List[str] = Field(default_factory=list)


class MissingQuestionItem(BaseModel):
    question_key: str
    category: str
    importance: str = "normal"
    question: str


class MissingQuestionConversationStartResponse(BaseModel):
    questions: List[MissingQuestionItem]
    safety_note: str


class MissingQuestionApplyAnswerRequest(BaseModel):
    question_key: Optional[str] = None
    category: Optional[str] = None
    question: str
    answer: str
    existing_case_sections: Dict[str, Any] = Field(default_factory=dict)


class MissingQuestionApplyAnswerResponse(BaseModel):
    case_section_updates: Dict[str, Any] = Field(default_factory=dict)
    raw_case_note: str
    extracted_summary: str


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


def sentence_trim(text: str, max_length: int = 220) -> str:
    text = " ".join(text.split())

    if len(text) <= max_length:
        return text

    return text[:max_length].rstrip() + "..."


def _short_text(text: Optional[str], limit: int = 260) -> str:
    text = (text or "").strip().replace("\n", " ")
    text = " ".join(text.split())

    if len(text) <= limit:
        return text

    return text[: limit - 3].rstrip() + "..."


def _knowledge_by_type(chunks: List[Dict[str, Any]], source_type: str) -> List[Dict[str, Any]]:
    return [chunk for chunk in chunks if chunk.get("source_type") == source_type]


def _extract_matching_points(candidate: Dict[str, Any]) -> List[str]:
    points: List[str] = []
    repertory = candidate.get("repertory_evidence", {}) or {}
    supporting = repertory.get("supporting_rubrics", []) or []

    for rubric in supporting[:6]:
        if isinstance(rubric, dict):
            path = rubric.get("rubric_path") or rubric.get("rubric") or rubric.get("path")
            grade = rubric.get("remedy_grade") or rubric.get("grade")
            if path:
                grade_text = f" grade {grade}" if grade else ""
                points.append(f"Covers rubric: {path}{grade_text}")
        elif isinstance(rubric, str):
            points.append(f"Covers rubric: {rubric}")

    for chunk in (candidate.get("materia_medica_chunks", []) or [])[:4]:
        section = chunk.get("section") or "Materia medica"
        content = _short_text(chunk.get("content"), 180)
        if content:
            points.append(f"{section}: {content}")

    return points[:8]


def _extract_differentiating_points(candidate: Dict[str, Any]) -> List[str]:
    points: List[str] = []
    missing = (
        candidate.get("missing_important_rubrics", [])
        or candidate.get("repertory_evidence", {}).get("missing_important_rubrics", [])
        or []
    )

    for item in missing[:5]:
        if isinstance(item, dict):
            path = item.get("rubric_path") or item.get("rubric") or item.get("path")
            if path:
                points.append(f"Not confirmed or missing: {path}")
        elif isinstance(item, str):
            points.append(f"Not confirmed or missing: {item}")

    if not points:
        points.append(
            "Differentiate with modalities, thermal state, thirst, mental generals, and concomitants."
        )

    return points


def _evidence_matrix(
    selected_rubrics: List[Dict[str, Any]],
    candidate: Dict[str, Any],
) -> List[Dict[str, Any]]:
    supporting = candidate.get("repertory_evidence", {}).get("supporting_rubrics", []) or []
    supporting_text = " ".join(
        [
            str(item.get("rubric_path") or item.get("rubric") or item.get("path") or item)
            if isinstance(item, dict)
            else str(item)
            for item in supporting
        ]
    ).lower()

    matrix = []

    for rubric in selected_rubrics[:12]:
        path = rubric.get("rubric_path") or ""
        matrix.append(
            {
                "rubric_path": path,
                "importance": rubric.get("importance"),
                "weight": rubric.get("weight"),
                "is_essential": rubric.get("is_essential"),
                "covered": path.lower() in supporting_text if path else False,
            }
        )

    return matrix


def _knowledge_notes(
    chunks: List[Dict[str, Any]],
    source_type: str,
    limit: int = 3,
) -> List[Dict[str, Any]]:
    notes = []

    for chunk in _knowledge_by_type(chunks, source_type)[:limit]:
        notes.append(
            {
                "source_title": chunk.get("source_title"),
                "author": chunk.get("author"),
                "title": chunk.get("title"),
                "source_ref": chunk.get("source_ref"),
                "note": _short_text(chunk.get("content"), 320),
            }
        )

    return notes


def _source_chunks(
    candidate: Dict[str, Any],
    knowledge_chunks: List[Dict[str, Any]],
) -> List[Dict[str, Any]]:
    chunks = []

    for chunk in (candidate.get("materia_medica_chunks", []) or [])[:5]:
        chunks.append(
            {
                "type": "materia_medica",
                "id": chunk.get("id"),
                "source_title": chunk.get("source_title"),
                "author": chunk.get("author"),
                "section": chunk.get("section"),
                "content": _short_text(chunk.get("content"), 500),
                "distance": chunk.get("distance"),
            }
        )

    for chunk in knowledge_chunks[:8]:
        chunks.append(
            {
                "type": chunk.get("source_type"),
                "id": chunk.get("id"),
                "source_title": chunk.get("source_title"),
                "author": chunk.get("author"),
                "title": chunk.get("title"),
                "source_ref": chunk.get("source_ref"),
                "content": _short_text(chunk.get("content"), 500),
                "distance": chunk.get("distance"),
            }
        )

    return chunks


def _lang_bn(language: Optional[str]) -> bool:
    return (language or "").lower().startswith("bn")


def _question_key(text: str, index: int) -> str:
    base = "".join(ch.lower() if ch.isalnum() else "_" for ch in text[:40])
    base = "_".join([part for part in base.split("_") if part])
    return f"q_{index + 1}_{base or 'question'}"


def _category_from_question(question: str) -> str:
    q = question.lower()

    if any(word in q for word in ["thermal", "hot", "cold", "chilly", "শীত", "গরম"]):
        return "thermal_state"

    if any(word in q for word in ["thirst", "পিপাসা"]):
        return "thirst"

    if any(word in q for word in ["better", "worse", "aggrav", "amelior", "modalit", "বাড়ে", "কমে"]):
        return "modalities"

    if any(word in q for word in ["fear", "anxiety", "mind", "mental", "ভয়", "মন", "অস্থির"]):
        return "mentals"

    if any(word in q for word in ["dream", "স্বপ্ন"]):
        return "dreams"

    if any(word in q for word in ["sleep", "ঘুম"]):
        return "sleep"

    if any(word in q for word in ["food", "desire", "খাবার", "পছন্দ"]):
        return "food_desires"

    if any(word in q for word in ["aversion", "অপছন্দ"]):
        return "food_aversions"

    if any(word in q for word in ["stool", "পায়খানা"]):
        return "stool"

    if any(word in q for word in ["urine", "প্রস্রাব"]):
        return "urine"

    if any(word in q for word in ["menses", "period", "menstrual", "মাসিক"]):
        return "menses"

    if any(word in q for word in ["breast", "discharge", "স্তন", "স্রাব"]):
        return "female_symptoms"

    return "general_case_detail"


def _default_questions(language: str) -> List[str]:
    if _lang_bn(language):
        return [
            "রোগীর গরমে বেশি কষ্ট হয় নাকি শীতে বেশি কষ্ট হয়?",
            "পিপাসা কেমন—কম, বেশি, নাকি স্বাভাবিক?",
            "কোন জিনিসে উপসর্গ বাড়ে এবং কোন জিনিসে কমে?",
            "রোগীর প্রধান ভয়, দুশ্চিন্তা বা মানসিক পরিবর্তন কী?",
            "ঘুম, স্বপ্ন এবং ঘুম থেকে ওঠার পর অনুভূতি কেমন?",
            "খাবারের পছন্দ-অপছন্দ, মিষ্টি/ঝাল/ডিম/দুধের প্রতি আকর্ষণ আছে কি?",
            "পায়খানা ও প্রস্রাবের কোনো পরিবর্তন আছে কি?",
            "যদি নারী রোগী হন, মাসিক, স্তন বা স্রাব সংক্রান্ত কোনো উপসর্গ আছে কি?",
        ]

    return [
        "Is the patient generally worse from heat or cold?",
        "How is the thirst: low, increased, or normal?",
        "What makes the complaint worse and what makes it better?",
        "What are the main fears, anxieties, or mental changes?",
        "How are sleep, dreams, and waking condition?",
        "Any food desires or aversions such as sweets, spicy food, eggs, milk?",
        "Any change in stool or urine?",
        "For female patients, any menstrual, breast, or discharge symptoms?",
    ]


def _answer_category_update(
    category: str,
    question: str,
    answer: str,
    existing_case_sections: Dict[str, Any],
) -> Dict[str, Any]:
    updates: Dict[str, Any] = {}

    existing_answers = existing_case_sections.get("missing_question_answers") or {}
    if not isinstance(existing_answers, dict):
        existing_answers = {}

    return_updates_key = category if category in CASE_SECTION_KEYS else "reports_note"

    if category == "medical_safety":
        existing_safety = existing_case_sections.get("medical_safety_notes") or []
        if not isinstance(existing_safety, list):
            existing_safety = [str(existing_safety)]

        existing_safety.append(f"{question} Answer: {answer}")
        updates["medical_safety_notes"] = existing_safety
    elif category == "female_symptoms":
        updates["menses"] = append_text(str(existing_case_sections.get("menses", "") or ""), answer)
    elif category == "general_case_detail":
        updates["generals"] = append_text(str(existing_case_sections.get("generals", "") or ""), answer)
    else:
        current = str(existing_case_sections.get(return_updates_key, "") or "")
        updates[return_updates_key] = append_text(current, answer)

    return updates


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


@app.post(
    "/case/missing-question-conversation/start",
    response_model=MissingQuestionConversationStartResponse,
)
def start_missing_question_conversation(
    payload: MissingQuestionConversationStartRequest,
) -> MissingQuestionConversationStartResponse:
    questions: List[MissingQuestionItem] = []
    source_questions = payload.missing_questions or _default_questions(payload.language)

    for index, question in enumerate(source_questions[: payload.max_questions]):
        category = _category_from_question(question)
        questions.append(
            MissingQuestionItem(
                question_key=_question_key(question, index),
                category=category,
                importance=(
                    "important"
                    if category in ["mentals", "modalities", "thermal_state"]
                    else "normal"
                ),
                question=question,
            )
        )

    for red_index, red_flag in enumerate(payload.red_flags[:3]):
        if _lang_bn(payload.language):
            question = (
                f"রেড ফ্ল্যাগ যাচাই: {red_flag} — এ বিষয়ে মেডিক্যাল "
                "মূল্যায়ন/রিপোর্ট/ডাক্তারের পরামর্শ হয়েছে কি?"
            )
        else:
            question = (
                f"Red flag check: {red_flag}. Has this been medically "
                "evaluated or investigated?"
            )

        questions.append(
            MissingQuestionItem(
                question_key=f"red_flag_{red_index + 1}",
                category="medical_safety",
                importance="red_flag",
                question=question,
            )
        )

    safety_note = (
        "This missing-question conversation is for doctor-side case completion only. "
        "It does not prescribe medicine."
    )

    return MissingQuestionConversationStartResponse(
        questions=questions[: payload.max_questions],
        safety_note=safety_note,
    )


@app.post(
    "/case/missing-question-conversation/apply-answer",
    response_model=MissingQuestionApplyAnswerResponse,
)
def apply_missing_question_answer(
    payload: MissingQuestionApplyAnswerRequest,
) -> MissingQuestionApplyAnswerResponse:
    category = payload.category or _category_from_question(payload.question)
    question_key = payload.question_key or "question"
    answer = payload.answer.strip()
    question = payload.question.strip()

    updates = _answer_category_update(
        category=category,
        question=question,
        answer=answer,
        existing_case_sections=payload.existing_case_sections,
    )

    existing_answers = payload.existing_case_sections.get("missing_question_answers") or {}
    if not isinstance(existing_answers, dict):
        existing_answers = {}

    existing_answers[question_key] = {
        "category": category,
        "question": question,
        "answer": answer,
    }
    updates["missing_question_answers"] = existing_answers

    raw_note = f"Q: {question}\nA: {answer}"
    summary = f"Answer saved under {category}: {answer[:220]}"

    return MissingQuestionApplyAnswerResponse(
        case_section_updates=updates,
        raw_case_note=raw_note,
        extracted_summary=summary,
    )


@app.post("/materia-medica/compare")
def compare_materia_medica(payload: MateriaMedicaCompareRequest):
    chunks_by_remedy: Dict[str, List[KnowledgeChunk]] = {}

    for chunk in payload.chunks:
        chunks_by_remedy.setdefault(chunk.remedy_code, []).append(chunk)

    remedy_items: List[RemedyComparisonItem] = []

    for candidate in payload.candidates:
        remedy_chunks = chunks_by_remedy.get(candidate.remedy_code, [])

        matching_points = [
            sentence_trim(chunk.content)
            for chunk in remedy_chunks[:3]
        ]

        differentiating_points = []
        sections = {chunk.section for chunk in remedy_chunks if chunk.section}

        if "mind" in sections:
            differentiating_points.append("Review mental and emotional symptoms carefully.")
        if "generals" in sections:
            differentiating_points.append("Compare general symptoms such as thermal state, thirst, sleep, and energy.")
        if "skin" in sections:
            differentiating_points.append("Confirm the character, location, and modalities of skin symptoms.")
        if "female" in sections or "glands" in sections:
            differentiating_points.append("Confirm breast/gland symptoms and consider medical evaluation for red flags.")

        if not differentiating_points:
            differentiating_points.append("Compare characteristic symptoms with the totality before prescription.")

        missing_questions = [
            "Which symptoms are most characteristic and uncommon in this patient?",
            "What are the strongest modalities: better, worse, time, weather, position?",
            "Are the mental generals and physical generals confirming the same remedy?",
        ]

        source_chunks = [
            {
                "section": chunk.section,
                "source_title": chunk.source_title,
                "content": sentence_trim(chunk.content, 260),
                "distance": chunk.distance,
            }
            for chunk in remedy_chunks[:4]
        ]

        remedy_items.append(
            RemedyComparisonItem(
                remedy_code=candidate.remedy_code,
                remedy_name=candidate.remedy_name,
                rank=candidate.rank,
                total_score=candidate.total_score,
                matching_points=matching_points,
                differentiating_points=differentiating_points,
                missing_questions=missing_questions,
                source_chunks=source_chunks,
            )
        )

    return {
        "data": MateriaMedicaCompareResponse(
            summary="Materia medica comparison generated from retrieved knowledge chunks. Use this as clinical decision support, not as an automatic prescription.",
            remedies=remedy_items,
            safety_note="Final remedy, potency, repetition, and prescription must be decided by the qualified practitioner.",
            engine="local_materia_medica_rag_v1",
        )
    }


@app.post("/remedy/suggest", response_model=RemedySuggestResponse)
def remedy_suggest(payload: RemedySuggestRequest) -> RemedySuggestResponse:
    selected_rubrics = payload.selected_rubrics
    knowledge_chunks = payload.knowledge_chunks
    red_flags = payload.case_snapshot.get("red_flags") or []
    missing_questions = payload.case_snapshot.get("missing_questions") or []
    method = payload.repertorization_run.get("method", "selected")
    suggestions: List[RemedySuggestItem] = []

    for candidate in payload.candidates:
        rank = int(candidate.get("rank") or 1)
        rubric_coverage = float(candidate.get("rubric_coverage") or 0)
        essential_coverage = float(candidate.get("essential_coverage") or 0)
        total_score = float(candidate.get("total_score") or 0)
        mm_chunks = candidate.get("materia_medica_chunks") or []

        repertory_score = min(total_score, 100)
        materia_score = min(len(mm_chunks) * 12, 40)
        knowledge_score = min(len(knowledge_chunks) * 2, 20)
        confidence = min(
            100,
            (rubric_coverage * 12)
            + (essential_coverage * 15)
            + materia_score
            + max(0, 25 - rank * 4),
        )

        remedy_name = candidate.get("remedy_name") or "Unknown remedy"
        remedy_code = candidate.get("remedy_code")
        summary = (
            f"{remedy_name} appears as rank {rank} in the {method} repertorization result. "
            "It should be considered only as a doctor-reviewed possibility, supported by "
            "rubric coverage and retrieved knowledge evidence."
        )

        suggestions.append(
            RemedySuggestItem(
                remedy_code=remedy_code,
                remedy_name=remedy_name,
                rank=rank,
                confidence_score=round(confidence, 2),
                repertory_score=round(repertory_score, 2),
                materia_medica_score=round(materia_score, 2),
                knowledge_score=round(knowledge_score, 2),
                summary=summary,
                matching_points=_extract_matching_points(candidate),
                differentiating_points=_extract_differentiating_points(candidate),
                missing_questions=missing_questions[:8],
                evidence_matrix=_evidence_matrix(selected_rubrics, candidate),
                repertory_evidence=candidate.get("repertory_evidence") or {},
                materia_medica_evidence=[
                    {
                        "source_title": chunk.get("source_title"),
                        "author": chunk.get("author"),
                        "section": chunk.get("section"),
                        "content": _short_text(chunk.get("content"), 320),
                    }
                    for chunk in mm_chunks[:5]
                ],
                potency_considerations=_knowledge_notes(knowledge_chunks, "potency", 4)
                + _knowledge_notes(knowledge_chunks, "organon", 2)
                + _knowledge_notes(knowledge_chunks, "philosophy", 2),
                relationship_notes=_knowledge_notes(knowledge_chunks, "relationship", 4),
                medical_safety_notes=_knowledge_notes(knowledge_chunks, "medical", 4),
                source_chunks=_source_chunks(candidate, knowledge_chunks),
                metadata={
                    "rubric_coverage": rubric_coverage,
                    "essential_coverage": essential_coverage,
                    "total_score": total_score,
                },
            )
        )

    safety_note = (
        "This is a doctor-facing decision-support suggestion. It is not an automatic "
        "prescription. Final remedy, potency, repetition, and follow-up must be decided "
        "by the practitioner."
    )

    if red_flags:
        safety_note += " Red flags detected: " + "; ".join([str(flag) for flag in red_flags[:5]])

    return RemedySuggestResponse(
        safety_note=safety_note,
        suggestions=suggestions,
        engine="local_remedy_suggestion_rag_v1",
    )
