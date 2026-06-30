import { Link } from "react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Bell } from "lucide-react";
import {
  getNotifications,
  getUnreadNotificationCount,
  markAllNotificationsAsRead,
  markNotificationAsRead,
} from "../../lib/api";

export function NotificationBell() {
  const queryClient = useQueryClient();

  const countQuery = useQuery({
    queryKey: ["notifications", "unread-count"],
    queryFn: getUnreadNotificationCount,
    refetchInterval: 15000,
  });

  const notificationsQuery = useQuery({
    queryKey: ["notifications", "latest"],
    queryFn: () => getNotifications(),
    refetchInterval: 15000,
  });

  const readMutation = useMutation({
    mutationFn: markNotificationAsRead,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["notifications"] });
      await queryClient.invalidateQueries({ queryKey: ["dashboard", "overview"] });
    },
  });

  const readAllMutation = useMutation({
    mutationFn: markAllNotificationsAsRead,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["notifications"] });
      await queryClient.invalidateQueries({ queryKey: ["dashboard", "overview"] });
    },
  });

  const unreadCount = countQuery.data ?? 0;
  const notifications = notificationsQuery.data?.data ?? [];

  return (
    <div className="notification-bell">
      <button className="notification-button" type="button" aria-label="Notifications">
        <Bell size={18} />
        {unreadCount > 0 && <span>{unreadCount}</span>}
      </button>

      <div className="notification-dropdown">
        <div className="notification-header">
          <strong>Notifications</strong>

          {unreadCount > 0 && (
            <button
              type="button"
              onClick={() => readAllMutation.mutate()}
              disabled={readAllMutation.isPending}
            >
              Mark all read
            </button>
          )}
        </div>

        {notifications.length === 0 ? (
          <p className="notification-empty">No notifications yet.</p>
        ) : (
          <div className="notification-list">
            {notifications.map((notification) => (
              <div
                className={`notification-item ${notification.is_read ? "" : "unread"}`}
                key={notification.id}
              >
                <div>
                  <strong>{notification.title}</strong>
                  {notification.message && <p>{notification.message}</p>}
                  <small>
                    {notification.created_at
                      ? new Date(notification.created_at).toLocaleString()
                      : ""}
                  </small>
                </div>

                <div className="notification-actions">
                  {notification.action_url && (
                    <Link
                      to={notification.action_url}
                      onClick={() => readMutation.mutate(notification.id)}
                    >
                      Open
                    </Link>
                  )}

                  {!notification.is_read && (
                    <button
                      type="button"
                      onClick={() => readMutation.mutate(notification.id)}
                      disabled={readMutation.isPending}
                    >
                      Read
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
