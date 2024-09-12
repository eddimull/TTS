<template>
  <Container>
    <FinanceMenu :routes="filteredRoutes" />
  </Container>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import FinanceMenu from './Components/FinanceMenu.vue';
import { ref, computed } from "vue";
import { Ziggy } from '@/ziggy';

defineOptions({
      layout: BreezeAuthenticatedLayout,
    })


const filteredRoutes = computed(() => {
  return Object.entries(Ziggy.routes).reduce((acc, [name, route]) => {
    if (route.uri.startsWith('finances/') && route.methods.includes('GET')) {
      acc[name] = route
    }
    return acc
  }, {})
})
</script>
