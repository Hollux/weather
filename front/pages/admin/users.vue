<template>
  <b-container class="component">
    <h2>Gestion des comptes</h2>

    <b-table striped hover responsive="sm" :items="users" :fields="fields">
      <template #cell(roles)="data">
        <b-form-checkbox
          :checked="data.item.roles.includes('ROLE_ADMIN')"
          switch
          :disabled="data.item.id === currentUserId"
          @change="(checked) => toggleAdmin(data.item, checked)"
        >
          Admin
        </b-form-checkbox>
        <b-form-checkbox
          :checked="data.item.roles.includes('ROLE_CRANGE')"
          switch
          @change="(checked) => toggleCRange(data.item, checked)"
        >
          CRange
        </b-form-checkbox>
      </template>
      <template #cell(hasGoogle)="data">
        <i v-if="data.item.hasGoogle" class="fab fa-google"></i>
      </template>
      <template #cell(actions)="data">
        <b-button
          size="sm"
          variant="outline-danger"
          :disabled="data.item.id === currentUserId"
          @click="deleteUser(data.item)"
        >
          Supprimer
        </b-button>
      </template>
    </b-table>
  </b-container>
</template>

<script>
export default {
  layout: "default",
  middleware: "admin",
  data() {
    return {
      users: [],
      fields: [
        { key: "id", sortable: true },
        { key: "username", sortable: true },
        { key: "email", sortable: true },
        { key: "roles", label: "Rôles" },
        { key: "hasGoogle", label: "Google" },
        { key: "createdAt", label: "Créé le", sortable: true },
        { key: "actions", label: "" },
      ],
    };
  },
  computed: {
    currentUserId() {
      return this.$store.state.auth.user?.id;
    },
  },
  async mounted() {
    await this.loadUsers();
  },
  methods: {
    async loadUsers() {
      try {
        this.users = await this.$axios.$get("/api/admin/users");
      } catch (err) {
        this.$toast.error(err.response?.data?.error || "Impossible de charger les comptes");
      }
    },
    async toggleAdmin(user, checked) {
      const roles = checked
        ? ["ROLE_USER", "ROLE_ADMIN", ...(user.roles.includes("ROLE_CRANGE") ? ["ROLE_CRANGE"] : [])]
        : ["ROLE_USER", ...(user.roles.includes("ROLE_CRANGE") ? ["ROLE_CRANGE"] : [])];
      await this.updateRoles(user, roles);
    },
    async toggleCRange(user, checked) {
      const roles = checked
        ? [...user.roles.filter((r) => r !== "ROLE_CRANGE"), "ROLE_CRANGE"]
        : user.roles.filter((r) => r !== "ROLE_CRANGE");
      await this.updateRoles(user, roles);
    },
    async updateRoles(user, roles) {
      try {
        const updated = await this.$axios.$patch(`/api/admin/users/${user.id}/roles`, { roles });
        const index = this.users.findIndex((u) => u.id === user.id);
        this.$set(this.users, index, updated);
      } catch (err) {
        this.$toast.error(err.response?.data?.error || "Impossible de modifier les rôles");
      }
    },
    async deleteUser(user) {
      if (!confirm(`Supprimer le compte "${user.username}" ?`)) {
        return;
      }
      try {
        await this.$axios.$delete(`/api/admin/users/${user.id}`);
        this.users = this.users.filter((u) => u.id !== user.id);
        this.$toast.success("Compte supprimé");
      } catch (err) {
        this.$toast.error(err.response?.data?.error || "Impossible de supprimer le compte");
      }
    },
  },
};
</script>
