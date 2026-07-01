import type { ElementType, ReactNode } from "react";
import { NavLink, useNavigate } from "react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  CalendarClock,
  LayoutDashboard,
  Users,
  ClipboardList,
  Search,
  BookOpen,
  FileText,
  Receipt,
  Settings,
  LogOut,
  Activity,
  HeartPulse,
  Inbox,
} from "lucide-react";
import { getMe, logout } from "../../lib/api";
import { hasPermission } from "../../lib/permissions";
import type { Permission } from "../../lib/api";
import { NotificationBell } from "../notifications/NotificationBell";

type DashboardLayoutProps = {
  children: ReactNode;
};

const navItems: Array<{
  label: string;
  path: string;
  icon: ElementType;
  permission: Permission;
}> = [
  {
    label: "Dashboard",
    path: "/dashboard",
    icon: LayoutDashboard,
    permission: "view_dashboard",
  },
  {
    label: "Patients",
    path: "/patients",
    icon: Users,
    permission: "manage_patients",
  },
  {
    label: "Appointments",
    path: "/appointments",
    icon: CalendarClock,
    permission: "manage_visits",
  },
  {
    label: "Review Queue",
    path: "/doctor-review-queue",
    icon: Inbox,
    permission: "manage_visits",
  },
  {
    label: "Advanced Search",
    path: "/search",
    icon: Search,
    permission: "view_dashboard",
  },
  {
    label: "Clinical Dashboard",
    path: "/clinical-dashboard",
    icon: HeartPulse,
    permission: "view_dashboard",
  },
  {
    label: "Clinic Reports",
    path: "/clinic-reports",
    icon: FileText,
    permission: "view_activity_logs",
  },
  {
    label: "Case Taking",
    path: "/case-taking",
    icon: ClipboardList,
    permission: "manage_visits",
  },
  {
    label: "Repertory",
    path: "/repertory",
    icon: Search,
    permission: "manage_rubrics",
  },
  {
    label: "Materia Medica",
    path: "/materia-medica",
    icon: BookOpen,
    permission: "compare_materia_medica",
  },
  {
    label: "Prescriptions",
    path: "/prescriptions",
    icon: FileText,
    permission: "manage_prescriptions",
  },
  {
    label: "Fees",
    path: "/fees",
    icon: Receipt,
    permission: "manage_fees",
  },
  {
    label: "Activity",
    path: "/activity",
    icon: Activity,
    permission: "view_activity_logs",
  },
  {
    label: "Settings",
    path: "/settings",
    icon: Settings,
    permission: "manage_clinic_settings",
  },
];

export function DashboardLayout({ children }: DashboardLayoutProps) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const { data } = useQuery({
    queryKey: ["auth", "me"],
    queryFn: getMe,
    retry: false,
  });
  const permissions = data?.permissions ?? [];

  const logoutMutation = useMutation({
    mutationFn: logout,
    onSuccess: async () => {
      queryClient.clear();
      navigate("/login", { replace: true });
    },
  });

  return (
    <div className="dashboard-shell">
      <aside className="sidebar">
        <div className="brand">
          <div className="brand-mark">S</div>
          <div>
            <h1>Similia AI</h1>
            <p>Doctor Workspace</p>
          </div>
        </div>

        <nav className="sidebar-nav">
          {navItems
            .filter((item) => hasPermission(permissions, item.permission))
            .map((item) => {
              const Icon = item.icon;

              return (
                <NavLink
                  key={item.path}
                  to={item.path}
                  className={({ isActive }) =>
                    `nav-item ${isActive ? "active" : ""}`
                  }
                >
                  <Icon size={18} />
                  <span>{item.label}</span>
                </NavLink>
              );
            })}
        </nav>
      </aside>

      <main className="main-area">
        <header className="topbar">
          <div>
            <h2>Clinical Dashboard</h2>
            <p>Case-taking, repertorization, prescription and timeline workspace.</p>
          </div>

          <div className="user-menu">
            <NotificationBell />

            <div className="user-meta">
              <strong>{data?.user.name}</strong>
              <span>{data?.user.role}</span>
            </div>

            <button
              className="logout-button"
              onClick={() => logoutMutation.mutate()}
              disabled={logoutMutation.isPending}
            >
              <LogOut size={16} />
              Logout
            </button>
          </div>
        </header>

        <section className="content-area">{children}</section>
      </main>
    </div>
  );
}
