<script setup lang="ts">
import { type MonitorFormValues } from '@/composables/useMonitorForm';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import MonitorForm from './MonitorForm.vue';

interface Monitor {
    id: number;
    name: string;
    type: string;
    url: string;
    method: string;
    headers: Array<{ key: string; value: string }>;
    parameters: Array<{ key: string; value: string }>;
    enable_content_validation: boolean;
    expected_title?: string;
    expected_content?: string;
    is_active: boolean;
    check_interval: number;
}

interface Props {
    monitor: Monitor;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Monitors',
        href: '/monitors',
    },
    {
        title: 'Edit Monitor',
        href: `/monitors/${props.monitor.id}/edit`,
    },
];

const initialData: Partial<MonitorFormValues> = {
    name: props.monitor.name,
    type: props.monitor.type as 'website' | 'ip',
    url: props.monitor.url,
    method: props.monitor.method as 'GET' | 'POST',
    headers: props.monitor.headers,
    parameters: props.monitor.parameters,
    enable_content_validation: props.monitor.enable_content_validation,
    expected_title: props.monitor.expected_title,
    expected_content: props.monitor.expected_content,
    is_active: props.monitor.is_active,
    check_interval: props.monitor.check_interval,
};
</script>

<template>
    <Head title="Edit Monitor" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div>
                <h1 class="text-2xl font-bold">Edit Monitor</h1>
                <p class="text-muted-foreground">Update monitor settings</p>
            </div>

            <MonitorForm
                mode="edit"
                :initial-data="initialData"
                :monitor-id="monitor.id"
            />
        </div>
    </AppLayout>
</template>
