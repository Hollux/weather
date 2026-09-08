<template>
  <b-container class="component">
    <h2>Luminosité Journalière</h2>

    <b-card class="mb-4" v-if="today">
      <div class="mb-2"><strong>{{ todayLabel }}</strong></div>
      <div class="d-flex align-items-center flex-wrap">
        <template v-if="today.filled && !today.editing">
          <span class="mr-3">{{ today.hours }} h {{ today.minutes }} minute</span>
        </template>
        <template v-else>
          <b-form-input
            type="number"
            min="0"
            max="24"
            v-model.number="today.hours"
            style="width: 80px"
            class="mr-2"
          />
          <span class="mr-3">h</span>
          <b-form-input
            type="number"
            min="0"
            max="60"
            v-model.number="today.minutes"
            style="width: 80px"
            class="mr-2"
          />
          <span class="mr-3">minute</span>
        </template>
        <b-button
          v-if="today.filled"
          :variant="today.editing ? 'secondary' : 'danger'"
          class="mr-2"
          @click="toggleEdit(today)"
        >
          {{ today.editing ? "Annuler" : "Modifier" }}
        </b-button>
        <b-button
          v-if="!today.filled || today.editing"
          variant="primary"
          @click="save(today)"
        >
          Enregistrer
        </b-button>
      </div>
    </b-card>

    <h3>10 derniers jours</h3>
    <b-table striped hover :items="days" :fields="fields">
      <template #cell(date)="data">
        {{ formatDay(data.item.day) }}
      </template>
      <template #cell(duree)="data">
        <div class="d-flex align-items-center">
          <template v-if="data.item.filled && !data.item.editing">
            <span>{{ data.item.hours }} h {{ data.item.minutes }} minute</span>
          </template>
          <template v-else>
            <b-form-input
              type="number"
              min="0"
              max="24"
              v-model.number="data.item.hours"
              style="width: 70px"
              class="mr-1"
            />
            <span class="mr-2">h</span>
            <b-form-input
              type="number"
              min="0"
              max="60"
              v-model.number="data.item.minutes"
              style="width: 70px"
              class="mr-1"
            />
            <span class="mr-2">minute</span>
          </template>
        </div>
      </template>
      <template #cell(actions)="data">
        <b-button
          v-if="data.item.filled"
          size="sm"
          :variant="data.item.editing ? 'secondary' : 'danger'"
          class="mr-2"
          @click="toggleEdit(data.item)"
        >
          {{ data.item.editing ? "Annuler" : "Modifier" }}
        </b-button>
        <b-button
          v-if="!data.item.filled || data.item.editing"
          size="sm"
          variant="primary"
          @click="save(data.item)"
        >
          Enregistrer
        </b-button>
      </template>
    </b-table>
  </b-container>
</template>

<script>
export default {
  layout: "default",
  middleware: "crange",
  data() {
    return {
      days: [],
      fields: [
        { key: "date", label: "Jour" },
        { key: "duree", label: "Durée d'ensoleillement" },
        { key: "actions", label: "" },
      ],
    };
  },
  computed: {
    today() {
      return this.days[0] || null;
    },
    todayLabel() {
      return this.$dayjs().format("dddd D MMMM YYYY");
    },
  },
  async mounted() {
    await this.load();
  },
  methods: {
    async load() {
      try {
        const days = await this.$axios.$get("/api/crange/luminosity/10");
        this.days = days.map((day) => ({ ...day, editing: false }));
      } catch (err) {
        this.$toast.error(
          err.response?.data?.error || "Impossible de charger la luminosité"
        );
      }
    },
    formatDay(day) {
      return this.$dayjs(day).format("dddd D MMMM YYYY");
    },
    toggleEdit(item) {
      if (item.editing) {
        item.hours = item._editBackup.hours;
        item.minutes = item._editBackup.minutes;
        item.editing = false;
        delete item._editBackup;
      } else {
        item._editBackup = { hours: item.hours, minutes: item.minutes };
        item.editing = true;
      }
    },
    async save(item) {
      const hours = Number(item.hours);
      const minutes = Number(item.minutes);
      if (
        !Number.isInteger(hours) ||
        hours < 0 ||
        hours > 24 ||
        !Number.isInteger(minutes) ||
        minutes < 0 ||
        minutes > 60
      ) {
        this.$toast.error("Durée invalide (heures 0-24, minutes 0-60)");
        return;
      }
      try {
        const updated = await this.$axios.$post("/api/crange/luminosity", {
          day: item.day,
          hours,
          minutes,
        });
        Object.assign(item, updated, { editing: false });
        delete item._editBackup;
        this.$toast.success("Enregistré");
      } catch (err) {
        this.$toast.error(
          err.response?.data?.error || "Impossible d'enregistrer"
        );
      }
    },
  },
};
</script>
