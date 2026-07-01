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
    settings: Dict[str, Any] = Field(default_factory=dict)
    response_language: str = "auto"


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


class RemedyRelationshipRequest(BaseModel):
    primary_remedy: Dict[str, Any] = Field(default_factory=dict)
    comparison_remedy: Dict[str, Any] = Field(default_factory=dict)
    purpose: str = "general"
    case_snapshot: Dict[str, Any] = Field(default_factory=dict)
    prescription_snapshot: Dict[str, Any] = Field(default_factory=dict)
    follow_up_snapshot: Dict[str, Any] = Field(default_factory=dict)
    knowledge_chunks: List[Dict[str, Any]] = Field(default_factory=list)
    response_language: str = "auto"


class RemedyRelationshipFindingModel(BaseModel):
    related_remedy_code: Optional[str] = None
    related_remedy_name: Optional[str] = None
    relationship_type: str = "unknown"
    direction: Optional[str] = None
    rank: int = 1
    confidence_score: float = 0
    summary: Optional[str] = None
    clinical_note: Optional[str] = None
    caution: Optional[str] = None
    evidence: List[str] = Field(default_factory=list)
    source_chunks: List[Dict[str, Any]] = Field(default_factory=list)
    metadata: Dict[str, Any] = Field(default_factory=dict)


class RemedyRelationshipResponse(BaseModel):
    relationship_summary: str
    sequence_guidance: str
    antidote_guidance: str
    inimical_warning: str
    complementary_note: str
    cautions: List[str] = Field(default_factory=list)
    doctor_review_points: List[str] = Field(default_factory=list)
    suggested_questions: List[str] = Field(default_factory=list)
    findings: List[RemedyRelationshipFindingModel] = Field(default_factory=list)
    safety_note: str


class PrescriptionReviewRequest(BaseModel):
    case_snapshot: Dict[str, Any] = Field(default_factory=dict)
    prescription_snapshot: Dict[str, Any] = Field(default_factory=dict)
    remedy_suggestion_snapshot: Dict[str, Any] = Field(default_factory=dict)
    potency_guidance_snapshot: Dict[str, Any] = Field(default_factory=dict)
    relationship_snapshot: Dict[str, Any] = Field(default_factory=dict)
    follow_up_snapshot: Dict[str, Any] = Field(default_factory=dict)
    response_language: str = "auto"


class PrescriptionReviewCheckModel(BaseModel):
    check_key: str
    category: str = "general"
    severity: str = "normal"
    status: str = "pending"
    is_required: bool = True
    is_blocking: bool = False
    title: str
    description: Optional[str] = None
    ai_assessment: Optional[str] = None
    evidence: List[str] = Field(default_factory=list)
    metadata: Dict[str, Any] = Field(default_factory=dict)


class PrescriptionReviewResponse(BaseModel):
    review_status: str = "needs_doctor_review"
    safety_score: float = 0
    review_summary: str
    decision_guidance: str
    risk_summary: str
    red_flags: List[str] = Field(default_factory=list)
    missing_information: List[str] = Field(default_factory=list)
    doctor_review_points: List[str] = Field(default_factory=list)
    recommended_actions: List[str] = Field(default_factory=list)
    checks: List[PrescriptionReviewCheckModel] = Field(default_factory=list)
    safety_note: str


class PatientHandoutRequest(BaseModel):
    case_snapshot: Dict[str, Any] = Field(default_factory=dict)
    prescription_snapshot: Dict[str, Any] = Field(default_factory=dict)
    clinic_snapshot: Dict[str, Any] = Field(default_factory=dict)
    review_snapshot: Dict[str, Any] = Field(default_factory=dict)
    response_language: str = "auto"
    style: str = "simple"
    include_warning_signs: bool = True
    include_do_and_dont: bool = True


class PatientHandoutSectionModel(BaseModel):
    section_key: str
    category: str = "instruction"
    sort_order: int = 1
    title: str
    content: str
    is_important: bool = False
    metadata: Dict[str, Any] = Field(default_factory=dict)


class PatientHandoutResponse(BaseModel):
    title: str
    resolved_language: str
    patient_summary: str
    medicine_instruction: str
    diet_lifestyle_instruction: str
    follow_up_instruction: str
    warning_instruction: str
    warning_signs: List[str] = Field(default_factory=list)
    do_and_dont: List[str] = Field(default_factory=list)
    footer_note: str
    safety_note: str
    sections: List[PatientHandoutSectionModel] = Field(default_factory=list)


class ClinicReportSummaryRequest(BaseModel):
    report_type: str = "monthly"
    period_start: str
    period_end: str
    dashboard_snapshot: Dict[str, Any] = Field(default_factory=dict)
    response_language: str = "auto"
    include_finance: bool = True
    include_safety: bool = True
    include_follow_ups: bool = True
    include_recommendations: bool = True


class ClinicReportSectionModel(BaseModel):
    section_key: str
    category: str = "summary"
    sort_order: int = 1
    title: str
    content: str
    metrics: Dict[str, Any] = Field(default_factory=dict)
    metadata: Dict[str, Any] = Field(default_factory=dict)


class ClinicReportSummaryResponse(BaseModel):
    title: str
    resolved_language: str

    executive_summary: str
    clinical_activity_summary: str
    outcome_summary: str
    remedy_summary: str
    safety_summary: str
    finance_summary: str
    follow_up_summary: str

    key_metrics: Dict[str, Any] = Field(default_factory=dict)
    recommendations: List[str] = Field(default_factory=list)
    limitations: List[str] = Field(default_factory=list)

    safety_note: str

    sections: List[ClinicReportSectionModel] = Field(default_factory=list)


class MissingQuestionConversationStartRequest(BaseModel):
    language: str = "bn-BD"
    response_language: str = "auto"
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
    response_language: str = "auto"
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
    response_language: str = "auto"


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
    response_language: str = "auto"


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


def detect_language_from_text(text: Optional[str]) -> str:
    text = text or ""

    bangla_chars = sum(1 for ch in text if "\u0980" <= ch <= "\u09FF")
    devanagari_chars = sum(1 for ch in text if "\u0900" <= ch <= "\u097F")
    latin_chars = sum(1 for ch in text if "a" <= ch.lower() <= "z")

    if bangla_chars > 5 and bangla_chars >= latin_chars:
        return "bn-BD"

    if devanagari_chars > 5 and devanagari_chars >= latin_chars:
        return "hi-IN"

    return "en-US"


def resolve_response_language(
    requested: Optional[str],
    *texts: Optional[str],
) -> str:
    if requested and requested != "auto":
        return requested

    combined = "\n".join([text or "" for text in texts])

    return detect_language_from_text(combined)


def language_name(language: str) -> str:
    mapping = {
        "bn-BD": "Bangla",
        "en-US": "English",
        "hi-IN": "Hindi",
        "ar": "Arabic",
        "fr": "French",
        "es": "Spanish",
    }

    return mapping.get(language, language)


def requested_response_language(
    top_level: Optional[str],
    settings: Optional[Dict[str, Any]] = None,
) -> str:
    if top_level and top_level != "auto":
        return top_level

    settings_language = (settings or {}).get("response_language")
    if settings_language:
        return str(settings_language)

    return top_level or "auto"


def localized_safety_note(language: str, feature: str = "AI assistant") -> str:
    prescription_type = (
        "potency prescription"
        if feature == "potency guidance"
        else "prescription"
    )

    if language == "bn-BD":
        prescription_phrase = (
            "স্বয়ংক্রিয় potency prescription"
            if feature == "potency guidance"
            else "স্বয়ংক্রিয় প্রেসক্রিপশন"
        )
        return (
            f"এটি শুধুমাত্র চিকিৎসকের জন্য {feature} সিদ্ধান্ত-সহায়ক বিশ্লেষণ। "
            f"এটি {prescription_phrase} নয়। চূড়ান্ত সিদ্ধান্ত চিকিৎসক নিবেন।"
        )

    if language == "hi-IN":
        prescription_phrase = (
            "automatic potency prescription"
            if feature == "potency guidance"
            else "automatic prescription"
        )
        return (
            f"यह केवल डॉक्टर के लिए {feature} decision-support है। "
            f"यह {prescription_phrase} नहीं है। अंतिम निर्णय डॉक्टर लेंगे।"
        )

    return (
        f"This is doctor-facing {feature} decision support only. "
        f"It is not an automatic {prescription_type}. "
        "Final decision must be made by the practitioner."
    )


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


def _relationship_source_chunks(
    knowledge_chunks: List[Dict[str, Any]],
    limit: int = 5,
) -> List[Dict[str, Any]]:
    chunks = []

    for chunk in knowledge_chunks[:limit]:
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


def _relationship_detect_types(text: str) -> List[str]:
    lowered = text.lower()
    detected: List[str] = []

    checks = [
        ("inimical", ["inimical", "incompatible", "do not follow"]),
        ("antidote", ["antidote", "antidotal", "antidotes", "antidoted"]),
        ("follows_well", ["follows well", "follow well", "followed well", "follows"]),
        ("followed_by", ["followed by", "is followed by"]),
        ("complementary", ["complementary", "complement", "complements"]),
    ]

    for relationship_type, keywords in checks:
        if any(keyword in lowered for keyword in keywords):
            detected.append(relationship_type)

    return detected


def _relationship_evidence(
    chunks: List[Dict[str, Any]],
    relationship_type: str,
    primary_name: str,
    comparison_name: str,
) -> List[str]:
    keywords_by_type = {
        "complementary": ["complementary", "complement"],
        "follows_well": ["follows well", "follow well", "follows"],
        "followed_by": ["followed by"],
        "antidote": ["antidote", "antidotal", "antidotes"],
        "inimical": ["inimical", "incompatible"],
    }
    keywords = keywords_by_type.get(relationship_type, [])
    evidence: List[str] = []

    for chunk in chunks:
        content = str(chunk.get("content") or "")
        lowered = content.lower()
        names_match = (
            primary_name.lower() in lowered
            or (comparison_name and comparison_name.lower() in lowered)
        )
        type_match = any(keyword in lowered for keyword in keywords)

        if type_match or names_match:
            evidence.append(_short_text(content, 280))

    if not evidence and chunks:
        evidence.append(_short_text(chunks[0].get("content"), 280))

    return evidence[:4]


