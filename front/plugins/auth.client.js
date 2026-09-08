// Récupère le JWT au démarrage : soit déposé dans l'URL par le retour Google
// (?token=...), soit déjà en localStorage. Nettoie l'URL une fois le token lu
// pour ne pas le laisser traîner dans l'historique / les logs.
export default async ({ store, $axios, app }) => {
  const params = new URLSearchParams(window.location.search)
  const urlToken = params.get('token')
  let token = urlToken

  if (urlToken) {
    localStorage.setItem('token', urlToken)

    params.delete('token')
    const query = params.toString()
    const newUrl = window.location.pathname + (query ? `?${query}` : '') + window.location.hash
    window.history.replaceState({}, '', newUrl)
  } else {
    token = localStorage.getItem('token')
  }

  if (token) {
    store.commit('auth/SET_TOKEN', token)
    $axios.setToken(token, 'Bearer')
  }

  // Un token périmé/invalide fait échouer toutes les requêtes protégées : sur un
  // 401, on le purge pour retomber en visiteur anonyme plutôt que rester bloqué.
  $axios.onError((error) => {
    if (error.response && error.response.status === 401 && store.state.auth.token) {
      store.dispatch('auth/logout')
    }

    return Promise.reject(error)
  })

  await store.dispatch('auth/fetchUser')
}
