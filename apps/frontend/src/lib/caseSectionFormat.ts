export function formatCaseSectionValue(value: unknown): string {
  if (value === null || value === undefined) {
    return "-";
  }

  if (typeof value === "string") {
    return value.trim() === "" ? "-" : value;
  }

  if (typeof value === "number" || typeof value === "boolean") {
    return String(value);
  }

  if (Array.isArray(value)) {
    if (value.length === 0) {
      return "-";
    }

    return value.map((item) => formatCaseSectionValue(item)).join("; ");
  }

  if (typeof value === "object") {
    const entries = Object.entries(value as Record<string, unknown>);

    if (entries.length === 0) {
      return "-";
    }

    return entries
      .map(([key, item]) => {
        if (
          item &&
          typeof item === "object" &&
          !Array.isArray(item) &&
          ("question" in item || "answer" in item)
        ) {
          const detail = item as Record<string, unknown>;
          const question =
            typeof detail.question === "string" ? detail.question : key;
          const answer =
            typeof detail.answer === "string"
              ? detail.answer
              : formatCaseSectionValue(detail.answer);

          return `${question}: ${answer}`;
        }

        return `${key.replaceAll("_", " ")}: ${formatCaseSectionValue(item)}`;
      })
      .join("\n");
  }

  return String(value);
}
