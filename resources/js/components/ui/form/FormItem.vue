<script setup lang="ts">
import type { HTMLAttributes } from 'vue'

interface Props {
  class?: HTMLAttributes['class']
}

const props = defineProps<Props>()
</script>

<template>
  <div :class="props.class">
    <slot />
  </div>
</template>
