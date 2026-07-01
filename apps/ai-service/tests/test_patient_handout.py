from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_patient_handout_generates_patient_friendly_sections():
    response = client.post(
        "/patient-handout/generate",
        json={
            "case_snapshot": {
                "patient_name": "Test Patient",
                "chief_complaint": "Chilly anxiety",
                "raw_case_text": "Chilly, low thirst, desire sweets.",
                "red_flags": [],
            },
            "prescription_snapshot": {
                "remedy_name": "Calcarea carbonica",
                "potency": "200C",
                "repetition": "single dose",
                "dose_instruction": "Take one dose at night.",
                "advice": "Report any aggravation.",
                "food_lifestyle_note": "Sleep early and avoid excess stimulants.",
                "follow_up_date": "2026-08-01",
            },
            "clinic_snapshot": {
                "clinic_name": "Kabir's Homeopathic Center",
                "doctor_name": "Dr. Kabir",
                "prescription_footer": "Follow the doctor-approved instructions.",
            },
            "response_language": "en-US",
            "style": "detailed",
        },
    )

    assert response.status_code == 200

    data = response.json()
    body = str(data).lower()

    assert data["resolved_language"] == "en-US"
    assert data["title"] == "Patient Treatment Instructions"
    assert "Calcarea carbonica 200C" in data["medicine_instruction"]
    assert any(section["section_key"] == "medicine" for section in data["sections"])
    assert any(section["section_key"] == "what_to_expect" for section in data["sections"])
    assert "doctor-approved" in data["footer_note"]
    assert "repertorization" not in body
    assert "materia medica" not in body
    assert "internal" not in body


def test_patient_handout_honors_bangla_language_and_warning_controls():
    response = client.post(
        "/patient-handout/generate",
        json={
            "case_snapshot": {
                "patient_name": "রহিমা",
                "chief_complaint": "বুক ব্যথা",
                "raw_case_text": "রোগীর বুক ব্যথা আছে।",
                "red_flags": ["Chest pain may need urgent medical evaluation."],
            },
            "prescription_snapshot": {
                "remedy_name": "Nux vomica",
                "potency": "30C",
                "repetition": "single dose",
                "dose_instruction": "রাতে এক ডোজ।",
            },
            "response_language": "bn-BD",
            "include_do_and_dont": False,
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert data["resolved_language"] == "bn-BD"
    assert data["title"] == "রোগীর জন্য চিকিৎসা নির্দেশনা"
    assert "Nux vomica" in data["medicine_instruction"]
    assert data["do_and_dont"] == []
    assert "Chest pain may need urgent medical evaluation." in data["warning_signs"]
    assert "চিকিৎসকের" in data["safety_note"]

