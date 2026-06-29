import type { ReactNode } from "react";
import { NavLink, useNavigate } from "react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  LayoutDashboard,
  Users,
  ClipboardList,
  Search,
  BookOpen,
  FileText,
  Receipt,
  Settings,
  LogOut,
} from "lucide-react";
import { getMe, logout } from "../../lib/api";

type DashboardLayoutProps = {
  children: ReactNode;
};

const navItems = [
  { label: "Dashboard", path: "/dashboard", icon: LayoutDashboard },
  { label: "Patients", path: "/patients", icon: Users },
  { label: "Case Taking", path: "/case-taking", icon: ClipboardList },
  { label: "Repertory", path: "/repertory", icon: Search },
  { label: "Materia Medica", path: "/materia-medica", icon: BookOpen },
  { label: "Prescriptions", path: "/prescriptions", icon: FileText },
  { label: "Fees", path: "/fees", icon: Receipt },
  { label: "Settings", path: "/settings", icon: Settings },
];

export function DashboardLayout({ children }: DashboardLayoutProps) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const { data } = useQuery({
    queryKey: ["auth", "me"],
    queryFn: getMe,
    retry: false,
  });

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
          {navItems.map((item) => {
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