def _relationship_safety_note(language: str) -> str:
    if _lang_bn(language):
        return (
            "এটি চিকিৎসকের জন্য remedy relationship decision-support মাত্র। "
            "এটি স্বয়ংক্রিয় prescription, antidote, repeat, remedy change বা potency সিদ্ধান্ত নয়। "
            "চূড়ান্ত clinical action চিকিৎসক সিদ্ধান্ত নিবেন।"
        )

    if language == "hi-IN":
        return (
            "यह doctor-facing remedy relationship decision-support है। "
            "यह automatic prescription, antidote, repeat, remedy change, या potency decision नहीं है। "
            "Final clinical action डॉक्टर तय करेंगे।"
        )

    return (
        "This is doctor-facing remedy relationship decision support only. "
        "It is not an automatic prescription, antidote, repeat, remedy change, or potency decision. "
        "Final clinical action must be decided by the practitioner."
    )


def _relationship_text_from_payload(payload: RemedyRelationshipRequest) -> str:
    chunks_text = "\n".join(
        [str(chunk.get("content") or "") for chunk in payload.knowledge_chunks]
    )

    return "\n".join(
        [
            str(payload.primary_remedy.get("remedy_name") or ""),
            str(payload.comparison_remedy.get("remedy_name") or ""),
            str(payload.case_snapshot.get("chief_complaint") or ""),
            str(payload.case_snapshot.get("raw_case_text") or ""),
            str(payload.prescription_snapshot.get("remedy_name") or ""),
            str(payload.follow_up_snapshot.get("analysis_summary") or ""),
            chunks_text,
        ]
    )


def _prescription_review_text(payload: PrescriptionReviewRequest) -> str:
    parts: List[str] = []

    for source in [
        payload.case_snapshot,
        payload.prescription_snapshot,
        payload.remedy_suggestion_snapshot,
        payload.potency_guidance_snapshot,
        payload.relationship_snapshot,
        payload.follow_up_snapshot,
    ]:
        parts.append(str(source))

    return "\n".join(parts)


def _prescription_review_safety_note(language: str) -> str:
    if _lang_bn(language):
        return (
            "এটি চিকিৎসকের জন্য prescription decision safety checklist মাত্র। "
            "AI prescription finalize করে না। Doctor checklist confirm করে final decision নিবেন."
        )

    if language == "hi-IN":
        return (
            "यह doctor-facing prescription decision safety checklist है। "
            "AI prescription finalize नहीं करता। Doctor checklist confirm करके final decision लेंगे."
        )

    return (
        "This is a doctor-facing prescription decision safety checklist only. "
        "AI does not finalize the prescription. The practitioner confirms the checklist and makes the final decision."
    )


def _patient_handout_text(payload: PatientHandoutRequest) -> str:
    return "\n".join(
        [
            str(payload.case_snapshot.get("patient_name") or ""),
            str(payload.case_snapshot.get("chief_complaint") or ""),
            str(payload.case_snapshot.get("raw_case_text") or ""),
            str(payload.prescription_snapshot.get("remedy_name") or ""),
            str(payload.prescription_snapshot.get("advice") or ""),
            str(payload.prescription_snapshot.get("food_lifestyle_note") or ""),
        ]
    )


def _patient_handout_patient_name(case_snapshot: Dict[str, Any]) -> str:
    return str(
        case_snapshot.get("patient_name")
        or case_snapshot.get("name")
        or "Patient"
    )


def _patient_handout_safety_note(language: str) -> str:
    if _lang_bn(language):
        return (
            "এই handout চিকিৎসকের দেওয়া নির্দেশনা সহজভাবে বোঝানোর জন্য। "
            "জরুরি সমস্যা, নতুন গুরুতর লক্ষণ, বা দ্রুত অবনতি হলে দ্রুত চিকিৎসকের সাথে যোগাযোগ করুন।"
        )

    if language == "hi-IN":
        return (
            "यह handout doctor के निर्देशों को सरल भाषा में समझाने के लिए है। "
            "Urgent symptoms, नए गंभीर symptoms, या तेजी से worsening हो तो doctor से तुरंत संपर्क करें."
        )

    return (
        "This handout explains the doctor's instructions in simple language. "
        "If urgent symptoms, new serious symptoms, or rapid worsening occur, contact the doctor promptly."
    )


def _patient_handout_default_warnings(language: str) -> List[str]:
    if _lang_bn(language):
        return [
            "শ্বাসকষ্ট, বুক ব্যথা, অজ্ঞান হওয়া বা তীব্র দুর্বলতা",
            "অস্বাভাবিক রক্তপাত বা দ্রুত অবনতি",
            "নতুন গুরুতর ব্যথা, ফোলা, জ্বর বা সংক্রমণের লক্ষণ",
            "মানসিকভাবে নিজেকে ক্ষতি করার চিন্তা",
            "চিকিৎসকের নির্দেশনার বাইরে নিজে নিজে ওষুধ পরিবর্তন করার আগে যোগাযোগ করুন",
        ]

    if language == "hi-IN":
        return [
            "Breathing difficulty, chest pain, fainting, or severe weakness",
            "Unusual bleeding or rapid worsening",
            "New severe pain, swelling, fever, or signs of infection",
            "Thoughts of self-harm",
            "Contact the doctor before changing medicines on your own",
        ]

    return [
        "Breathing difficulty, chest pain, fainting, or severe weakness",
        "Unusual bleeding or rapid worsening",
        "New severe pain, swelling, fever, or signs of infection",
        "Thoughts of self-harm",
        "Contact the doctor before changing medicines on your own",
    ]


def _patient_handout_do_and_dont(language: str) -> List[str]:
    if _lang_bn(language):
        return [
            "চিকিৎসকের দেওয়া dose instruction ঠিকভাবে অনুসরণ করুন",
            "নিজে নিজে repetition বাড়াবেন না",
            "উন্নতি চলতে থাকলে চিকিৎসককে না জানিয়ে ওষুধ পুনরায় খাবেন না",
            "নতুন লক্ষণ বা aggravation হলে নোট করে রাখুন",
            "পরবর্তী follow-up তারিখ মেনে চলুন",
        ]

    if language == "hi-IN":
        return [
            "Follow the dose instruction exactly as advised",
            "Do not increase repetition on your own",
            "Do not repeat the medicine while improvement continues unless advised",
            "Note any new symptom or aggravation",
            "Attend the follow-up as advised",
        ]

    return [
        "Follow the dose instruction exactly as advised",
        "Do not increase repetition on your own",
        "Do not repeat the medicine while improvement continues unless advised",
        "Note any new symptom or aggravation",
        "Attend the follow-up as advised",
    ]


def _clinic_report_resolve_language(requested: Optional[str]) -> str:
    if requested and requested != "auto":
        return requested

    return "en-US"


def _clinic_report_safety_note(language: str) -> str:
    if _lang_bn(language):
        return (
            "এই রিপোর্টটি ক্লিনিকের internal audit এবং practice improvement-এর জন্য। "
            "এটি cure-rate claim, public medical proof, বা guaranteed outcome হিসেবে ব্যবহার করা যাবে না।"
        )

    if language == "hi-IN":
        return (
            "यह रिपोर्ट internal clinic audit और practice improvement के लिए है। "
            "इसे cure-rate claim, public medical proof, या guaranteed outcome statement के रूप में इस्तेमाल न करें."
        )

    return (
        "This report is for internal clinic audit and practice improvement. "
        "It must not be used as a cure-rate claim, public medical proof, or guaranteed outcome statement."
    )


def _clinic_report_int(value: Any) -> int:
    try:
        return int(value or 0)
    except (TypeError, ValueError):
        return 0


def _clinic_report_float(value: Any) -> float:
    try:
        return float(value or 0)
    except (TypeError, ValueError):
        return 0


def _clinic_report_top_remedy_text(top_remedies: List[Dict[str, Any]]) -> str:
    names = [
        f"{item.get('remedy') or item.get('remedy_name') or 'Unknown'} ({item.get('total') or 0})"
        for item in top_remedies[:3]
        if isinstance(item, dict)
    ]

    return ", ".join(names) if names else "No remedy usage data was available."


def _patient_handout_warning_list(
    payload: PatientHandoutRequest,
    language: str,
) -> List[str]:
    if not payload.include_warning_signs:
        return []

    warnings = _patient_handout_default_warnings(language)

    for source in [payload.case_snapshot, payload.review_snapshot]:
        for item in source.get("red_flags") or []:
            text = str(item).strip()
            if text:
                warnings.append(text)

    return list(dict.fromkeys(warnings))


def _patient_handout_section(
    section_key: str,
    category: str,
    sort_order: int,
    title: str,
    content: str,
    is_important: bool = False,
) -> PatientHandoutSectionModel:
    return PatientHandoutSectionModel(
        section_key=section_key,
        category=category,
        sort_order=sort_order,
        title=title,
        content=content.strip(),
        is_important=is_important,
    )


def _has_snapshot(snapshot: Dict[str, Any]) -> bool:
    return any(value not in [None, "", [], {}] for value in snapshot.values())


