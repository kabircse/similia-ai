from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_clinic_report_monthly_summary_uses_dashboard_snapshot():
    response = client.post(
        "/clinic-report/monthly-summary",
        json={
            "report_type": "monthly",
            "period_start": "2026-06-01",
            "period_end": "2026-06-30",
            "response_language": "en-US",
            "dashboard_snapshot": {
                "kpis": {
                    "new_patients": 2,
                    "visits": 5,
                    "follow_up_visits": 3,
                    "prescriptions": 4,
                    "outcome_analyses": 2,
                    "average_progress_score": 48.5,
                    "patient_handouts": 1,
                },
                "clinic_activity": {
                    "visits_by_day": [{"date": "2026-06-01", "total": 1}],
                    "new_patients_by_day": [{"date": "2026-06-01", "total": 1}],
                    "visit_type_distribution": [{"visit_type": "follow_up", "total": 3}],
                },
                "outcomes": {
                    "response_level_distribution": [{"response_level": "improved", "total": 2}],
                    "progress_score_trend": [{"date": "2026-06-01", "average_score": 48.5}],
                },
                "remedies": {
                    "top_prescribed_remedies": [{"remedy": "Calcarea carbonica", "total": 2}],
                    "top_potencies": [{"potency": "200C", "total": 2}],
                },
                "safety": {
                    "red_flag_count": 1,
                },
                "finance": {
                    "paid_amount": 3000,
                    "due_amount": 500,
                },
                "follow_ups": {
                    "overdue": [{"patient_id": 1}],
                    "due_next_7_days": [{"patient_id": 2}],
                },
            },
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert data["resolved_language"] == "en-US"
    assert data["key_metrics"]["visits"] == 5
    assert data["key_metrics"]["overdue_follow_ups"] == 1
    assert "Calcarea carbonica" in data["remedy_summary"]
    assert any(section["section_key"] == "finance" for section in data["sections"])
    assert "cure-rate claim" in data["safety_note"]


def test_clinic_report_honors_bangla_language_and_optional_sections():
    response = client.post(
        "/clinic-report/monthly-summary",
        json={
            "period_start": "2026-06-01",
            "period_end": "2026-06-30",
            "response_language": "bn-BD",
            "include_finance": False,
            "include_recommendations": False,
            "dashboard_snapshot": {
                "kpis": {
                    "visits": 1,
                    "new_patients": 1,
                    "prescriptions": 1,
                    "outcome_analyses": 0,
                },
                "finance": {
                    "paid_amount": 2000,
                    "due_amount": 0,
                },
                "follow_ups": {},
                "safety": {},
                "outcomes": {},
                "remedies": {},
            },
        },
    )

    assert response.status_code == 200

    data = response.json()

    assert data["resolved_language"] == "bn-BD"
    assert data["recommendations"] == []
    assert "অন্তর্ভুক্ত করা হয়নি" in data["finance_summary"]
    assert "internal audit" in data["safety_note"]
