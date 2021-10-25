<template>
  <b-container class="component">
    <h1 class="text-center">Meteo page 2</h1>
    <br />

    <div>
      <b-tabs content-class="mt-3">
        <b-tab v-for="(data, key) in dataHW" :title="key" v-bind:key>
          <!-- CURRENT -->
          <!-- <p>baro, thermo, hygro, vent</p>
          <p>pluie, point de rosée, uv</p>

          <b-list-group v-if="key == 'current'">
            <b-list-list-group-item
              >Pression : {{ data.pressure }} hPa</b-list-list-group-item
            >
            <b-list-list-group-item>{{ data.temp }}°</b-list-list-group-item>
            <b-list-list-group-item
              >Humidité : {{ data.humidity }}%</b-list-list-group-item
            >
            <b-list-list-group-item
              >Vitesse du vent :
              {{ data.wind_speed * 3.6 }} km/h</b-list-list-group-item
            >
          </b-list-group>
          <b-list-group v-if="key == 'current'">
            <b-list-list-group-item></b-list-list-group-item>
            <b-list-list-group-item></b-list-list-group-item>
            <b-list-list-group-item></b-list-list-group-item>
            <b-list-list-group-item></b-list-list-group-item>
          </b-list-group> -->

          <b-row v-if="key == 'current'">
            <b-col>
              <b-list-group>
                <b-list-group-item>{{ data.temp }}°</b-list-group-item>
                <b-list-group-item
                  >Humidité : {{ data.humidity }}%</b-list-group-item
                >
                <b-list-group-item
                  >nuages : {{ data.clouds }}%
                </b-list-group-item>
                <b-list-group-item
                  >Pression : {{ data.pressure }} hPa
                </b-list-group-item>
              </b-list-group>
            </b-col>
            <b-col>
              <b-list-group>
                <b-list-group-item
                  >Vitesse du vent :
                  {{ data.wind_speed * 3.6 }} km/h</b-list-group-item
                >
                <b-list-group-item
                  >Orientation du vent : {{ data.wind_deg }}°</b-list-group-item
                >
                <b-list-group-item
                  >Description : {{ data.weather[0].description }}
                </b-list-group-item>
                <b-list-group-item> uvi : {{ data.uvi }} </b-list-group-item>
              </b-list-group>
            </b-col>
            <b-col>
              <b-list-group>
                <b-list-group-item>
                  heure des infos : {{ $dayjs(data.dt * 1000).format("HH:mm") }}
                </b-list-group-item>
                <b-list-group-item>
                  levé du soleil :
                  {{ $dayjs(data.sunrise * 1000).format("HH:mm") }}
                </b-list-group-item>
                <b-list-group-item>
                  couché du soleil :
                  {{ $dayjs(data.sunset * 1000).format("HH:mm") }}
                </b-list-group-item>
              </b-list-group>
            </b-col>
            <b-col>{{ data }}</b-col>
          </b-row>

          <!-- Days  -->
          <b-row v-else>
            <b-col>
              <b-list-group>
                <b-list-group-item>{{ data.temp.day }}°</b-list-group-item>
                <b-list-group-item
                  >Humidité : {{ data.humidity }}%</b-list-group-item
                >
                <b-list-group-item
                  >Nuages : {{ data.clouds }}%
                </b-list-group-item>
                <b-list-group-item
                  >Pression : {{ data.pressure }} hPa</b-list-group-item
                >
              </b-list-group>
            </b-col>
            <b-col>
              <b-list-group>
                <b-list-group-item
                  >Vitesse du vent :
                  {{ data.wind_speed * 3.6 }} km/h</b-list-group-item
                >
                <b-list-group-item
                  >Rafale : {{ data.wind_gust * 3.6 }} km/h</b-list-group-item
                >
                <b-list-group-item
                  >Orientation du vent : {{ data.wind_deg }}°</b-list-group-item
                >
                <b-list-group-item
                  >t. mini : {{ data.temp.min }}°</b-list-group-item
                >
                <b-list-group-item
                  >t.maxi : {{ data.temp.max }}°</b-list-group-item
                >
                <b-list-group-item
                  >Description : {{ data.weather[0].description }}
                </b-list-group-item>
              </b-list-group>
            </b-col>
            <b-col>
              <b-list-group>
                <b-list-group-item>
                  date ? : {{ $dayjs(data.dt * 1000).format("DD:MM:YYYY") }}
                </b-list-group-item>
                <b-list-group-item>
                  levé du soleil :
                  {{ $dayjs(data.sunrise * 1000).format("HH:mm") }}
                </b-list-group-item>
                <b-list-group-item>
                  couché du soleil :
                  {{ $dayjs(data.sunset * 1000).format("HH:mm") }}
                </b-list-group-item>
              </b-list-group>
            </b-col>
            <b-col>{{ data }}</b-col>
          </b-row>
        </b-tab>
      </b-tabs>
    </div>

    <br />
    <br />
    <br />
    <div>{{ dataHW }}</div>
  </b-container>
</template>

<script>
export default {
  layout: "default",
  data() {
    return {
      dataHW: [],
    };
  },
  async fetch() {
    this.dataHW = dataFilter(
      await fetch(
        "https://weather.hollux.fr/api_weather_detail/horbourg-wihr"
      ).then((res) => res.json())
    );
  },
};

function dataFilter(data) {
  let resp = {};

  resp["current"] = data.success.current;
  data.success.daily.forEach(function (value, i) {
    resp["jour" + i] = value;
  });

  return resp;
}
</script>
