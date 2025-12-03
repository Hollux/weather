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
      <b-button variant="primary" @click="getMinutly()">Valider</b-button>
    </b-form>

    <!-- debug -->
    <!-- {{ charDataF }} -->

    <template v-if="charDataF.temp">
      <GChart
        type="AreaChart"
        :data="charDataF.temp.data"
        :options="charDataF.temp.options"
      />
    </template>

    <template v-if="charDataF.pressure">
      <GChart
        type="AreaChart"
        :data="charDataF.pressure.data"
        :options="charDataF.pressure.options"
      />
    </template>

    <template v-if="charDataF.humidity">
      <GChart
        type="AreaChart"
        :data="charDataF.humidity.data"
        :options="charDataF.humidity.options"
      />
    </template>

    <template v-if="charDataF.uvi">
      <GChart
        type="AreaChart"
        :data="charDataF.uvi.data"
        :options="charDataF.uvi.options"
      />
    </template>

    <template v-if="charDataF.wind_speed">
      <GChart
        type="AreaChart"
        :data="charDataF.wind_speed.data"
        :options="charDataF.wind_speed.options"
      />
    </template>
  </b-container>
</template>

<script>
export default {
  layout: "default",
  data() {
    return {
      charDataF: [],
      min: "",
      max: "",
      globalOptions: {
        legend: { position: "top" },
        hAxis: { format: "dd MMM HH:mm" },
        vAxis: { viewWindowMode: "pretty" },
        explorer: {
          actions: ["dragToZoom", "rightClickToReset"],
          axis: "horizontal",
        },
      },
      // couleurs demandées : mini bleu, centrale verte, maxi rouge
      colorMap: {
        main: "#2e7d32", // green
        min: "#1565c0", // blue
        max: "#c62828", // red
      },
    };
  },
  methods: {
    getMinutly() {
      let data = [this.min, this.max];
      let returnAxios = this.$axiosPostAndInfos("getminutly", data);

      returnAxios.then((value) => {
        if (value[0] === true) {
          this.charDataF = dataFormat(
            value[1],
            this.globalOptions,
            this.colorMap
          );
        } else {
          this.charDataF = [];
        }
      });
    },
  },
};

/**
 * Formatte les données pour Google Charts.
 * Construit dynamiquement les colonnes selon la présence des min/max.
 *
 * Retour :
 * {
 *   temp: { data: [...], options: {...} } | undefined,
 *   pressure: { ... },
 *   humidity: { ... },
 *   uvi: { ... },
 *   wind_speed: { ... }
 * }
 */
function dataFormat(data, globalOptions, colorMap) {
  if (!data || !Array.isArray(data) || data.length === 0) return {};

  // détecter presence des min/max
  let has = {
    temp_min: false,
    temp_max: false,
    pressure_min: false,
    pressure_max: false,
    humidity_min: false,
    humidity_max: false,
    uvi_max: false,
    wind_speed_max: false,
  };

  data.forEach((r) => {
    if (r.temp_min !== undefined && r.temp_min !== null) has.temp_min = true;
    if (r.temp_max !== undefined && r.temp_max !== null) has.temp_max = true;
    if (r.pressure_min !== undefined && r.pressure_min !== null)
      has.pressure_min = true;
    if (r.pressure_max !== undefined && r.pressure_max !== null)
      has.pressure_max = true;
    if (r.humidity_min !== undefined && r.humidity_min !== null)
      has.humidity_min = true;
    if (r.humidity_max !== undefined && r.humidity_max !== null)
      has.humidity_max = true;
    if (r.uvi_max !== undefined && r.uvi_max !== null) has.uvi_max = true;
    if (r.wind_speed_max !== undefined && r.wind_speed_max !== null)
      has.wind_speed_max = true;
  });

  // helper pour construire chart object
  function buildChart(mainKey, minKey, maxKey, title) {
    // déterminer colonnes (ordre : main, min, max)
    const columns = ["dt", mainKey];
    if (minKey && has[minKey]) columns.push(minKey);
    if (maxKey && has[maxKey]) columns.push(maxKey);

    // construire colors array correspondant à l'ordre des séries (excluant dt)
    const colors = [];
    // main
    colors.push(colorMap.main);
    // min
    if (minKey && has[minKey]) colors.push(colorMap.min);
    // max
    if (maxKey && has[maxKey]) colors.push(colorMap.max);

    // header row pour Google Charts : remplacer keys par labels
    const header = columns.map((c) => {
      if (c === "dt") return "dt";
      // labels lisibles
      switch (c) {
        case mainKey:
          return labelFromKey(mainKey);
        case minKey:
          return labelFromKey(minKey);
        case maxKey:
          return labelFromKey(maxKey);
        default:
          return c;
      }
    });

    const rows = [];
    data.forEach((r) => {
      const dt = new Date(r.dt * 1000);
      const row = [dt];
      // main value
      row.push(parseNumberSafe(r[mainKey]));
      // min if expected in columns
      if (minKey && has[minKey]) {
        row.push(parseNumberSafe(r[minKey]));
      }
      // max if expected in columns
      if (maxKey && has[maxKey]) {
        row.push(parseNumberSafe(r[maxKey]));
      }
      rows.push(row);
    });

    // assemble data table: header + rows
    const table = [header, ...rows];

    const options = Object.assign({}, globalOptions, {
      title,
      colors,
      // rendre les séries visibles/invisibles selon besoin géré par absence de colonne
      series: {}, // pas nécessaire ici mais left for future customizations
      legend: { position: "top" },
      hAxis: { format: "dd MMM HH:mm" },
      curveType: "function",
      pointSize: 2,
    });

    return { data: table, options };
  }

  // helper label
  function labelFromKey(key) {
    if (!key) return "";
    if (key.includes("temp")) return "Temp (°C)";
    if (key.includes("pressure")) return "Pressure (hPa)";
    if (key.includes("humidity")) return "Humidity (%)";
    if (key.includes("uvi")) return "UVI";
    if (key.includes("wind_speed")) return "Wind speed (m/s)";
    return key;
  }

  // safe parse float, return null when not numeric
  function parseNumberSafe(v) {
    if (v === undefined || v === null) return null;
    if (typeof v === "number") return Number(v);
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : null;
  }

  // construire chaque chart
  const result = {};

  result.temp = buildChart("temp", "temp_min", "temp_max", "Température");
  result.pressure = buildChart(
    "pressure",
    "pressure_min",
    "pressure_max",
    "Pression"
  );
  result.humidity = buildChart(
    "humidity",
    "humidity_min",
    "humidity_max",
    "Humidité"
  );
  // UVI n'a pas de min, seulement max parfois
  result.uvi = buildChart("uvi", null, "uvi_max", "Indice UV");
  // Vent : main + max parfois
  result.wind_speed = buildChart(
    "wind_speed",
    null,
    "wind_speed_max",
    "Vitesse du vent"
  );

  return result;
}
</script>

<style scoped>
table#table-transition .flip-list-move {
  transition: transform 1s;
}
</style>
