<template>
  <div class="date-range-form">
    <label class="date-range-label">{{ label }}</label>
    <b-form-datepicker
      :value="min"
      class="date-range-input"
      @input="$emit('update:min', $event)"
    ></b-form-datepicker>
    <b-form-datepicker
      :value="max"
      class="date-range-input"
      @input="$emit('update:max', $event)"
    ></b-form-datepicker>
    <b-button variant="primary" class="date-range-submit" @click="$emit('submit')">
      {{ buttonLabel }}
    </b-button>
  </div>
</template>

<script>
export default {
  props: {
    min: { type: String, default: "" },
    max: { type: String, default: "" },
    label: { type: String, default: "Choisissez une plage :" },
    buttonLabel: { type: String, default: "Valider" },
  },
};
</script>

<style scoped>
.date-range-form {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  margin: 0.5rem 0 1.25rem;
}

.date-range-label {
  margin: 0;
  white-space: nowrap;
}

.date-range-input {
  width: auto;
  min-width: 12rem;
}

.date-range-submit {
  align-self: center;
}
</style>
