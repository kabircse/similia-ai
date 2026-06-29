import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Filter, ShieldCheck } from "lucide-react";
import {
  getRepertorizationRuns,
  getVisitRubrics,
  runEliminativeRepertorization,
} from "../../lib/api";
import { RepertorizationResultCard } from "./RepertorizationResultCard";

type EliminativeRepertorizationPanelProps = {
  patientId: string;
  visitId: string;
};

export function EliminativeRepertorizationPanel({
  patientId,
  visitId,
}: EliminativeRepertorizationPanelProps) {
  const queryClient = useQueryClient();

  const rubricsQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "rubrics"],
    queryFn: () => getVisitRubrics(patientId, visitId),
  });

  const runsQuery = useQuery({
    queryKey: [
      "patients",
      patientId,
      "visits",
      visitId,
      "repertorization-runs",
      "eliminative",
    ],
    queryFn: () => getRepertorizationRuns(patientId, visitId, "eliminative"),
  });

  const runMutation = useMutation({
    mutationFn: () => runEliminativeRepertorization(patientId, visitId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: [
          "patients",
          patientId,
          "visits",
          visitId,
          "repertorization-runs",
          "eliminative",
        ],
      });

      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "timeline"],
      });
    },
  });

  const selectedRubrics = rubricsQuery.data?.data ?? [];
  const selectedRubricsCount = selectedRubrics.length;
  const essentialRubricsCount = selectedRubrics.filter(
    (rubric) => rubric.is_essential || rubric.importance === "essential"
  ).length;

  const latestRun = runsQuery.data?.data[0];

  return (
    <section className="panel">
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>Eliminative Repertorization</h3>
          <p className="panel-subtitle">
            Keeps only remedies that cover every essential rubric.
          </p>
        </div>

        <button
          className="primary-button inline-button"
          disabled={
            runMutation.isPending ||
            selectedRubricsCount === 0 ||
            essentialRubricsCount === 0
          }
          onClick={() => runMutation.mutate()}
        >
          <Filter size={16} />
          {runMutation.isPending ? "Filtering..." : "Run Eliminative"}
        </button>
      </div>

      {selectedRubricsCount === 0 && (
        <p className="empty-state">
          Select rubrics first before running eliminative repertorization.
        </p>
      )}

      {selectedRubricsCount > 0 && essentialRubricsCount === 0 && (
        <div className="warning-panel">
          Mark at least one selected rubric as essential before running
          eliminative repertorization.
        </div>
      )}

      {runMutation.isError && (
        <div className="form-error">
          Unable to run eliminative repertorization. No remedy may cover all
          essential rubrics, or no essential rubric was selected.
        </div>
      )}

      {!latestRun && selectedRubricsCount > 0 && essentialRubricsCount > 0 && (
        <div className="empty-panel compact-empty">
          <ShieldCheck size={34} />
          <h3>No eliminative run yet</h3>
          <p>
            Run eliminative repertorization to filter remedies by essential
            rubrics.
          </p>
        </div>
      )}

      {latestRun && (
        <div className="repertory-run-panel">
          <div className="run-meta">
            <span>Method: {latestRun.method}</span>
            <span>Total rubrics: {latestRun.total_rubrics}</span>
            <span>Essential: {latestRun.essential_rubrics_count}</span>
            <span>
              Passed: {String(latestRun.settings?.total_remedies_after_elimination ?? "-")}
            </span>
          </div>

          <div className="repertory-results-list">
            {latestRun.results.slice(0, 10).map((result) => (
              <RepertorizationResultCard
                key={result.id}
                result={result}
                scoreLabel="Eliminative Score"
                showCoveragePercent
              />
            ))}
          </div>
        </div>
      )}
    </section>
  );
}
