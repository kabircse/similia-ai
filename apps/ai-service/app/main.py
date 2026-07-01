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


class FollowUpAnalyzeRequest(BaseModel):
    previous_visit: Dict[str, Any] = Field(default_factory=dict)
    current_visit: Dict[str, Any] = Field(default_factory=dict)
    prescription: Dict[str, Any] = Field(default_factory=dict)
    timeline_context: List[Dict[str, Any]] = Field(default_factory=list)


class FollowUpProgressItemModel(BaseModel):
    category: Optional[str] = None
    symptom: str
    change_status: str = "unchanged"
    previous_intensity: Optional[int] = None
    current_intensity: Optional[int] = None
    change_score: float = 0
    evidence: Optional[str] = None
    metadata: Dict[str, Any] = Field(default_factory=dict)


class FollowUpAnalyzeResponse(BaseModel):
    response_level: str = "unclear"
    progress_score: float = 0

    analysis_summary: str
    remedy_response_assessment: str

    improvement_points: List[str] = Field(default_factory=list)
    worsening_points: List[str] = Field(default_factory=list)
    unchanged_points: List[str] = Field(default_factory=list)
    new_symptoms: List[str] = Field(default_factory=list)
    old_symptoms_returned: List[str] = Field(default_factory=list)
    possible_aggravation_signs: List[str] = Field(default_factory=list)
    red_flags: List[str] = Field(default_factory=list)

    suggested_follow_up_questions: List[str] = Field(default_factory=list)
    doctor_review_points: List[str] = Field(default_factory=list)
    recommended_next_steps: List[str] = Field(default_factory=list)

    progress_items: List[FollowUpProgressItemModel] = Field(default_factory=list)

    safety_note: str


class PotencyGuidanceRequest(BaseModel):
    case_snapshot: Any = Field(default_factory=dict)
    prescription_snapshot: Any = Field(default_factory=dict)
    follow_up_snapshot: Any = Field(default_factory=dict)
    remedy: Any = Field(default_factory=dict)
    settings: Dict[str, Any] = Field(default_factory=dict)
    knowledge_chunks: List[Dict[str, Any]] = Field(default_factory=list)


class PotencyGuidanceOptionModel(BaseModel):
    potency_range: str
    potency_label: Optional[str] = None
    rank: int = 1
    suitability_score: float = 0
    rationale: Optional[str] = None
    repetition_note: Optional[str] = None
    caution: Optional[str] = None
    source_chunks: List[Dict[str, Any]] = Field(default_factory=list)
    metadata: Dict[str, Any] = Field(default_factory=dict)


class PotencyGuidanceResponse(BaseModel):
    case_phase: str = "unclear"
    vitality_level: str = "unclear"
    sensitivity_level: str = "unclear"
    pathology_depth: str = "unclear"

    guidance_summary: str
    repetition_guidance: str
    wait_and_watch_guidance: str
    aggravation_guidance: str

    cautions: List[str] = Field(default_factory=list)
    follow_up_questions: List[str] = Field(default_factory=list)
    doctor_review_points: List[str] = Field(default_factory=list)

    options: List[PotencyGuidanceOptionModel] = Field(default_factory=list)

    safety_note: str


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


def _stringify_case_value(value: Any) -> str:
    if value is None:
        return ""

    if isinstance(value, list):
        return ", ".join([_stringify_case_value(item) for item in value if item is not None])

    if isinstance(value, dict):
        return "; ".join(
            [
                f"{key}: {_stringify_case_value(item)}"
                for key, item in value.items()
                if item is not None
            ]
        )

    return str(value)


def _text_from_visit(visit: Dict[str, Any]) -> str:
    parts: List[str] = []

    for key in ["chief_complaint", "raw_case_text", "doctor_notes"]:
        value = visit.get(key)
        if value:
            parts.append(str(value))

    case_sections = visit.get("case_sections") or {}

    if isinstance(case_sections, dict):
        for key, value in case_sections.items():
            text = _stringify_case_value(value)
            if text:
                parts.append(f"{key}: {text}")

    return "\n".join(parts).lower()


def _followup_contains_any(text: str, words: List[str]) -> bool:
    return any(word.lower() in text for word in words)


