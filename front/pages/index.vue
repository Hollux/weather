<template>
  <b-container class="component py-4">

    <div class="text-center mb-4">
      <h1 class="display-4 font-weight-bold">Météo – Horbourg-Wihr</h1>
      <p class="text-muted">Statistiques minimales & maximales</p>
    </div>

    <b-row>
      <b-col
        v-for="(datav2, key) in dataHWv2.success"
        :key="key"
        cols="12"
        md="6"
        lg="4"
        class="mb-4"
      >
        <b-card class="shadow-sm border-0 weather-card">

          <!-- Titre FR + icône -->
          <div class="text-center mb-3">
            <!-- <i :class="getIcon(key)" class="weather-icon mb-2"></i> -->
            <h4 class="text-primary">{{ translate(key) }}</h4>
          </div>

          <!-- Mini & maxi -->
          <div class="d-flex justify-content-between align-items-center mb-3">

            <div class="text-center">
              <span class="label-mini">MIN</span>
              <div class="temp-mini">{{ datav2[0] }} {{ unit(key) }}</div>
            </div>

            <div class="text-center">
              <span class="label-maxi">MAX</span>
              <div class="temp-maxi">{{ datav2[2] }} {{ unit(key) }}</div>
            </div>

          </div>

          <!-- Dates -->
          <p class="text-muted small text-center">
            Mini le <strong>{{ formatDate(datav2[1]) }}</strong><br />
            Maxi le <strong>{{ formatDate(datav2[3]) }}</strong>
          </p>

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
      dataHW: [],
      dataHWv2: [],
    };
  },
  async fetch() {
    const baseUrl = process.server ? "http://weather.hollux.fr" : "";

    this.dataHW = await fetch(
      process.server
        ? `${baseUrl}/api_weather_detail/horbourg-wihr`
        : `/api/api_weather_detail/horbourg-wihr`
    ).then((res) => res.json());

    this.dataHWv2 = await fetch(
      process.server
        ? `${baseUrl}/savedaily/toto`
        : `/api/savedaily/toto`
    ).then((res) => res.json());
  },

  methods: {

  formatDate(timestamp) {
    return this.$dayjs(timestamp * 1000).format("DD/MM/YYYY HH:mm");
  },

  translate(key) {
    const dictionary = {
      temp: "Température",
      humidity: "Humidité",
      pressure: "Pression atmosphérique",
      wind_speed: "Vitesse du vent",
      wind_gust: "Rafales",
      wind_deg: "Direction du vent",
      clouds: "Nébulosité",
    };
    return dictionary[key] || key;
  },

  unit(key) {
    const units = {
      temp: "°C",
      humidity: "%",
      pressure: "hPa",
      wind_speed: "m/s",
      wind_gust: "m/s",
      wind_deg: "°",
      clouds: "%",
    };
    return units[key] || "";
  },

  getIcon(key) {
    const icons = {
      temp: "fas fa-thermometer-half",
      humidity: "fas fa-tint",
      pressure: "fas fa-tachometer-alt",
      wind_speed: "fas fa-wind",
      wind_gust: "fas fa-bolt",
      wind_deg: "fas fa-compass",
      clouds: "fas fa-cloud",
    };
    return icons[key] || "fas fa-circle";
  },

}

};
</script>

<style scoped>
.weather-card {
  border-radius: 12px;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.weather-card:hover {
  transform: translateY(-4px);
  box-shadow: 0px 6px 16px rgba(0, 0, 0, 0.12) !important;
}

.label-mini,
.label-maxi {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: bold;
  color: white;
}

.label-mini {
  background-color: #007bff;
}

.label-maxi {
  background-color: #dc3545;
}

.temp-mini,
.temp-maxi {
  font-size: 1.75rem;
  font-weight: bold;
  text-align: center;
}

.temp-mini {
  color: #007bff;
}

.temp-maxi {
  color: #dc3545;
}

.margin {
  margin-bottom: 60px;
}
</style>
