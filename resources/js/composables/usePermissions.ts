import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
  const page = usePage();

  const userPermissions = computed<string[]>(() => ((page.props as any)?.auth?.permissions ?? []) as string[]);
  const userRole = computed<string>(() => ((page.props as any)?.auth?.role ?? '') as string);

  function can(permission: string): boolean {
    return userPermissions.value.includes(permission);
  }

  function hasRole(role: string): boolean {
    return userRole.value === role;
  }

  function isAdmin(): boolean {
    return hasRole('HR Admin') || hasRole('super-admin');
  }

  return { can, hasRole, isAdmin, userRole, userPermissions };
}