def _contains_non_negated(text: str, words: List[str]) -> bool:
    for word in words:
        word = word.lower()
        if word not in text:
            continue

        negated_patterns = [
            f"no {word}",
            f"without {word}",
            f"denies {word}",
            f"not {word}",
            f"কোনো {word} নেই",
            f"{word} নেই",
        ]

        if any(pattern in text for pattern in negated_patterns):
            continue

        return True

    return False


def _extract_progress_points(current_text: str) -> tuple[List[str], List[str], List[str], List[str]]:
    improvement: List[str] = []
    worsening: List[str] = []
    unchanged: List[str] = []
    new_symptoms: List[str] = []

    improved_words = ["better", "improved", "relief", "less", "reduced", "ভালো", "কমেছে", "আরাম"]
    worse_words = ["worse", "increased", "aggravated", "more", "severe", "বেড়েছে", "বাড়ে", "খারাপ"]
    unchanged_words = ["same", "unchanged", "no change", "একই", "পরিবর্তন নেই"]
    new_words = ["new", "started", "now", "নতুন", "শুরু", "এখন"]

    sentences = [
        sentence.strip()
        for sentence in current_text.replace("\n", ". ").split(".")
        if sentence.strip()
    ]

    for sentence in sentences:
        lower = sentence.lower()

        if _followup_contains_any(lower, improved_words):
            improvement.append(sentence)

        if _followup_contains_any(lower, worse_words):
            worsening.append(sentence)

        if _followup_contains_any(lower, unchanged_words):
            unchanged.append(sentence)

        if _followup_contains_any(lower, new_words):
            new_symptoms.append(sentence)

    return improvement[:8], worsening[:8], unchanged[:8], new_symptoms[:8]


def _red_flags_from_text(text: str) -> List[str]:
    flags: List[str] = []

    checks = {
        "Chest pain or cardiac warning": ["chest pain", "বুকে ব্যথা"],
        "Breathing difficulty": ["breathing difficulty", "shortness of breath", "শ্বাসকষ্ট"],
        "Unconsciousness or severe weakness": ["unconscious", "অজ্ঞান", "severe weakness"],
        "Bleeding": ["bleeding", "blood", "রক্তপাত", "রক্ত"],
        "Rapid weight loss": ["rapid weight loss", "ওজন দ্রুত কমছে"],
        "Breast lump/discharge warning": ["breast lump", "breast discharge", "স্তনে", "স্রাব"],
        "Suicidal thought warning": ["suicidal", "kill myself", "আত্মহত্যা"],
    }

    for label, words in checks.items():
        if _contains_non_negated(text, words):
            flags.append(label)

    return flags


def _make_progress_items(
    improvement: List[str],
    worsening: List[str],
    unchanged: List[str],
    new_symptoms: List[str],
) -> List[FollowUpProgressItemModel]:
    items: List[FollowUpProgressItemModel] = []

    for point in improvement:
        items.append(
            FollowUpProgressItemModel(
                category="reported_change",
                symptom=point[:180],
                change_status="improved",
                change_score=20,
                evidence=point,
            )
        )

    for point in worsening:
        items.append(
            FollowUpProgressItemModel(
                category="reported_change",
                symptom=point[:180],
                change_status="worse",
                change_score=-20,
                evidence=point,
            )
        )

    for point in unchanged:
        items.append(
            FollowUpProgressItemModel(
                category="reported_change",
                symptom=point[:180],
                change_status="unchanged",
                change_score=0,
                evidence=point,
            )
        )

    for point in new_symptoms:
        items.append(
            FollowUpProgressItemModel(
                category="new_symptom",
                symptom=point[:180],
                change_status="new",
                change_score=-10,
                evidence=point,
            )
        )

    return items[:20]


def _response_level(
    score: float,
    improvement: List[str],
    worsening: List[str],
    new_symptoms: List[str],
) -> str:
    if score >= 30 and not worsening:
        return "improved"

    if score >= 10 and worsening:
        return "mixed"

    if score <= -30:
        return "worse"

    if new_symptoms and improvement:
        return "mixed"

    if new_symptoms and not improvement:
        return "new_symptoms"

    if -10 <= score <= 10:
        return "same"

    return "unclear"


def _dict_or_empty(value: Any) -> Dict[str, Any]:
    if isinstance(value, dict):
        return value

    return {}


