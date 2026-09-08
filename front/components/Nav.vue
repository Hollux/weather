<template>
  <div>
    <b-navbar toggleable="lg" type="dark" variant="info">
      <b-navbar-brand href="/">Meteo Horbourg-wihr</b-navbar-brand>
      <b-navbar-toggle target="nav-collapse"></b-navbar-toggle>

      <b-collapse id="nav-collapse" is-nav>
        <div class="nav-groups">
          <div class="nav-group nav-group-api" :class="{ 'nav-group-active': isActiveAny(['/infominutly', '/graph', '/compare']) }">
            <span class="nav-group-label">API</span>
            <nuxt-link class="nav-link" :class="{ active: isActive('/infominutly') }" to="/infominutly">InfosMinute</nuxt-link>
            <nuxt-link class="nav-link" :class="{ active: isActive('/graph') }" to="/graph">Graph</nuxt-link>
            <nuxt-link class="nav-link" :class="{ active: isActive('/compare') }" to="/compare">Comparaison</nuxt-link>
          </div>
          <div class="nav-group nav-group-crange" :class="{ 'nav-group-active': isActiveAny(['/graph-crange', '/compare-crange']) }">
            <span class="nav-group-label">CRange</span>
            <nuxt-link class="nav-link" :class="{ active: isActive('/graph-crange') }" to="/graph-crange">Graph</nuxt-link>
            <nuxt-link class="nav-link" :class="{ active: isActive('/compare-crange') }" to="/compare-crange">Comparaison</nuxt-link>
          </div>
        </div>

        <!-- Right aligned nav items -->
        <b-navbar-nav class="ml-auto">
          <template v-if="isAuthenticated">
            <b-nav-item
              v-if="isCRange"
              :disabled="cRangeSyncStep === 'syncing'"
              @click="openCRangeSyncConfirm"
            >Maj CRange</b-nav-item>
            <b-nav-item v-if="isAdmin" to="/admin/users" :active="isActive('/admin')">Administration</b-nav-item>
            <b-nav-item v-if="isCRange" to="/luminosite-journaliere" :active="isActive('/luminosite-journaliere')">Luminosité Journalière</b-nav-item>
            <b-nav-item-dropdown right :text="username">
              <b-dropdown-item to="/account">Mon compte</b-dropdown-item>
              <b-dropdown-item @click="logout">Déconnexion</b-dropdown-item>
            </b-nav-item-dropdown>
          </template>
          <b-nav-item v-else to="/login">Connexion</b-nav-item>
        </b-navbar-nav>
      </b-collapse>
    </b-navbar>

    <b-modal
      :visible="cRangeSyncStep !== 'idle'"
      :title="cRangeModalTitle"
      :hide-footer="cRangeSyncStep === 'syncing'"
      :ok-only="cRangeSyncStep !== 'confirm'"
      :ok-title="cRangeSyncStep === 'confirm' ? 'Lancer la mise à jour' : 'OK'"
      cancel-title="Annuler"
      :no-close-on-backdrop="cRangeSyncStep === 'syncing'"
      :no-close-on-esc="cRangeSyncStep === 'syncing'"
      centered
      @ok="onCRangeModalOk"
      @hidden="resetCRangeSync"
    >
      <div v-if="cRangeSyncStep === 'confirm'">
        <p class="mb-0">
          Lancer la mise à jour CRange ? Les nouveaux relevés seront téléchargés
          depuis Google Drive puis importés.
        </p>
      </div>
      <div v-else-if="cRangeSyncStep === 'syncing'" class="text-center py-3">
        <b-spinner class="mb-2" />
        <div>Mise à jour en cours…</div>
      </div>
      <div v-else-if="cRangeSyncStep === 'error'">
        <p class="text-danger mb-0">{{ cRangeSyncError }}</p>
      </div>
      <div v-else-if="cRangeSyncStep === 'result'">
        <p v-if="cRangeSyncResult.filesUpdated.length === 0" class="mb-0">
          Déjà à jour, aucune nouvelle donnée à importer.
        </p>
        <template v-else>
          <p>Import terminé :</p>
          <ul>
            <li v-for="f in cRangeSyncResult.filesUpdated" :key="f.filename">
              {{ f.filename }} : {{ f.rowsInserted }} nouvelle(s) mesure(s)
            </li>
          </ul>
          <p class="mb-0">
            Total : {{ cRangeSyncResult.totalRowsInserted }} mesure(s) ajoutée(s),
            {{ cRangeSyncResult.daysRecomputed }} jour(s) recalculé(s).
          </p>
        </template>
      </div>
    </b-modal>
  </div>
