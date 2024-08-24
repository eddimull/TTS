<template>
  <Link
    :href="href"
    class="underline"
  >
    <slot />
  </Link>
</template>

<script>
    export default {
        props: ['href', 'active']
    }
</script>
