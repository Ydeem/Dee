import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
  const page = usePage();

  const authUser = computed<any>(() => ((page.props as any)?.auth?.user ?? null));
  const currentUser = authUser;

  function toNames(values: unknown): string[] {
    if (!Array.isArray(values)) return [];

    return values
      .map((value) => {
        if (typeof value === 'string') return value.trim();
        if (value && typeof value === 'object') {
          const record = value as Record<string, unknown>;
          if (typeof record.name === 'string') return record.name.trim();
          if (typeof record.title === 'string') return record.title.trim();
        }
        return '';
      })
      .filter(Boolean);
  }

  function unique(values: string[]): string[] {
    return [...new Set(values)];
  }

  const roles = computed<string[]>(() => {
    const fromUser = toNames(authUser.value?.roles);
    const legacyRole = (page.props as any)?.auth?.role;
    const fromLegacy = typeof legacyRole === 'string' && legacyRole.trim() ? [legacyRole.trim()] : [];
    return unique([...fromUser, ...fromLegacy]);
  });

  const permissions = computed<string[]>(() => {
    const fromUser = toNames(authUser.value?.permissions);
    const fromLegacy = toNames((page.props as any)?.auth?.permissions);

    return unique(
      [...fromUser, ...fromLegacy]
        .map((permission) => permission.trim().toLowerCase())
        .filter(Boolean)
    );
  });

  const userPermissions = permissions;

  const isAdmin = computed<boolean>(() => {
    const hasAdminRole = roles.value.some((role) => {
      const normalized = role.trim().toLowerCase();
      return normalized === 'hr admin' || normalized === 'super-admin' || normalized === 'super admin';
    });

    return authUser.value?.is_admin === true || hasAdminRole;
  });

  function normalizePermission(permission: string): string {
    return String(permission).trim().toLowerCase();
  }

  function can(permission: string): boolean {
    if (isAdmin.value) return true;
    return permissions.value.includes(normalizePermission(permission));
  }

  function hasRole(role: string): boolean {
    const target = String(role).trim().toLowerCase();
    return roles.value.some((item) => item.toLowerCase() === target);
  }

  function canAny(...permissionsToCheck: string[]): boolean {
    if (permissionsToCheck.length === 0) return false;
    return permissionsToCheck.some((permission) => can(permission));
  }

  function canAll(...permissionsToCheck: string[]): boolean {
    if (permissionsToCheck.length === 0) return false;
    return permissionsToCheck.every((permission) => can(permission));
  }

  return {
    can,
    hasRole,
    canAny,
    canAll,
    isAdmin,
    authUser,
    currentUser,
    roles,
    permissions,
    userPermissions,
  };
}
