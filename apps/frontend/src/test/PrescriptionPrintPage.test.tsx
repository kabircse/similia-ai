import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi, beforeEach } from "vitest";
import { PrescriptionPrintPage } from "../pages/print/PrescriptionPrintPage";

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

describe("PrescriptionPrintPage", () => {
  beforeEach(() => {
    mockUseQuery.mockReset();
  });

  it("renders doctor-scoped prescription header and disclaimer", () => {
    mockUseQuery.mockReturnValue({
      data: {
        clinic: {
          name: "Similia Clinic",
          tagline: "Classical Homeopathy",
          phone: null,
          email: "doctor@example.com",
          website: null,
          address: "Dhaka",
          logo_url: null,
          prescription_footer: "Please follow the prescribed instructions.",
          case_sheet_footer: null,
          prescription_header: "Dr. Kabir Hossain\nD.H.M.S",
          prescription_disclaimer: "Use this prescription only as advised.",
        },
        doctor: {
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
        },
        visit: {
          id: 2,
          visit_date: "2026-07-01",
          chief_complaint: "Sleeplessness",
        },
        prescription: {
          remedy_name: "Arsenicum Album",
          potency: "30C",
          repetition: "BD",
          dose_instruction: "Take 5 drops twice daily",
          advice: null,
          food_lifestyle_note: null,
          follow_up_date: null,
          status: "active",
        },
      },
      isLoading: false,
      isError: false,
    });

    render(<PrescriptionPrintPage />);

    expect(screen.getByText("Dr. Kabir Hossain")).toBeInTheDocument();
    expect(screen.getByText("D.H.M.S")).toBeInTheDocument();
    expect(screen.getByText("Use this prescription only as advised.")).toBeInTheDocument();
  });
});
