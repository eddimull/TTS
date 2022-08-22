<template>
  <div class="border-b py-2">
    <h2 class="text-xl">
      <span class="text-2xl text-green-700">{{ number }}.</span> {{ title }}
    </h2>
  </div>
</template>

<script>
export default {
    props:{
        number: {
            type: String,
            default: "1"
        },
        title: {
            type: String,
            default: 'Initial Information'
        }

    }
}
</script>
