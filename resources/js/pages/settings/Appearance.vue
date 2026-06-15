<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit().url,
            },
        ],
    },
});
</script>

<template>
    <Head title="Appearance settings" />

    <div class="space-y-6">
        <HeadingSmall
            title="Appearance settings"
            description="Update your account's appearance settings"
        />
        <AppearanceTabs />
    </div>
</template>
