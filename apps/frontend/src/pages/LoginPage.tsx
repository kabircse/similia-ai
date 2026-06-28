import { FormEvent, useState } from "react";
import { useNavigate } from "react-router";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { login } from "../lib/api";

export function LoginPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [email, setEmail] = useState("doctor@similia.test");
  const [password, setPassword] = useState("password");

  const loginMutation = useMutation({
    mutationFn: () => login(email, password),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["auth", "me"] });
      navigate("/dashboard", { replace: true });
    },
  });

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    loginMutation.mutate();
  }

  return (
    <main className="login-screen">
      <section className="login-card">
        <div className="login-brand">
          <div className="brand-mark large">S</div>
          <h1>Similia AI</h1>
          <p>Doctor Workspace Login</p>
        </div>

        <form onSubmit={handleSubmit} className="login-form">
          <label>
            Email
            <input
              type="email"
              value={email}
              autoComplete="email"
              onChange={(event) => setEmail(event.target.value)}
            />
          </label>

          <label>
            Password
            <input
              type="password"
              value={password}
              autoComplete="current-password"
              onChange={(event) => setPassword(event.target.value)}
            />
          </label>

          {loginMutation.isError && (
            <div className="form-error">
              Login failed. Please check your credentials.
            </div>
          )}

          <button type="submit" disabled={loginMutation.isPending}>
            {loginMutation.isPending ? "Signing in..." : "Sign in"}
          </button>
        </form>

        <div className="demo-note">
          <strong>Demo:</strong> doctor@similia.test / password
        </div>
      </section>
    </main>
  );
}