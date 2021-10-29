<template>
  <b-container class="component">
    <h1 class="text-center">Meteo</h1>
    <br />

    <h2>Infos V2</h2>

    <div v-for="(datav2, key) in dataHWv2['success']" :title="key" v-bind="key">
      <br />
      <p>
        {{ key }} : <strong>{{ dataHW.current[key] }}</strong> , mini :
        {{ datav2[0] }} le
        {{ $dayjs(datav2[1] * 1000).format("DD:MM:YYYY à HH") }}h{{
          $dayjs(datav2[1] * 1000).format("mm")
        }}, maxi : {{ datav2[2] }} le
        {{ $dayjs(datav2[3] * 1000).format("DD:MM:YYYY à HH") }}h{{
          $dayjs(datav2[3] * 1000).format("mm")
        }}
      </p>
    </div>
    <div class="margin"></div>

    <h2>Infos V1</h2>
    <div>
      <b-tabs content-class="mt-3">
        <b-tab v-for="(data, key) in dataHW" :title="key" v-bind="key">
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
                <b-list-group-item
                  >Vitesse du vent :
                  {{ data.wind_speed * 3.6 }} km/h</b-list-group-item
                >
                <b-list-group-item
                  >Orientation du vent : {{ data.wind_deg }}°</b-list-group-item
                >
              </b-list-group>
            </b-col>
            <b-col>
              <b-list-group>
                <b-list-group-item
                  >Description : {{ data.weather[0].description }}
                </b-list-group-item>
                <b-list-group-item> uvi : {{ data.uvi }} </b-list-group-item>
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
            <b-col>
              <b-list-group>
                <b-list-group-item
                  >dt / date :{{
                    $dayjs(data.dt * 1000).format("DD:MM:YYYY")
                  }}</b-list-group-item
                >
                <b-list-group-item
                  >sunrise / levé de soleil :
                  {{
                    $dayjs(data.sunrise * 1000).format("HH:mm")
                  }}</b-list-group-item
                >
                <b-list-group-item
                  >sunset / couché de soleil :
                  {{
                    $dayjs(data.sunset * 1000).format("HH:mm")
                  }}</b-list-group-item
                >
                <b-list-group-item
                  >temp / t° : {{ data.temp }}°</b-list-group-item
                >
                <b-list-group-item
                  >feels_like / t° resenti :
                  {{ data.feels_like }}°</b-list-group-item
                >
                <b-list-group-item
                  >pressure / pression :
                  {{ data.pressure }} hPa</b-list-group-item
                >
                <b-list-group-item
                  >humidity / humidité : {{ data.humidity }}%</b-list-group-item
                >
                <b-list-group-item
                  >dew_point / point de rosé :
                  {{ data.dew_point }}°</b-list-group-item
                >
                <b-list-group-item
                  >uvi / uv index : {{ data.uvi }}</b-list-group-item
                >
                <b-list-group-item
                  >clouds / nuages : {{ data.clouds }}</b-list-group-item
                >
                <b-list-group-item
                  >visibility / visivilité : {{ data.visibility }}
                </b-list-group-item>
                <b-list-group-item
                  >wind_speed / vitesse du vent :
                  {{ data.wind_speed }}</b-list-group-item
                >
                <b-list-group-item
                  >wind_deg / degré du vent
                  {{ data.wind_deg }}°</b-list-group-item
                >
                <b-list-group-item
                  >weather [] / meteo [] :
                  {{ data.weather[0].description }}</b-list-group-item
                >
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
      dataHWv2: [],
    };
  },
  async fetch() {
    this.dataHW = dataFilter(
      await fetch(
        "https://weather.hollux.fr/api_weather_detail/horbourg-wihr"
      ).then((res) => res.json())
    );
    this.dataHWv2 = await fetch(
      "https://weather.hollux.fr/savedaily/toto"
    ).then((res) => res.json());
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

<style scoped>
.margin {
  margin-bottom: 60px;
}
</style>