<script setup lang="ts">
import { create, index as monitorsIndex } from '@/routes/monitors';
import { Head } from '@inertiajs/vue3';
import MonitorForm from './MonitorForm.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Monitors',
                href: monitorsIndex(),
            },
            {
                title: 'Create Monitor',
                href: create(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Create Monitor" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div>
            <h1 class="text-2xl font-bold">Create Monitor</h1>
            <p class="text-muted-foreground">
                Add a new website or IP to monitor
            </p>
        </div>

        <MonitorForm mode="create" />
    </div>
</template>