def _review_check(
    check_key: str,
    category: str,
    severity: str,
    status: str,
    title: str,
    description: str,
    ai_assessment: str,
    evidence: Optional[List[str]] = None,
    is_required: bool = True,
    is_blocking: bool = False,
) -> PrescriptionReviewCheckModel:
    return PrescriptionReviewCheckModel(
        check_key=check_key,
        category=category,
        severity=severity,
        status=status,
        is_required=is_required,
        is_blocking=is_blocking,
        title=title,
        description=description,
        ai_assessment=ai_assessment,
        evidence=evidence or [],
    )


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

    if language == "hi-IN":
        return [
            "रोगी को सामान्यतः गर्मी से अधिक परेशानी होती है या ठंड से?",
            "प्यास कैसी है: कम, अधिक, या सामान्य?",
            "किससे complaint बढ़ती है और किससे कम होती है?",
            "मुख्य डर, चिंता, या mental changes क्या हैं?",
            "नींद, सपने, और जागने के बाद की स्थिति कैसी है?",
            "Food desires या aversions हैं, जैसे sweets, spicy food, eggs, milk?",
            "Stool या urine में कोई बदलाव है?",
            "Female patient में menstrual, breast, या discharge symptoms हैं?",
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


def _localized_question_for_category(category: str, language: str, original: str) -> str:
    questions_by_category = {
        "thermal_state": {
            "bn-BD": "রোগীর গরমে বেশি কষ্ট হয় নাকি শীতে বেশি কষ্ট হয়?",
            "hi-IN": "रोगी को सामान्यतः गर्मी से अधिक परेशानी होती है या ठंड से?",
            "en-US": "Is the patient generally worse from heat or cold?",
        },
        "thirst": {
            "bn-BD": "পিপাসা কেমন—কম, বেশি, নাকি স্বাভাবিক?",
            "hi-IN": "प्यास कैसी है: कम, अधिक, या सामान्य?",
            "en-US": "How is the thirst: low, increased, or normal?",
        },
        "modalities": {
            "bn-BD": "কোন জিনিসে উপসর্গ বাড়ে এবং কোন জিনিসে কমে?",
            "hi-IN": "किससे complaint बढ़ती है और किससे कम होती है?",
            "en-US": "What makes the complaint worse and what makes it better?",
        },
        "mentals": {
            "bn-BD": "রোগীর প্রধান ভয়, দুশ্চিন্তা বা মানসিক পরিবর্তন কী?",
            "hi-IN": "मुख्य डर, चिंता, या mental changes क्या हैं?",
            "en-US": "What are the main fears, anxieties, or mental changes?",
        },
        "dreams": {
            "bn-BD": "স্বপ্নের ধরন বা বারবার দেখা কোনো স্বপ্ন আছে কি?",
            "hi-IN": "किस तरह के dreams आते हैं या कोई recurring dream है?",
            "en-US": "What kinds of dreams or recurring dreams are present?",
        },
        "sleep": {
            "bn-BD": "ঘুম এবং ঘুম থেকে ওঠার পর অনুভূতি কেমন?",
            "hi-IN": "नींद और जागने के बाद की feeling कैसी है?",
            "en-US": "How are sleep and the feeling after waking?",
        },
        "food_desires": {
            "bn-BD": "খাবারের বিশেষ পছন্দ বা আকর্ষণ কী আছে?",
            "hi-IN": "Food desires या strong cravings क्या हैं?",
            "en-US": "What food desires or strong cravings are present?",
        },
        "food_aversions": {
            "bn-BD": "খাবারের বিশেষ অপছন্দ বা intolerance কী আছে?",
            "hi-IN": "Food aversions या intolerance क्या हैं?",
            "en-US": "What food aversions or intolerances are present?",
        },
        "stool": {
            "bn-BD": "পায়খানার কোনো পরিবর্তন আছে কি?",
            "hi-IN": "Stool में कोई बदलाव है?",
            "en-US": "Any change in stool?",
        },
        "urine": {
            "bn-BD": "প্রস্রাবের কোনো পরিবর্তন আছে কি?",
            "hi-IN": "Urine में कोई बदलाव है?",
            "en-US": "Any change in urine?",
        },
        "menses": {
            "bn-BD": "মাসিক সংক্রান্ত কোনো উপসর্গ বা পরিবর্তন আছে কি?",
            "hi-IN": "Menstrual symptoms या changes क्या हैं?",
            "en-US": "Any menstrual symptoms or changes?",
        },
        "female_symptoms": {
            "bn-BD": "মাসিক, স্তন বা স্রাব সংক্রান্ত কোনো উপসর্গ আছে কি?",
            "hi-IN": "Menstrual, breast, या discharge symptoms हैं?",
            "en-US": "Any menstrual, breast, or discharge symptoms?",
        },
    }

    language_key = "bn-BD" if _lang_bn(language) else language
    localized = questions_by_category.get(category, {}).get(language_key)
    if localized:
        return localized

    if _lang_bn(language):
        return f"এই বিষয়টি বিস্তারিত বলুন: {original}"

    if language == "hi-IN":
        return f"इस बात को विस्तार से बताएं: {original}"

    return original


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
    language = resolve_response_language(
        payload.response_language,
        payload.raw_case_text,
        payload.chief_complaint,
        payload.language,
    )
    source_questions = payload.missing_questions or _default_questions(language)

    for index, question in enumerate(source_questions[: payload.max_questions]):
        category = _category_from_question(question)
        localized_question = _localized_question_for_category(
            category,
            language,
            question,
        )
        questions.append(
            MissingQuestionItem(
                question_key=_question_key(localized_question, index),
                category=category,
                importance=(
                    "important"
                    if category in ["mentals", "modalities", "thermal_state"]
                    else "normal"
                ),
                question=localized_question,
            )
        )

    for red_index, red_flag in enumerate(payload.red_flags[:3]):
        if _lang_bn(language):
            question = (
                f"রেড ফ্ল্যাগ যাচাই: {red_flag} — এ বিষয়ে মেডিক্যাল "
                "মূল্যায়ন/রিপোর্ট/ডাক্তারের পরামর্শ হয়েছে কি?"
            )
        elif language == "hi-IN":
            question = (
                f"Red flag check: {red_flag}. क्या इसकी medical evaluation "
                "या investigation हुई है?"
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

    safety_note = localized_safety_note(language, "missing-question conversation")

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
    language = resolve_response_language(
        payload.response_language,
        payload.answer,
        payload.question,
    )
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
    if _lang_bn(language):
        summary = f"{category} বিভাগে উত্তর সংরক্ষণ করা হয়েছে: {answer[:220]}"
    elif language == "hi-IN":
        summary = f"{category} में answer save किया गया: {answer[:220]}"
    else:
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
    language = resolve_response_language(
        payload.response_language,
        current_text,
        previous_text,
    )
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
            "রোগী পুরনো লক্ষণ ফিরে আসার কথা বলেছেন; direction of cure সতর্কভাবে review করুন."
            if _lang_bn(language)
            else "Patient reports return of old symptoms; review direction of cure carefully."
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
            "সম্ভাব্য aggravation বলা হয়েছে; timing, intensity, duration এবং general wellbeing নিশ্চিত করুন."
            if _lang_bn(language)
            else "Possible aggravation reported; confirm timing, intensity, duration, and general wellbeing."
        )

    if _lang_bn(language):
        analysis_summary = (
            f"Follow-up response সম্ভবত {response_level}. "
            f"Improvement points: {len(improvement)}, worsening points: {len(worsening)}, "
            f"new symptoms: {len(new_symptoms)}, red flags: {len(red_flags)}."
        )
        remedy_response_assessment = (
            f"{remedy_label} এর পর response {response_level} হিসেবে দেখা যাচ্ছে. "
            "এটি শুধু clinical progress summary, prescription decision নয়."
        )
        suggested_follow_up_questions = [
            "Prescription এর পর প্রথম কোন পরিবর্তন হয়েছে, এবং কখন?",
            "পূর্বের visit এর তুলনায় energy, sleep, appetite, thirst, stool এবং mood কেমন?",
            "কোন পুরনো symptom ফিরে এসেছে কি, এবং তা milder বা shorter ছিল কি?",
            "Remedy এর পর কোনো নতুন symptom এসেছে কি, এবং intensity কত?",
            "Improvement এর আগে initial aggravation হয়েছিল কি?",
        ]
    elif language == "hi-IN":
        analysis_summary = (
            f"Follow-up response {response_level} लगता है. "
            f"Improvement points: {len(improvement)}, worsening points: {len(worsening)}, "
            f"new symptoms: {len(new_symptoms)}, red flags: {len(red_flags)}."
        )
        remedy_response_assessment = (
            f"{remedy_label} के बाद response {response_level} assess किया गया. "
            "यह clinical progress summary है, prescription decision नहीं."
        )
        suggested_follow_up_questions = [
            "Prescription के बाद सबसे पहले क्या बदला, और कब?",
            "पिछली visit की तुलना में energy, sleep, appetite, thirst, stool और mood कैसे हैं?",
            "क्या कोई old symptom वापस आया?",
            "Remedy के बाद कोई new symptom आया, और intensity कितनी है?",
            "Improvement से पहले initial aggravation हुआ था?",
        ]
    else:
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
            "Reported warning symptoms এখন active/severe/recurrent কি, বা medically evaluated হয়েছে?"
            if _lang_bn(language)
            else "Are the reported warning symptoms active now, severe, recurrent, or medically evaluated?",
        )

    doctor_review_points = [
        "Generals এবং mental state compare করে wait/repeat/change plan সিদ্ধান্ত নিন.",
        "পরিবর্তন sustained নাকি temporary তা confirm করুন.",
        "New symptoms এবং expected old symptom return আলাদাভাবে review করুন.",
    ] if _lang_bn(language) else [
        "Compare generals and mental state before deciding whether to wait, repeat, or change plan.",
        "Confirm whether changes are sustained or only temporary.",
        "Review new symptoms separately from expected return of old symptoms.",
    ]

    recommended_next_steps = [
        "প্রতিটি changed symptom এর intensity এবং duration লিখে রাখুন.",
        "Symptom change matrix supporting notes হিসেবে ব্যবহার করুন, automatic prescription rule নয়.",
        "Wait, repeat, potency change, remedy change, বা referral চিকিৎসক সিদ্ধান্ত নিবেন.",
    ] if _lang_bn(language) else [
        "Document intensity and duration for each changed symptom.",
        "Use the symptom change matrix as supporting notes, not as an automatic prescription rule.",
        "Practitioner should decide wait, repeat, potency change, remedy change, or referral.",
    ]

    if red_flags:
        recommended_next_steps.insert(
            0,
            "Red flags detected: প্রয়োজন হলে medical evaluation/referral বিবেচনা করুন."
            if _lang_bn(language)
            else "Red flags detected: consider medical evaluation or referral where appropriate.",
        )

    safety_note = localized_safety_note(language, "follow-up analysis")

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
    language = resolve_response_language(
        requested_response_language(payload.response_language, payload.settings),
        text,
    )

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

    if _lang_bn(language):
        cautions: List[str] = [
            "Final potency এবং repetition চিকিৎসক সিদ্ধান্ত নিবেন.",
            "Improvement চলতে থাকলে automatic repetition করবেন না.",
            "Potency selection এর আগে sensitivity, vitality, pathology depth এবং remedy certainty review করুন.",
        ]
    elif language == "hi-IN":
        cautions = [
            "Final potency और repetition डॉक्टर तय करेंगे.",
            "Improvement जारी हो तो automatic repetition न करें.",
            "Potency selection से पहले sensitivity, vitality, pathology depth और remedy certainty review करें.",
        ]
    else:
        cautions = [
            "Final potency and repetition must be decided by the practitioner.",
            "Do not repeat automatically while improvement is continuing.",
            "Review sensitivity, vitality, pathology depth, and remedy certainty before potency selection.",
        ]

    if sensitivity == "high":
        if _lang_bn(language):
            cautions.append(
                "High sensitivity suspected: potency এবং repetition এ extra caution বিবেচনা করুন."
            )
        elif language == "hi-IN":
            cautions.append(
                "High sensitivity suspected: potency और repetition में extra caution रखें."
            )
        else:
            cautions.append(
                "High sensitivity suspected: consider extra caution with potency and repetition."
            )

    if vitality == "low":
        if _lang_bn(language):
            cautions.append(
                "Low vitality suspected: close review ছাড়া aggressive repetition এড়িয়ে চলুন."
            )
        elif language == "hi-IN":
            cautions.append(
                "Low vitality suspected: close review के बिना aggressive repetition से बचें."
            )
        else:
            cautions.append(
                "Low vitality suspected: avoid aggressive repetition without close review."
            )

    if pathology in ["structural", "advanced_pathology"]:
        if _lang_bn(language):
            cautions.append(
                "Structural/advanced pathology suspected: প্রয়োজন হলে medical evaluation coordinate করুন."
            )
        elif language == "hi-IN":
            cautions.append(
                "Structural/advanced pathology suspected: जरूरत हो तो medical evaluation coordinate करें."
            )
        else:
            cautions.append(
                "Structural or advanced pathology suspected: coordinate medical evaluation where appropriate."
            )

    if phase == "follow_up":
        if _lang_bn(language):
            cautions.append(
                "Follow-up case: repeat করার আগে direction of cure, aggravation, old symptom return এবং general wellbeing assess করুন."
            )
        elif language == "hi-IN":
            cautions.append(
                "Follow-up case: repeat करने से पहले direction of cure, aggravation, old symptom return, और general wellbeing assess करें."
            )
        else:
            cautions.append(
                "Follow-up case: assess direction of cure, aggravation, old symptom return, and general wellbeing before repeating."
            )

    if _lang_bn(language):
        guidance_summary = (
            f"{remedy_name} এর potency guidance: case phase সম্ভবত {phase}, "
            f"vitality {vitality}, sensitivity {sensitivity}, "
            f"এবং pathology depth {pathology}. এটি শুধু decision-support indicator."
        )
        repetition_guidance = (
            "Repetition fixed routine অনুযায়ী নয়, রোগীর response অনুযায়ী বিবেচনা করতে হবে. "
            "উন্নতি পরিষ্কারভাবে চলতে থাকলে mechanical repetition এর চেয়ে wait-and-watch নিরাপদ হতে পারে."
        )
        wait_guidance = (
            "Steady improvement, high sensitivity, recent aggravation followed by improvement, "
            "বা repetition দরকার কিনা unclear হলে wait-and-watch বিশেষ গুরুত্বপূর্ণ."
        )
        aggravation_guidance = (
            "Aggravation reported হলে timing, intensity, duration, general wellbeing, old symptom return, "
            "এবং aggravation এর পরে improvement হয়েছে কিনা confirm করুন."
        )
        follow_up_questions = [
            "Dose এর পরে প্রথম কী পরিবর্তন হয়েছে?",
            "Improvement এখনও চলছে কি?",
            "Initial aggravation হয়েছিল কি? কতক্ষণ ছিল?",
            "Energy, sleep, appetite, mood এবং generals improve করেছে কি?",
            "কোন old symptom ফিরে এসেছে কি?",
            "Remedy এর পরে কোনো new symptom এসেছে কি?",
            "অন্য কোনো medicine বা treatment ব্যবহার হয়েছে কি?",
            "কোন red-flag symptom বা clinical deterioration আছে কি?",
        ]
        review_points = [
            "Potency escalation বিবেচনার আগে remedy similarity confirm করুন.",
            "Patient sensitivity এবং vitality review করুন.",
            "Pathology depth এবং risk review করুন.",
            "Repetition আদৌ দরকার কিনা review করুন.",
            "Potency এবং repetition কেন chosen হয়েছে document করুন.",
        ]
    elif language == "hi-IN":
        guidance_summary = (
            f"{remedy_name} के लिए potency guidance: case phase {phase} लगता है, "
            f"vitality {vitality}, sensitivity {sensitivity}, "
            f"और pathology depth {pathology}. ये केवल decision-support indicators हैं."
        )
        repetition_guidance = (
            "Repetition fixed routine से नहीं, patient response के अनुसार विचार करें. "
            "अगर improvement साफ और जारी है, तो mechanical repetition से wait-and-watch बेहतर हो सकता है."
        )
        wait_guidance = (
            "Steady improvement, high sensitivity, recent aggravation followed by improvement, "
            "या repetition की need unclear हो तो wait-and-watch विशेष रूप से important है."
        )
        aggravation_guidance = (
            "Aggravation reported हो तो timing, intensity, duration, general wellbeing, "
            "old symptom return, और aggravation के बाद improvement हुआ या नहीं confirm करें."
        )
        follow_up_questions = [
            "Dose के बाद सबसे पहले क्या बदला?",
            "Improvement अभी भी जारी है?",
            "Initial aggravation हुआ था? कितनी देर रहा?",
            "Energy, sleep, appetite, mood, और generals improve हुए?",
            "कोई old symptom वापस आया?",
            "Remedy के बाद कोई new symptom आया?",
            "कोई दूसरी medicine या treatment use हुआ?",
            "कोई red-flag symptom या clinical deterioration है?",
        ]
        review_points = [
            "Potency escalation से पहले remedy similarity confirm करें.",
            "Patient sensitivity और vitality review करें.",
            "Pathology depth और risk review करें.",
            "Repetition सच में needed है या नहीं review करें.",
            "Potency और repetition क्यों चुना गया, document करें.",
        ]
    else:
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

    safety_note = localized_safety_note(language, "potency guidance")

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


