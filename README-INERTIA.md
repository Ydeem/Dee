# Laravel + Vue + Inertia Setup

This folder is a **full Laravel 12 + Inertia + Vue 3** app created with the official commands:

1. **Laravel**: `composer create-project laravel/laravel laravel-app`
2. **Inertia server**: `composer require inertiajs/inertia-laravel`
3. **Inertia middleware**: `php artisan inertia:middleware`
4. **Vue + Inertia client**: `npm install vue @inertiajs/vue3 @vitejs/plugin-vue`

## Run the app

```bash
cd laravel-app

# Terminal 1 – Laravel
php artisan serve

# Terminal 2 – Vite
npm run dev
```

Then open http://localhost:8000

## Database (SQLite) and login

The app uses **SQLite** and **database sessions** by default (see `.env.example`: `DB_CONNECTION=sqlite`, `SESSION_DRIVER=database`).

**First-time setup:**

```bash
cd laravel-app
touch database/database.sqlite
php artisan migrate:fresh --seed
```

**Seeded login credentials:**

| Email                   | Password  |
|-------------------------|-----------|
| `admin@ableadmin.test`  | `password` |
| `info@phoenixcoded.co`  | `admin123` |

- **Dashboard:** `/` and `/dashboard` (main). `/dashboard/default` and `/welcome` redirect to `/dashboard` when logged in.
- **Session:** After login you stay on the dashboard and can use the sidebar to open Starter, Typography, Colors, Shadows, etc. Logout via the profile menu in the header.

## Migrating Able Admin Vue into this app

Your existing Vue app lives in **`../src/`** (layouts, views, components, Vuetify, etc.). To finish the conversion:

1. **Copy or move** `../src/` contents into `resources/js/` (e.g. keep `layouts/`, `views/`, `components/`, `scss/`, `plugins/`, `stores/`).
2. **Add Vuetify and other deps** to this app:  
   `npm install vuetify vite-plugin-vuetify pinia …` (match `../package.json`).
3. **Update `resources/js/app.js`** to use the same entry as before (Vuetify, Pinia, global components, SCSS).
4. **Replace Vue Router** with Inertia:
   - Use `<Link>` from `@inertiajs/vue3` instead of `<router-link>`.
   - Use `router.visit()` or forms for navigation instead of `router.push()`.
5. **Create Inertia page components** in `resources/js/Pages/` that use your existing layouts and views (e.g. `Dashboard/Default.vue` wrapping `DashboardLayout` + `DefaultDashboard`).
6. **Add Laravel routes** in `routes/web.php` that return `Inertia::render('PageName')` and add auth middleware as needed.

The Laravel app in this folder is the single source of truth for the stack; the parent `src/` is the reference Vue UI to migrate into it.
