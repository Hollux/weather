// Protège une page : redirige vers /login si pas de token. À poser sur la
// page avec `middleware: 'auth'`. Le plugin auth.client.js n'ayant pas encore
// forcément tourné côté serveur (target static), on ne vérifie que côté client.
export default function ({ store, redirect, route }) {
  if (process.client && !store.state.auth.token) {
    redirect(`/login?redirect=${encodeURIComponent(route.fullPath)}`)
  }
}
