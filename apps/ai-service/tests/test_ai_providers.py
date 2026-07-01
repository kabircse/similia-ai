from fastapi.testclient import TestClient

from app.main import app
from app.services.ai_providers import (
    DeterministicAIProvider,
    OllamaAIProvider,
    OpenAIReadyProvider,
    generate_with_provider_fallback,
    get_ai_provider,
)

client = TestClient(app)


def _clear_provider_env(monkeypatch):
    for key in [
        "AI_PROVIDER",
        "AI_PROVIDER_FALLBACK",
        "AI_PROVIDER_TIMEOUT_SECONDS",
        "OLLAMA_BASE_URL",
        "OLLAMA_MODEL",
        "OPENAI_BASE_URL",
        "OPENAI_API_KEY",
        "OPENAI_MODEL",
    ]:
        monkeypatch.delenv(key, raising=False)


def test_deterministic_provider_returns_safe_response():
    result = DeterministicAIProvider().generate_json(
        task="test_task",
        system_prompt="System",
        user_prompt="User content",
        metadata={"endpoint": "/test"},
    )

    assert result["provider"] == "deterministic"
    assert result["task"] == "test_task"
    assert result["content"] == "User content"
    assert result["structured"] == {}
    assert "Doctor review is required" in result["safety_note"]
    assert result["metadata"]["endpoint"] == "/test"


def test_factory_defaults_to_deterministic(monkeypatch):
    _clear_provider_env(monkeypatch)

    assert get_ai_provider().name == "deterministic"


def test_factory_selects_ollama_when_configured(monkeypatch):
    _clear_provider_env(monkeypatch)
    monkeypatch.setenv("AI_PROVIDER", "ollama")
    monkeypatch.setenv("OLLAMA_MODEL", "llama3.1")

    provider = get_ai_provider()

    assert provider.name == "ollama"
    assert provider.model == "llama3.1"


def test_ollama_provider_parses_json_response(monkeypatch):
    _clear_provider_env(monkeypatch)
    monkeypatch.setenv("OLLAMA_BASE_URL", "http://ollama.test")

    class FakeResponse:
        def raise_for_status(self):
            return None

        def json(self):
            return {"response": '{"summary": "ready"}'}

    def fake_post(url, json, timeout):
        assert url == "http://ollama.test/api/generate"
        assert json["stream"] is False
        assert timeout == 30
        return FakeResponse()

    monkeypatch.setattr("app.services.ai_providers.ollama.httpx.post", fake_post)

    result = OllamaAIProvider().generate_json(
        task="test_task",
        system_prompt="System",
        user_prompt="User content",
    )

    assert result["provider"] == "ollama"
    assert result["task"] == "test_task"
    assert result["summary"] == "ready"


def test_openai_provider_throws_clear_error_when_missing_key_or_model(monkeypatch):
    _clear_provider_env(monkeypatch)
    monkeypatch.setenv("AI_PROVIDER", "openai")

    provider = OpenAIReadyProvider()

    try:
        provider.generate_json(
            task="test_task",
            system_prompt="System",
            user_prompt="User content",
        )
    except RuntimeError as error:
        assert "OPENAI_API_KEY or OPENAI_MODEL is missing" in str(error)
    else:
        raise AssertionError("Expected OpenAI provider to require credentials.")


def test_fallback_returns_deterministic_when_selected_provider_fails(monkeypatch):
    _clear_provider_env(monkeypatch)
    monkeypatch.setenv("AI_PROVIDER", "openai")
    monkeypatch.setenv("AI_PROVIDER_FALLBACK", "deterministic")

    result = generate_with_provider_fallback(
        task="fallback_test",
        system_prompt="System",
        user_prompt="User content",
    )

    assert result["provider_used"] == "deterministic"
    assert result["fallback_used"] is True
    assert result["failed_provider"] == "openai"
    assert result["metadata"]["failed_provider"] == "openai"


def test_provider_status_endpoint_defaults_to_deterministic(monkeypatch):
    _clear_provider_env(monkeypatch)

    response = client.get("/ai/provider")

    assert response.status_code == 200

    data = response.json()

    assert data["provider"] == "deterministic"
    assert data["fallback_provider"] == "deterministic"
    assert data["timeout_seconds"] == 30
    assert data["openai_configured"] is False
