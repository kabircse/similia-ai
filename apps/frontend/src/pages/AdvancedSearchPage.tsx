import { type FormEvent, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ExternalLink, Search, SlidersHorizontal } from "lucide-react";
import { advancedSearch, type AdvancedSearchResult, type AdvancedSearchType } from "../lib/api";

const SEARCH_TYPES: Array<{ value: AdvancedSearchType; label: string }> = [
  { value: "patients", label: "Patients" },
  { value: "visits", label: "Visits" },
  { value: "prescriptions", label: "Prescriptions" },
  { value: "remedy_suggestions", label: "Remedy Suggestions" },
  { value: "follow_up_analyses", label: "Follow-up Analyses" },
  { value: "potency_guidance", label: "Potency Guidance" },
  { value: "remedy_relationships", label: "Remedy Relationships" },
  { value: "prescription_reviews", label: "Prescription Reviews" },
  { value: "patient_handouts", label: "Patient Handouts" },
  { value: "clinic_reports", label: "Clinic Reports" },
];

const DEFAULT_TYPES: AdvancedSearchType[] = [
  "patients",
  "visits",
  "prescriptions",
  "follow_up_analyses",
  "prescription_reviews",
];

export function AdvancedSearchPage() {
  const [query, setQuery] = useState("");
  const [submittedQuery, setSubmittedQuery] = useState("");
  const [types, setTypes] = useState<AdvancedSearchType[]>(DEFAULT_TYPES);
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");

  const searchQuery = useQuery({
    queryKey: ["advanced-search", submittedQuery, types, dateFrom, dateTo],
    queryFn: () =>
      advancedSearch({
        q: submittedQuery,
        types,
        date_from: dateFrom || null,
        date_to: dateTo || null,
        limit: 75,
      }),
    enabled: submittedQuery.trim().length >= 2,
  });

  function submit(event: FormEvent) {
    event.preventDefault();
    setSubmittedQuery(query.trim());
  }

  function toggleType(type: AdvancedSearchType) {
    setTypes((current) =>
      current.includes(type)
        ? current.filter((item) => item !== type)
        : [...current, type]
    );
  }

  const grouped = useMemo(() => {
    return (searchQuery.data?.data ?? []).reduce<Record<string, AdvancedSearchResult[]>>(
      (carry, item) => {
        carry[item.type] = carry[item.type] ?? [];
        carry[item.type].push(item);
        return carry;
      },
      {}
    );
  }, [searchQuery.data]);

  return (
    <main className="page advanced-search-page">
      <div className="page-header">
        <div>
          <p className="eyebrow">Global Search</p>
          <h1>Advanced Search</h1>
          <p>
            Search across patients, visits, prescriptions, AI outputs, handouts,
            and reports.
          </p>
        </div>
      </div>

      <section className="panel advanced-search-panel">
        <form className="advanced-search-form" onSubmit={submit}>
          <div className="advanced-search-box">
            <Search size={18} />
            <input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search name, phone, remedy, symptom, red flag, potency, report..."
            />
            <button className="primary-button inline-button" type="submit">
              Search
            </button>
          </div>

          <details className="advanced-search-filters">
            <summary>
              <SlidersHorizontal size={16} />
              Filters
            </summary>

            <div className="search-filter-grid">
              <label>
                Date From
                <input
                  type="date"
                  value={dateFrom}
                  onChange={(event) => setDateFrom(event.target.value)}
                />
              </label>

              <label>
                Date To
                <input
                  type="date"
                  value={dateTo}
                  onChange={(event) => setDateTo(event.target.value)}
                />
              </label>
            </div>

            <div className="search-type-grid">
              {SEARCH_TYPES.map((item) => (
                <label className="search-type-pill" key={item.value}>
                  <input
                    type="checkbox"
                    checked={types.includes(item.value)}
                    onChange={() => toggleType(item.value)}
                  />
                  <span>{item.label}</span>
                </label>
              ))}
            </div>
          </details>
        </form>
      </section>

      {searchQuery.isLoading && <p className="empty-state">Searching records...</p>}

      {searchQuery.isError && (
        <div className="form-error">
          Search failed. Try a shorter query or fewer filters.
        </div>
      )}

      {searchQuery.data && (
        <section className="search-results-section">
          <div className="search-results-summary">
            <strong>{searchQuery.data.meta.total}</strong> result(s) for{" "}
            <strong>{searchQuery.data.meta.query}</strong>
          </div>

          {Object.entries(grouped).map(([type, results]) => (
            <div className="search-result-group" key={type}>
              <h3>{labelForType(type as AdvancedSearchType)}</h3>

              <div className="search-result-list">
                {results.map((result) => (
                  <SearchResultCard result={result} key={`${result.type}-${result.id}`} />
                ))}
              </div>
            </div>
          ))}

          {searchQuery.data.data.length === 0 && (
            <p className="empty-state">No matching records found.</p>
          )}
        </section>
      )}
    </main>
  );
}

function SearchResultCard({ result }: { result: AdvancedSearchResult }) {
  const content = (
    <article className={`search-result-card ${result.type}`}>
      <div>
        <p className="eyebrow">{result.label}</p>
        <h4>{result.title}</h4>

        {result.subtitle && <p className="search-result-subtitle">{result.subtitle}</p>}
        {result.snippet && <p className="search-result-snippet">{result.snippet}</p>}

        <div className="search-result-meta">
          {result.patient_name && <span>Patient: {result.patient_name}</span>}
          {result.created_at && (
            <span>{new Date(result.created_at).toLocaleString()}</span>
          )}
          <span>Score: {result.score}</span>
        </div>
      </div>

      {result.url && <ExternalLink size={18} />}
    </article>
  );

  if (!result.url) {
    return content;
  }

  return (
    <a className="search-result-link" href={result.url}>
      {content}
    </a>
  );
}

function labelForType(type: AdvancedSearchType) {
  return SEARCH_TYPES.find((item) => item.value === type)?.label ?? type;
}
