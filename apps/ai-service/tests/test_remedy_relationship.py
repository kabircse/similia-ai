from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_remedy_relationship_returns_source_backed_findings():
    response = client.post(
        "/remedy/relationship",
        json={
            "primary_remedy": {
                "remedy_code": "calc",
                "remedy_name": "Calcarea carbonica",
            },
            "comparison_remedy": {
                "remedy_code": "sulph",
                "remedy_name": "Sulphur",
            },
            "purpose": "change_remedy",
            "knowledge_chunks": [
                {
                    "id": 1,
                    "source_type": "relationship",
                    "source_title": "Relationship of Remedies",
                    "title": "Calcarea carbonica",
                    "content": "Calcarea carbonica. Complementary: Sulphur. Follows well after Belladonna.",
                }
            ],
            "response_language": "en-US",
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert "Calcarea carbonica" in data["relationship_summary"]
    assert "Sulphur" in data["relationship_summary"]
    assert data["findings"][0]["relationship_type"] in ["follows_well", "complementary"]
    assert data["findings"][0]["related_remedy_name"] == "Sulphur"
    assert data["findings"][0]["source_chunks"][0]["source_title"] == "Relationship of Remedies"
    assert "not an automatic prescription" in data["safety_note"]


def test_remedy_relationship_honors_bangla_response_language():
    response = client.post(
        "/remedy/relationship",
        json={
            "primary_remedy": {
                "remedy_name": "Nux vomica",
            },
            "comparison_remedy": {
                "remedy_name": "Sulphur",
            },
            "purpose": "antidote_check",
            "knowledge_chunks": [
                {
                    "source_type": "relationship",
                    "source_title": "Relationship Notes",
                    "content": "Nux vomica. Antidote: Sulphur. Inimical caution should be reviewed.",
                }
            ],
            "response_language": "bn-BD",
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert "Nux vomica" in data["relationship_summary"]
    assert "Sulphur" in data["relationship_summary"]
    assert "চূড়ান্ত clinical action" in data["safety_note"]
    assert any(
        finding["relationship_type"] == "antidote"
        for finding in data["findings"]
    )
