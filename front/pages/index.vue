<template>
  <b-container class="component">
    <h1 class="text-center">Meteo</h1>
    <br />

    <h2>Infos V2.1</h2>

    <div v-for="(datav2, key) in dataHWv2['success']" :key="key">
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
  // data.success.daily.forEach(function (value, i) {
  //   resp["jour" + i] = value;
  // });

  return resp;
}
</script>

<style scoped>
.margin {
  margin-bottom: 60px;
}
</style>