def _potency_text_from_case(payload: PotencyGuidanceRequest) -> str:
    parts: List[str] = []

    for source in [
        _dict_or_empty(payload.case_snapshot),
        _dict_or_empty(payload.prescription_snapshot),
        _dict_or_empty(payload.follow_up_snapshot),
    ]:
        for key in [
            "chief_complaint",
            "raw_case_text",
            "doctor_notes",
            "analysis_summary",
            "remedy_response_assessment",
            "repetition",
            "dose_instruction",
            "reason",
        ]:
            value = source.get(key)
            if value:
                parts.append(str(value))

        case_sections = source.get("case_sections") or {}
        if isinstance(case_sections, dict):
            for key, value in case_sections.items():
                text = _stringify_case_value(value)
                if text:
                    parts.append(f"{key}: {text}")

        for list_key in [
            "improvement_points",
            "worsening_points",
            "new_symptoms",
            "possible_aggravation_signs",
            "red_flags",
        ]:
            value = source.get(list_key)
            if isinstance(value, list):
                parts.append(", ".join([str(item) for item in value if item]))

    return "\n".join(parts).lower()


def _infer_case_phase(text: str, settings: Dict[str, Any]) -> str:
    requested = settings.get("case_phase")
    if requested and requested != "unclear":
        return str(requested)

    if _followup_contains_any(
        text,
        [
            "acute",
            "sudden",
            "fever",
            "diarrhoea",
            "diarrhea",
            "vomiting",
            "জ্বর",
            "হঠাৎ",
            "বমি",
            "পাতলা পায়খানা",
        ],
    ):
        return "acute"

    if _followup_contains_any(
        text,
        [
            "follow-up",
            "follow up",
            "after medicine",
            "after remedy",
            "improved",
            "worse",
            "follow_up",
        ],
    ):
        return "follow_up"

    if _followup_contains_any(
        text,
        ["chronic", "long time", "years", "constitutional", "বছর", "অনেকদিন", "ক্রনিক"],
    ):
        return "chronic"

    return "constitutional"


def _infer_sensitivity(text: str, settings: Dict[str, Any]) -> str:
    requested = settings.get("patient_sensitivity") or settings.get("sensitivity_level")
    if requested and requested != "unclear":
        return str(requested)

    high_words = [
        "sensitive",
        "reacts strongly",
        "medicine aggravates",
        "allergy",
        "very weak",
        "অল্পতেই",
        "সহ্য হয় না",
        "খুব সংবেদনশীল",
    ]
    low_words = [
        "robust",
        "strong vitality",
        "less sensitive",
        "সহজে প্রতিক্রিয়া হয় না",
    ]

    if _followup_contains_any(text, high_words):
        return "high"

    if _followup_contains_any(text, low_words):
        return "low"

    return "moderate"


def _infer_vitality(text: str, settings: Dict[str, Any]) -> str:
    requested = settings.get("vitality_level")
    if requested and requested != "unclear":
        return str(requested)

    low_words = [
        "very weak",
        "cachexia",
        "bedridden",
        "advanced",
        "severe pathology",
        "খুব দুর্বল",
        "শয্যাশায়ী",
    ]
    high_words = [
        "strong",
        "active",
        "good energy",
        "high vitality",
        "শক্তি ভালো",
    ]

    if _followup_contains_any(text, low_words):
        return "low"

    if _followup_contains_any(text, high_words):
        return "high"

    return "moderate"


def _infer_pathology_depth(text: str, settings: Dict[str, Any]) -> str:
    requested = settings.get("pathology_depth")
    if requested and requested != "unclear":
        return str(requested)

    advanced_words = [
        "cancer",
        "renal failure",
        "heart failure",
        "advanced pathology",
        "severe structural",
        "malignancy",
        "ক্যান্সার",
    ]
    structural_words = [
        "stone",
        "tumor",
        "tumour",
        "fibroid",
        "cyst",
        "ulcer",
        "structural",
        "পাথর",
        "টিউমার",
        "আলসার",
    ]

    if _followup_contains_any(text, advanced_words):
        return "advanced_pathology"

    if _followup_contains_any(text, structural_words):
        return "structural"

    return "functional"


