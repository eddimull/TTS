<template>
  <Container>
    <div class="max-w-7xl mx-auto">
      <RoleManager
        :band="band"
        :roles="roles"
      />
    </div>
  </Container>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import RoleManager from './Components/RoleManager.vue'

export default {
  components: {
    RoleManager,
  },
  layout: BreezeAuthenticatedLayout,
  pageTitle: 'Band Roles',
  props: {
    band: {
      type: Object,
      required: true
    },
    roles: {
      type: Array,
      default: () => []
    }
  }
}
</script>
