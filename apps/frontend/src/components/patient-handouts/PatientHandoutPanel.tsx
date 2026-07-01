import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2, FileText, Printer, Sparkles } from "lucide-react";
import {
  generatePatientHandout,
  getPatientHandouts,
  markPatientHandoutPrinted,
  type AiResponseLanguage,
  type PatientHandoutRun,
  type PatientHandoutStyle,
} from "../../lib/api";

type PatientHandoutPanelProps = {
  patientId: string | number;
  visitId: string | number;
};

function apiErrorMessage(error: unknown, fallback: string) {
  if (
    error &&
    typeof error === "object" &&
    "response" in error &&
    error.response &&
    typeof error.response === "object" &&
    "data" in error.response &&
    error.response.data &&
    typeof error.response.data === "object" &&
    "message" in error.response.data &&
    typeof error.response.data.message === "string"
  ) {
    return error.response.data.message;
  }

  return fallback;
}

export function PatientHandoutPanel({
  patientId,
  visitId,
}: PatientHandoutPanelProps) {
  const queryClient = useQueryClient();
  const [responseLanguage, setResponseLanguage] =
    useState<AiResponseLanguage>("auto");
  const [style, setStyle] = useState<PatientHandoutStyle>("simple");
  const [includeWarningSigns, setIncludeWarningSigns] = useState(true);
  const [includeDoAndDont, setIncludeDoAndDont] = useState(true);

  const handoutsQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "patient-handouts"],
    queryFn: () => getPatientHandouts(patientId, visitId),
  });

  const generateMutation = useMutation({
    mutationFn: () =>
      generatePatientHandout(patientId, visitId, {
        response_language: responseLanguage,
        style,
        include_clinic_branding: true,
        include_warning_signs: includeWarningSigns,
        include_do_and_dont: includeDoAndDont,
      }),
    onSuccess: async () => {
      await invalidateHandoutQueries(queryClient, patientId, visitId);
    },
  });

  const latestHandout = handoutsQuery.data?.data?.[0];

  return (
    <section className="panel patient-handout-panel">
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>
            <FileText size={20} /> Patient Instruction Handout
          </h3>
          <p className="panel-subtitle">
            Patient-friendly instructions from the saved prescription.
          </p>
        </div>
      </div>

      <div className="safety-note">
        AI creates clear patient instructions. Doctor reviews before giving it
        to the patient.
      </div>

      <div className="handout-action-row">
        <label>
          AI Response Language
          <select
            className="method-select"
            value={responseLanguage}
            onChange={(event) =>
              setResponseLanguage(event.target.value as AiResponseLanguage)
            }
          >
            <option value="auto">Auto Detect</option>
            <option value="bn-BD">Bangla</option>
            <option value="en-US">English</option>
            <option value="hi-IN">Hindi</option>
          </select>
        </label>

        <label>
          Style
          <select
            className="method-select"
            value={style}
            onChange={(event) =>
              setStyle(event.target.value as PatientHandoutStyle)
            }
          >
            <option value="simple">Simple</option>
            <option value="detailed">Detailed</option>
            <option value="minimal">Minimal</option>
          </select>
        </label>

        <label className="compact-checkbox">
          <input
            type="checkbox"
            checked={includeWarningSigns}
            onChange={(event) => setIncludeWarningSigns(event.target.checked)}
          />
          Warning Signs
        </label>

        <label className="compact-checkbox">
          <input
            type="checkbox"
            checked={includeDoAndDont}
            onChange={(event) => setIncludeDoAndDont(event.target.checked)}
          />
          Do and Don&apos;t
        </label>

        <button
          className="primary-button inline-button"
          onClick={() => generateMutation.mutate()}
          disabled={generateMutation.isPending}
        >
          <Sparkles size={16} />
          {generateMutation.isPending ? "Generating..." : "Generate Handout"}
        </button>
      </div>

      {generateMutation.isError && (
        <div className="form-error">
          {apiErrorMessage(
            generateMutation.error,
            "Unable to generate handout. Save a prescription and make sure FastAPI is running."
          )}
        </div>
      )}

      {handoutsQuery.isError && (
        <div className="form-error">
          {apiErrorMessage(handoutsQuery.error, "Unable to load patient handouts.")}
        </div>
      )}

      {handoutsQuery.isLoading && (
        <p className="empty-state">Loading handouts...</p>
      )}

      {latestHandout ? (
        <PatientHandoutCard
          handout={latestHandout}
          patientId={patientId}
          visitId={visitId}
        />
      ) : (
        !handoutsQuery.isLoading && (
          <p className="empty-state">
            No patient handout yet. Save a prescription first, then generate.
          </p>
        )
      )}
    </section>
  );
}