def _source_notes(
    chunks: List[Dict[str, Any]],
    source_types: List[str],
    limit: int = 6,
) -> List[Dict[str, Any]]:
    output: List[Dict[str, Any]] = []

    for chunk in chunks:
        if chunk.get("source_type") not in source_types:
            continue

        output.append(
            {
                "id": chunk.get("id"),
                "source_type": chunk.get("source_type"),
                "source_title": chunk.get("source_title"),
                "author": chunk.get("author"),
                "title": chunk.get("title"),
                "source_ref": chunk.get("source_ref"),
                "content": _short_text(str(chunk.get("content") or ""), 420),
                "distance": chunk.get("distance"),
            }
        )

        if len(output) >= limit:
            break

    return output


def _potency_options(
    phase: str,
    vitality: str,
    sensitivity: str,
    pathology: str,
    chunks: List[Dict[str, Any]],
) -> List[PotencyGuidanceOptionModel]:
    source_notes = _source_notes(chunks, ["potency", "organon", "philosophy"], 5)

    options: List[PotencyGuidanceOptionModel] = []

    if sensitivity == "high" or vitality == "low" or pathology == "advanced_pathology":
        options.append(
            PotencyGuidanceOptionModel(
                potency_range="low",
                potency_label="Low potency consideration",
                rank=1,
                suitability_score=78,
                rationale="High sensitivity, low vitality, or advanced pathology suggests cautious potency consideration.",
                repetition_note="Avoid automatic repetition. Observe response carefully and repeat only if clinically justified.",
                caution="High sensitivity or deep pathology may aggravate easily; practitioner judgment required.",
                source_chunks=source_notes,
            )
        )
        options.append(
            PotencyGuidanceOptionModel(
                potency_range="wait",
                potency_label="Wait and watch",
                rank=2,
                suitability_score=70,
                rationale="If there is ongoing improvement or sensitivity is high, observation may be safer than repetition.",
                repetition_note="Wait while improvement continues.",
                caution="Do not wait if urgent red flags or clinical deterioration appear.",
                source_chunks=source_notes,
            )
        )

        return options

    if phase == "acute":
        options.append(
            PotencyGuidanceOptionModel(
                potency_range="medium",
                potency_label="Medium potency consideration",
                rank=1,
                suitability_score=74,
                rationale="Acute cases may require close observation and potency choice based on intensity, vitality, and clarity of remedy picture.",
                repetition_note="Repetition depends on pace of acute symptoms and response after each dose.",
                caution="Urgent acute danger signs require medical evaluation.",
                source_chunks=source_notes,
            )
        )
        options.append(
            PotencyGuidanceOptionModel(
                potency_range="low",
                potency_label="Low potency consideration",
                rank=2,
                suitability_score=62,
                rationale="Lower potency may be considered when the case is unclear, sensitivity is uncertain, or pathology risk exists.",
                repetition_note="Repeat only after reassessing response.",
                caution="Avoid mechanical frequent repetition.",
                source_chunks=source_notes,
            )
        )

        return options

    if phase in ["chronic", "constitutional", "follow_up"]:
        options.append(
            PotencyGuidanceOptionModel(
                potency_range="medium",
                potency_label="30C / 200C consideration",
                rank=1,
                suitability_score=72,
                rationale="For chronic or constitutional work, potency depends on vitality, sensitivity, pathology depth, and certainty of remedy selection.",
                repetition_note="Single dose and wait-and-watch may be appropriate when response is clear and ongoing.",
                caution="Do not repeat while improvement continues.",
                source_chunks=source_notes,
            )
        )
        options.append(
            PotencyGuidanceOptionModel(
                potency_range="high",
                potency_label="High potency consideration",
                rank=2,
                suitability_score=58,
                rationale="Higher potency may require strong vitality, clear remedy similarity, and careful follow-up.",
                repetition_note="Usually avoid frequent repetition unless practitioner has clear indication.",
                caution="Not suitable for every chronic case; sensitivity and pathology depth must be reviewed.",
                source_chunks=source_notes,
            )
        )
        options.append(
            PotencyGuidanceOptionModel(
                potency_range="wait",
                potency_label="Wait and watch",
                rank=3,
                suitability_score=68,
                rationale="If follow-up shows sustained improvement, waiting may be preferable to repetition.",
                repetition_note="Wait while improvement continues; reassess if improvement stops or old symptoms return.",
                caution="Do not ignore red flags or serious deterioration.",
                source_chunks=source_notes,
            )
        )

        return options

    return [
        PotencyGuidanceOptionModel(
            potency_range="unclear",
            potency_label="Potency unclear",
            rank=1,
            suitability_score=40,
            rationale="Case phase, vitality, sensitivity, and pathology depth are not clear enough.",
            repetition_note="Ask more questions before deciding potency or repetition.",
            caution="Do not prescribe mechanically.",
            source_chunks=source_notes,
        )
    ]


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


