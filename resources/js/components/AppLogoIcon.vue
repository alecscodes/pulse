<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img
        src="/logo.svg"
        alt="Pulse Logo"
        :class="['h-full w-full object-contain', className]"
        v-bind="$attrs"
    />
</template>
