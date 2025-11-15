<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square h-12 w-12 items-center justify-center rounded-md bg-black p-1.5 dark:bg-white"
    >
        <AppLogoIcon class="size-full object-contain invert dark:invert-0" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">Pulse</span>
    </div>
</template>
