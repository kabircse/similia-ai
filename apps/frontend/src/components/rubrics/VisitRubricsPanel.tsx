import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus, Search, Trash2 } from "lucide-react";
import {
    addVisitRubric,
    deleteVisitRubric,
    getVisitRubrics,
    searchRepertoryRubrics,
    updateVisitRubric,
  } from "../../lib/api";
  import type { CaseRubricInput, RepertoryRubric } from "../../lib/api";

type VisitRubricsPanelProps = {
  patientId: string;
  visitId: string;
};

const defaultInput: Omit<CaseRubricInput, "repertory_rubric_id"> = {
  symptom_type: "general",
  importance: "important",
  weight: 3,
  is_essential: false,
  note: "",
};

export function VisitRubricsPanel({ patientId, visitId }: VisitRubricsPanelProps) {
  const queryClient = useQueryClient();

  const [search, setSearch] = useState("");
  const [selectedRubric, setSelectedRubric] = useState<RepertoryRubric | null>(null);
  const [form, setForm] = useState(defaultInput);

  const selectedQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "rubrics"],
    queryFn: () => getVisitRubrics(patientId, visitId),
  });

  const searchQuery = useQuery({
    queryKey: ["repertory-rubrics", search],
    queryFn: () => searchRepertoryRubrics(search),
    enabled: search.trim().length >= 2,
  });

  const addMutation = useMutation({
    mutationFn: () => {
      if (!selectedRubric) {
        throw new Error("No rubric selected");
      }

      return addVisitRubric(patientId, visitId, {
        repertory_rubric_id: selectedRubric.id,
        ...form,
      });
    },
    onSuccess: async () => {
      setSelectedRubric(null);
      setForm(defaultInput);
      setSearch("");
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits", visitId, "rubrics"],
      });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({
      caseRubricId,
      input,
    }: {
      caseRubricId: number;
      input: CaseRubricInput;
    }) => updateVisitRubric(patientId, visitId, caseRubricId, input),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits", visitId, "rubrics"],
      });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (caseRubricId: number) =>
      deleteVisitRubric(patientId, visitId, caseRubricId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits", visitId, "rubrics"],
      });
    },
  });

  return (
    <section className="panel">
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>Selected Rubrics</h3>
          <p className="panel-subtitle">
            Search rubrics and select those that represent the case totality.
          </p>
        </div>
      </div>

      <div className="rubric-search-box">
        <Search size={18} />
        <input
          value={search}
          placeholder="Search rubric, e.g. fear cancer, chilly, sweets..."
          onChange={(event) => setSearch(event.target.value)}
        />
      </div>

      {search.trim().length >= 2 && (
        <div className="rubric-search-results">
          {searchQuery.isLoading && <p className="empty-state">Searching rubrics...</p>}

          {searchQuery.data?.data.map((rubric) => (
            <button
              type="button"
              key={rubric.id}
              className={`rubric-result ${
                selectedRubric?.id === rubric.id ? "selected" : ""
              }`}
              onClick={() => setSelectedRubric(rubric)}
            >
              <strong>{rubric.rubric_path}</strong>
              <span>
                {rubric.chapter || "Unknown"} · {rubric.remedies_count ?? 0} remedies
              </span>
            </button>
          ))}

          {searchQuery.data?.data.length === 0 && (
            <p className="empty-state">No matching rubric found.</p>
          )}
        </div>
      )}

      {selectedRubric && (
        <div className="rubric-add-panel">
          <div>
            <p className="eyebrow">Selected Rubric</p>
            <h4>{selectedRubric.rubric_path}</h4>
          </div>

          <div className="rubric-form-grid">
            <label>
              Symptom Type
              <select
                value={form.symptom_type}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    symptom_type: event.target.value,
                  }))
                }
              >
                <option value="mental">Mental</option>
                <option value="general">General</option>
                <option value="particular">Particular</option>
                <option value="modality">Modality</option>
                <option value="concomitant">Concomitant</option>
                <option value="pathological">Pathological</option>
                <option value="common">Common</option>
                <option value="other">Other</option>
              </select>
            </label>

            <label>
              Importance
              <select
                value={form.importance}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    importance: event.target.value,
                  }))
                }
              >
                <option value="essential">Essential</option>
                <option value="important">Important</option>
                <option value="supportive">Supportive</option>
                <option value="optional">Optional</option>
              </select>
            </label>

            <label>
              Weight
              <select
                value={form.weight}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    weight: Number(event.target.value),
                  }))
                }
              >
                <option value={1}>1 - Low</option>
                <option value={2}>2</option>
                <option value={3}>3 - Medium</option>
                <option value={4}>4</option>
                <option value={5}>5 - Highest</option>
              </select>
            </label>

            <label className="checkbox-label">
              <input
                type="checkbox"
                checked={form.is_essential}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    is_essential: event.target.checked,
                    importance: event.target.checked ? "essential" : current.importance,
                  }))
                }
              />
              Essential rubric
            </label>

            <label className="full-field">
              Note
              <textarea
                rows={3}
                value={form.note}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    note: event.target.value,
                  }))
                }
              />
            </label>
          </div>

          <button
            className="primary-button inline-button"
            onClick={() => addMutation.mutate()}
            disabled={addMutation.isPending}
          >
            <Plus size={16} />
            {addMutation.isPending ? "Adding..." : "Add Rubric"}
          </button>
        </div>
      )}

      <div className="selected-rubric-list">
        {selectedQuery.isLoading && <p className="empty-state">Loading selected rubrics...</p>}

        {selectedQuery.data?.data.length === 0 && (
          <p className="empty-state">No rubrics selected yet.</p>
        )}

        {selectedQuery.data?.data.map((caseRubric) => (
          <article className="selected-rubric-card" key={caseRubric.id}>
            <div>
              <h4>{caseRubric.rubric.rubric_path}</h4>
              <p>
                {caseRubric.symptom_type} · {caseRubric.importance} · weight{" "}
                {caseRubric.weight}
                {caseRubric.is_essential ? " · essential" : ""}
              </p>
              {caseRubric.note && <p className="notes-text">{caseRubric.note}</p>}
            </div>

            <div className="table-actions">
              <button
                className="secondary-button"
                onClick={() =>
                  updateMutation.mutate({
                    caseRubricId: caseRubric.id,
                    input: {
                      repertory_rubric_id: caseRubric.repertory_rubric_id,
                      symptom_type: caseRubric.symptom_type,
                      importance:
                        caseRubric.importance === "essential"
                          ? "important"
                          : "essential",
                      weight: caseRubric.weight,
                      is_essential: !caseRubric.is_essential,
                      note: caseRubric.note ?? "",
                    },
                  })
                }
              >
                {caseRubric.is_essential ? "Unmark Essential" : "Mark Essential"}
              </button>

              <button
                className="danger-button"
                onClick={() => {
                  if (confirm("Remove this rubric from the case?")) {
                    deleteMutation.mutate(caseRubric.id);
                  }
                }}
                disabled={deleteMutation.isPending}
              >
                <Trash2 size={15} />
                Remove
              </button>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}