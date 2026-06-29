import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { BarChart3, Calculator, ChevronDown, ChevronUp } from "lucide-react";
import { useState } from "react";
import {
  getRepertorizationRuns,
  getVisitRubrics,
  type RepertorizationResult,
  runWeightedRepertorization,
} from "../../lib/api";

type WeightedRepertorizationPanelProps = {
  patientId: string;
  visitId: string;
};

function ResultDetails({ result }: { result: RepertorizationResult }) {
  const [open, setOpen] = useState(false);

  return (
    <div className="repertory-result-card">
      <div className="repertory-result-summary">
        <div>
          <span className="rank-badge">#{result.rank}</span>
        </div>

        <div>
          <h4>{result.remedy_name}</h4>
          <p>
            Score {result.total_score} · Covered {result.rubric_coverage} rubrics
            · Essential {result.essential_coverage}
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
                  {rubric.rubric_weight} × {rubric.remedy_grade} ={" "}
                  <strong>{rubric.score}</strong>
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
                    {rubric.rubric_path}{" "}
                    {rubric.is_essential ? "(essential)" : `(${rubric.importance})`}
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

export function WeightedRepertorizationPanel({
  patientId,
  visitId,
}: WeightedRepertorizationPanelProps) {
  const queryClient = useQueryClient();

  const rubricsQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "rubrics"],
    queryFn: () => getVisitRubrics(patientId, visitId),
  });

  const runsQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "repertorization-runs"],
    queryFn: () => getRepertorizationRuns(patientId, visitId),
  });

  const runMutation = useMutation({
    mutationFn: () => runWeightedRepertorization(patientId, visitId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits", visitId, "repertorization-runs"],
      });
    },
  });

  const selectedRubricsCount = rubricsQuery.data?.data.length ?? 0;
  const latestRun = runsQuery.data?.data[0];

  return (
    <section className="panel">
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>Weighted Repertorization</h3>
          <p className="panel-subtitle">
            Calculates remedy ranking using rubric weight × remedy grade.
          </p>
        </div>

        <button
          className="primary-button inline-button"
          disabled={runMutation.isPending || selectedRubricsCount === 0}
          onClick={() => runMutation.mutate()}
        >
          <Calculator size={16} />
          {runMutation.isPending ? "Calculating..." : "Run Weighted"}
        </button>
      </div>

      {selectedRubricsCount === 0 && (
        <p className="empty-state">
          Select rubrics first before running repertorization.
        </p>
      )}

      {runMutation.isError && (
        <div className="form-error">
          Unable to run repertorization. Please check selected rubrics.
        </div>
      )}

      {!latestRun && selectedRubricsCount > 0 && (
        <div className="empty-panel compact-empty">
          <BarChart3 size={34} />
          <h3>No repertorization yet</h3>
          <p>Run weighted repertorization to rank possible remedies.</p>
        </div>
      )}

      {latestRun && (
        <div className="repertory-run-panel">
          <div className="run-meta">
            <span>Method: {latestRun.method}</span>
            <span>Total rubrics: {latestRun.total_rubrics}</span>
            <span>Essential: {latestRun.essential_rubrics_count}</span>
          </div>

          <div className="repertory-results-list">
            {latestRun.results.slice(0, 10).map((result) => (
              <ResultDetails key={result.id} result={result} />
            ))}
          </div>
        </div>
      )}
    </section>
  );
}