</template>

<script>
export default {
  data() {
    return {
      // Machine à états de la popin : idle (fermée) -> confirm -> syncing -> result|error.
      cRangeSyncStep: "idle",
      cRangeSyncResult: null,
      cRangeSyncError: null,
    };
  },
  computed: {
    isAuthenticated() {
      return this.$store.getters["auth/isAuthenticated"];
    },
    isAdmin() {
      return this.$store.getters["auth/isAdmin"];
    },
    isCRange() {
      return this.$store.getters["auth/isCRange"];
    },
    username() {
      return this.$store.state.auth.user?.username || "";
    },
    cRangeModalTitle() {
      return {
        confirm: "Mise à jour CRange",
        syncing: "Mise à jour CRange",
        result: "Mise à jour terminée",
        error: "Échec de la mise à jour",
      }[this.cRangeSyncStep];
    },
  },
  methods: {
    isActive(path) {
      return this.$route.path === path || this.$route.path.startsWith(path + "/");
    },
    isActiveAny(paths) {
      return paths.some((path) => this.isActive(path));
    },
    logout() {
      this.$store.dispatch("auth/logout");
      this.$router.push("/");
    },
    openCRangeSyncConfirm() {
      this.cRangeSyncStep = "confirm";
    },
    onCRangeModalOk(bvModalEvt) {
      if (this.cRangeSyncStep !== "confirm") {
        return; // result/error : on laisse le bouton OK fermer normalement.
      }
      bvModalEvt.preventDefault(); // on garde la popin ouverte pour passer à "syncing".
      this.runCRangeSync();
    },
    async runCRangeSync() {
      this.cRangeSyncStep = "syncing";
      try {
        this.cRangeSyncResult = await this.$axios.$post("/api/crange/sync");
        this.cRangeSyncStep = "result";
      } catch (err) {
        this.cRangeSyncError =
          err.response?.data?.error || "Échec de la mise à jour CRange";
        this.cRangeSyncStep = "error";
      }
    },
    resetCRangeSync() {
      this.cRangeSyncStep = "idle";
      this.cRangeSyncResult = null;
      this.cRangeSyncError = null;
    },
  },
};
</script>

<style scoped>
.navbar-nav .nav-link.active {
  color: #fff !important;
  font-weight: 600;
  position: relative;
}

.navbar-nav .nav-link.active::after {
  content: "";
  position: absolute;
  left: 1rem;
  right: 1rem;
  bottom: 0.15rem;
  height: 2px;
  background-color: #fff;
  border-radius: 1px;
}

/* Groupes de navigation "API" / "CRange" : deux sections visuellement
   distinctes mais sans dropdown, pour éviter un clic supplémentaire. */
.nav-groups {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  margin: 0.25rem 0;
}

.nav-group {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  border-radius: 999px;
  padding: 0.2rem 0.5rem;
  transition: background-color 0.15s ease, box-shadow 0.15s ease;
}

.nav-group-label {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.75);
  padding: 0 0.5rem 0 0.25rem;
}

.nav-group .nav-link {
  padding: 0.35rem 0.7rem;
  border-radius: 999px;
  color: rgba(255, 255, 255, 0.9);
}

.nav-group .nav-link.active {
  background-color: rgba(255, 255, 255, 0.28);
}

.nav-group .nav-link.active::after {
  content: none;
}

/* Les deux sections "API" et "CRange" partagent la même bulle neutre : le
   libellé suffit à les distinguer, pas besoin d'une couleur par section. */
.nav-group-api,
.nav-group-crange {
  background: rgba(255, 255, 255, 0.12);
}

.nav-group-api.nav-group-active,
.nav-group-crange.nav-group-active {
  background: rgba(255, 255, 255, 0.2);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.35);
}
</style>