@app.post("/follow-up/analyze", response_model=FollowUpAnalyzeResponse)
def analyze_follow_up(payload: FollowUpAnalyzeRequest) -> FollowUpAnalyzeResponse:
    previous_text = _text_from_visit(payload.previous_visit)
    current_text = _text_from_visit(payload.current_visit)
    improvement, worsening, unchanged, new_symptoms = _extract_progress_points(current_text)
    red_flags = _red_flags_from_text(current_text)
    progress_items = _make_progress_items(
        improvement=improvement,
        worsening=worsening,
        unchanged=unchanged,
        new_symptoms=new_symptoms,
    )

    progress_score = sum(float(item.change_score or 0) for item in progress_items)

    if red_flags:
        progress_score -= 15

    progress_score = max(-100, min(100, progress_score))
    response_level = _response_level(
        score=progress_score,
        improvement=improvement,
        worsening=worsening,
        new_symptoms=new_symptoms,
    )

    prescription = payload.prescription or {}
    remedy_name = prescription.get("remedy_name") or "the prescribed remedy"
    potency = prescription.get("potency") or ""
    remedy_label = f"{remedy_name} {potency}".strip()

    old_symptoms_returned: List[str] = []
    if previous_text and current_text and _followup_contains_any(
        current_text,
        ["old symptom", "old symptoms", "পুরনো লক্ষণ", "পুরাতন লক্ষণ"],
    ):
        old_symptoms_returned.append(
            "Patient reports return of old symptoms; review direction of cure carefully."
        )

    possible_aggravation_signs: List[str] = []
    if _followup_contains_any(
        current_text,
        [
            "aggravation",
            "homeopathic aggravation",
            "first worse",
            "first two days",
            "initially worse",
            "প্রথমে বেড়েছে",
            "প্রথমে বেড়েছে",
            "শুরুতে বেড়েছে",
            "শুরুতে বেড়েছে",
        ],
    ):
        possible_aggravation_signs.append(
            "Possible aggravation reported; confirm timing, intensity, duration, and general wellbeing."
        )

    analysis_summary = (
        f"Follow-up response appears {response_level}. "
        f"Reported improvements: {len(improvement)}, worsening points: {len(worsening)}, "
        f"new symptoms: {len(new_symptoms)}, red flags: {len(red_flags)}."
    )

    remedy_response_assessment = (
        f"Response after {remedy_label} is assessed as {response_level}. "
        "This is only a clinical progress summary and not a prescription decision."
    )

    suggested_follow_up_questions = [
        "What changed first after the prescription, and when did it happen?",
        "How are energy, sleep, appetite, thirst, stool, and mood compared with the previous visit?",
        "Did any old symptom return, and was it milder, shorter, or in reverse order?",
        "Did any new symptom appear after the remedy, and how intense is it?",
        "Was there any initial aggravation before improvement?",
    ]

    if red_flags:
        suggested_follow_up_questions.insert(
            0,
            "Are the reported warning symptoms active now, severe, recurrent, or medically evaluated?",
        )

    doctor_review_points = [
        "Compare generals and mental state before deciding whether to wait, repeat, or change plan.",
        "Confirm whether changes are sustained or only temporary.",
        "Review new symptoms separately from expected return of old symptoms.",
    ]

    recommended_next_steps = [
        "Document intensity and duration for each changed symptom.",
        "Use the symptom change matrix as supporting notes, not as an automatic prescription rule.",
        "Practitioner should decide wait, repeat, potency change, remedy change, or referral.",
    ]

    if red_flags:
        recommended_next_steps.insert(
            0,
            "Red flags detected: consider medical evaluation or referral where appropriate.",
        )

    safety_note = (
        "This follow-up analysis is doctor-facing decision support only. It does not decide "
        "repetition, potency, remedy change, or referral. Final decision must be made by the practitioner."
    )

    return FollowUpAnalyzeResponse(
        response_level=response_level,
        progress_score=round(progress_score, 2),
        analysis_summary=analysis_summary,
        remedy_response_assessment=remedy_response_assessment,
        improvement_points=improvement,
        worsening_points=worsening,
        unchanged_points=unchanged,
        new_symptoms=new_symptoms,
        old_symptoms_returned=old_symptoms_returned,
        possible_aggravation_signs=possible_aggravation_signs,
        red_flags=red_flags,
        suggested_follow_up_questions=suggested_follow_up_questions,
        doctor_review_points=doctor_review_points,
        recommended_next_steps=recommended_next_steps,
        progress_items=progress_items,
        safety_note=safety_note,
    )


