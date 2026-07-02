import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { CaseSheetPrintPage } from "../pages/print/CaseSheetPrintPage";

const mockUseQuery = vi.fn();

vi.mock("@tanstack/react-query", () => ({
  useQuery: (...args: unknown[]) => mockUseQuery(...args),
}));

vi.mock("react-router", () => ({
  Link: ({ children, to }: { children: React.ReactNode; to: string }) => (
    <a href={to}>{children}</a>
  ),
  useParams: () => ({ patientId: "1", visitId: "2" }),
}));

describe("CaseSheetPrintPage", () => {
  beforeEach(() => {
    mockUseQuery.mockReset();
  });

  it("renders doctor-scoped print branding on case sheets", () => {
    mockUseQuery.mockReturnValue({
      data: {
        document_type: "doctor_case_sheet",
        generated_at: "2026-07-02T08:00:00.000Z",
        clinic: {
          name: "Similia Clinic",
          tagline: "Classical Homeopathy",
          phone: null,
          email: "doctor@example.com",
          website: null,
          address: "Dhaka",
          logo_url: null,
          prescription_footer: null,
          case_sheet_footer: "Private clinical document.",
          prescription_header: "Dr. Case Header\nCase Sheet Brand",
          prescription_disclaimer: "Doctor-only case sheet disclaimer.",
        },
        doctor: {
          id: 7,
          name: "Dr. Kabir Hossain",
          email: "doctor@example.com",
          role: "doctor",
          qualification: "D.H.M.S",
        },
        patient: {
          id: 1,
          name: "A. Rahman",
          age_years: 34,
          gender: "male",
          phone: "0123456789",
          address: "Dhaka",
          occupation: null,
          marital_status: null,
          emergency_contact: null,
        },
        visit: {
          id: 2,
          visit_date: "2026-07-01",
          visit_type: "initial",
          status: "draft",
          case_source: "manual",
          chief_complaint: "Sleeplessness",
          raw_case_text: null,
          case_sections: {},
          missing_questions: [],
          red_flags: [],
          doctor_notes: null,
          next_follow_up_date: null,
        },
        rubrics: [],
        repertorization_runs: [],
        prescription: null,
        fee: null,
      },
      isLoading: false,
      isError: false,
    });

    render(<CaseSheetPrintPage />);

    expect(screen.getByText(/Case Sheet Brand/)).toBeInTheDocument();
    expect(screen.getByText("Doctor-only case sheet disclaimer.")).toBeInTheDocument();
    expect(screen.getByText("Private clinical document.")).toBeInTheDocument();
  });
});
