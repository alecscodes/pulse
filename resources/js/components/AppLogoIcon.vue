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
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        :class="className"
        v-bind="$attrs"
    >
        <!-- Pulse/Heartbeat Monitor Icon -->
        <path
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M4 20h4l2-4 3 8 5-16 3 12h4l2-4"
        />
        <circle fill="currentColor" cx="10" cy="16" r="1.5" />
        <circle fill="currentColor" cx="22" cy="16" r="1.5" />
    </svg>
</template>
