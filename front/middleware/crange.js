// Protège une page CRange : redirige les non-connectés vers /login, et les
// connectés sans ROLE_CRANGE (ni ROLE_ADMIN) vers l'accueil.
export default function ({ store, redirect, route }) {
  if (!process.client) {
    return
  }

  if (!store.state.auth.token) {
    redirect(`/login?redirect=${encodeURIComponent(route.fullPath)}`)
    return
  }

  if (!store.getters['auth/isCRange']) {
    redirect('/')
  }
}
