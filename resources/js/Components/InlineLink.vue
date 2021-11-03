<template>
  <inertia-link
    :href="href"
    class="underline"
  >
    <slot />
  </inertia-link>
</template>

<script>
    export default {
        props: ['href', 'active']
    }
</script>
