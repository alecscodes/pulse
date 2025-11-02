<script setup lang="ts">
import type { HTMLAttributes } from 'vue'

interface Props {
  class?: HTMLAttributes['class']
}

defineProps<Props>()
</script>

<template>
  <div :class="class">
    <slot />
  </div>
</template>
