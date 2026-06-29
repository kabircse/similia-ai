import { Link } from "react-router";

export function NotFoundPage() {
  return (
    <main className="screen-center">
      <section className="login-card">
        <p className="eyebrow">404</p>
        <h1>Page not found</h1>
        <p>The page you are looking for does not exist or was moved.</p>

        <Link to="/dashboard" className="primary-link">
          Back to Dashboard
        </Link>
      </section>
    </main>
  );
}
