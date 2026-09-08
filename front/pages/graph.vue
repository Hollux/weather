<template>
  <div>
    <b-container class="component">
      <PageTitle title="Graph" group="API" />

      <DateRangeForm
        :min.sync="min"
        :max.sync="max"
        @submit="getMinutly()"
      />
    </b-container>

    <b-container fluid>
      <template v-if="charDataF.temp">
        <GChart
          type="LineChart"
          :data="charDataF.temp.data"
          :options="charDataF.temp.options"
          :style="{ height: '60vh', minHeight: '300px' }"
        />
      </template>

      <template v-if="charDataF.pressure">
        <GChart
          type="LineChart"
          :data="charDataF.pressure.data"
          :options="charDataF.pressure.options"
        />
      </template>

      <template v-if="charDataF.humidity">
        <GChart
          type="LineChart"
          :data="charDataF.humidity.data"
          :options="charDataF.humidity.options"
        />
      </template>

      <template v-if="charDataF.uvi">
        <GChart
          type="LineChart"
          :data="charDataF.uvi.data"
          :options="charDataF.uvi.options"
        />
      </template>

      <template v-if="charDataF.wind_speed">
        <GChart
          type="LineChart"
          :data="charDataF.wind_speed.data"
          :options="charDataF.wind_speed.options"
        />
      </template>
    </b-container>
  </div>
</template>

<script>
export default {
  layout: "default",
  data() {
    return {
      charDataF: [],
      min: "",
      max: new Date().toISOString().split("T")[0], // Formater la date au format 'YYYY-MM-DD',

      globalOptions: {
        legend: { position: "top" },
        hAxis: { format: "dd/MM/yyyy" },
        vAxis: { viewWindowMode: "pretty" },
        explorer: {
          actions: ["dragToZoom", "rightClickToReset"],
          axis: "horizontal",
        },
      },
      globalOptionsTemps: {
        legend: { position: "top" },
        hAxis: { format: "dd/MM/yyyy" },
        vAxis: {
          viewWindowMode: "pretty", // Pour une vue plus jolie et ajustée
          minValue: -20, // Plage de température minimale
          maxValue: 40, // Plage de température maximale
          format: "## °C", // Format d'affichage pour les températures, ici en ajoutant "°C"
          title: "Température (°C)", // Titre de l'axe Y
          gridlines: {
            count: 2, // Nombre de lignes de la grille, tu peux ajuster ça pour mieux visualiser
          },
        },
        explorer: {
          actions: ["dragToZoom", "rightClickToReset"],
          axis: "horizontal",
        },
      },
      // Échelle fixe et réaliste : la pression atmosphérique au niveau de la
      // mer reste dans cette plage — sans ça, un point aberrant fait passer
      // le vAxis "pretty" (auto) sur une échelle 0-3000 illisible.
      globalOptionsPressure: {
        legend: { position: "top" },
        hAxis: { format: "dd/MM/yyyy" },
        vAxis: {
          viewWindowMode: "explicit",
          viewWindow: { min: 950, max: 1080 },
          minValue: 950,
          maxValue: 1080,
          format: "# hPa",
          title: "Pression (hPa)",
          gridlines: { count: 4 },
        },
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
            this.colorMap,
            this.globalOptionsTemps,
            this.globalOptionsPressure
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
function dataFormat(data, globalOptions, colorMap, globalOptionsTemps, globalOptionsPressure) {
  if (!data || !Array.isArray(data) || data.length === 0) return {};

  // Mode journalier (WeatherDaily) vs minutely : seul le journalier porte des
  // agrégats temp_0 / mini / maxi. Au pas journalier on remplace les infobulles
  // par des textes sur mesure : jour seul pour les moyennes (pas plus précis
  // que la journée), jour + heure pour les mini/maxi (l'heure vient des champs
  // *_dt renvoyés par l'API, en heure locale du navigateur).
  const isDaily = data[0] && data[0].temp_0 !== undefined;

  function pad2(n) {
    return String(n).padStart(2, "0");
  }
  function frDate(dtSec) {
    const d = new Date(dtSec * 1000);
    return `${pad2(d.getDate())}/${pad2(d.getMonth() + 1)}/${d.getFullYear()}`;
  }
  function frDateHeure(dtSec) {
    const d = new Date(dtSec * 1000);
    return `${frDate(dtSec)} à ${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
  }

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

  // infobulle sur mesure au pas journalier ; null en minutely (infobulle par défaut)
  function dailyTooltip(r, key, kind) {
    if (!isDaily) return null;
    const label = labelFromKey(key);
    if (kind === "moy") {
      // moyenne / valeur journalière : pas d'heure, c'est la journée
      return `${frDate(r.dt)}\n${label} : ${parseNumberSafe(r[key])}`;
    }
    const whenSec = r[key + "_dt"];
    const when =
      whenSec !== undefined && whenSec !== null
        ? frDateHeure(whenSec)
        : frDate(r.dt);
    return `${when}\n${label} ${kind} : ${parseNumberSafe(r[key])}`;
  }

  // helper pour construire chart object
  function buildChart(mainKey, minKey, maxKey, title, mainColor, axisOptions) {
    const useMin = minKey && has[minKey];
    const useMax = maxKey && has[maxKey];

    // construire colors array correspondant à l'ordre des séries (excluant dt
    // et les colonnes de rôle "tooltip", qui ne consomment pas de couleur)
    const colors = [mainColor || colorMap.main];
    if (useMin) colors.push(colorMap.min);
    if (useMax) colors.push(colorMap.max);

    // header row pour Google Charts (+ colonne tooltip par série au pas journalier)
    const header = ["dt", labelFromKey(mainKey)];
    if (isDaily) header.push({ role: "tooltip", type: "string" });
    if (useMin) {
      header.push(labelFromKey(minKey));
      if (isDaily) header.push({ role: "tooltip", type: "string" });
    }
    if (useMax) {
      header.push(labelFromKey(maxKey));
      if (isDaily) header.push({ role: "tooltip", type: "string" });
    }

    const rows = [];
    data.forEach((r) => {
      const row = [new Date(r.dt * 1000), parseNumberSafe(r[mainKey])];
      if (isDaily) row.push(dailyTooltip(r, mainKey, "moy"));
      if (useMin) {
        row.push(parseNumberSafe(r[minKey]));
        if (isDaily) row.push(dailyTooltip(r, minKey, "mini"));
      }
      if (useMax) {
        row.push(parseNumberSafe(r[maxKey]));
        if (isDaily) row.push(dailyTooltip(r, maxKey, "maxi"));
      }
      rows.push(row);
    });

    // assemble data table: header + rows
    const table = [header, ...rows];

    // Options avec spéciales pour la température
    let pre_options = globalOptions;
    if (axisOptions) {
      pre_options = Object.assign({}, axisOptions);
    } else if (mainKey === "temp_old") {
      pre_options = Object.assign({}, globalOptionsTemps);
    } else {
      pre_options = Object.assign({}, globalOptions);
    }

    const options = Object.assign({}, pre_options, {
      title,
      colors,
      // rendre les séries visibles/invisibles selon besoin géré par absence de colonne
      series: {}, // pas nécessaire ici mais left for future customizations
      legend: { position: "top" },
      hAxis: { format: "dd/MM/yyyy" },
      curveType: "function",
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
    "Pression",
    undefined,
    globalOptionsPressure
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