@app.post("/potency/guidance", response_model=PotencyGuidanceResponse)
def potency_guidance(payload: PotencyGuidanceRequest) -> PotencyGuidanceResponse:
    text = _potency_text_from_case(payload)
    remedy = _dict_or_empty(payload.remedy)
    prescription_snapshot = _dict_or_empty(payload.prescription_snapshot)

    phase = _infer_case_phase(text, payload.settings)
    sensitivity = _infer_sensitivity(text, payload.settings)
    vitality = _infer_vitality(text, payload.settings)
    pathology = _infer_pathology_depth(text, payload.settings)

    remedy_name = (
        remedy.get("remedy_name")
        or prescription_snapshot.get("remedy_name")
        or "selected remedy"
    )

    options = _potency_options(
        phase=phase,
        vitality=vitality,
        sensitivity=sensitivity,
        pathology=pathology,
        chunks=payload.knowledge_chunks,
    )

    cautions: List[str] = [
        "Final potency and repetition must be decided by the practitioner.",
        "Do not repeat automatically while improvement is continuing.",
        "Review sensitivity, vitality, pathology depth, and remedy certainty before potency selection.",
    ]

    if sensitivity == "high":
        cautions.append("High sensitivity suspected: consider extra caution with potency and repetition.")

    if vitality == "low":
        cautions.append("Low vitality suspected: avoid aggressive repetition without close review.")

    if pathology in ["structural", "advanced_pathology"]:
        cautions.append(
            "Structural or advanced pathology suspected: coordinate medical evaluation where appropriate."
        )

    if phase == "follow_up":
        cautions.append(
            "Follow-up case: assess direction of cure, aggravation, old symptom return, and general wellbeing before repeating."
        )

    guidance_summary = (
        f"Potency guidance for {remedy_name}: case phase appears {phase}, "
        f"vitality appears {vitality}, sensitivity appears {sensitivity}, "
        f"and pathology depth appears {pathology}. These are decision-support indicators only."
    )

    repetition_guidance = (
        "Repetition should be based on the patient response, not a fixed routine. "
        "If improvement is clear and continuing, wait-and-watch is usually safer than mechanical repetition."
    )

    wait_guidance = (
        "Wait-and-watch is especially important when there is steady improvement, high sensitivity, "
        "recent aggravation followed by improvement, or unclear need for repetition."
    )

    aggravation_guidance = (
        "If aggravation is reported, confirm timing, intensity, duration, general wellbeing, "
        "return of old symptoms, and whether the aggravation is followed by improvement."
    )

    follow_up_questions = [
        "What changed first after the dose?",
        "Is improvement still continuing?",
        "Was there any initial aggravation? How long did it last?",
        "Did energy, sleep, appetite, mood, and generals improve?",
        "Did any old symptom return?",
        "Are there any new symptoms after the remedy?",
        "Was any other medicine or treatment used?",
        "Any red-flag symptom or clinical deterioration?",
    ]

    review_points = [
        "Confirm remedy similarity before considering potency escalation.",
        "Review patient sensitivity and vitality.",
        "Review pathology depth and risk.",
        "Review whether repetition is needed at all.",
        "Document why potency and repetition were chosen.",
    ]

    safety_note = (
        "This is doctor-facing potency guidance only. It is not an automatic potency prescription. "
        "Final potency, repetition, wait-and-watch, remedy change, and referral decisions must be made by the practitioner."
    )

    return PotencyGuidanceResponse(
        case_phase=phase,
        vitality_level=vitality,
        sensitivity_level=sensitivity,
        pathology_depth=pathology,
        guidance_summary=guidance_summary,
        repetition_guidance=repetition_guidance,
        wait_and_watch_guidance=wait_guidance,
        aggravation_guidance=aggravation_guidance,
        cautions=cautions,
        follow_up_questions=follow_up_questions,
        doctor_review_points=review_points,
        options=options,
        safety_note=safety_note,
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
