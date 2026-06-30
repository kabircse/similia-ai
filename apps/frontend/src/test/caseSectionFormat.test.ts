import { describe, expect, it } from "vitest";
import { formatCaseSectionValue } from "../lib/caseSectionFormat";

describe("formatCaseSectionValue", () => {
  it("formats missing-question answer objects as text", () => {
    expect(
      formatCaseSectionValue({
        q_1_what_makes_the_breast_complaint_better_o: {
          category: "modalities",
          question: "What makes the breast complaint better or worse?",
          answer: "Worse before menses and better from warm compress.",
        },
      })
    ).toContain(
      "What makes the breast complaint better or worse?: Worse before menses"
    );
  });
});
