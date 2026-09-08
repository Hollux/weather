// Protège une page admin : redirige les non-connectés vers /login, et les
// connectés sans ROLE_ADMIN vers l'accueil.
export default function ({ store, redirect, route }) {
  if (!process.client) {
    return
  }

  if (!store.state.auth.token) {
    redirect(`/login?redirect=${encodeURIComponent(route.fullPath)}`)
    return
  }

  if (!store.getters['auth/isAdmin']) {
    redirect('/')
  }
}