@app.post("/patient-handout/generate", response_model=PatientHandoutResponse)
def generate_patient_handout(
    payload: PatientHandoutRequest,
) -> PatientHandoutResponse:
    language = resolve_response_language(
        payload.response_language,
        _patient_handout_text(payload),
    )

    prescription = payload.prescription_snapshot
    clinic = payload.clinic_snapshot

    patient_name = _patient_handout_patient_name(payload.case_snapshot)
    remedy_name = str(prescription.get("remedy_name") or "medicine")
    potency = str(prescription.get("potency") or "").strip()
    repetition = str(prescription.get("repetition") or "").strip()
    dose_instruction = str(prescription.get("dose_instruction") or "").strip()
    advice = str(prescription.get("advice") or "").strip()
    food_lifestyle_note = str(prescription.get("food_lifestyle_note") or "").strip()
    follow_up_date = str(prescription.get("follow_up_date") or "").strip()
    clinic_name = str(
        clinic.get("clinic_name")
        or clinic.get("name")
        or "Clinic"
    )
    doctor_name = str(clinic.get("doctor_name") or "").strip()

    warning_signs = _patient_handout_warning_list(payload, language)
    do_and_dont = (
        _patient_handout_do_and_dont(language)
        if payload.include_do_and_dont
        else []
    )
    style = (payload.style or "simple").lower()

    if _lang_bn(language):
        title = "রোগীর জন্য চিকিৎসা নির্দেশনা"
        patient_summary = (
            f"{patient_name} এর জন্য চিকিৎসকের দেওয়া নির্দেশনা নিচে সহজ ভাষায় লেখা হলো। "
            "অনুগ্রহ করে নির্দেশনাগুলো ভালোভাবে অনুসরণ করুন।"
        )
        medicine_instruction = (
            f"ওষুধ: {remedy_name}"
            + (f" {potency}" if potency else "")
            + (f"\nRepetition: {repetition}" if repetition else "")
            + (f"\nDose instruction: {dose_instruction}" if dose_instruction else "")
        )
        if medicine_instruction.strip() == "ওষুধ: medicine":
            medicine_instruction = "চিকিৎসক যেভাবে বলেছেন, ওষুধ ঠিক সেভাবেই নিন।"

        diet_instruction = (
            food_lifestyle_note
            or advice
            or "খাবার, ঘুম, পানি এবং দৈনন্দিন অভ্যাস সম্পর্কে চিকিৎসকের দেওয়া সাধারণ নির্দেশনা মেনে চলুন।"
        )
        follow_up_instruction = (
            f"পরবর্তী follow-up: {follow_up_date}."
            if follow_up_date
            else "চিকিৎসকের পরামর্শ অনুযায়ী follow-up করুন।"
        )
        warning_instruction = (
            "উপরের warning signs এর কোনোটি দেখা দিলে বা দ্রুত অবনতি হলে চিকিৎসকের সাথে যোগাযোগ করুন।"
        )
        footer_note = str(
            clinic.get("prescription_footer")
            or f"{clinic_name} থেকে দেওয়া doctor-approved patient instruction."
        )
        section_labels = {
            "summary": "সারাংশ",
            "medicine": "ওষুধের নির্দেশনা",
            "what_to_expect": "কী আশা করবেন",
            "diet_lifestyle": "খাবার ও জীবনযাপন",
            "follow_up": "Follow-up",
            "warning": "কখন যোগাযোগ করবেন",
        }
        expect_instruction = (
            "উন্নতি, নতুন লক্ষণ, বা aggravation হলে তা লিখে রাখুন এবং follow-up এ চিকিৎসককে জানান।"
        )
    elif language == "hi-IN":
        title = "Patient Treatment Instructions"
        patient_summary = (
            f"{patient_name} के लिए doctor के निर्देश सरल भाषा में नीचे दिए गए हैं. "
            "कृपया इन्हें ध्यान से follow करें."
        )
        medicine_instruction = (
            f"Medicine: {remedy_name}"
            + (f" {potency}" if potency else "")
            + (f"\nRepetition: {repetition}" if repetition else "")
            + (f"\nDose instruction: {dose_instruction}" if dose_instruction else "")
        )
        diet_instruction = (
            food_lifestyle_note
            or advice
            or "Follow the general diet, sleep, water, and daily routine advice given by the doctor."
        )
        follow_up_instruction = (
            f"Next follow-up: {follow_up_date}."
            if follow_up_date
            else "Attend follow-up as advised by the doctor."
        )
        warning_instruction = (
            "If any warning sign appears, or if symptoms worsen quickly, contact the doctor promptly."
        )
        footer_note = str(
            clinic.get("prescription_footer")
            or f"Doctor-approved patient instruction from {clinic_name}."
        )
        section_labels = {
            "summary": "Summary",
            "medicine": "Medicine Instruction",
            "what_to_expect": "What to Expect",
            "diet_lifestyle": "Diet and Lifestyle",
            "follow_up": "Follow-up",
            "warning": "When to Contact the Doctor",
        }
        expect_instruction = (
            "Note improvement, new symptoms, or aggravation and share them with the doctor at follow-up."
        )
    else:
        title = "Patient Treatment Instructions"
        patient_summary = (
            f"These are the doctor's instructions for {patient_name}, written in simple patient-friendly language. "
            "Please follow them carefully."
        )
        medicine_instruction = (
            f"Medicine: {remedy_name}"
            + (f" {potency}" if potency else "")
            + (f"\nRepetition: {repetition}" if repetition else "")
            + (f"\nDose instruction: {dose_instruction}" if dose_instruction else "")
        )
        if medicine_instruction.strip() == "Medicine: medicine":
            medicine_instruction = "Take the medicine exactly as explained by the doctor."

        diet_instruction = (
            food_lifestyle_note
            or advice
            or "Follow the general diet, sleep, water, and daily routine advice given by the doctor."
        )
        follow_up_instruction = (
            f"Next follow-up: {follow_up_date}."
            if follow_up_date
            else "Attend follow-up as advised by the doctor."
        )
        warning_instruction = (
            "If any warning sign appears, or if symptoms worsen quickly, contact the doctor promptly."
        )
        footer_note = str(
            clinic.get("prescription_footer")
            or f"Doctor-approved patient instruction from {clinic_name}."
        )
        section_labels = {
            "summary": "Summary",
            "medicine": "Medicine Instruction",
            "what_to_expect": "What to Expect",
            "diet_lifestyle": "Diet and Lifestyle",
            "follow_up": "Follow-up",
            "warning": "When to Contact the Doctor",
        }
        expect_instruction = (
            "Note improvement, new symptoms, or aggravation and share them with the doctor at follow-up."
        )

    if doctor_name:
        footer_note = f"{footer_note}\nDoctor: {doctor_name}"

    sections: List[PatientHandoutSectionModel] = []

    if style != "minimal":
        sections.append(
            _patient_handout_section(
                section_key="summary",
                category="instruction",
                sort_order=1,
                title=section_labels["summary"],
                content=patient_summary,
            )
        )

    sections.append(
        _patient_handout_section(
            section_key="medicine",
            category="instruction",
            sort_order=2,
            title=section_labels["medicine"],
            content=medicine_instruction,
            is_important=True,
        )
    )

    if style == "detailed":
        sections.append(
            _patient_handout_section(
                section_key="what_to_expect",
                category="instruction",
                sort_order=3,
                title=section_labels["what_to_expect"],
                content=expect_instruction,
            )
        )

    if style != "minimal":
        sections.append(
            _patient_handout_section(
                section_key="diet_lifestyle",
                category="instruction",
                sort_order=4,
                title=section_labels["diet_lifestyle"],
                content=diet_instruction,
            )
        )

    sections.extend(
        [
            _patient_handout_section(
                section_key="follow_up",
                category="follow_up",
                sort_order=5,
                title=section_labels["follow_up"],
                content=follow_up_instruction,
                is_important=True,
            ),
            _patient_handout_section(
                section_key="warning",
                category="warning",
                sort_order=6,
                title=section_labels["warning"],
                content=warning_instruction,
                is_important=True,
            ),
        ]
    )

    return PatientHandoutResponse(
        title=title,
        resolved_language=language,
        patient_summary=patient_summary,
        medicine_instruction=medicine_instruction,
        diet_lifestyle_instruction=diet_instruction,
        follow_up_instruction=follow_up_instruction,
        warning_instruction=warning_instruction,
        warning_signs=warning_signs,
        do_and_dont=do_and_dont,
        footer_note=footer_note,
        safety_note=_patient_handout_safety_note(language),
        sections=sections,
    )


