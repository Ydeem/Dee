import { createRouter, createWebHistory } from 'vue-router'
import hrRoutes from './hr'

const routes = [
  ...hrRoutes
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

export default router
