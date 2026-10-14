<script setup>
// Replaces the /:pathMatch(.*)* catch-all redirect from src/router.js.
// Any unknown path is sent back to the home page, exactly as before.
definePageMeta({ redirect: '/' })
</script>

<template>
  <div />
</template>