@app.post("/clinic-report/monthly-summary", response_model=ClinicReportSummaryResponse)
def clinic_report_monthly_summary(
    payload: ClinicReportSummaryRequest,
) -> ClinicReportSummaryResponse:
    language = _clinic_report_resolve_language(payload.response_language)
    snapshot = payload.dashboard_snapshot or {}

    kpis = snapshot.get("kpis") or {}
    finance = snapshot.get("finance") or {}
    safety = snapshot.get("safety") or {}
    outcomes = snapshot.get("outcomes") or {}
    remedies = snapshot.get("remedies") or {}
    follow_ups = snapshot.get("follow_ups") or {}
    clinic_activity = snapshot.get("clinic_activity") or {}

    new_patients = _clinic_report_int(kpis.get("new_patients"))
    visits = _clinic_report_int(kpis.get("visits"))
    follow_up_visits = _clinic_report_int(kpis.get("follow_up_visits"))
    prescriptions = _clinic_report_int(kpis.get("prescriptions"))
    outcome_analyses = _clinic_report_int(kpis.get("outcome_analyses"))
    average_progress = _clinic_report_float(kpis.get("average_progress_score"))
    patient_handouts = _clinic_report_int(kpis.get("patient_handouts"))
    red_flags = _clinic_report_int(safety.get("red_flag_count"))

    paid_amount = _clinic_report_float(finance.get("paid_amount"))
    due_amount = _clinic_report_float(finance.get("due_amount"))

    outcome_distribution = outcomes.get("response_level_distribution") or []
    top_remedies = remedies.get("top_prescribed_remedies") or []
    top_remedies_text = _clinic_report_top_remedy_text(top_remedies)

    overdue_count = len(follow_ups.get("overdue") or [])
    due_next_count = len(follow_ups.get("due_next_7_days") or [])

    key_metrics = {
        "new_patients": new_patients,
        "visits": visits,
        "follow_up_visits": follow_up_visits,
        "prescriptions": prescriptions,
        "outcome_analyses": outcome_analyses,
        "average_progress_score": average_progress,
        "patient_handouts": patient_handouts,
        "red_flag_count": red_flags,
        "paid_amount": paid_amount,
        "due_amount": due_amount,
        "overdue_follow_ups": overdue_count,
        "due_next_7_days": due_next_count,
    }

    if _lang_bn(language):
        title = f"মাসিক ক্লিনিক রিপোর্ট: {payload.period_start} থেকে {payload.period_end}"
        executive_summary = (
            f"এই সময়ে মোট {visits}টি visit, {new_patients}জন নতুন patient, "
            f"{prescriptions}টি prescription এবং {outcome_analyses}টি follow-up outcome analysis হয়েছে। "
            f"Average progress score ছিল {average_progress}।"
        )
        clinical_activity_summary = (
            f"Clinic activity অনুযায়ী এই period-এ মোট visit ছিল {visits}, "
            f"যার মধ্যে follow-up visit ছিল {follow_up_visits}।"
        )
        outcome_summary = (
            f"Outcome analysis সংখ্যা: {outcome_analyses}. Average progress score: {average_progress}. "
            "এই data doctor-entered follow-up information এবং AI analysis-এর উপর নির্ভরশীল।"
        )
        remedy_summary = (
            f"Top prescribed remedies: {top_remedies_text}. Remedy usage শুধুমাত্র clinic audit-এর জন্য; "
            "এটি effectiveness claim নয়।"
        )
        safety_summary = (
            f"এই সময়ে red flag alert পাওয়া গেছে {red_flags}টি।"
            if payload.include_safety
            else "Safety summary এই report-এ অন্তর্ভুক্ত করা হয়নি।"
        )
        finance_summary = (
            f"Paid amount: {paid_amount} BDT, due amount: {due_amount} BDT।"
            if payload.include_finance
            else "Finance summary এই report-এ অন্তর্ভুক্ত করা হয়নি।"
        )
        follow_up_summary = (
            f"Overdue follow-up: {overdue_count}, next 7 days due follow-up: {due_next_count}।"
            if payload.include_follow_ups
            else "Follow-up summary এই report-এ অন্তর্ভুক্ত করা হয়নি।"
        )
        recommendations = [
            "Overdue follow-up patient list নিয়মিত review করুন।",
            "Red flag cases আলাদা priority দিয়ে check করুন।",
            "Prescription review safety_warning বা incomplete থাকলে final decision-এর আগে review করুন।",
            "Outcome data public cure-rate claim হিসেবে ব্যবহার করবেন না।",
        ]
        limitations = [
            "Data doctor-entered record এবং generated analysis-এর উপর নির্ভরশীল।",
            "Outcome score clinical proof নয়; এটি audit indicator মাত্র।",
            "Small sample size বা incomplete follow-up থাকলে interpretation সীমিত হবে।",
        ]
        section_titles = {
            "overview": "Executive Summary",
            "activity": "Clinic Activity",
            "outcomes": "Outcome Summary",
            "remedies": "Remedy Usage",
            "safety": "Safety Review",
            "finance": "Finance Summary",
            "follow_up": "Follow-up Summary",
            "recommendations": "Recommendations",
            "limitations": "Limitations",
        }
    elif language == "hi-IN":
        title = f"Monthly Clinic Report: {payload.period_start} to {payload.period_end}"
        executive_summary = (
            f"इस period में clinic ने {visits} visits, {new_patients} new patients, "
            f"{prescriptions} prescriptions, और {outcome_analyses} follow-up outcome analyses record किए। "
            f"Average progress score {average_progress} था।"
        )
        clinical_activity_summary = (
            f"Clinic activity में {visits} total visits थे, जिनमें {follow_up_visits} follow-up visits थे."
        )
        outcome_summary = (
            f"Outcome analyses completed: {outcome_analyses}. Average progress score: {average_progress}. "
            "यह doctor-entered follow-up data और AI analysis पर निर्भर है."
        )
        remedy_summary = (
            f"Top prescribed remedies: {top_remedies_text}. Remedy usage internal audit के लिए है; "
            "इसे effectiveness claim न समझें."
        )
        safety_summary = (
            f"{red_flags} red-flag alerts इस period में detect हुए."
            if payload.include_safety
            else "Safety summary इस report में include नहीं की गई."
        )
        finance_summary = (
            f"Paid amount: {paid_amount} BDT. Due amount: {due_amount} BDT."
            if payload.include_finance
            else "Finance summary इस report में include नहीं की गई."
        )
        follow_up_summary = (
            f"Overdue follow-ups: {overdue_count}. Next 7 days due follow-ups: {due_next_count}."
            if payload.include_follow_ups
            else "Follow-up summary इस report में include नहीं की गई."
        )
        recommendations = [
            "Review overdue follow-up patients regularly.",
            "Prioritize red-flag cases for clinical review.",
            "Review prescription safety warnings or incomplete reviews before final decisions.",
            "Do not use outcome data as a public cure-rate claim.",
        ]
        limitations = [
            "The report depends on doctor-entered records and generated analyses.",
            "Outcome score is an audit indicator, not clinical proof.",
            "Small sample size or incomplete follow-up limits interpretation.",
        ]
        section_titles = {
            "overview": "Executive Summary",
            "activity": "Clinic Activity",
            "outcomes": "Outcome Summary",
            "remedies": "Remedy Usage",
            "safety": "Safety Review",
            "finance": "Finance Summary",
            "follow_up": "Follow-up Summary",
            "recommendations": "Recommendations",
            "limitations": "Limitations",
        }
    else:
        title = f"Monthly Clinic Report: {payload.period_start} to {payload.period_end}"
        executive_summary = (
            f"During this period, the clinic recorded {visits} visits, {new_patients} new patients, "
            f"{prescriptions} prescriptions, and {outcome_analyses} follow-up outcome analyses. "
            f"The average progress score was {average_progress}."
        )
        clinical_activity_summary = (
            f"Clinic activity included {visits} total visits, including {follow_up_visits} follow-up visits."
        )
        outcome_summary = (
            f"Outcome analyses completed: {outcome_analyses}. Average progress score: {average_progress}. "
            "This depends on doctor-entered follow-up data and AI analysis."
        )
        remedy_summary = (
            f"Top prescribed remedies: {top_remedies_text}. Remedy usage is for internal audit only "
            "and should not be interpreted as an effectiveness claim."
        )
        safety_summary = (
            f"{red_flags} red-flag alerts were detected during this period. "
            "Any safety warning should be reviewed clinically."
            if payload.include_safety
            else "Safety summary was not included in this report."
        )
        finance_summary = (
            f"Paid amount: {paid_amount} BDT. Due amount: {due_amount} BDT."
            if payload.include_finance
            else "Finance summary was not included in this report."
        )
        follow_up_summary = (
            f"Overdue follow-ups: {overdue_count}. Follow-ups due in the next 7 days: {due_next_count}."
            if payload.include_follow_ups
            else "Follow-up summary was not included in this report."
        )
        recommendations = [
            "Review overdue follow-up patients regularly.",
            "Prioritize red-flag cases for clinical review.",
            "Review prescription safety warnings or incomplete reviews before final decisions.",
            "Do not use outcome data as a public cure-rate claim.",
        ]
        limitations = [
            "The report depends on doctor-entered records and generated analyses.",
            "Outcome score is an audit indicator, not clinical proof.",
            "Small sample size or incomplete follow-up limits interpretation.",
        ]
        section_titles = {
            "overview": "Executive Summary",
            "activity": "Clinic Activity",
            "outcomes": "Outcome Summary",
            "remedies": "Remedy Usage",
            "safety": "Safety Review",
            "finance": "Finance Summary",
            "follow_up": "Follow-up Summary",
            "recommendations": "Recommendations",
            "limitations": "Limitations",
        }

    recommendation_content = (
        "\n".join([f"- {item}" for item in recommendations])
        if payload.include_recommendations
        else "Recommendations were not included in this report."
    )

    sections = [
        ClinicReportSectionModel(
            section_key="overview",
            category="summary",
            sort_order=1,
            title=section_titles["overview"],
            content=executive_summary,
            metrics=key_metrics,
        ),
        ClinicReportSectionModel(
            section_key="activity",
            category="analytics",
            sort_order=2,
            title=section_titles["activity"],
            content=clinical_activity_summary,
            metrics={
                "visits_by_day": clinic_activity.get("visits_by_day", []),
                "new_patients_by_day": clinic_activity.get("new_patients_by_day", []),
                "visit_type_distribution": clinic_activity.get("visit_type_distribution", []),
            },
        ),
        ClinicReportSectionModel(
            section_key="outcomes",
            category="analytics",
            sort_order=3,
            title=section_titles["outcomes"],
            content=outcome_summary,
            metrics={
                "response_level_distribution": outcome_distribution,
                "progress_score_trend": outcomes.get("progress_score_trend") or [],
                "average_progress_score": average_progress,
            },
        ),
        ClinicReportSectionModel(
            section_key="remedies",
            category="analytics",
            sort_order=4,
            title=section_titles["remedies"],
            content=remedy_summary,
            metrics={
                "top_prescribed_remedies": top_remedies,
                "top_potencies": remedies.get("top_potencies") or [],
            },
        ),
        ClinicReportSectionModel(
            section_key="safety",
            category="safety",
            sort_order=5,
            title=section_titles["safety"],
            content=safety_summary,
            metrics=safety if payload.include_safety else {},
        ),
        ClinicReportSectionModel(
            section_key="finance",
            category="finance",
            sort_order=6,
            title=section_titles["finance"],
            content=finance_summary,
            metrics=finance if payload.include_finance else {},
        ),
        ClinicReportSectionModel(
            section_key="follow_up",
            category="follow_up",
            sort_order=7,
            title=section_titles["follow_up"],
            content=follow_up_summary,
            metrics=follow_ups if payload.include_follow_ups else {},
        ),
        ClinicReportSectionModel(
            section_key="recommendations",
            category="recommendation",
            sort_order=8,
            title=section_titles["recommendations"],
            content=recommendation_content,
        ),
        ClinicReportSectionModel(
            section_key="limitations",
            category="limitation",
            sort_order=9,
            title=section_titles["limitations"],
            content="\n".join([f"- {item}" for item in limitations]),
        ),
    ]

    return ClinicReportSummaryResponse(
        title=title,
        resolved_language=language,
        executive_summary=executive_summary,
        clinical_activity_summary=clinical_activity_summary,
        outcome_summary=outcome_summary,
        remedy_summary=remedy_summary,
        safety_summary=safety_summary,
        finance_summary=finance_summary,
        follow_up_summary=follow_up_summary,
        key_metrics=key_metrics,
        recommendations=recommendations if payload.include_recommendations else [],
        limitations=limitations,
        safety_note=_clinic_report_safety_note(language),
        sections=sections,
    )


