import { useState } from "react";
import { ChevronDown, ChevronUp } from "lucide-react";
import type { RepertorizationResult } from "../../lib/api";

type RepertorizationResultCardProps = {
  result: RepertorizationResult;
  scoreLabel?: string;
  showCoveragePercent?: boolean;
};

export function RepertorizationResultCard({
  result,
  scoreLabel = "Score",
  showCoveragePercent = false,
}: RepertorizationResultCardProps) {
  const [open, setOpen] = useState(false);
  const coveragePercent = result.metrics?.coverage_percent;

  return (
    <div className="repertory-result-card">
      <div className="repertory-result-summary">
        <div>
          <span className="rank-badge">#{result.rank}</span>
        </div>

        <div>
          <h4>{result.remedy_name}</h4>
          <p>
            {scoreLabel} {result.total_score} · Covered {result.rubric_coverage} rubrics · Essential {result.essential_coverage}
            {showCoveragePercent && coveragePercent ? ` · Coverage ${coveragePercent}%` : ""}
          </p>
        </div>

        <button className="secondary-button" onClick={() => setOpen(!open)}>
          {open ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
          Details
        </button>
      </div>

      {open && (
        <div className="repertory-result-details">
          <h5>Supporting Rubrics</h5>

          <div className="score-breakdown-list">
            {result.supporting_rubrics.map((rubric) => (
              <div className="score-breakdown-item" key={rubric.case_rubric_id}>
                <div>
                  <strong>{rubric.rubric_path}</strong>
                  <p>
                    {rubric.symptom_type} · {rubric.importance}
                    {rubric.is_essential ? " · essential" : ""}
                  </p>
                </div>

                <span>
                  {rubric.rubric_weight} × {rubric.remedy_grade} = <strong>{rubric.score}</strong>
                </span>
              </div>
            ))}
          </div>

          {result.missing_important_rubrics.length > 0 && (
            <>
              <h5>Missing Important Rubrics</h5>

              <ul className="clinical-list">
                {result.missing_important_rubrics.map((rubric) => (
                  <li key={rubric.case_rubric_id}>
                    {rubric.rubric_path} {rubric.is_essential ? "(essential)" : `(${rubric.importance})`}
                  </li>
                ))}
              </ul>
            </>
          )}
        </div>
      )}
    </div>
  );
}
