from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_prescription_review_generates_required_checklist():
    response = client.post(
        "/prescription/review",
        json={
            "case_snapshot": {
                "chief_complaint": "Chilly anxiety",
                "raw_case_text": "Chilly, low thirst, desire sweets.",
                "red_flags": [],
                "missing_questions": [],
            },
            "prescription_snapshot": {
                "remedy_name": "Calcarea carbonica",
                "remedy_code": "calc",
                "potency": "200C",
                "repetition": "single dose",
                "dose_instruction": "Take one dose at night.",
                "reason": "Matches totality.",
                "advice": "Report aggravation.",
                "follow_up_date": "2026-08-01",
            },
            "remedy_suggestion_snapshot": {
                "items": [
                    {
                        "remedy_name": "Calcarea carbonica",
                        "summary": "Ranked candidate with matching chilly generals.",
                    }
                ]
            },
            "potency_guidance_snapshot": {
                "guidance_summary": "Medium potency consideration.",
                "repetition_guidance": "Do not repeat while improving.",
            },
            "relationship_snapshot": {
                "relationship_summary": "No inimical warning found.",
            },
            "response_language": "en-US",
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert data["review_status"] == "needs_doctor_review"
    assert data["safety_score"] > 70
    assert "Calcarea carbonica" in data["review_summary"]
    assert any(
        check["check_key"] == "red_flags_reviewed"
        for check in data["checks"]
    )
    assert any(
        check["check_key"] == "potency_reviewed"
        for check in data["checks"]
    )
    assert "AI does not finalize" in data["safety_note"]


def test_prescription_review_reports_bangla_safety_warning():
    response = client.post(
        "/prescription/review",
        json={
            "case_snapshot": {
                "chief_complaint": "বুক ব্যথা",
                "raw_case_text": "রোগীর বুক ব্যথা আছে।",
                "red_flags": ["Chest pain may require urgent medical evaluation."],
            },
            "prescription_snapshot": {
                "remedy_name": "Nux vomica",
                "potency": "30C",
                "repetition": "",
            },
            "response_language": "bn-BD",
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert data["review_status"] == "safety_warning"
    assert "Nux vomica" in data["review_summary"]
    assert "Doctor checklist confirm" in data["safety_note"]
    assert data["red_flags"] == ["Chest pain may require urgent medical evaluation."]
    assert any(check["is_blocking"] for check in data["checks"])
