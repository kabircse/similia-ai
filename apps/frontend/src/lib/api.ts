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
  role: "doctor" | "admin" | string;
};

export type DashboardOverview = {
  doctor: AuthUser;
  summary: {
    total_patients: number;
    today_visits: number;
    pending_followups: number;
    prescriptions_saved: number;
  };
  clinical_workflow: Array<{
    title: string;
    description: string;
    status: string;
  }>;
  recent_activity: Array<{
    title: string;
    description: string;
    created_at: string;
  }>;
};

export async function csrfCookie() {
  await api.get("/sanctum/csrf-cookie");
}

export async function login(email: string, password: string) {
  await csrfCookie();

  const response = await api.post("/api/login", {
    email,
    password,
  });

  return response.data;
}

export async function getMe(): Promise<{ user: AuthUser }> {
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

export type RepertoryRubric = {
  id: number;
  source: string;
  chapter: string | null;
  rubric_path: string;
  rubric_text: string;
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