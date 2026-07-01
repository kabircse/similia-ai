from pathlib import Path
import os
from typing import Any

from dotenv import load_dotenv

from .base import AIProvider
from .deterministic import DeterministicAIProvider
from .ollama import OllamaAIProvider
from .openai_ready import OpenAIReadyProvider


SERVICE_ROOT = Path(__file__).resolve().parents[3]
load_dotenv(SERVICE_ROOT / ".env")


def _selected_provider_name(value: str | None) -> str:
    provider = (value or "deterministic").lower().strip()

    if provider in {"deterministic", "ollama", "openai"}:
        return provider

    return "deterministic"


def get_ai_provider() -> AIProvider:
    provider = _selected_provider_name(os.getenv("AI_PROVIDER"))

    if provider == "ollama":
        return OllamaAIProvider()

    if provider == "openai":
        return OpenAIReadyProvider()

    return DeterministicAIProvider()


def get_fallback_provider() -> AIProvider:
    fallback = (os.getenv("AI_PROVIDER_FALLBACK", "deterministic") or "").lower().strip()

    if fallback == "none":
        raise RuntimeError("No fallback AI provider configured.")

    return DeterministicAIProvider()


def provider_timeout_seconds() -> int:
    try:
        return int(os.getenv("AI_PROVIDER_TIMEOUT_SECONDS", "30"))
    except ValueError:
        return 30


def generate_with_provider_fallback(
    *,
    task: str,
    system_prompt: str,
    user_prompt: str,
    schema_hint: dict[str, Any] | None = None,
    metadata: dict[str, Any] | None = None,
) -> dict[str, Any]:
    provider = get_ai_provider()

    try:
        result = provider.generate_json(
            task=task,
            system_prompt=system_prompt,
            user_prompt=user_prompt,
            schema_hint=schema_hint,
            metadata=metadata,
        )
        result.setdefault("provider_used", provider.name)
        result.setdefault("fallback_used", False)
        return result
    except Exception as error:
        fallback = get_fallback_provider()

        result = fallback.generate_json(
            task=task,
            system_prompt=system_prompt,
            user_prompt=user_prompt,
            schema_hint=schema_hint,
            metadata={
                **(metadata or {}),
                "provider_error": str(error),
                "failed_provider": provider.name,
            },
        )

        result.setdefault("provider_used", fallback.name)
        result.setdefault("fallback_used", True)
        result.setdefault("failed_provider", provider.name)

        return result
