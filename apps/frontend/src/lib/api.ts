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