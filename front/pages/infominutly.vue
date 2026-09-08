<template>
  <b-container class="component">
    <PageTitle title="InfosMinute" group="API" />

    <DateRangeForm
      :min.sync="min"
      :max.sync="max"
      @submit="getMinutly()"
    />

    <b-table
      v-if="dataMinutly"
      id="table-transition"
      striped
      hover
      responsive="sm"
      :filterIncludedFields="filterOn"
      :items="dataMinutly"
      :fields="fields"
    >
      <template #cell(dt)="data">
        {{ $dayjs(data.item.dt * 1000).format("D/MM à HH") }}h{{
          $dayjs(data.item.dt * 1000).format("mm")
        }}
      </template>
    </b-table>
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
        { key: "dt", sortable: true },
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
