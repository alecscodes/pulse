<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import UpdateNotification from '@/components/UpdateNotification.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    CheckCircle2,
    Clock,
    Plus,
} from 'lucide-vue-next';

interface Monitor {
    id: number;
    name: string;
    url: string;
    status: 'up' | 'down' | 'unknown';
    is_down: boolean;
    response_time?: number;
}

interface Props {
    monitors: Monitor[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <UpdateNotification />

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Dashboard</h1>
                    <p class="text-muted-foreground">
                        Overview of your monitors
                    </p>
                </div>
                <Link href="/monitors/create">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        Add Monitor
                    </Button>
                </Link>
            </div>

            <div
                v-if="monitors.length === 0"
                class="flex flex-col items-center justify-center rounded-xl border border-sidebar-border/70 p-12 dark:border-sidebar-border"
            >
                <Activity class="mb-4 h-12 w-12 text-muted-foreground" />
                <h2 class="mb-2 text-xl font-semibold">No monitors yet</h2>
                <p class="mb-4 text-center text-muted-foreground">
                    Get started by creating your first monitor to track website
                    uptime.
                </p>
                <Link href="/monitors/create">
                    <Button>Create Your First Monitor</Button>
                </Link>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="monitor in monitors"
                    :key="monitor.id"
                    class="cursor-pointer transition-shadow hover:shadow-lg"
                >
                    <Link :href="`/monitors/${monitor.id}`">
                        <CardHeader>
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <CardTitle class="text-lg">{{
                                        monitor.name
                                    }}</CardTitle>
                                    <CardDescription class="mt-1">
                                        <a
                                            :href="monitor.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="hover:underline"
                                            @click.stop
                                        >
                                            {{ monitor.url }}
                                        </a>
                                    </CardDescription>
                                </div>
                                <Badge
                                    :variant="
                                        monitor.is_down
                                            ? 'destructive'
                                            : monitor.status === 'up'
                                              ? 'default'
                                              : 'secondary'
                                    "
                                >
                                    <AlertCircle
                                        v-if="
                                            monitor.is_down ||
                                            monitor.status === 'down'
                                        "
                                        class="mr-1 h-3 w-3"
                                    />
                                    <CheckCircle2
                                        v-else-if="monitor.status === 'up'"
                                        class="mr-1 h-3 w-3"
                                    />
                                    <Clock v-else class="mr-1 h-3 w-3" />
                                    {{
                                        monitor.is_down
                                            ? 'Down'
                                            : monitor.status === 'up'
                                              ? 'Up'
                                              : 'Unknown'
                                    }}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="monitor.response_time"
                                class="text-sm text-muted-foreground"
                            >
                                Response time: {{ monitor.response_time }}ms
                            </div>
                        </CardContent>
                    </Link>
                </Card>
            </div>

            <div v-if="monitors.length > 0" class="mt-4">
                <Link href="/monitors">
                    <Button variant="outline" class="w-full"
                        >View All Monitors</Button
                    >
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
