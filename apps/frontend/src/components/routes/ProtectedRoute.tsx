import type { ReactNode } from "react";
import { Navigate } from "react-router";
import { useQuery } from "@tanstack/react-query";
import { getMe } from "../../lib/api";

type ProtectedRouteProps = {
  children: ReactNode;
};

export function ProtectedRoute({ children }: ProtectedRouteProps) {
  const { isLoading, isError } = useQuery({
    queryKey: ["auth", "me"],
    queryFn: getMe,
    retry: false,
  });

  if (isLoading) {
    return (
      <div className="screen-center">
        <div className="loading-card">Checking authentication...</div>
      </div>
    );
  }

  if (isError) {
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
}
