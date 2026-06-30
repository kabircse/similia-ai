import axios from "axios";

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000",
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: "application/json",
  },
});

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: "doctor" | "admin" | "assistant" | string;
};

export type Permission =
  | "view_dashboard"
  | "manage_patients"
  | "manage_visits"
  | "manage_rubrics"
  | "run_repertorization"
  | "compare_materia_medica"
  | "manage_prescriptions"
  | "manage_fees"
  | "print_documents"
  | "view_activity_logs"
  | "manage_clinic_settings"
  | "manage_users";

export type AuthResponse = {
  message?: string;
  user: AuthUser;
  permissions: Permission[];
};

export type DashboardOverview = {
  doctor: AuthUser;
  summary: {
    total_patients: number;
    today_visits: number;
    pending_followups: number;
    prescriptions_saved: number;
    unread_notifications: number;
  };
  clinical_workflow: Array<{
    title: string;
    description: string;
    status: string;
  }>;
  recent_activity: Array<{
    type: string;
    action?: string;
    title: string;
    description: string | null;
    patient_id?: number | null;
    patient_name?: string | null;
    patient_visit_id?: number | null;
    created_at: string | null;
  }>;
};

export async function csrfCookie() {
  await api.get("/sanctum/csrf-cookie");
}

export async function login(
  email: string,
  password: string
): Promise<AuthResponse> {
  await csrfCookie();

  const response = await api.post("/api/login", {
    email,
    password,
  });

  return response.data;
}

export async function getMe(): Promise<AuthResponse> {
  const response = await api.get("/api/me");
  return response.data;
}

export async function logout() {
  const response = await api.post("/api/logout");
  return response.data;
}

export async function getDashboardOverview(): Promise<DashboardOverview> {
  const response = await api.get("/api/dashboard/overview");
  return response.data.data;
}

