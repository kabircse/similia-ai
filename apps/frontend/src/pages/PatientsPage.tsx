import { useState } from "react";
import { Link } from "react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus, Search, Trash2, UserRound } from "lucide-react";
import { deletePatient, getPatients } from "../lib/api";

export function PatientsPage() {
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");

  const { data, isLoading, isError } = useQuery({
    queryKey: ["patients", search],
    queryFn: () => getPatients(search),
  });

  const deleteMutation = useMutation({
    mutationFn: deletePatient,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["patients"] });
      await queryClient.invalidateQueries({ queryKey: ["dashboard", "overview"] });
    },
  });

  return (
    <div className="page-stack">
      <section className="page-header">
        <div>
          <p className="eyebrow">Patients</p>
          <h1>Patient Records</h1>
          <p>Create, search, and manage patient profiles for clinical visits.</p>
        </div>

        <Link to="/patients/new" className="primary-link">
          <Plus size={18} />
          New Patient
        </Link>
      </section>

      <section className="panel">
        <div className="search-row">
          <div className="search-box">
            <Search size={18} />
            <input
              value={search}
              placeholder="Search by name or phone..."
              onChange={(event) => setSearch(event.target.value)}
            />
          </div>
        </div>

        {isLoading && <p className="empty-state">Loading patients...</p>}

        {isError && (
          <p className="form-error">Unable to load patients. Please try again.</p>
        )}

        {!isLoading && data?.data.length === 0 && (
          <div className="empty-panel">
            <UserRound size={34} />
            <h3>No patients yet</h3>
            <p>Create your first patient record to start case-taking.</p>
            <Link to="/patients/new" className="secondary-link">
              Add patient
            </Link>
          </div>
        )}

        {data && data.data.length > 0 && (
          <div className="patient-table">
            <div className="patient-table-head">
              <span>Name</span>
              <span>Age/Gender</span>
              <span>Phone</span>
              <span>Occupation</span>
              <span>Actions</span>
            </div>

            {data.data.map((patient) => (
              <div className="patient-row" key={patient.id}>
                <div>
                  <Link to={`/patients/${patient.id}`} className="patient-name">
                    {patient.name}
                  </Link>
                  <p>{patient.address || "No address added"}</p>
                </div>

                <span>
                  {patient.age_years ?? "-"} / {patient.gender ?? "-"}
                </span>

                <span>{patient.phone || "-"}</span>

                <span>{patient.occupation || "-"}</span>

                <div className="table-actions">
                  <Link to={`/patients/${patient.id}/edit`}>Edit</Link>
                  <button
                    className="danger-button"
                    onClick={() => {
                      if (confirm(`Delete ${patient.name}?`)) {
                        deleteMutation.mutate(patient.id);
                      }
                    }}
                    disabled={deleteMutation.isPending}
                  >
                    <Trash2 size={15} />
                    Delete
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}