import axios from "axios";

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000",
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: "application/json",
  },
});

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

export async function getMe() {
  const response = await api.get("/api/me");
  return response.data;
}

export async function logout() {
  const response = await api.post("/api/logout");
  return response.data;
}