@app.post("/prescription/review", response_model=PrescriptionReviewResponse)
def prescription_review(
    payload: PrescriptionReviewRequest,
) -> PrescriptionReviewResponse:
    prescription = payload.prescription_snapshot
    case_snapshot = payload.case_snapshot
    remedy_suggestion = payload.remedy_suggestion_snapshot
    potency_guidance = payload.potency_guidance_snapshot
    relationship = payload.relationship_snapshot
    follow_up = payload.follow_up_snapshot
    language = resolve_response_language(
        payload.response_language,
        _prescription_review_text(payload),
    )

    remedy_name = str(prescription.get("remedy_name") or "selected remedy")
    potency = str(prescription.get("potency") or "")
    repetition = str(prescription.get("repetition") or "")
    dose_instruction = str(prescription.get("dose_instruction") or "")
    reason = str(prescription.get("reason") or "")
    follow_up_date = str(prescription.get("follow_up_date") or "")

    red_flags = [
        str(item)
        for item in (case_snapshot.get("red_flags") or [])
        if str(item).strip()
    ]
    red_flags += [
        str(item)
        for item in (follow_up.get("red_flags") or [])
        if str(item).strip()
    ]
    red_flags = list(dict.fromkeys(red_flags))

    missing_information: List[str] = []

    if not str(prescription.get("remedy_name") or "").strip():
        missing_information.append("Remedy name is missing.")

    if not potency.strip():
        missing_information.append("Potency is missing.")

    if not repetition.strip():
        missing_information.append("Repetition instruction is missing or unclear.")

    if not dose_instruction.strip():
        missing_information.append("Dose instruction is missing.")

    if not reason.strip():
        missing_information.append("Reason for remedy selection is not documented.")

    if not follow_up_date.strip():
        missing_information.append("Follow-up date is not documented.")

    for question in (case_snapshot.get("missing_questions") or [])[:5]:
        missing_information.append(f"Case question still open: {question}")

    has_remedy_evidence = _has_snapshot(remedy_suggestion)
    has_potency_guidance = _has_snapshot(potency_guidance)
    has_relationship = _has_snapshot(relationship)
    has_follow_up = _has_snapshot(follow_up)
    fatal_missing = not str(prescription.get("remedy_name") or "").strip() or not potency.strip()

    score = 100
    score -= min(35, len(red_flags) * 18)
    score -= min(35, len(missing_information) * 7)

    if not has_remedy_evidence:
        score -= 8

    if not has_potency_guidance:
        score -= 8

    if not has_relationship:
        score -= 4

    if not has_follow_up and str(case_snapshot.get("visit_type") or "") == "follow_up":
        score -= 8

    safety_score = max(0, min(100, score))

    if fatal_missing:
        review_status = "blocked"
    elif red_flags:
        review_status = "safety_warning"
    elif missing_information:
        review_status = "incomplete"
    else:
        review_status = "needs_doctor_review"

    if _lang_bn(language):
        review_summary = (
            f"{remedy_name} {potency}".strip()
            + " prescription decision review তৈরি হয়েছে. "
            "AI safety, completeness, potency, repetition এবং relationship checkpoints সাজিয়েছে."
        )
        decision_guidance = (
            "সব required checklist item doctor confirm/override না করা পর্যন্ত prescription final করবেন না."
        )
        risk_summary = (
            f"Red flags: {len(red_flags)}, missing information: {len(missing_information)}, "
            f"safety score: {round(safety_score)}."
        )
        doctor_review_points = [
            "Current totality কি remedy selection support করছে?",
            "Potency এবং repetition কি sensitivity/vitality/pathology অনুযায়ী review করা হয়েছে?",
            "Relationship বা antidote/inimical caution থাকলে তা clinical context এ review করুন.",
            "Patient instructions, warning signs এবং follow-up plan পরিষ্কার আছে কি?",
        ]
        recommended_actions = [
            "প্রতিটি checklist item doctor confirm করুন.",
            "Missing information থাকলে prescription final করার আগে update করুন.",
            "Red flag থাকলে medical evaluation/referral প্রয়োজন কিনা বিবেচনা করুন.",
        ]
    elif language == "hi-IN":
        review_summary = (
            f"{remedy_name} {potency}".strip()
            + " prescription decision review तैयार है. "
            "AI ने safety, completeness, potency, repetition और relationship checkpoints बनाए हैं."
        )
        decision_guidance = (
            "सभी required checklist items doctor confirm/override किए बिना prescription final न करें."
        )
        risk_summary = (
            f"Red flags: {len(red_flags)}, missing information: {len(missing_information)}, "
            f"safety score: {round(safety_score)}."
        )
        doctor_review_points = [
            "Current totality remedy selection को support करती है?",
            "Potency और repetition sensitivity/vitality/pathology के अनुसार review हुए?",
            "Relationship या antidote/inimical caution clinical context में review करें.",
            "Patient instructions, warning signs और follow-up plan clear हैं?",
        ]
        recommended_actions = [
            "हर checklist item doctor confirm करें.",
            "Missing information हो तो prescription final करने से पहले update करें.",
            "Red flag हो तो medical evaluation/referral consider करें.",
        ]
    else:
        review_summary = (
            f"Prescription decision review generated for {remedy_name} {potency}".strip()
            + ". AI prepared safety, completeness, potency, repetition, and relationship checkpoints."
        )
        decision_guidance = (
            "Do not finalize the prescription until required checklist items are confirmed or deliberately overridden by the practitioner."
        )
        risk_summary = (
            f"Red flags: {len(red_flags)}, missing information: {len(missing_information)}, "
            f"safety score: {round(safety_score)}."
        )
        doctor_review_points = [
            "Does the current totality support the remedy selection?",
            "Have potency and repetition been reviewed against sensitivity, vitality, and pathology depth?",
            "If relationship or antidote/inimical cautions exist, review them in clinical context.",
            "Are patient instructions, warning signs, and follow-up plan clear?",
        ]
        recommended_actions = [
            "Confirm each checklist item as the practitioner.",
            "Update missing information before finalizing the prescription.",
            "If red flags exist, consider medical evaluation or referral where appropriate.",
        ]

    checks = [
        _review_check(
            check_key="red_flags_reviewed",
            category="safety",
            severity="critical" if red_flags else "important",
            status="warning" if red_flags else "passed",
            is_blocking=bool(red_flags),
            title="Red flags reviewed",
            description="Review urgent or medically concerning symptoms before final prescription.",
            ai_assessment=(
                "Red flags require practitioner review."
                if red_flags
                else "No active red flags were found in the available snapshot."
            ),
            evidence=red_flags,
        ),
        _review_check(
            check_key="missing_information_reviewed",
            category="case_completeness",
            severity="warning" if missing_information else "normal",
            status="warning" if missing_information else "passed",
            title="Missing information reviewed",
            description="Check unresolved case questions and incomplete prescription fields.",
            ai_assessment=(
                "Missing information should be resolved or knowingly accepted before finalizing."
                if missing_information
                else "No missing prescription-critical information was detected."
            ),
            evidence=missing_information,
        ),
        _review_check(
            check_key="remedy_evidence_reviewed",
            category="remedy",
            severity="important",
            status="pending",
            title="Remedy evidence reviewed by doctor",
            description="Confirm repertory, materia medica, and case-totality support for the remedy.",
            ai_assessment=(
                "A remedy suggestion snapshot is available for review."
                if has_remedy_evidence
                else "No remedy suggestion snapshot was available; doctor must confirm remedy evidence manually."
            ),
            evidence=[
                str(item.get("summary") or item.get("remedy_name") or "")
                for item in (remedy_suggestion.get("items") or [])[:3]
                if isinstance(item, dict)
            ],
        ),
        _review_check(
            check_key="potency_reviewed",
            category="potency",
            severity="important" if has_potency_guidance else "warning",
            status="pending",
            title="Potency reviewed",
            description="Confirm potency against case phase, vitality, sensitivity, pathology depth, and remedy certainty.",
            ai_assessment=(
                str(potency_guidance.get("guidance_summary") or "Potency guidance is available.")
                if has_potency_guidance
                else "No potency guidance snapshot was available."
            ),
            evidence=[
                str(potency_guidance.get("guidance_summary") or ""),
                *[str(item) for item in (potency_guidance.get("cautions") or [])[:3]],
            ],
        ),
        _review_check(
            check_key="repetition_reviewed",
            category="repetition",
            severity="important" if repetition else "warning",
            status="pending",
            title="Repetition reviewed",
            description="Confirm repetition is not mechanical and matches patient response and risk.",
            ai_assessment=(
                f"Current repetition instruction: {repetition}."
                if repetition
                else "Repetition instruction is missing or unclear."
            ),
            evidence=[str(potency_guidance.get("repetition_guidance") or "")],
        ),
        _review_check(
            check_key="relationship_reviewed",
            category="relationship",
            severity="important" if has_relationship else "normal",
            status="pending",
            is_required=False,
            title="Relationship cautions reviewed",
            description="Review complementary, follows-well, antidote, inimical, and sequence context when relevant.",
            ai_assessment=(
                str(relationship.get("relationship_summary") or "Relationship guidance is available.")
                if has_relationship
                else "No relationship guidance snapshot was available."
            ),
            evidence=[
                str(relationship.get("relationship_summary") or ""),
                str(relationship.get("inimical_warning") or ""),
            ],
        ),
        _review_check(
            check_key="follow_up_plan_reviewed",
            category="follow_up",
            severity="important" if follow_up_date else "warning",
            status="pending",
            title="Follow-up plan reviewed",
            description="Confirm follow-up timing, patient instructions, and escalation guidance.",
            ai_assessment=(
                f"Follow-up date: {follow_up_date}."
                if follow_up_date
                else "Follow-up date is not documented."
            ),
            evidence=[
                str(follow_up.get("analysis_summary") or ""),
                *[str(item) for item in (follow_up.get("recommended_next_steps") or [])[:3]],
            ],
        ),
        _review_check(
            check_key="patient_instructions_reviewed",
            category="documentation",
            severity="important",
            status="pending",
            title="Patient instructions reviewed",
            description="Confirm dose instructions, advice, warning signs, and lifestyle notes are clear enough for the patient.",
            ai_assessment=(
                "Dose instruction and advice are documented."
                if dose_instruction and str(prescription.get("advice") or "").strip()
                else "Patient-facing instruction fields need doctor review."
            ),
            evidence=[dose_instruction, str(prescription.get("advice") or "")],
        ),
    ]

    return PrescriptionReviewResponse(
        review_status=review_status,
        safety_score=round(safety_score, 2),
        review_summary=review_summary,
        decision_guidance=decision_guidance,
        risk_summary=risk_summary,
        red_flags=red_flags,
        missing_information=missing_information,
        doctor_review_points=doctor_review_points,
        recommended_actions=recommended_actions,
        checks=checks,
        safety_note=_prescription_review_safety_note(language),
    )


