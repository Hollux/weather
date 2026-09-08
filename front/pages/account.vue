<template>
  <b-container class="component">
    <h2>Mon compte</h2>

    <b-row class="justify-content-center">
      <b-col md="6" lg="5">
        <b-card title="Profil" class="mb-4">
          <b-form @submit.prevent="updateProfile">
            <b-form-group label="Nom d'utilisateur">
              <b-form-input v-model="profileForm.username" required />
            </b-form-group>
            <b-form-group label="Email">
              <b-form-input v-model="profileForm.email" type="email" required />
            </b-form-group>
            <b-button type="submit" variant="primary" :disabled="loadingProfile">
              Enregistrer
            </b-button>
          </b-form>
        </b-card>

        <b-card title="Mot de passe">
          <b-form @submit.prevent="updatePassword">
            <b-form-group v-if="user && user.hasPassword" label="Mot de passe actuel">
              <b-form-input v-model="passwordForm.currentPassword" type="password" required />
            </b-form-group>
            <b-form-group label="Nouveau mot de passe (8 caractères minimum)">
              <b-form-input v-model="passwordForm.newPassword" type="password" required minlength="8" />
            </b-form-group>
            <b-button type="submit" variant="primary" :disabled="loadingPassword">
              Changer le mot de passe
            </b-button>
          </b-form>
        </b-card>
      </b-col>
    </b-row>
  </b-container>
</template>

<script>
export default {
  layout: "default",
  middleware: "auth",
  data() {
    return {
      profileForm: { username: "", email: "" },
      passwordForm: { currentPassword: "", newPassword: "" },
      loadingProfile: false,
      loadingPassword: false,
    };
  },
  computed: {
    user() {
      return this.$store.state.auth.user;
    },
  },
  created() {
    if (this.user) {
      this.profileForm.username = this.user.username;
      this.profileForm.email = this.user.email;
    }
  },
  methods: {
    async updateProfile() {
      this.loadingProfile = true;
      try {
        const user = await this.$axios.$patch("/api/me", { ...this.profileForm });
        this.$store.commit("auth/SET_USER", user);
        this.$toast.success("Profil mis à jour");
      } catch (err) {
        this.$toast.error(err.response?.data?.error || "Erreur lors de la mise à jour");
      } finally {
        this.loadingProfile = false;
      }
    },
    async updatePassword() {
      this.loadingPassword = true;
      try {
        const user = await this.$axios.$post("/api/me/password", { ...this.passwordForm });
        this.$store.commit("auth/SET_USER", user);
        this.passwordForm = { currentPassword: "", newPassword: "" };
        this.$toast.success("Mot de passe changé");
      } catch (err) {
        this.$toast.error(err.response?.data?.error || "Erreur lors du changement de mot de passe");
      } finally {
        this.loadingPassword = false;
      }
    },
  },
};
</script>
