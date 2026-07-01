import { useMutation, useQueryClient } from "@tanstack/react-query";
import { BookOpenCheck } from "lucide-react";
import { useState } from "react";
import {
  compareMateriaMedica,
  queueMateriaMedicaComparison,
  type MateriaMedicaComparisonResponse,
  type MateriaMedicaMethod,
} from "../../lib/api";
import { AiTaskStatus } from "../ai/AiTaskStatus";

type MateriaMedicaComparisonPanelProps = {
  patientId: string;
  visitId: string;
};

export function MateriaMedicaComparisonPanel({
  patientId,
  visitId,
}: MateriaMedicaComparisonPanelProps) {
  const queryClient = useQueryClient();
  const [method, setMethod] = useState<MateriaMedicaMethod>("weighted");
  const [comparison, setComparison] =
    useState<MateriaMedicaComparisonResponse | null>(null);
  const [comparisonTaskId, setComparisonTaskId] = useState<number | null>(null);

  const compareMutation = useMutation({
    mutationFn: () => compareMateriaMedica(patientId, visitId, method),
    onSuccess: (data) => {
      setComparison(data);
    },
  });

  const queueComparisonMutation = useMutation({
    mutationFn: () =>
      queueMateriaMedicaComparison(patientId, visitId, {
        method,
        limit: 3,
      }),
    onSuccess: async (task) => {
      setComparisonTaskId(task.id);
      await queryClient.invalidateQueries({ queryKey: ["notifications"] });
      await queryClient.invalidateQueries({ queryKey: ["dashboard", "overview"] });
    },
  });

  const refreshAfterComparisonTask = async () => {
    await queryClient.invalidateQueries({ queryKey: ["notifications"] });
    await queryClient.invalidateQueries({ queryKey: ["dashboard", "overview"] });
  };

  return (
    <section className="panel">
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>Materia Medica RAG Comparison</h3>
          <p className="panel-subtitle">
            Retrieves materia medica chunks and compares top repertorization remedies.
          </p>
        </div>

        <div className="inline-actions">
          <select
            className="method-select"
            value={method}
            onChange={(event) =>
              setMethod(event.target.value as MateriaMedicaMethod)
            }
          >
            <option value="weighted">Weighted</option>
            <option value="cross">Cross</option>
            <option value="eliminative">Eliminative</option>
          </select>

          <button
            className="primary-button inline-button"
            onClick={() => compareMutation.mutate()}
            disabled={compareMutation.isPending}
          >
            <BookOpenCheck size={16} />
            {compareMutation.isPending ? "Comparing..." : "Compare Remedies"}
          </button>

          <button
            className="secondary-button inline-button"
            onClick={() => queueComparisonMutation.mutate()}
            disabled={queueComparisonMutation.isPending}
          >
            <BookOpenCheck size={16} />
            {queueComparisonMutation.isPending
              ? "Queuing..."
              : "Compare in Background"}
          </button>
        </div>
      </div>

      <AiTaskStatus
        taskId={comparisonTaskId}
        onCompleted={refreshAfterComparisonTask}
      />

      {compareMutation.isError && (
        <div className="form-error">
          Unable to compare remedies. Run repertorization first and make sure
          FastAPI is running.
        </div>
      )}

      {queueComparisonMutation.isError && (
        <div className="form-error">
          Unable to queue background comparison. Run repertorization first.
        </div>
      )}

      {!comparison && !compareMutation.isPending && (
        <p className="empty-state">
          Run comparison after weighted, cross, or eliminative repertorization.
        </p>
      )}

      {comparison && (
        <div className="mm-comparison">
          <div className="question-panel mm-summary">
            <h4>Summary</h4>
            <p>{comparison.summary}</p>
            <p>
              <strong>Safety:</strong> {comparison.safety_note}
            </p>
          </div>

          {comparison.remedies.map((remedy) => (
            <article className="mm-remedy-card" key={remedy.remedy_code}>
              <div className="mm-remedy-header">
                <div>
                  <span className="rank-badge">#{remedy.rank}</span>
                </div>
                <div>
                  <h4>{remedy.remedy_name}</h4>
                  <p>Repertorization score: {remedy.total_score}</p>
                </div>
              </div>

              <div className="mm-grid">
                <div>
                  <h5>Matching Points</h5>
                  <ul className="clinical-list">
                    {remedy.matching_points.map((point) => (
                      <li key={point}>{point}</li>
                    ))}
                  </ul>
                </div>

                <div>
                  <h5>Differentiating Points</h5>
                  <ul className="clinical-list">
                    {remedy.differentiating_points.map((point) => (
                      <li key={point}>{point}</li>
                    ))}
                  </ul>
                </div>

                <div className="full-field">
                  <h5>Missing Questions</h5>
                  <ul className="clinical-list">
                    {remedy.missing_questions.map((question) => (
                      <li key={question}>{question}</li>
                    ))}
                  </ul>
                </div>
              </div>

              <details className="source-details">
                <summary>Source chunks</summary>

                <div className="source-chunk-list">
                  {remedy.source_chunks.map((chunk, index) => (
                    <div
                      className="source-chunk"
                      key={`${remedy.remedy_code}-${index}`}
                    >
                      <strong>
                        {chunk.source_title || "Sample Materia Medica"} ·{" "}
                        {chunk.section || "general"}
                      </strong>
                      <p>{chunk.content}</p>
                    </div>
                  ))}
                </div>
              </details>
            </article>
          ))}
        </div>
      )}
    </section>
  );
}
