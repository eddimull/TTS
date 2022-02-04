<template>
  <div>
    <ul>
      <li>Venue: {{ event.venue_name }}</li>
      <li>What should go here? Maybe special notes?</li>
    </ul>
    <!-- {{ event }} -->
  </div>
</template>

<script>

export default {
    props:['event']    
}
</script>