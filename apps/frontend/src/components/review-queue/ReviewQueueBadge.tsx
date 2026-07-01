import { useQuery } from "@tanstack/react-query";
import { AlertTriangle, Bell } from "lucide-react";
import { Link } from "react-router";
import { getDoctorReviewQueueSummary } from "../../lib/api";

export function ReviewQueueBadge() {
  const summaryQuery = useQuery({
    queryKey: ["doctor-review-queue-summary"],
    queryFn: getDoctorReviewQueueSummary,
    refetchInterval: 60_000,
  });

  const summary = summaryQuery.data;
  const openCount = summary?.open_count ?? 0;
  const urgentCount = summary?.urgent_count ?? 0;
  const badgeCount = urgentCount > 0 ? urgentCount : openCount;

  return (
    <Link className="review-queue-badge" to="/doctor-review-queue">
      {urgentCount > 0 ? <AlertTriangle size={17} /> : <Bell size={17} />}
      <span>Review Queue</span>
      {badgeCount > 0 && (
        <strong className={urgentCount > 0 ? "urgent" : ""}>{badgeCount}</strong>
      )}
    </Link>
  );
}
