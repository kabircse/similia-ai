from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_follow_up_analysis_extracts_progress_without_negated_red_flags():
    response = client.post(
        "/follow-up/analyze",
        json={
            "previous_visit": {
                "chief_complaint": "Anxiety, poor sleep, and low appetite.",
                "raw_case_text": "Patient was anxious and slept poorly.",
            },
            "current_visit": {
                "chief_complaint": "Follow-up",
                "raw_case_text": (
                    "Sleep improved. Anxiety reduced. "
                    "New mild headache started yesterday. "
                    "No chest pain, no breathing difficulty."
                ),
            },
            "prescription": {
                "remedy_name": "Calcarea carbonica",
                "potency": "200C",
            },
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert data["response_level"] in ["improved", "mixed"]
    assert any("sleep improved" in item for item in data["improvement_points"])
    assert any("anxiety reduced" in item for item in data["improvement_points"])
    assert any("headache" in item for item in data["new_symptoms"])
    assert data["red_flags"] == []
    assert "doctor-facing" in data["safety_note"]


def test_follow_up_analysis_reports_positive_red_flags():
    response = client.post(
        "/follow-up/analyze",
        json={
            "previous_visit": {
                "chief_complaint": "Breast pain.",
                "raw_case_text": "Breast pain, better warm application.",
            },
            "current_visit": {
                "chief_complaint": "Follow-up",
                "raw_case_text": "Pain is worse and now has chest pain with shortness of breath.",
            },
            "prescription": {
                "remedy_name": "Phytolacca",
                "potency": "30C",
            },
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert "Chest pain or cardiac warning" in data["red_flags"]
    assert "Breathing difficulty" in data["red_flags"]
    assert data["recommended_next_steps"][0].startswith("Red flags detected")
