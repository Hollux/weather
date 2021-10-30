<template>
  <b-container class="component">
    <b-form inline>
      <label for="example-datepicker">Choisissez une plage : </label>
      <b-form-datepicker
        id="min"
        v-model="min"
        class="mb-2"
      ></b-form-datepicker>
      <b-form-datepicker
        id="max"
        v-model="max"
        class="mb-2"
      ></b-form-datepicker>
      <b-button variant="primary" v-on:click="getMinutly()">Valider</b-button>
    </b-form>

    <b-table
      v-if="dataMinutly"
      id="table-transition"
      striped
      hover
      responsive="sm"
      :sort-by.sync="sortBy"
      :filter="filter"
      :filterIncludedFields="filterOn"
      @filtered="onFiltered"
      @row-clicked="onRowClick"
      :items="dataMinutly"
      :fields="fields"
      :small="small"
    >
      <template #cell(dt)="data">
        {{ $dayjs(data.item.dt * 1000).format("HH") }}h{{
          $dayjs(data.item.dt * 1000).format("mm")
        }}
      </template>
    </b-table>

    <div v-if="dataMinutly" v-for="(data, key) in dataMinutly" v-bind:key>
      <p>{{ data }}</p>
    </div>
  </b-container>
</template>


<script>
export default {
  layout: "default",
  data() {
    return {
      dataMinutly: [],
      min: "",
      max: "",
      transProps: {
        // Transition name
        name: "flip-list",
      },
      filterOn: [
        "dt",
        "temp",
        "pressure",
        "humidity",
        "uvi",
        "wind_speed",
        "wind_deg",
      ],
      fields: [
        { key: "dt", sortable: false },
        { key: "temp", sortable: true },
        { key: "pressure", sortable: true },
        { key: "humidity", sortable: true },
        { key: "uvi", sortable: true },
        { key: "wind_speed", sortable: true },
        { key: "wind_deg", sortable: true },
      ],
    };
  },
  methods: {
    getMinutly() {
      let data = [this.min, this.max];

      let returnAxios = this.$axiosPostAndInfos("getminutly", data);

      returnAxios.then((value) => {
        if (value[0] == true) {
          console.log(this.min, this.max, value, returnAxios);
          this.dataMinutly = value[1];
        }
      });
    },
  },
};
</script>

<style scoped>
table#table-transition .flip-list-move {
  transition: transform 1s;
}
</style>
