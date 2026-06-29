import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { GitBranch, Network } from "lucide-react";
import {
  getRepertorizationRuns,
  getVisitRubrics,
  runCrossRepertorization,
} from "../../lib/api";
import { RepertorizationResultCard } from "./RepertorizationResultCard";

type CrossRepertorizationPanelProps = {
  patientId: string;
  visitId: string;
};

export function CrossRepertorizationPanel({
  patientId,
  visitId,
}: CrossRepertorizationPanelProps) {
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
      "cross",
    ],
    queryFn: () => getRepertorizationRuns(patientId, visitId, "cross"),
  });

  const runMutation = useMutation({
    mutationFn: () => runCrossRepertorization(patientId, visitId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: [
          "patients",
          patientId,
          "visits",
          visitId,
          "repertorization-runs",
          "cross",
        ],
      });
    },
  });

  const selectedRubricsCount = rubricsQuery.data?.data.length ?? 0;
  const latestRun = runsQuery.data?.data[0];

  return (
    <section className="panel">
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>Cross Repertorization</h3>
          <p className="panel-subtitle">
            Finds remedies that appear across the highest number of selected rubrics.
          </p>
        </div>

        <button
          className="primary-button inline-button"
          disabled={runMutation.isPending || selectedRubricsCount === 0}
          onClick={() => runMutation.mutate()}
        >
          <GitBranch size={16} />
          {runMutation.isPending ? "Calculating..." : "Run Cross"}
        </button>
      </div>

      {selectedRubricsCount === 0 && (
        <p className="empty-state">
          Select rubrics first before running cross repertorization.
        </p>
      )}

      {runMutation.isError && (
        <div className="form-error">
          Unable to run cross repertorization. Please check selected rubrics.
        </div>
      )}

      {!latestRun && selectedRubricsCount > 0 && (
        <div className="empty-panel compact-empty">
          <Network size={34} />
          <h3>No cross repertorization yet</h3>
          <p>Run cross repertorization to find remedies common across rubrics.</p>
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
              <RepertorizationResultCard
                key={result.id}
                result={result}
                scoreLabel="Cross Score"
                showCoveragePercent
              />
            ))}
          </div>
        </div>
      )}
    </section>
  );
}
