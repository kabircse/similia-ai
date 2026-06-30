import type { Permission } from "./api";

export function hasPermission(
  permissions: Permission[] | undefined,
  permission: Permission
) {
  return Boolean(permissions?.includes(permission));
}
