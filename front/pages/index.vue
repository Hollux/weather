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
              <span class="label-now"></span>
              <div class="temp-now">
                {{ dataHW.success[key] }} {{ unit(key) }}
              </div>
            </div>

            <div class="text-center">
              <span class="label-maxi">MAX</span>
              <div class="temp-maxi">{{ datav2[2] }} {{ unit(key) }}</div>
            </div>
          </div>

          <!-- Dates -->
          <p class="text-muted small text-center">
            Mini le <strong>{{ formatDate(datav2[1]) }}</strong
            ><br />
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
      refreshTimeout: null,
      refreshInterval: null,
    };
  },
  async mounted() {
    // Ancien Fetch :
    const baseUrl = process.env.urlBack;

    try {
      const res1 = await fetch(`${baseUrl}/api_weather_detail/horbourg-wihr`);

      if (!res1.ok) {
        console.error(
          "❌ Erreur HTTP (dataHW) :",
          res1.status,
          res1.statusText
        );
        throw new Error(`Erreur HTTP ${res1.status}`);
      }
      this.dataHW = await res1.json();
    } catch (err) {
      console.error("🔥 Erreur lors de la récupération de dataHW :", err);
    }

    try {
      const res2 = await fetch(`${baseUrl}/savedaily/toto`);

      if (!res2.ok) {
        console.error(
          "❌ Erreur HTTP (dataHWv2) :",
          res2.status,
          res2.statusText
        );
        throw new Error(`Erreur HTTP ${res2.status}`);
      }

      this.dataHWv2 = await res2.json();
    } catch (err) {
      console.error("🔥 Erreur lors de la récupération de dataHWv2 :", err);
    }
    // Ancien fetch
    // Fonction pour calculer le temps en ms avant la prochaine exécution à 6, 11, 16... minutes de l'heure
    const getDelayToNextUpdate = () => {
      const now = new Date();
      const minutes = now.getMinutes();
      const seconds = now.getSeconds();
      const milliseconds = now.getMilliseconds();

      // Minutes cibles dans chaque heure où rafraîchir : 6 + 5n (6,11,16,...)
      // On trouve la prochaine minute cible supérieure aux minutes actuelles
      const base = 6;
      const interval = 5;
      let nextMin = base;
      while (nextMin <= minutes) {
        nextMin += interval;
      }
      if (nextMin >= 60) nextMin -= 60; // Cas où on passe à l'heure suivante

      // Calcul du délai en ms jusqu'à cette minute cible dans l'heure
      let target = new Date(now);
      target.setMinutes(nextMin);
      target.setSeconds(0);
      target.setMilliseconds(0);

      // Si le target est dans l'heure suivante, on décale d'une heure
      if (nextMin <= minutes) {
        target.setHours(target.getHours() + 1);
      }

      return target - now;
    };

    // Premier timeout pour attendre la première exécution au bon moment
    /* this.refreshTimeout = setTimeout(() => {
      this.fetch();

      // Puis intervalle toutes les 5 minutes
      this.refreshInterval = setInterval(() => {
        this.fetch();
      }, 5 * 60 * 1000); // 5 minutes en ms
    }, getDelayToNextUpdate()); */
  },
  beforeDestroy() {
    // Nettoyer les timers pour éviter fuite mémoire
    if (this.refreshTimeout) clearTimeout(this.refreshTimeout);
    if (this.refreshInterval) clearInterval(this.refreshInterval);
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
        wind_speed: "km/h",
        wind_gust: "km/h",
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
  },
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
.label-maxi,
.label-now {
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

.label-now {
  background-color: #28a745;
}

.temp-mini,
.temp-maxi,
.temp-now {
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
.temp-now {
  color: #28a745;
}

.margin {
  margin-bottom: 60px;
}
</style>
