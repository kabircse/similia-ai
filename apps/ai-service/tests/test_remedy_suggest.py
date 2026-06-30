from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_remedy_suggest_returns_structured_suggestions():
    response = client.post(
        "/remedy/suggest",
        json={
            "repertorization_run": {"method": "weighted"},
            "case_snapshot": {
                "missing_questions": ["How is thirst?"],
                "red_flags": [],
            },
            "selected_rubrics": [
                {
                    "rubric_path": "Mind > Anxiety",
                    "importance": "important",
                    "weight": 2,
                    "is_essential": False,
                }
            ],
            "candidates": [
                {
                    "remedy_code": "calc",
                    "remedy_name": "Calcarea Carbonica",
                    "rank": 1,
                    "total_score": 24,
                    "rubric_coverage": 1,
                    "essential_coverage": 0,
                    "repertory_evidence": {
                        "supporting_rubrics": [
                            {
                                "rubric_path": "Mind > Anxiety",
                                "remedy_grade": 2,
                            }
                        ]
                    },
                    "materia_medica_chunks": [
                        {
                            "section": "Generals",
                            "content": "Chilly patient with anxiety and weakness.",
                        }
                    ],
                }
            ],
            "knowledge_chunks": [],
            "retrieved_sources": {},
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert data["suggestions"][0]["remedy_code"] == "calc"
    assert data["suggestions"][0]["evidence_matrix"][0]["covered"] is True
    assert "doctor-facing" in data["safety_note"]
