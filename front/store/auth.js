export const state = () => ({
  token: null,
  user: null,
  userLoaded: false
})

export const getters = {
  isAuthenticated: (state) => Boolean(state.token),
  isAdmin: (state) => Boolean(state.user && state.user.roles && state.user.roles.includes('ROLE_ADMIN')),
  isCRange: (state) => Boolean(state.user && state.user.roles && (state.user.roles.includes('ROLE_CRANGE') || state.user.roles.includes('ROLE_ADMIN')))
}

export const mutations = {
  SET_TOKEN(state, token) {
    state.token = token
  },
  SET_USER(state, user) {
    state.user = user
  },
  SET_USER_LOADED(state, loaded) {
    state.userLoaded = loaded
  }
}

export const actions = {
  /** Pose le token (login/register/Google) et recharge le profil associé. */
  setToken({ commit, dispatch }, token) {
    if (process.client) {
      localStorage.setItem('token', token)
    }
    commit('SET_TOKEN', token)
    this.$axios.setToken(token, 'Bearer')

    return dispatch('fetchUser')
  },

  /** (Re)charge le profil depuis /api/me ; vide le profil si pas de token ou si l'appel échoue. */
  async fetchUser({ commit, state }) {
    if (!state.token) {
      commit('SET_USER', null)
      commit('SET_USER_LOADED', true)
      return
    }

    try {
      const user = await this.$axios.$get('/api/me')
      commit('SET_USER', user)
    } catch (e) {
      commit('SET_USER', null)
    } finally {
      commit('SET_USER_LOADED', true)
    }
  },

  /** Connexion classique par username/email + mot de passe. */
  async login({ dispatch }, { username, password }) {
    const { token } = await this.$axios.$post('/api/login_check', { username, password })
    await dispatch('setToken', token)
  },

  /** Inscription par username + email + mot de passe. */
  async register({ dispatch }, { username, email, password }) {
    const { token } = await this.$axios.$post('/api/register', { username, email, password })
    await dispatch('setToken', token)
  },

  /** Déconnexion locale (JWT stateless, pas d'appel serveur nécessaire). */
  logout({ commit }) {
    if (process.client) {
      localStorage.removeItem('token')
    }
    this.$axios.setToken(false)
    commit('SET_TOKEN', null)
    commit('SET_USER', null)
  }
}
