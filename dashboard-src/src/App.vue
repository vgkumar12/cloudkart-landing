<template>
  <div id="app">
    <router-view></router-view>
  </div>
</template>

<script setup>
// Main App Component simply acts as a router container
</script>

<style>
:root {
  --primary: #6B46C1;
  --bg-color: #f8fafc;
}

body {
  background: var(--bg-color);
  margin: 0;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  overflow-x: hidden;
}

/* Global Utility */
.mr-1 {
  margin-right: 0.25rem;
}

.mr-2 {
  margin-right: 0.5rem;
}
</style>
