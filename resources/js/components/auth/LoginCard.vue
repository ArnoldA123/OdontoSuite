<template>
  <div class="login-card">
    <div class="login-card-container">
      <slot />
    </div>
  </div>
</template>

<script setup>
// Componente reutilizable para la tarjeta de login
// Estilo tipo iCloud con fondo abstracto y tarjeta blanca redondeada
</script>

<style scoped>
.login-card {
  @apply relative w-full max-w-md mx-auto;
}

.login-card-container {
  @apply bg-theme-surface-elevated rounded-3xl;
  @apply backdrop-blur-xl;
  @apply border border-theme;
  @apply p-8 sm:p-10;
  @apply transition-all duration-300;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
}

/* Efecto de elevación al hover */
.login-card-container:hover {
  box-shadow: 0 25px 70px rgba(0, 0, 0, 0.15);
  transform: translateY(-2px);
}

/* Responsive padding */
@media (max-width: 640px) {
  .login-card-container {
    @apply p-6 rounded-2xl;
  }
}
</style>