export type AiTask = {
  id: number;
  user_id: number;
  patient_id: number | null;
  patient_visit_id: number | null;
  type: "structure_case" | "compare_materia_medica" | string;
  status: "queued" | "running" | "completed" | "failed" | string;
  title: string;
  message: string | null;
  progress: number;
  payload: Record<string, unknown>;
  result: Record<string, unknown>;
  error_message: string | null;
  started_at: string | null;
  completed_at: string | null;
  failed_at: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type UserNotification = {
  id: number;
  user_id: number;
  patient_id: number | null;
  patient_visit_id: number | null;
  ai_task_id: number | null;
  type: "info" | "success" | "warning" | "error" | string;
  category: string;
  title: string;
  message: string | null;
  action_url: string | null;
  metadata: Record<string, unknown>;
  is_read: boolean;
  read_at: string | null;
  created_at: string | null;
};

export type NotificationResponse = {
  data: UserNotification[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export async function getAiTask(taskId: string | number): Promise<AiTask> {
  const response = await api.get(`/api/ai-tasks/${taskId}`);
  return response.data.data;
}

export async function getNotifications(params?: {
  unread_only?: boolean;
}): Promise<NotificationResponse> {
  const response = await api.get("/api/notifications", {
    params: {
      per_page: 10,
      ...params,
    },
  });

  return response.data;
}

export async function getUnreadNotificationCount(): Promise<number> {
  const response = await api.get("/api/notifications/unread-count");
  return response.data.data.unread_count;
}

export async function markNotificationAsRead(
  notificationId: string | number
): Promise<UserNotification> {
  const response = await api.patch(`/api/notifications/${notificationId}/read`);
  return response.data.data;
}

export async function markAllNotificationsAsRead() {
  const response = await api.post("/api/notifications/read-all");
  return response.data;
}

export type ClinicSetting = {
  id: number;
  doctor_id: number;

  clinic_name: string;
  tagline: string | null;

  doctor_display_name: string | null;
  doctor_qualification: string | null;

  phone: string | null;
  email: string | null;
  website: string | null;
  address: string | null;
  logo_url: string | null;

  default_currency: string;
  default_consultation_fee: string;
  default_followup_fee: string;
  medicine_fee_included: boolean;

  prescription_footer: string | null;
  case_sheet_footer: string | null;

  created_at: string | null;
  updated_at: string | null;
};

export type ClinicSettingInput = {
  clinic_name: string;
  tagline: string;

  doctor_display_name: string;
  doctor_qualification: string;

  phone: string;
  email: string;
  website: string;
  address: string;
  logo_url: string;

  default_currency: string;
  default_consultation_fee: string;
  default_followup_fee: string;
  medicine_fee_included: boolean;

  prescription_footer: string;
  case_sheet_footer: string;
};

export async function getClinicSettings(): Promise<ClinicSetting> {
  const response = await api.get("/api/clinic-settings");
  return response.data.data;
}

export async function updateClinicSettings(
  input: ClinicSettingInput
): Promise<ClinicSetting> {
  const response = await api.put("/api/clinic-settings", {
    ...input,
    tagline: input.tagline || null,
    doctor_display_name: input.doctor_display_name || null,
    doctor_qualification: input.doctor_qualification || null,
    phone: input.phone || null,
    email: input.email || null,
    website: input.website || null,
    address: input.address || null,
    logo_url: input.logo_url || null,
    default_currency: input.default_currency || "BDT",
    default_consultation_fee:
      input.default_consultation_fee === ""
        ? 0
        : Number(input.default_consultation_fee),
    default_followup_fee:
      input.default_followup_fee === "" ? 0 : Number(input.default_followup_fee),
    prescription_footer: input.prescription_footer || null,
    case_sheet_footer: input.case_sheet_footer || null,
  });

  return response.data.data;
}

export type AuditLog = {
  id: number;

  user_id: number | null;
  patient_id: number | null;
  patient_visit_id: number | null;

  category: string;
  action: string;

  entity_type: string | null;
  entity_id: number | null;

  title: string;
  description: string | null;

  metadata: Record<string, unknown>;
  before: Record<string, unknown>;
  after: Record<string, unknown>;

  ip_address: string | null;
  created_at: string | null;

  user?: {
    id: number;
    name: string;
    email: string;
    role: string;
  };

  patient?: {
    id: number;
    name: string;
    phone: string | null;
  } | null;

  visit?: {
    id: number;
    visit_date: string | null;
    visit_type: string;
  } | null;
};

export type AuditLogResponse = {
  data: AuditLog[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export async function getActivityLogs(params?: {
  patient_id?: string | number;
  patient_visit_id?: string | number;
  category?: string;
}): Promise<AuditLogResponse> {
  const response = await api.get("/api/activity-logs", {
    params: {
      per_page: 30,
      ...params,
    },
  });

  return response.data;
}

export type Patient = {
  id: number;
  doctor_id: number;
  name: string;
  age_years: number | null;
  gender: "male" | "female" | "other" | "unknown" | null;
  phone: string | null;
  address: string | null;
  occupation: string | null;
  marital_status: "single" | "married" | "widowed" | "divorced" | "unknown" | null;
  emergency_contact: string | null;
  notes: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type PatientInput = {
  name: string;
  age_years: number | "";
  gender: string;
  phone: string;
  address: string;
  occupation: string;
  marital_status: string;
  emergency_contact: string;
  notes: string;
};

export type PatientListResponse = {
  data: Patient[];
  links?: unknown;
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

function normalizePatientInput(input: PatientInput) {
  return {
    ...input,
    age_years: input.age_years === "" ? null : Number(input.age_years),
    gender: input.gender || null,
    marital_status: input.marital_status || null,
    phone: input.phone || null,
    address: input.address || null,
    occupation: input.occupation || null,
    emergency_contact: input.emergency_contact || null,
    notes: input.notes || null,
  };
}

export async function getPatients(search = ""): Promise<PatientListResponse> {
  const response = await api.get("/api/patients", {
    params: {
      search,
      per_page: 20,
    },
  });

  return response.data;
}

export async function getPatient(patientId: string | number): Promise<Patient> {
  const response = await api.get(`/api/patients/${patientId}`);
  return response.data.data;
}

export async function createPatient(input: PatientInput): Promise<Patient> {
  const response = await api.post("/api/patients", normalizePatientInput(input));
  return response.data.data;
}

export async function updatePatient(
  patientId: string | number,
  input: PatientInput
): Promise<Patient> {
  const response = await api.put(
    `/api/patients/${patientId}`,
    normalizePatientInput(input)
  );

  return response.data.data;
}

export async function deletePatient(patientId: string | number) {
  const response = await api.delete(`/api/patients/${patientId}`);
  return response.data;
}

export type CaseSections = {
  location: string;
  sensation: string;
  modalities: string;
  concomitants: string;
  mentals: string;
  generals: string;
  thermal_state: string;
  thirst: string;
  appetite: string;
  food_desires: string;
  food_aversions: string;
  sleep: string;
  dreams: string;
  stool: string;
  urine: string;
  menses: string;
  past_history: string;
  family_history: string;
  current_medicine: string;
  reports_note: string;
};

export type PatientVisit = {
  id: number;
  patient_id: number;
  doctor_id: number;
  visit_date: string;
  visit_type: "initial" | "follow_up";
  status: "draft" | "completed";
  case_source: "manual" | "raw" | "mixed";
  chief_complaint: string | null;
  raw_case_text: string | null;
  case_sections: Partial<CaseSections>;
  missing_questions: string[];
  red_flags: string[];
  doctor_notes: string | null;
  next_follow_up_date: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type VisitInput = {
  visit_date: string;
  visit_type: "initial" | "follow_up";
  status: "draft" | "completed";
  case_source: "manual" | "raw" | "mixed";
  chief_complaint: string;
  raw_case_text: string;
  case_sections: CaseSections;
  doctor_notes: string;
  next_follow_up_date: string;
};

export type VisitListResponse = {
  data: PatientVisit[];
  links?: unknown;
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

function nullableString(value: string) {
  return value.trim() === "" ? null : value;
}

function normalizeVisitInput(input: VisitInput) {
  return {
    ...input,
    chief_complaint: nullableString(input.chief_complaint),
    raw_case_text: nullableString(input.raw_case_text),
    doctor_notes: nullableString(input.doctor_notes),
    next_follow_up_date: input.next_follow_up_date || null,
  };
}

export async function getPatientVisits(
  patientId: string | number
): Promise<VisitListResponse> {
  const response = await api.get(`/api/patients/${patientId}/visits`, {
    params: {
      per_page: 20,
    },
  });

  return response.data;
}

export async function getPatientVisit(
  patientId: string | number,
  visitId: string | number
): Promise<PatientVisit> {
  const response = await api.get(`/api/patients/${patientId}/visits/${visitId}`);
  return response.data.data;
}

export async function createPatientVisit(
  patientId: string | number,
  input: VisitInput
): Promise<PatientVisit> {
  const response = await api.post(
    `/api/patients/${patientId}/visits`,
    normalizeVisitInput(input)
  );

  return response.data.data;
}

export async function updatePatientVisit(
  patientId: string | number,
  visitId: string | number,
  input: VisitInput
): Promise<PatientVisit> {
  const response = await api.put(
    `/api/patients/${patientId}/visits/${visitId}`,
    normalizeVisitInput(input)
  );

  return response.data.data;
}

export async function deletePatientVisit(
  patientId: string | number,
  visitId: string | number
) {
  const response = await api.delete(`/api/patients/${patientId}/visits/${visitId}`);
  return response.data;
}

export type PatientTimelineRubric = {
  rubric_path: string | null;
  symptom_type: string;
  importance: string;
  weight: number;
  is_essential: boolean;
};

export type PatientTimelineTopResult = {
  rank: number;
  remedy_code: string;
  remedy_name: string;
  total_score: number;
  rubric_coverage: number;
  essential_coverage: number;
};

export type PatientTimelineRun = {
  id: number;
  method: string;
  created_at: string | null;
  top_results: PatientTimelineTopResult[];
};

export type PatientTimelineItem = {
  id: string;
  type: "visit";
  date: string | null;
  title: string;

  visit: {
    id: number;
    visit_date: string | null;
    visit_type: string;
    status: string;
    case_source: string;
    chief_complaint: string | null;
    doctor_notes: string | null;
    next_follow_up_date: string | null;
  };

  case_summary: {
    rubrics_count: number;
    essential_rubrics_count: number;
    selected_rubrics: PatientTimelineRubric[];
  };

  repertorization: PatientTimelineRun[];

  prescription: {
    id: number;
    remedy_code: string | null;
    remedy_name: string;
    potency: string;
    repetition: string | null;
    follow_up_date: string | null;
    status: string;
  } | null;

  fee: {
    id: number;
    currency: string;
    total_amount: string;
    paid_amount: string;
    due_amount: string;
    payment_status: string;
    payment_method: string | null;
    payment_date: string | null;
  } | null;
};

export type PatientTimelineResponse = {
  data: PatientTimelineItem[];
  meta: {
    patient: {
      id: number;
      name: string;
      age_years: number | null;
      gender: string | null;
      phone: string | null;
    };
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export async function getPatientTimeline(
  patientId: string | number
): Promise<PatientTimelineResponse> {
  const response = await api.get(`/api/patients/${patientId}/timeline`, {
    params: {
      per_page: 20,
    },
  });

  return response.data;
}

export async function structurePatientVisit(
  patientId: string | number,
  visitId: string | number,
  overwriteExistingSections = false
): Promise<PatientVisit> {
  const response = await api.post(
    `/api/patients/${patientId}/visits/${visitId}/structure-case`,
    {
      overwrite_existing_sections: overwriteExistingSections,
    }
  );

  return response.data.data;
}

export async function queueCaseStructuring(
  patientId: string | number,
  visitId: string | number
): Promise<AiTask> {
  const response = await api.post(
    `/api/patients/${patientId}/visits/${visitId}/structure-case/async`
  );

  return response.data.data;
}

export type VoiceTranscript = {
  id: number;

  patient_id: number;
  patient_visit_id: number;
  doctor_id: number;

  language: string;
  source: string;
  status: string;

  transcript_text: string;
  segments: Array<Record<string, unknown>>;

  merged_to_case_text: boolean;
  merge_mode: "append" | "prepend" | "replace" | null;

  started_at: string | null;
  completed_at: string | null;

  created_at: string | null;
  updated_at: string | null;
};

export type VoiceTranscriptResponse = {
  data: VoiceTranscript[];
};

export type SaveVoiceTranscriptResult = {
  transcript: VoiceTranscript;
  queued_ai_task_id: number | null;
};

export async function getVoiceTranscripts(
  patientId: string | number,
  visitId: string | number
): Promise<VoiceTranscriptResponse> {
  const response = await api.get(
    `/api/patients/${patientId}/visits/${visitId}/voice-transcripts`,
    {
      params: {
        per_page: 10,
      },
    }
  );

  return response.data;
}

export async function saveVoiceTranscript(
  patientId: string | number,
  visitId: string | number,
  input: {
    language: string;
    transcript_text: string;
    segments?: Array<Record<string, unknown>>;
    merge_to_case_text?: boolean;
    merge_mode?: "append" | "prepend" | "replace";
    started_at?: string | null;
    completed_at?: string | null;
  }
): Promise<SaveVoiceTranscriptResult> {
  const response = await api.post(
    `/api/patients/${patientId}/visits/${visitId}/voice-transcripts`,
    {
      language: input.language,
      transcript_text: input.transcript_text,
      segments: input.segments ?? [],
      merge_to_case_text: input.merge_to_case_text ?? true,
      merge_mode: input.merge_mode ?? "append",
      started_at: input.started_at ?? null,
      completed_at: input.completed_at ?? null,
    }
  );

  return {
    transcript: response.data.data,
    queued_ai_task_id: response.data.meta?.queued_ai_task_id ?? null,
  };
}

export type RepertoryRubric = {
  id: number;
  repertory_source_id: number | null;
  external_id: number | null;
  external_repertory_id: number | null;
  source: string;
  chapter: string | null;
  rubric_path: string;
  rubric_text: string;
  medicine_count: number;
  default_weight: number;
  is_selectable: boolean;
  page: number | null;
  remedies_count?: number;
};

export type CaseRubric = {
  id: number;
  patient_visit_id: number;
  repertory_rubric_id: number;
  doctor_id: number;
  symptom_type: string;
  importance: string;
  weight: number;
  is_essential: boolean;
  note: string | null;
  rubric: RepertoryRubric;
};

export type RubricSearchResponse = {
  data: RepertoryRubric[];
};

export type CaseRubricListResponse = {
  data: CaseRubric[];
};

export type CaseRubricInput = {
  repertory_rubric_id: number;
  symptom_type: string;
  importance: string;
  weight: number;
  is_essential: boolean;
  note: string;
};

export async function searchRepertoryRubrics(
  search: string
): Promise<RubricSearchResponse> {
  const response = await api.get("/api/repertory/rubrics", {
    params: {
      search,
      per_page: 20,
    },
  });

  return response.data;
}

export async function getVisitRubrics(
  patientId: string | number,
  visitId: string | number
): Promise<CaseRubricListResponse> {
  const response = await api.get(`/api/patients/${patientId}/visits/${visitId}/rubrics`);
  return response.data;
}

export async function addVisitRubric(
  patientId: string | number,
  visitId: string | number,
  input: CaseRubricInput
): Promise<CaseRubric> {
  const response = await api.post(
    `/api/patients/${patientId}/visits/${visitId}/rubrics`,
    input
  );

  return response.data.data;
}

export async function updateVisitRubric(
  patientId: string | number,
  visitId: string | number,
  caseRubricId: string | number,
  input: CaseRubricInput
): Promise<CaseRubric> {
  const response = await api.patch(
    `/api/patients/${patientId}/visits/${visitId}/rubrics/${caseRubricId}`,
    input
  );

  return response.data.data;
}

export async function deleteVisitRubric(
  patientId: string | number,
  visitId: string | number,
  caseRubricId: string | number
) {
  const response = await api.delete(
    `/api/patients/${patientId}/visits/${visitId}/rubrics/${caseRubricId}`
  );

  return response.data;
}

export type SupportingRubric = {
  case_rubric_id: number;
  repertory_rubric_id: number;
  rubric_path: string;
  rubric_text: string;
  symptom_type: string;
  importance: string;
  is_essential: boolean;
  rubric_weight: number;
  remedy_grade: number;
  score: number;
};

export type MissingImportantRubric = {
  case_rubric_id: number;
  repertory_rubric_id: number | null;
  rubric_path: string | null;
  importance: string;
  is_essential: boolean;
  weight: number;
};

export type RepertorizationResult = {
  id: number;
  repertorization_run_id: number;
  remedy_code: string;
  remedy_name: string;
  total_score: number;
  rubric_coverage: number;
  essential_coverage: number;
  rank: number;
  supporting_rubrics: SupportingRubric[];
  missing_important_rubrics: MissingImportantRubric[];
  metrics: Record<string, number | string | null>;
  created_at: string | null;
  updated_at: string | null;
};

export type RepertorizationRun = {
  id: number;
  patient_visit_id: number;
  doctor_id: number;
  method: "weighted" | "cross" | "eliminative" | string;
  total_rubrics: number;
  essential_rubrics_count: number;
  settings: Record<string, unknown>;
  selected_rubrics_snapshot: unknown[];
  results: RepertorizationResult[];
  created_at: string | null;
  updated_at: string | null;
};

export type RepertorizationRunListResponse = {
  data: RepertorizationRun[];
};

export async function getRepertorizationRuns(
  patientId: string | number,
  visitId: string | number,
  method?: string
): Promise<RepertorizationRunListResponse> {
  const response = await api.get(
    `/api/patients/${patientId}/visits/${visitId}/repertorization-runs`,
    {
      params: {
        per_page: 10,
        method,
      },
    }
  );

  return response.data;
}

export async function runWeightedRepertorization(
  patientId: string | number,
  visitId: string | number
): Promise<RepertorizationRun> {
  const response = await api.post(
    `/api/patients/${patientId}/visits/${visitId}/repertorize/weighted`,
    {
      settings: {
        limit: 50,
      },
    }
  );

  return response.data.data;
}

export async function runCrossRepertorization(
  patientId: string | number,
  visitId: string | number
): Promise<RepertorizationRun> {
  const response = await api.post(
    `/api/patients/${patientId}/visits/${visitId}/repertorize/cross`,
    {
      settings: {
        limit: 50,
      },
    }
  );

  return response.data.data;
}

export async function runEliminativeRepertorization(
  patientId: string | number,
  visitId: string | number
): Promise<RepertorizationRun> {
  const response = await api.post(
    `/api/patients/${patientId}/visits/${visitId}/repertorize/eliminative`,
    {
      settings: {
        limit: 50,
        strict_essential: true,
      },
    }
  );

  return response.data.data;
}

export type MateriaMedicaMethod = "weighted" | "cross" | "eliminative";

export type MateriaMedicaRemedyComparison = {
  remedy_code: string;
  remedy_name: string;
  rank: number;
  total_score: number;
  matching_points: string[];
  differentiating_points: string[];
  missing_questions: string[];
  source_chunks: Array<{
    section: string | null;
    source_title: string | null;
    content: string;
    distance: number | null;
  }>;
};

export type MateriaMedicaComparisonResponse = {
  summary: string;
  remedies: MateriaMedicaRemedyComparison[];
  safety_note: string;
  engine: string;
};

export async function compareMateriaMedica(
  patientId: string | number,
  visitId: string | number,
  method?: MateriaMedicaMethod
): Promise<MateriaMedicaComparisonResponse> {
  const response = await api.post(
    `/api/patients/${patientId}/visits/${visitId}/materia-medica/compare`,
    {
      method,
      limit: 3,
    }
  );

  return response.data.data;
}

export async function queueMateriaMedicaComparison(
  patientId: string | number,
  visitId: string | number,
  input?: {
    repertorization_run_id?: number | null;
    method?: MateriaMedicaMethod | "";
    limit?: number;
  }
): Promise<AiTask> {
  const response = await api.post(
    `/api/patients/${patientId}/visits/${visitId}/materia-medica/compare/async`,
    {
      repertorization_run_id: input?.repertorization_run_id || null,
      method: input?.method || null,
      limit: input?.limit ?? 3,
    }
  );

  return response.data.data;
}

export type RemedySuggestionMethod = "weighted" | "cross" | "eliminative";

export type RemedySuggestionItem = {
  id: number;
  remedy_id: number | null;
  remedy_code: string | null;
  remedy_name: string;
  rank: number;
  confidence_score: string;
  repertory_score: string;
  materia_medica_score: string;
  knowledge_score: string;
  summary: string | null;
  matching_points: string[];
  differentiating_points: string[];
  missing_questions: string[];
  evidence_matrix: Array<{
    rubric_path: string;
    importance: string | null;
    weight: number | null;
    is_essential: boolean | null;
    covered: boolean;
  }>;
  repertory_evidence: Record<string, unknown>;
  materia_medica_evidence: Array<Record<string, unknown>>;
  potency_considerations: Array<Record<string, unknown>>;
  relationship_notes: Array<Record<string, unknown>>;
  medical_safety_notes: Array<Record<string, unknown>>;
  source_chunks: Array<Record<string, unknown>>;
  metadata: Record<string, unknown>;
  created_at: string | null;
};

export type RemedySuggestionRun = {
  id: number;
  patient_id: number;
  patient_visit_id: number;
  doctor_id: number;
  repertorization_run_id: number | null;
  method: RemedySuggestionMethod | null;
  status: string;
  limit: number;
  case_snapshot: Record<string, unknown>;
  selected_rubrics_snapshot: Array<Record<string, unknown>>;
  retrieved_sources: Record<string, unknown>;
  settings: Record<string, unknown>;
  safety_note: string | null;
  error_message: string | null;
  items: RemedySuggestionItem[];
  created_at: string | null;
  updated_at: string | null;
};

export type RemedySuggestionRunResponse = {
  data: RemedySuggestionRun[];
};

export async function getRemedySuggestionRuns(
  patientId: string | number,
  visitId: string | number
): Promise<RemedySuggestionRunResponse> {
  const response = await api.get(
    `/api/patients/${patientId}/visits/${visitId}/remedy-suggestions`,
    {
      params: {
        per_page: 5,
      },
    }
  );

  return response.data;
}

export async function generateRemedySuggestions(
  patientId: string | number,
  visitId: string | number,
  input: {
    method?: RemedySuggestionMethod | "";
    repertorization_run_id?: number | null;
    limit?: number;
    include_potency?: boolean;
    include_relationship?: boolean;
    include_medical_safety?: boolean;
    include_organon?: boolean;
  }
): Promise<RemedySuggestionRun> {
  const response = await api.post(
    `/api/patients/${patientId}/visits/${visitId}/remedy-suggestions/generate`,
    {
      method: input.method || null,
      repertorization_run_id: input.repertorization_run_id || null,
      limit: input.limit ?? 3,
      include_potency: input.include_potency ?? true,
      include_relationship: input.include_relationship ?? true,
      include_medical_safety: input.include_medical_safety ?? true,
      include_organon: input.include_organon ?? true,
    }
  );

  return response.data.data;
}

export type PrescriptionSourceMethod =
  | "manual"
  | "weighted"
  | "cross"
  | "eliminative";

export type PatientPrescription = {
  id: number;
  patient_visit_id: number;
  patient_id: number;
  doctor_id: number;

  repertorization_run_id: number | null;
  repertorization_result_id: number | null;
  remedy_id: number | null;
  source_method: PrescriptionSourceMethod | null;

  remedy_code: string | null;
  remedy_name: string;
  potency: string;
  repetition: string | null;

  dose_instruction: string | null;
  reason: string | null;
  advice: string | null;
  food_lifestyle_note: string | null;

  follow_up_date: string | null;

  status: "draft" | "final";
  finalized_at: string | null;

  created_at: string | null;
  updated_at: string | null;
};

export type PrescriptionInput = {
  repertorization_result_id: number | null;
  source_method: PrescriptionSourceMethod;
  remedy_code: string;
  remedy_name: string;
  potency: string;
  repetition: string;
  dose_instruction: string;
  reason: string;
  advice: string;
  food_lifestyle_note: string;
  follow_up_date: string;
  status: "draft" | "final";
};

function normalizePrescriptionInput(input: PrescriptionInput) {
  return {
    ...input,
    repertorization_result_id: input.repertorization_result_id || null,
    remedy_code: input.remedy_code || null,
    repetition: input.repetition || null,
    dose_instruction: input.dose_instruction || null,
    reason: input.reason || null,
    advice: input.advice || null,
    food_lifestyle_note: input.food_lifestyle_note || null,
    follow_up_date: input.follow_up_date || null,
  };
}

export async function getVisitPrescription(
  patientId: string | number,
  visitId: string | number
): Promise<PatientPrescription | null> {
  const response = await api.get(
    `/api/patients/${patientId}/visits/${visitId}/prescription`
  );

  return response.data.data;
}

export async function saveVisitPrescription(
  patientId: string | number,
  visitId: string | number,
  input: PrescriptionInput
): Promise<PatientPrescription> {
  const response = await api.put(
    `/api/patients/${patientId}/visits/${visitId}/prescription`,
    normalizePrescriptionInput(input)
  );

  return response.data.data;
}

export async function deleteVisitPrescription(
  patientId: string | number,
  visitId: string | number
) {
  const response = await api.delete(
    `/api/patients/${patientId}/visits/${visitId}/prescription`
  );

  return response.data;
}

export type PaymentMethod =
  | "cash"
  | "bkash"
  | "nagad"
  | "card"
  | "bank"
  | "other";

export type PatientFee = {
  id: number;

  patient_visit_id: number;
  patient_id: number;
  doctor_id: number;

  currency: string;

  consultation_fee: string;
  medicine_fee: string;
  discount_amount: string;
  total_amount: string;
  paid_amount: string;
  due_amount: string;

  payment_method: PaymentMethod | null;
  payment_status: "unpaid" | "partial" | "paid";
  payment_date: string | null;

  note: string | null;

  created_at: string | null;
  updated_at: string | null;
};

export type FeeInput = {
  currency: string;
  consultation_fee: string;
  medicine_fee: string;
  discount_amount: string;
  paid_amount: string;
  payment_method: string;
  payment_date: string;
  note: string;
};

function normalizeFeeInput(input: FeeInput) {
  return {
    currency: input.currency || "BDT",
    consultation_fee:
      input.consultation_fee === "" ? 0 : Number(input.consultation_fee),
    medicine_fee: input.medicine_fee === "" ? 0 : Number(input.medicine_fee),
    discount_amount:
      input.discount_amount === "" ? 0 : Number(input.discount_amount),
    paid_amount: input.paid_amount === "" ? 0 : Number(input.paid_amount),
    payment_method: input.payment_method || null,
    payment_date: input.payment_date || null,
    note: input.note || null,
  };
}

export async function getVisitFee(
  patientId: string | number,
  visitId: string | number
): Promise<PatientFee | null> {
  const response = await api.get(
    `/api/patients/${patientId}/visits/${visitId}/fee`
  );

  return response.data.data;
}

export async function saveVisitFee(
  patientId: string | number,
  visitId: string | number,
  input: FeeInput
): Promise<PatientFee> {
  const response = await api.put(
    `/api/patients/${patientId}/visits/${visitId}/fee`,
    normalizeFeeInput(input)
  );

  return response.data.data;
}

export async function deleteVisitFee(
  patientId: string | number,
  visitId: string | number
) {
  const response = await api.delete(
    `/api/patients/${patientId}/visits/${visitId}/fee`
  );

  return response.data;
}

export type PrintPatient = {
  id: number;
  name: string;
  age_years: number | null;
  gender: string | null;
  phone: string | null;
  address: string | null;
  occupation?: string | null;
  marital_status?: string | null;
  emergency_contact?: string | null;
  notes?: string | null;
};

export type PrintDoctor = {
  id: number;
  name: string;
  email: string;
  role: string;
  qualification?: string | null;
};

export type PrintClinic = {
  name: string;
  tagline: string | null;
  phone: string | null;
  email?: string | null;
  website?: string | null;
  address: string | null;
  logo_url?: string | null;
  prescription_footer?: string | null;
  case_sheet_footer?: string | null;
};

export type PrintVisit = {
  id: number;
  visit_date: string | null;
  visit_type?: string;
  status?: string;
  case_source?: string;
  chief_complaint: string | null;
  raw_case_text?: string | null;
  case_sections?: Record<string, string>;
  missing_questions?: string[];
  red_flags?: string[];
  doctor_notes?: string | null;
  next_follow_up_date?: string | null;
};

export type PrintPrescription = {
  remedy_code?: string | null;
  remedy_name: string;
  potency: string;
  repetition: string | null;
  dose_instruction: string | null;
  reason?: string | null;
  advice: string | null;
  food_lifestyle_note: string | null;
  follow_up_date: string | null;
  status: string;
};

export type PrintRubric = {
  id: number;
  rubric_path: string | null;
  symptom_type: string;
  importance: string;
  weight: number;
  is_essential: boolean;
  note: string | null;
};

export type PrintRepertorizationRun = {
  id: number;
  method: string;
  total_rubrics: number;
  essential_rubrics_count: number;
  results: Array<{
    rank: number;
    remedy_code: string;
    remedy_name: string;
    total_score: number;
    rubric_coverage: number;
    essential_coverage: number;
  }>;
};

export type PrintFee = {
  currency: string;
  consultation_fee: string;
  medicine_fee: string;
  discount_amount: string;
  total_amount: string;
  paid_amount: string;
  due_amount: string;
  payment_method: string | null;
  payment_status: string;
  payment_date: string | null;
  note: string | null;
};

export type CaseSheetPrintData = {
  document_type: "doctor_case_sheet";
  generated_at: string;
  clinic: PrintClinic;
  doctor: PrintDoctor;
  patient: PrintPatient;
  visit: PrintVisit;
  rubrics: PrintRubric[];
  repertorization_runs: PrintRepertorizationRun[];
  prescription: PrintPrescription | null;
  fee: PrintFee | null;
};

export type PrescriptionPrintData = {
  document_type: "patient_prescription";
  generated_at: string;
  clinic: PrintClinic;
  doctor: PrintDoctor;
  patient: PrintPatient;
  visit: PrintVisit;
  prescription: PrintPrescription | null;
};

export async function getCaseSheetPrintData(
  patientId: string | number,
  visitId: string | number
): Promise<CaseSheetPrintData> {
  const response = await api.get(
    `/api/patients/${patientId}/visits/${visitId}/print/case-sheet`
  );

  return response.data.data;
}

export async function getPrescriptionPrintData(
  patientId: string | number,
  visitId: string | number
): Promise<PrescriptionPrintData> {
  const response = await api.get(
    `/api/patients/${patientId}/visits/${visitId}/print/prescription`
  );

  return response.data.data;
}
