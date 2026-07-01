import { Navigate, Route, Routes } from "react-router";
import { ProtectedRoute } from "./components/routes/ProtectedRoute";
import { DashboardLayout } from "./components/layout/DashboardLayout";
import { AdvancedSearchPage } from "./pages/AdvancedSearchPage";
import { DashboardPage } from "./pages/DashboardPage";
import { LoginPage } from "./pages/LoginPage";
import { PatientsPage } from "./pages/PatientsPage";
import { PatientFormPage } from "./pages/PatientFormPage";
import { PatientDetailPage } from "./pages/PatientDetailPage";
import { VisitFormPage } from "./pages/VisitFormPage";
import { VisitDetailPage } from "./pages/VisitDetailPage";
import { ActivityLogPage } from "./pages/ActivityLogPage";
import { ClinicSettingsPage } from "./pages/ClinicSettingsPage";
import { ClinicalDashboardPage } from "./pages/ClinicalDashboardPage";
import { ClinicReportPrintPage } from "./pages/ClinicReportPrintPage";
import { ClinicReportsPage } from "./pages/ClinicReportsPage";
import { CaseSheetPrintPage } from "./pages/print/CaseSheetPrintPage";
import { PatientHandoutPrintPage } from "./pages/print/PatientHandoutPrintPage";
import { PrescriptionPrintPage } from "./pages/print/PrescriptionPrintPage";
import { NotFoundPage } from "./pages/NotFoundPage";

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
        path="/clinical-dashboard"
        element={
          <ProtectedLayout>
            <ClinicalDashboardPage />
          </ProtectedLayout>
        }
      />

      <Route
        path="/clinic-reports"
        element={
          <ProtectedLayout>
            <ClinicReportsPage />
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
        path="/search"
        element={
          <ProtectedLayout>
            <AdvancedSearchPage />
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

      <Route
        path="/patients/:patientId/visits/new"
        element={
          <ProtectedRoute>
            <DashboardLayout>
              <VisitFormPage />
            </DashboardLayout>
          </ProtectedRoute>
        }
      />

      <Route
        path="/patients/:patientId/visits/:visitId"
        element={
          <ProtectedRoute>
            <DashboardLayout>
              <VisitDetailPage />
            </DashboardLayout>
          </ProtectedRoute>
        }
      />

      <Route
        path="/patients/:patientId/visits/:visitId/edit"
        element={
          <ProtectedRoute>
            <DashboardLayout>
              <VisitFormPage />
            </DashboardLayout>
          </ProtectedRoute>
        }
      />

      <Route
        path="/activity"
        element={
          <ProtectedLayout>
            <ActivityLogPage />
          </ProtectedLayout>
        }
      />

      <Route
        path="/settings"
        element={
          <ProtectedLayout>
            <ClinicSettingsPage />
          </ProtectedLayout>
        }
      />

      <Route
        path="/patients/:patientId/visits/:visitId/print/case-sheet"
        element={
          <ProtectedRoute>
            <CaseSheetPrintPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/patients/:patientId/visits/:visitId/print/prescription"
        element={
          <ProtectedRoute>
            <PrescriptionPrintPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/patients/:patientId/visits/:visitId/handouts/:handoutId/print"
        element={
          <ProtectedRoute>
            <PatientHandoutPrintPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/clinic-reports/:reportId/print"
        element={
          <ProtectedRoute>
            <ClinicReportPrintPage />
          </ProtectedRoute>
        }
      />

      {[
        ["case-taking", "Case Taking"],
        ["repertory", "Repertory"],
        ["materia-medica", "Materia Medica"],
        ["prescriptions", "Prescriptions"],
        ["fees", "Fees"],
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

      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}

export default App;
