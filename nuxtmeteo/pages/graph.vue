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

    <!-- TODO: pourquoi un v-for ne déroule pas l'obj ? -->

    <template v-if="charDataF.temp">
      <GChart type="AreaChart" :data="charDataF.temp" :options="chartOptions" />
    </template>
    <template v-if="charDataF.pressure">
      <GChart
        type="AreaChart"
        :data="charDataF.pressure"
        :options="chartOptions"
      />
    </template>
    <template v-if="charDataF.humidity">
      <GChart
        type="AreaChart"
        :data="charDataF.humidity"
        :options="chartOptions"
      />
    </template>
    <template v-if="charDataF.uvi">
      <GChart type="AreaChart" :data="charDataF.uvi" :options="chartOptions" />
    </template>
    <template v-if="charDataF.wind_speed">
      <GChart
        type="AreaChart"
        :data="charDataF.wind_speed"
        :options="chartOptions"
      />
    </template>
  </b-container>
</template>


<script>
export default {
  layout: "default",
  data() {
    return {
      chartData: [],
      charDataF: [],
      chartData2: [
        ["Year", "Sales", "Expenses", "Profit"],
        ["2014", 1000, 400, 200],
        ["2015", 1170, 460, 250],
        ["2016", 660, 1120, 300],
        ["2017", 1030, 540, 350],
      ],
      min: "",
      max: "",
      chartOptions: {
        chart: {
          title: "Company Performance",
          subtitle: "Sales, Expenses, and Profit: 2014-2017",
        },
      },
    };
  },
  methods: {
    getMinutly() {
      let data = [this.min, this.max];

      let returnAxios = this.$axiosPostAndInfos("getminutly", data);

      returnAxios.then((value) => {
        if (value[0] == true) {
          this.chartData = value[1];
          console.log(dataFormat(value[1]));
          this.charDataF = dataFormat(value[1]);
        }
      });
    },
  },
};

function dataFormat(data) {
  if (data) {
    let dataFormated = [];
    // dataFormated.push(["temp", "pressure", "humidity", "uvi", "wind_speed"]);
    dataFormated.temp = [["dt", "temp"]];
    dataFormated.pressure = [["dt", "pressure"]];
    dataFormated.humidity = [["dt", "humidity"]];
    dataFormated.uvi = [["dt", "uvi"]];
    dataFormated.wind_speed = [["dt", "wind_speed"]];

    data.forEach((dat) => {
      dataFormated.temp.push([parseFloat(dat.dt), parseFloat(dat.temp)]);
      dataFormated.pressure.push([
        parseFloat(dat.dt),
        parseFloat(dat.pressure),
      ]);
      dataFormated.humidity.push([
        parseFloat(dat.dt),
        parseFloat(dat.humidity),
      ]);
      dataFormated.uvi.push([parseFloat(dat.dt), parseFloat(dat.uvi)]);
      dataFormated.wind_speed.push([
        parseFloat(dat.dt),
        parseFloat(dat.wind_spee),
      ]);
    });

    return dataFormated;
  }

  return null;
}
</script>

<style scoped>
table#table-transition .flip-list-move {
  transition: transform 1s;
}
</style>
