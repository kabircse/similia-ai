import { Navigate, Route, Routes } from "react-router";
import { ProtectedRoute } from "./components/routes/ProtectedRoute";
import { DashboardLayout } from "./components/layout/DashboardLayout";
import { DashboardPage } from "./pages/DashboardPage";
import { LoginPage } from "./pages/LoginPage";
import { PatientsPage } from "./pages/PatientsPage";
import { PatientFormPage } from "./pages/PatientFormPage";
import { PatientDetailPage } from "./pages/PatientDetailPage";

function ComingSoonPage({ title }: { title: string }) {
  return (
    <div className="panel">
      <h1>{title}</h1>
      <p>This module will be implemented in upcoming issues.</p>
    </div>
  );
}

function ProtectedLayout({ children }: { children: React.ReactNode }) {
  return (
    <ProtectedRoute>
      <DashboardLayout>{children}</DashboardLayout>
    </ProtectedRoute>
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
          <ProtectedLayout>
            <DashboardPage />
          </ProtectedLayout>
        }
      />

      <Route
        path="/patients"
        element={
          <ProtectedLayout>
            <PatientsPage />
          </ProtectedLayout>
        }
      />

      <Route
        path="/patients/new"
        element={
          <ProtectedLayout>
            <PatientFormPage />
          </ProtectedLayout>
        }
      />

      <Route
        path="/patients/:patientId"
        element={
          <ProtectedLayout>
            <PatientDetailPage />
          </ProtectedLayout>
        }
      />

      <Route
        path="/patients/:patientId/edit"
        element={
          <ProtectedLayout>
            <PatientFormPage />
          </ProtectedLayout>
        }
      />

      {[
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
            <ProtectedLayout>
              <ComingSoonPage title={title} />
            </ProtectedLayout>
          }
        />
      ))}
    </Routes>
  );
}

export default App;