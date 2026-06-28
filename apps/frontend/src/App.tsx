import { Navigate, Route, Routes } from "react-router";
import { ProtectedRoute } from "./components/routes/ProtectedRoute";
import { DashboardLayout } from "./components/layout/DashboardLayout";
import { DashboardPage } from "./pages/DashboardPage";
import { LoginPage } from "./pages/LoginPage";

function ComingSoonPage({ title }: { title: string }) {
  return (
    <div className="panel">
      <h1>{title}</h1>
      <p>This module will be implemented in upcoming issues.</p>
    </div>
  );
}

function App() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/dashboard" replace />} />
      <Route path="/login" element={<LoginPage />} />

      <Route
        path="/dashboard"
        element={
          <ProtectedRoute>
            <DashboardLayout>
              <DashboardPage />
            </DashboardLayout>
          </ProtectedRoute>
        }
      />

      {[
        ["patients", "Patients"],
        ["case-taking", "Case Taking"],
        ["repertory", "Repertory"],
        ["materia-medica", "Materia Medica"],
        ["prescriptions", "Prescriptions"],
        ["fees", "Fees"],
        ["settings", "Settings"],
      ].map(([path, title]) => (
        <Route
          key={path}
          path={`/${path}`}
          element={
            <ProtectedRoute>
              <DashboardLayout>
                <ComingSoonPage title={title} />
              </DashboardLayout>
            </ProtectedRoute>
          }
        />
      ))}
    </Routes>
  );
}

export default App;