function PatientHandoutCard({
  handout,
  patientId,
  visitId,
}: {
  handout: PatientHandoutRun;
  patientId: string | number;
  visitId: string | number;
}) {
  const queryClient = useQueryClient();

  const printedMutation = useMutation({
    mutationFn: () => markPatientHandoutPrinted(patientId, visitId, handout.id),
    onSuccess: async () => {
      await invalidateHandoutQueries(queryClient, patientId, visitId);
    },
  });

  function printHandout() {
    const printWindow = window.open(
      `/patients/${patientId}/visits/${visitId}/handouts/${handout.id}/print`,
      "_blank"
    );

    if (printWindow) {
      printedMutation.mutate();
    }
  }

  return (
    <article className={`patient-handout-card ${handout.status}`}>
      <div className="handout-header">
        <div>
          <p className="eyebrow">Latest Handout</p>
          <h4>{handout.title ?? "Patient Treatment Instructions"}</h4>
          <p>
            Language: {handout.resolved_language ?? handout.response_language} |
            Status: {handout.status.replaceAll("_", " ")}
          </p>
        </div>

        <button
          className="secondary-button inline-button"
          onClick={printHandout}
          disabled={printedMutation.isPending}
        >
          <Printer size={16} />
          Print
        </button>
      </div>

      {handout.patient_summary && (
        <p className="handout-summary">{handout.patient_summary}</p>
      )}

      <div className="handout-section-list">
        {handout.sections.map((section) => (
          <div
            className={`handout-section ${section.category} ${
              section.is_important ? "important" : ""
            }`}
            key={section.id}
          >
            <strong>
              {section.is_important && <CheckCircle2 size={15} />}
              {section.title}
            </strong>
            <p>{section.content}</p>
          </div>
        ))}
      </div>

      {handout.warning_signs.length > 0 && (
        <div className="warning-panel">
          <strong>Warning Signs</strong>
          <ul>
            {handout.warning_signs.map((item, index) => (
              <li key={`${item}-${index}`}>{item}</li>
            ))}
          </ul>
        </div>
      )}

      {handout.do_and_dont.length > 0 && (
        <div className="handout-section-list">
          <div className="handout-section">
            <strong>Do and Don&apos;t</strong>
            <ul>
              {handout.do_and_dont.map((item, index) => (
                <li key={`${item}-${index}`}>{item}</li>
              ))}
            </ul>
          </div>
        </div>
      )}

      {handout.footer_note && (
        <div className="review-guidance-box">
          <h5>Clinic Footer</h5>
          <p>{handout.footer_note}</p>
        </div>
      )}

      {handout.safety_note && (
        <div className="safety-note">{handout.safety_note}</div>
      )}
    </article>
  );
}

async function invalidateHandoutQueries(
  queryClient: ReturnType<typeof useQueryClient>,
  patientId: string | number,
  visitId: string | number
) {
  await queryClient.invalidateQueries({
    queryKey: ["patients", patientId, "visits", visitId, "patient-handouts"],
  });

  await queryClient.invalidateQueries({ queryKey: ["activity-logs"] });
}
