from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_potency_guidance_returns_source_backed_options():
    response = client.post(
        "/potency/guidance",
        json={
            "case_snapshot": {
                "chief_complaint": "Chronic anxiety and chilly constitution.",
                "raw_case_text": "Chilly, low thirst, desire sweets for years.",
            },
            "prescription_snapshot": {
                "remedy_name": "Calcarea carbonica",
                "potency": "200C",
                "repetition": "single dose",
            },
            "settings": {
                "case_phase": "chronic",
                "patient_sensitivity": "moderate",
                "vitality_level": "moderate",
                "pathology_depth": "functional",
            },
            "knowledge_chunks": [
                {
                    "id": 1,
                    "source_type": "potency",
                    "source_title": "Potency Notes",
                    "content": "Do not repeat while improvement continues.",
                }
            ],
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert data["case_phase"] == "chronic"
    assert data["vitality_level"] == "moderate"
    assert data["sensitivity_level"] == "moderate"
    assert data["pathology_depth"] == "functional"
    assert data["options"][0]["potency_range"] == "medium"
    assert data["options"][0]["source_chunks"][0]["source_title"] == "Potency Notes"
    assert "not an automatic potency prescription" in data["safety_note"]


def test_potency_guidance_uses_caution_for_high_sensitivity():
    response = client.post(
        "/potency/guidance",
        json={
            "case_snapshot": {
                "chief_complaint": "Patient is very sensitive and reacts strongly.",
                "raw_case_text": "Medicine aggravates easily. Very weak.",
            },
            "remedy": {
                "remedy_name": "Pulsatilla",
            },
            "settings": {
                "case_phase": "constitutional",
            },
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert data["sensitivity_level"] == "high"
    assert data["vitality_level"] == "low"
    assert data["options"][0]["potency_range"] == "low"
    assert any("High sensitivity" in caution for caution in data["cautions"])


def test_potency_guidance_accepts_empty_php_array_snapshots():
    response = client.post(
        "/potency/guidance",
        json={
            "case_snapshot": {
                "chief_complaint": "Chronic chilly patient.",
            },
            "prescription_snapshot": [],
            "follow_up_snapshot": [],
            "remedy": [],
            "settings": {
                "case_phase": "unclear",
            },
            "knowledge_chunks": [],
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert data["case_phase"] == "chronic"
    assert "selected remedy" in data["guidance_summary"]


def test_potency_guidance_honors_bangla_response_language():
    response = client.post(
        "/potency/guidance",
        json={
            "case_snapshot": {
                "chief_complaint": "Chronic chilly patient with low thirst.",
                "raw_case_text": "Chilly, low thirst, desire sweets.",
            },
            "prescription_snapshot": {
                "remedy_name": "Calcarea carbonica",
                "potency": "200C",
            },
            "settings": {
                "case_phase": "chronic",
                "patient_sensitivity": "moderate",
                "vitality_level": "moderate",
                "pathology_depth": "functional",
                "response_language": "bn-BD",
            },
            "knowledge_chunks": [],
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert "Calcarea carbonica" in data["guidance_summary"]
    assert "potency guidance" in data["guidance_summary"]
    assert "চূড়ান্ত সিদ্ধান্ত" in data["safety_note"]
