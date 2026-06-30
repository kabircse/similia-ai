from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_case_structure_detects_chilly_low_thirst_and_red_flag():
    response = client.post(
        "/case/structure",
        json={
            "raw_text": "Female 26. Chilly. Low thirst. Fear of cancer. Left breast discharge.",
            "chief_complaint": "Breast discharge and fear of cancer",
            "existing_case_sections": {},
        },
    )

    assert response.status_code == 200

    data = response.json()["data"]

    assert "case_sections" in data
    assert "missing_questions" in data
    assert "red_flags" in data
    assert len(data["red_flags"]) >= 1
    assert "Chilly patient" in data["case_sections"]["thermal_state"]
    assert "Low thirst" in data["case_sections"]["thirst"]
