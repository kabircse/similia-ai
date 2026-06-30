from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_missing_question_conversation_start_uses_existing_questions():
    response = client.post(
        "/case/missing-question-conversation/start",
        json={
            "language": "en-US",
            "max_questions": 3,
            "missing_questions": [
                "How is thirst: low, normal, high, or thirstless?",
                "Any fears, anxiety, grief, anger, or emotional causation?",
            ],
            "red_flags": ["Breast discharge should be medically evaluated."],
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert len(data["questions"]) == 3
    assert data["questions"][0]["category"] == "thirst"
    assert data["questions"][1]["importance"] == "important"
    assert data["questions"][2]["importance"] == "red_flag"


def test_missing_question_apply_answer_returns_case_update():
    response = client.post(
        "/case/missing-question-conversation/apply-answer",
        json={
            "question_key": "q_1_thermal",
            "category": "thermal_state",
            "question": "Is the patient generally worse from heat or cold?",
            "answer": "The patient is chilly and worse from cold wind.",
            "existing_case_sections": {},
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert data["case_section_updates"]["thermal_state"] == (
        "The patient is chilly and worse from cold wind."
    )
    assert "q_1_thermal" in data["case_section_updates"]["missing_question_answers"]
    assert "Q: Is the patient" in data["raw_case_note"]