@app.post("/remedy/relationship", response_model=RemedyRelationshipResponse)
def remedy_relationship(
    payload: RemedyRelationshipRequest,
) -> RemedyRelationshipResponse:
    primary = _dict_or_empty(payload.primary_remedy)
    comparison = _dict_or_empty(payload.comparison_remedy)
    primary_name = str(primary.get("remedy_name") or "Primary remedy")
    comparison_name = str(comparison.get("remedy_name") or "")
    comparison_code = comparison.get("remedy_code")
    purpose_label = payload.purpose.replace("_", " ")
    relationship_text = _relationship_text_from_payload(payload)
    language = resolve_response_language(
        payload.response_language,
        relationship_text,
    )
    source_chunks = _relationship_source_chunks(payload.knowledge_chunks)
    relationship_types = _relationship_detect_types(relationship_text)

    if not relationship_types:
        relationship_types = ["compare" if comparison_name else "unknown"]

    findings: List[RemedyRelationshipFindingModel] = []
    confidence_base = 35 if not payload.knowledge_chunks else 55
    confidence = min(
        92,
        confidence_base
        + min(25, len(payload.knowledge_chunks) * 5)
        + (10 if comparison_name else 0),
    )

    for index, relationship_type in enumerate(relationship_types[:5]):
        evidence = _relationship_evidence(
            chunks=payload.knowledge_chunks,
            relationship_type=relationship_type,
            primary_name=primary_name,
            comparison_name=comparison_name,
        )
        related_name = comparison_name or None
        rel_label = relationship_type.replace("_", " ")

        if _lang_bn(language):
            summary = (
                f"Source chunks এ {primary_name}"
                + (f" এবং {comparison_name}" if comparison_name else "")
                + f" নিয়ে {rel_label} relationship signal পাওয়া গেছে."
            )
            clinical_note = (
                "এটি relationship clue মাত্র; current totality, remedy response, "
                "aggravation এবং generals review করে সিদ্ধান্ত নিন."
            )
        elif language == "hi-IN":
            summary = (
                f"Source chunks में {primary_name}"
                + (f" और {comparison_name}" if comparison_name else "")
                + f" के लिए {rel_label} relationship signal मिला."
            )
            clinical_note = (
                "यह relationship clue है; current totality, remedy response, "
                "aggravation और generals review करके निर्णय लें."
            )
        else:
            summary = (
                f"Source chunks suggest a {rel_label} relationship signal for {primary_name}"
                + (f" and {comparison_name}" if comparison_name else "")
                + "."
            )
            clinical_note = (
                "Use this only as a relationship clue; review current totality, "
                "remedy response, aggravation, and generals before action."
            )

        caution = None
        if relationship_type == "inimical":
            caution = (
                "Inimical warning: avoid mechanical sequencing or remedy change without careful review."
            )
            if _lang_bn(language):
                caution = "Inimical warning: careful review ছাড়া mechanical sequencing বা remedy change করবেন না."
            elif language == "hi-IN":
                caution = "Inimical warning: careful review के बिना mechanical sequencing या remedy change न करें."
        elif relationship_type == "antidote":
            caution = (
                "Antidote relationship does not automatically mean antidoting is needed."
            )
            if _lang_bn(language):
                caution = "Antidote relationship মানেই antidoting দরকার, এমন নয়."
            elif language == "hi-IN":
                caution = "Antidote relationship का मतलब automatic antidoting नहीं है."

        findings.append(
            RemedyRelationshipFindingModel(
                related_remedy_code=comparison_code,
                related_remedy_name=related_name,
                relationship_type=relationship_type,
                direction="unclear",
                rank=index + 1,
                confidence_score=round(confidence - (index * 4), 2),
                summary=summary,
                clinical_note=clinical_note,
                caution=caution,
                evidence=evidence,
                source_chunks=source_chunks,
                metadata={
                    "purpose": payload.purpose,
                    "source_chunks_count": len(payload.knowledge_chunks),
                },
            )
        )

    if _lang_bn(language):
        relationship_summary = (
            f"{primary_name}"
            + (f" ও {comparison_name}" if comparison_name else "")
            + f" এর remedy relationship review ({purpose_label}) তৈরি হয়েছে. "
            "Relationship, sequence, antidote এবং inimical points source chunks দিয়ে review করুন."
        )
        sequence_guidance = (
            "Remedy sequence করার আগে current case totality, first prescription response, "
            "old symptom return, aggravation এবং general wellbeing review করুন."
        )
        antidote_guidance = (
            "Antidote কেবল স্পষ্ট clinical need থাকলে বিবেচনা করুন; relationship book note একা যথেষ্ট নয়."
        )
        inimical_warning = (
            "Inimical বা sequence caution থাকলে repeat/change করার আগে extra clinical review প্রয়োজন."
        )
        complementary_note = (
            "Complementary relationship helpful clue হতে পারে, কিন্তু automatic remedy change নয়."
        )
        cautions = [
            "Doctor decides final repeat, wait, antidote, remedy change, or referral.",
            "Remedy names, potency names এবং source references অপরিবর্তিত রাখা হয়েছে.",
        ]
        doctor_review_points = [
            "বর্তমান totality এবং generals primary remedy-এর সাথে এখনও মেলে কি?",
            "Improvement এখনও চলছে কি, নাকি case stalled/worse হয়েছে?",
            "New symptoms remedy action, disease progress, না external factor থেকে এসেছে?",
            "Comparison remedy সত্যিই better similimum কিনা repertory ও materia medica দিয়ে confirm করুন.",
        ]
        suggested_questions = [
            "Primary remedy-এর পরে প্রথম কী পরিবর্তন হয়েছিল?",
            "কোন old symptom ফিরে এসেছে কি?",
            "Aggravation ছিল কি, এবং তার পরে improvement হয়েছে কি?",
            "Comparison remedy বিবেচনার প্রধান কারণ কী?",
        ]
    elif language == "hi-IN":
        relationship_summary = (
            f"{primary_name}"
            + (f" और {comparison_name}" if comparison_name else "")
            + f" के लिए remedy relationship review ({purpose_label}) तैयार है. "
            "Relationship, sequence, antidote और inimical points source chunks से review करें."
        )
        sequence_guidance = (
            "Remedy sequence से पहले current case totality, first prescription response, "
            "old symptom return, aggravation और general wellbeing review करें."
        )
        antidote_guidance = (
            "Antidote केवल clear clinical need पर consider करें; relationship book note अकेला काफी नहीं है."
        )
        inimical_warning = (
            "Inimical या sequence caution हो तो repeat/change से पहले extra clinical review करें."
        )
        complementary_note = (
            "Complementary relationship helpful clue हो सकता है, लेकिन automatic remedy change नहीं."
        )
        cautions = [
            "Doctor final repeat, wait, antidote, remedy change, या referral decide करेंगे.",
            "Remedy names, potency names और source references unchanged रखे गए हैं.",
        ]
        doctor_review_points = [
            "Current totality और generals अभी भी primary remedy से match करते हैं?",
            "Improvement जारी है या case stalled/worse हुआ?",
            "New symptoms remedy action, disease progress, या external factor से आए?",
            "Comparison remedy को repertory और materia medica से confirm करें.",
        ]
        suggested_questions = [
            "Primary remedy के बाद सबसे पहले क्या बदला?",
            "कोई old symptom वापस आया?",
            "Aggravation था, और उसके बाद improvement हुआ?",
            "Comparison remedy consider करने का मुख्य कारण क्या है?",
        ]
    else:
        relationship_summary = (
            f"Source-backed remedy relationship review generated for {primary_name}"
            + (f" and {comparison_name}" if comparison_name else "")
            + f" for {purpose_label}."
        )
        sequence_guidance = (
            "Before sequencing remedies, review the current totality, response to the first prescription, "
            "return of old symptoms, aggravation pattern, and general wellbeing."
        )
        antidote_guidance = (
            "Consider antidoting only when there is a clear clinical need; a relationship note alone is not enough."
        )
        inimical_warning = (
            "If an inimical or sequence caution is present, use extra review before repeating or changing remedies."
        )
        complementary_note = (
            "A complementary relationship can be a helpful clue, but it does not automatically justify a remedy change."
        )
        cautions = [
            "Doctor decides final repeat, wait, antidote, remedy change, or referral.",
            "Remedy names, potency names, and source references are preserved.",
        ]
        doctor_review_points = [
            "Does the current totality and generals still match the primary remedy?",
            "Is improvement still continuing, or has the case stalled or worsened?",
            "Are new symptoms from remedy action, disease progress, or an external factor?",
            "Confirm the comparison remedy through repertory and materia medica before changing.",
        ]
        suggested_questions = [
            "What changed first after the primary remedy?",
            "Did any old symptom return?",
            "Was there aggravation, and was it followed by improvement?",
            "What is the main reason for considering the comparison remedy?",
        ]

    return RemedyRelationshipResponse(
        relationship_summary=relationship_summary,
        sequence_guidance=sequence_guidance,
        antidote_guidance=antidote_guidance,
        inimical_warning=inimical_warning,
        complementary_note=complementary_note,
        cautions=cautions,
        doctor_review_points=doctor_review_points,
        suggested_questions=suggested_questions,
        findings=findings,
        safety_note=_relationship_safety_note(language),
    )


