import { createRouter, createWebHistory } from 'vue-router'
import hrRoutes from './hr'

const routes = [
  ...hrRoutes
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

function getSharedAuth() {
  const app = document.getElementById('app')
  const rawPage = app?.dataset?.page

  if (!rawPage) return { permissions: [], role: '' }

  try {
    const parsed = JSON.parse(rawPage)
    return parsed?.props?.auth ?? { permissions: [], role: '' }
  } catch {
    return { permissions: [], role: '' }
  }
}

router.beforeEach((to, _from, next) => {
  const requiredPermission = to.meta?.permission as string | undefined

  if (!requiredPermission) return next()

  const auth = getSharedAuth()
  const permissions = auth.permissions ?? []
  const role = auth.role ?? ''

  if (role === 'HR Admin' || role === 'super-admin' || permissions.includes(requiredPermission)) {
    return next()
  }

  return next('/hr/dashboard')
})

export default router
