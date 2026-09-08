<template>
  <b-container class="component">
    <b-row class="justify-content-center">
      <b-col md="6" lg="5">
        <b-card>
          <b-tabs v-model="tabIndex" justified content-class="mt-3">
            <b-tab title="Connexion">
              <b-form @submit.prevent="submitLogin">
                <b-form-group label="Nom d'utilisateur ou email">
                  <b-form-input v-model="loginForm.username" required autofocus />
                </b-form-group>
                <b-form-group label="Mot de passe">
                  <b-form-input v-model="loginForm.password" type="password" required />
                </b-form-group>
                <b-button type="submit" variant="primary" block :disabled="loading">
                  Se connecter
                </b-button>
              </b-form>
            </b-tab>

            <b-tab title="Inscription">
              <b-form @submit.prevent="submitRegister">
                <b-form-group label="Nom d'utilisateur">
                  <b-form-input v-model="registerForm.username" required />
                </b-form-group>
                <b-form-group label="Email">
                  <b-form-input v-model="registerForm.email" type="email" required />
                </b-form-group>
                <b-form-group label="Mot de passe (8 caractères minimum)">
                  <b-form-input v-model="registerForm.password" type="password" required minlength="8" />
                </b-form-group>
                <b-button type="submit" variant="primary" block :disabled="loading">
                  Créer mon compte
                </b-button>
              </b-form>
            </b-tab>
          </b-tabs>

          <hr />

          <b-button variant="outline-secondary" block :href="googleUrl">
            <i class="fab fa-google"></i> Continuer avec Google
          </b-button>
        </b-card>
      </b-col>
    </b-row>
  </b-container>
</template>

<script>
export default {
  layout: "default",
  data() {
    return {
      tabIndex: 0,
      loading: false,
      loginForm: { username: "", password: "" },
      registerForm: { username: "", email: "", password: "" },
    };
  },
  computed: {
    redirectTarget() {
      return typeof this.$route.query.redirect === "string" ? this.$route.query.redirect : "/";
    },
    googleUrl() {
      const currentOrigin = process.client ? window.location.origin : "";
      const redirect = currentOrigin + this.redirectTarget;

      return `${process.env.urlBack}/connect/google?redirect=${encodeURIComponent(redirect)}`;
    },
  },
  methods: {
    async submitLogin() {
      this.loading = true;
      try {
        await this.$store.dispatch("auth/login", { ...this.loginForm });
        this.$toast.success("Connecté !");
        this.$router.push(this.redirectTarget);
      } catch (err) {
        this.$toast.error(err.response?.data?.error || "Identifiants incorrects");
      } finally {
        this.loading = false;
      }
    },
    async submitRegister() {
      this.loading = true;
      try {
        await this.$store.dispatch("auth/register", { ...this.registerForm });
        this.$toast.success("Compte créé !");
        this.$router.push(this.redirectTarget);
      } catch (err) {
        this.$toast.error(err.response?.data?.error || "Impossible de créer le compte");
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
