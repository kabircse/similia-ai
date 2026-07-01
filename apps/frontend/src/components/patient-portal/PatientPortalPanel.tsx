import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { isAxiosError } from "axios";
import {
  Archive,
  CheckCircle2,
  Clipboard,
  ExternalLink,
  FilePlus2,
  Link2,
  RefreshCw,
  ShieldCheck,
  TriangleAlert,
} from "lucide-react";
import {
  convertPatientFollowUpSubmissionToVisit,
  createPatientPortalInvitation,
  getPatientFollowUpSubmissions,
  getPatientPortalInvitations,
  reviewPatientFollowUpSubmission,
  revokePatientPortalInvitation,
  type AiResponseLanguage,
  type PatientFollowUpSubmission,
  type PatientPortalInvitation,
} from "../../lib/api";

type PatientPortalPanelProps = {
  patientId: string;
  visitId: string;
};

const languageOptions: Array<[AiResponseLanguage, string]> = [
  ["auto", "Auto"],
  ["bn-BD", "Bangla"],
  ["en-US", "English"],
  ["hi-IN", "Hindi"],
];

function formatDateTime(value: string | null) {
  if (!value) {
    return "-";
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function formatLabel(value: string | null | undefined) {
  if (!value) {
    return "-";
  }

  return value.replaceAll("_", " ");
}

function mutationErrorMessage(error: unknown, fallback: string) {
  if (!isAxiosError(error)) {
    return fallback;
  }

  const data = error.response?.data as
    | { message?: string; errors?: Record<string, string[]> }
    | undefined;
  const firstValidationError = data?.errors
    ? Object.values(data.errors).flat()[0]
    : null;

  return (
    firstValidationError ||
    data?.message ||
    (error.response?.status
      ? `Request failed with status ${error.response.status}.`
      : fallback)
  );
}

function invalidatePortalQueries(
  queryClient: ReturnType<typeof useQueryClient>,
  patientId: string,
  visitId: string
) {
  return Promise.all([
    queryClient.invalidateQueries({
      queryKey: ["patients", patientId, "visits", visitId, "portal-invitations"],
    }),
    queryClient.invalidateQueries({
      queryKey: ["patients", patientId, "visits", visitId, "portal-submissions"],
    }),
  ]);
}

function InvitationCard({
  invitation,
  onRevoke,
  revokePending,
}: {
  invitation: PatientPortalInvitation;
  onRevoke: (invitationId: number) => void;
  revokePending: boolean;
}) {
  const [copied, setCopied] = useState(false);
  const canUseLink = Boolean(invitation.portal_url) && invitation.status !== "revoked";

  async function copyLink() {
    if (!invitation.portal_url || !navigator.clipboard) {
      return;
    }

    await navigator.clipboard.writeText(invitation.portal_url);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 1800);
  }

  return (
    <article className={`portal-invitation-card ${invitation.status}`}>
      <div className="portal-card-header">
        <div>
          <strong>{formatLabel(invitation.purpose)}</strong>
          <p>
            {formatLabel(invitation.status)} · expires{" "}
            {formatDateTime(invitation.expires_at)}
          </p>
        </div>
        <span className={`status-pill ${invitation.status}`}>
          {formatLabel(invitation.status)}
        </span>
      </div>

      {invitation.message_to_patient && (
        <p className="portal-muted">{invitation.message_to_patient}</p>
      )}

      <div className="portal-stats">
        <span>Opened {invitation.opened_count}</span>
        <span>
          Submitted {invitation.submission_count}/{invitation.max_submissions}
        </span>
        <span>{formatLabel(invitation.response_language)}</span>
      </div>

      {invitation.portal_url && (
        <div className="portal-link-box">
          <code>{invitation.portal_url}</code>
        </div>
      )}

      <div className="inline-actions">
        <button
          className="secondary-button"
          type="button"
          onClick={copyLink}
          disabled={!canUseLink}
          title="Copy portal link"
        >
          <Clipboard size={15} />
          {copied ? "Copied" : "Copy"}
        </button>

        {invitation.portal_url && (
          <a
            className="secondary-link"
            href={invitation.portal_url}
            target="_blank"
            rel="noreferrer"
            title="Open portal link"
          >
            <ExternalLink size={15} />
            Open
          </a>
        )}

        {invitation.status !== "revoked" && invitation.status !== "submitted" && (
          <button
            className="danger-button"
            type="button"
            onClick={() => onRevoke(invitation.id)}
            disabled={revokePending}
          >
            Revoke
          </button>
        )}
      </div>
    </article>
  );
}

function SubmissionField({
  label,
  value,
}: {
  label: string;
  value: string | null;
}) {
  if (!value) {
    return null;
  }

  return (
    <p>
      <strong>{label}:</strong> {value}
    </p>
  );
}

function SubmissionCard({
  submission,
  onReview,
  onConvert,
  reviewPending,
  convertPending,
}: {
  submission: PatientFollowUpSubmission;
  onReview: (
    submissionId: number,
    status: "reviewed" | "archived",
    doctorNote: string
  ) => void;
  onConvert: (submissionId: number) => void;
  reviewPending: boolean;
  convertPending: boolean;
}) {
  const [doctorNote, setDoctorNote] = useState(submission.doctor_note ?? "");
  const isConverted = submission.status === "converted_to_visit";

  return (
    <article className={`portal-submission-card ${submission.status}`}>
      <div className="portal-card-header">
        <div>
          <strong>{formatLabel(submission.overall_change)}</strong>
          <p>Submitted {formatDateTime(submission.submitted_at)}</p>
        </div>
        <span className={`status-pill ${submission.status}`}>
          {formatLabel(submission.status)}
        </span>
      </div>

      {submission.detected_red_flags.length > 0 && (
        <div className="portal-red-flags">
          <TriangleAlert size={16} />
          <span>{submission.detected_red_flags.join(", ")}</span>
        </div>
      )}

      <div className="portal-submission-content">
        <SubmissionField label="Main changes" value={submission.main_changes} />
        <SubmissionField
          label="Current symptoms"
          value={submission.current_symptoms}
        />
        <SubmissionField label="New symptoms" value={submission.new_symptoms} />
        <SubmissionField
          label="Aggravation"
          value={submission.aggravation_notes}
        />
        <SubmissionField
          label="Other medicines"
          value={submission.other_medicines}
        />
        <SubmissionField label="Notes" value={submission.general_notes} />
        <SubmissionField
          label="Patient questions"
          value={submission.patient_questions}
        />
      </div>

      <div className="portal-stats">
        <span>Medicine {submission.medicine_taken ? "taken" : "not confirmed"}</span>
        <span>Energy {formatLabel(submission.general_energy)}</span>
        <span>Sleep {formatLabel(submission.sleep)}</span>
        <span>Appetite {formatLabel(submission.appetite)}</span>
        <span>Mood {formatLabel(submission.mood)}</span>
      </div>

      {submission.converted_visit && (
        <div className="success-panel">
          Converted to visit #{submission.converted_visit.id} on{" "}
          {submission.converted_visit.visit_date ?? "-"}.
        </div>
      )}

      <label className="portal-note-field">
        Doctor note
        <textarea
          rows={3}
          value={doctorNote}
          onChange={(event) => setDoctorNote(event.target.value)}
        />
      </label>

      <div className="inline-actions">
        <button
          className="secondary-button"
          type="button"
          onClick={() => onReview(submission.id, "reviewed", doctorNote)}
          disabled={reviewPending || isConverted}
        >
          <CheckCircle2 size={15} />
          Reviewed
        </button>

        <button
          className="secondary-button"
          type="button"
          onClick={() => onReview(submission.id, "archived", doctorNote)}
          disabled={reviewPending || isConverted}
        >
          <Archive size={15} />
          Archive
        </button>

        <button
          className="primary-button inline-button"
          type="button"
          onClick={() => onConvert(submission.id)}
          disabled={convertPending || isConverted}
        >
          <FilePlus2 size={15} />
          Convert
        </button>
      </div>
    </article>
  );
}

export function PatientPortalPanel({
  patientId,
  visitId,
}: PatientPortalPanelProps) {
  const queryClient = useQueryClient();
  const [expiresInDays, setExpiresInDays] = useState(7);
  const [maxSubmissions, setMaxSubmissions] = useState(1);
  const [responseLanguage, setResponseLanguage] =
    useState<AiResponseLanguage>("auto");
  const [messageToPatient, setMessageToPatient] = useState("");

  const invitationsQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "portal-invitations"],
    queryFn: () => getPatientPortalInvitations(patientId, visitId),
  });

  const submissionsQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "portal-submissions"],
    queryFn: () => getPatientFollowUpSubmissions(patientId, visitId),
  });

  const createMutation = useMutation({
    mutationFn: () =>
      createPatientPortalInvitation(patientId, visitId, {
        expires_in_days: expiresInDays,
        max_submissions: maxSubmissions,
        response_language: responseLanguage,
        message_to_patient: messageToPatient,
      }),
    onSuccess: async () => {
      await invalidatePortalQueries(queryClient, patientId, visitId);
    },
  });

  const revokeMutation = useMutation({
    mutationFn: (invitationId: number) =>
      revokePatientPortalInvitation(patientId, visitId, invitationId),
    onSuccess: async () => {
      await invalidatePortalQueries(queryClient, patientId, visitId);
    },
  });

  const reviewMutation = useMutation({
    mutationFn: (input: {
      submissionId: number;
      status: "reviewed" | "archived";
      doctorNote: string;
    }) =>
      reviewPatientFollowUpSubmission(
        patientId,
        visitId,
        input.submissionId,
        {
          status: input.status,
          doctor_note: input.doctorNote,
        }
      ),
    onSuccess: async () => {
      await invalidatePortalQueries(queryClient, patientId, visitId);
    },
  });

  const convertMutation = useMutation({
    mutationFn: (submissionId: number) =>
      convertPatientFollowUpSubmissionToVisit(patientId, visitId, submissionId),
    onSuccess: async () => {
      await invalidatePortalQueries(queryClient, patientId, visitId);
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits"],
      });
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "timeline"],
      });
      await queryClient.invalidateQueries({ queryKey: ["dashboard", "overview"] });
    },
  });

  const invitations = invitationsQuery.data?.data ?? [];
  const submissions = submissionsQuery.data?.data ?? [];

  return (
    <section className="panel patient-portal-panel">
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>Patient Portal Follow-up</h3>
          <p className="panel-subtitle">
            Generate a secure patient link and review submitted follow-up forms.
          </p>
        </div>
        <ShieldCheck size={24} />
      </div>

      <form
        className="portal-invite-form"
        onSubmit={(event) => {
          event.preventDefault();
          createMutation.mutate();
        }}
      >
        <div className="portal-invite-grid">
          <label>
            Expires
            <input
              type="number"
              min={1}
              max={30}
              value={expiresInDays}
              onChange={(event) => setExpiresInDays(Number(event.target.value))}
            />
          </label>

          <label>
            Submissions
            <input
              type="number"
              min={1}
              max={3}
              value={maxSubmissions}
              onChange={(event) => setMaxSubmissions(Number(event.target.value))}
            />
          </label>

          <label>
            Language
            <select
              value={responseLanguage}
              onChange={(event) =>
                setResponseLanguage(event.target.value as AiResponseLanguage)
              }
            >
              {languageOptions.map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>
        </div>

        <label className="portal-message-field">
          Message to patient
          <textarea
            rows={3}
            value={messageToPatient}
            onChange={(event) => setMessageToPatient(event.target.value)}
          />
        </label>

        {createMutation.isError && (
          <div className="form-error">
            {mutationErrorMessage(
              createMutation.error,
              "Unable to create portal invitation."
            )}
          </div>
        )}

        <button
          className="primary-button inline-button"
          type="submit"
          disabled={createMutation.isPending}
        >
          <Link2 size={16} />
          {createMutation.isPending ? "Creating..." : "Create Secure Link"}
        </button>
      </form>

      <div className="portal-section-heading">
        <strong>Invitations</strong>
        {invitationsQuery.isFetching && <RefreshCw size={15} />}
      </div>

      {invitations.length === 0 ? (
        <div className="empty-panel compact-empty">
          <p>No portal invitations for this visit.</p>
        </div>
      ) : (
        <div className="portal-list">
          {invitations.map((invitation) => (
            <InvitationCard
              key={invitation.id}
              invitation={invitation}
              onRevoke={(invitationId) => revokeMutation.mutate(invitationId)}
              revokePending={revokeMutation.isPending}
            />
          ))}
        </div>
      )}

      <div className="portal-section-heading">
        <strong>Patient Submissions</strong>
        {submissionsQuery.isFetching && <RefreshCw size={15} />}
      </div>

      {submissions.length === 0 ? (
        <div className="empty-panel compact-empty">
          <p>No patient follow-up submissions yet.</p>
        </div>
      ) : (
        <div className="portal-list">
          {submissions.map((submission) => (
            <SubmissionCard
              key={submission.id}
              submission={submission}
              onReview={(submissionId, status, doctorNote) =>
                reviewMutation.mutate({ submissionId, status, doctorNote })
              }
              onConvert={(submissionId) => convertMutation.mutate(submissionId)}
              reviewPending={reviewMutation.isPending}
              convertPending={convertMutation.isPending}
            />
          ))}
        </div>
      )}
    </section>
  );
}
