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