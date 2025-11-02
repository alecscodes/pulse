<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import MonitorForm from './MonitorForm.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Monitors',
        href: '/monitors',
    },
    {
        title: 'Create Monitor',
        href: '/monitors/create',
    },
];
</script>

<template>
    <Head title="Create Monitor" />

    <AppLayout :breadcrumbs="breadcrumbs">
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
    </AppLayout>
</template>