@app.post("/remedy/suggest", response_model=RemedySuggestResponse)
def remedy_suggest(payload: RemedySuggestRequest) -> RemedySuggestResponse:
    selected_rubrics = payload.selected_rubrics
    knowledge_chunks = payload.knowledge_chunks
    red_flags = payload.case_snapshot.get("red_flags") or []
    missing_questions = payload.case_snapshot.get("missing_questions") or []
    response_language = requested_response_language(
        payload.response_language,
        payload.settings,
    )
    language = resolve_response_language(
        response_language,
        str(payload.case_snapshot.get("raw_case_text") or ""),
        str(payload.case_snapshot.get("chief_complaint") or ""),
        str(payload.case_snapshot.get("case_summary") or ""),
    )
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
        if _lang_bn(language):
            summary = (
                f"{remedy_name} {method} repertorization result এ rank {rank} হিসেবে এসেছে. "
                "Rubric coverage এবং retrieved knowledge evidence দিয়ে support থাকলেও "
                "এটি শুধু doctor-reviewed possibility."
            )
        elif language == "hi-IN":
            summary = (
                f"{remedy_name} {method} repertorization result में rank {rank} पर है. "
                "Rubric coverage और retrieved knowledge evidence support करते हैं, "
                "लेकिन यह केवल doctor-reviewed possibility है."
            )
        else:
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

    safety_note = localized_safety_note(language, "remedy suggestion")

    if red_flags:
        safety_note += (
            " Red flags detected: " + "; ".join([str(flag) for flag in red_flags[:5]])
            if not _lang_bn(language)
            else " Red flags পাওয়া গেছে: " + "; ".join([str(flag) for flag in red_flags[:5]])
        )

    return RemedySuggestResponse(
        safety_note=safety_note,
        suggestions=suggestions,
        engine="local_remedy_suggestion_rag_v1",
    )
