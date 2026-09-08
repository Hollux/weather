<template>
  <h1 class="page-title">{{ group ? `${title} ${group}` : title }}</h1>
</template>

<script>
export default {
  props: {
    title: { type: String, required: true },
    group: { type: String, default: "" },
  },
};
</script>

<style scoped>
.page-title {
  font-size: 1.6rem;
  font-weight: 600;
  color: #000;
  margin: 1rem 0 1.25rem;
}
</style>
