<template>
  <BreezeAuthenticatedLayout>
    <container>
      <FinanceMenu :routes="filteredRoutes" />
      <slot />
    </container>
  </BreezeAuthenticatedLayout>
</template>
  
  <script setup>
  import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
  import FinanceMenu from '../Components/FinanceMenu.vue'
  import { computed } from "vue"
  import { Ziggy } from '@/ziggy'
  
  const filteredRoutes = computed(() => {
    return Object.entries(Ziggy.routes).reduce((acc, [name, route]) => {
      if (route.uri.startsWith('finances/') && route.methods.includes('GET')) {
        acc[name] = route
      }
      return acc
    }, {})
  })
  </script>