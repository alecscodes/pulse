<script setup lang="ts">
import { ActionSheet, ActionSheetRoot } from '@/components/ui/action-sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle2,
    Clock,
    Eye,
    MoreVertical,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Monitor {
    id: number;
    name: string;
    type: string;
    url: string;
    method: string;
    is_active: boolean;
    check_interval: number;
    status: 'up' | 'down' | 'unknown';
    last_checked_at?: string;
    response_time?: number;
    is_down: boolean;
}

interface Props {
    monitors: Monitor[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Monitors',
        href: '/monitors',
    },
];

const showActionSheet = ref(false);
const actionSheetMonitor = ref<Monitor | null>(null);

function formatInterval(seconds: number): string {
    if (seconds < 60) {
        return `${seconds}s`;
    }
    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)}m`;
    }
    return `${Math.floor(seconds / 3600)}h`;
}

function deleteMonitor(id: number): void {
    if (confirm('Are you sure you want to delete this monitor?')) {
        router.delete(`/monitors/${id}`);
    }
}

const openActionSheet = (monitor: Monitor) => {
    actionSheetMonitor.value = monitor;
    showActionSheet.value = true;
};

const actionSheetButtons = computed(() => {
    if (!actionSheetMonitor.value) {
        return [
            {
                text: 'Add Monitor',
                icon: Plus,
                handler: () => {
                    showActionSheet.value = false;
                    router.visit('/monitors/create');
                },
            },
        ];
    }

    return [
        {
            text: 'View Details',
            icon: Eye,
            handler: () => {
                showActionSheet.value = false;
                router.visit(`/monitors/${actionSheetMonitor.value!.id}`);
            },
        },
        {
            text: 'Edit Monitor',
            icon: Pencil,
            handler: () => {
                showActionSheet.value = false;
                router.visit(`/monitors/${actionSheetMonitor.value!.id}/edit`);
            },
        },
        {
            text: 'Delete Monitor',
            icon: Trash2,
            role: 'destructive' as const,
            handler: () => {
                showActionSheet.value = false;
                deleteMonitor(actionSheetMonitor.value!.id);
            },
        },
    ];
});
</script>

<template>
    <Head title="Monitors" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Monitors</h1>
                    <p class="text-muted-foreground">
                        Manage your uptime monitoring
                    </p>
                </div>

                <!-- Desktop: Show button, Mobile: Show 3-dot menu -->
                <div class="hidden md:block">
                    <Link :href="'/monitors/create'">
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Add Monitor
                        </Button>
                    </Link>
                </div>

                <!-- Mobile: 3-dot menu -->
                <div class="md:hidden">
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="
                            actionSheetMonitor = null;
                            showActionSheet = true;
                        "
                    >
                        <MoreVertical class="h-5 w-5" />
                    </Button>
                </div>
            </div>

            <div
                v-if="monitors.length === 0"
                class="flex flex-col items-center justify-center rounded-xl border border-sidebar-border/70 p-12 dark:border-sidebar-border"
            >
                <p class="text-muted-foreground">
                    No monitors yet. Create your first monitor to get started.
                </p>
                <Link :href="'/monitors/create'" class="mt-4">
                    <Button>Create Monitor</Button>
                </Link>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="monitor in monitors" :key="monitor.id">
                    <CardHeader>
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <CardTitle class="text-lg">
                                    <Link
                                        :href="`/monitors/${monitor.id}`"
                                        class="hover:underline"
                                    >
                                        {{ monitor.name }}
                                    </Link>
                                </CardTitle>
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
                            <div class="flex items-center gap-2">
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
                                <!-- Mobile: 3-dot menu -->
                                <div class="md:hidden">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        @click="openActionSheet(monitor)"
                                    >
                                        <MoreVertical class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Type:</span>
                                <span class="font-medium">{{
                                    monitor.type
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Method:</span
                                >
                                <span class="font-medium">{{
                                    monitor.method
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Interval:</span
                                >
                                <span class="font-medium">{{
                                    formatInterval(monitor.check_interval)
                                }}</span>
                            </div>
                            <div
                                v-if="monitor.response_time"
                                class="flex justify-between"
                            >
                                <span class="text-muted-foreground"
                                    >Response Time:</span
                                >
                                <span class="font-medium"
                                    >{{ monitor.response_time }}ms</span
                                >
                            </div>
                            <div
                                v-if="monitor.last_checked_at"
                                class="flex justify-between"
                            >
                                <span class="text-muted-foreground"
                                    >Last Checked:</span
                                >
                                <span class="font-medium">{{
                                    new Date(
                                        monitor.last_checked_at,
                                    ).toLocaleString()
                                }}</span>
                            </div>
                        </div>

                        <!-- Desktop: Show buttons -->
                        <div class="mt-4 hidden gap-2 md:flex">
                            <Link
                                :href="`/monitors/${monitor.id}`"
                                class="flex-1"
                            >
                                <Button variant="outline" class="w-full"
                                    >View</Button
                                >
                            </Link>
                            <Link
                                :href="`/monitors/${monitor.id}/edit`"
                                class="flex-1"
                            >
                                <Button variant="outline" class="w-full"
                                    >Edit</Button
                                >
                            </Link>
                            <Button
                                variant="destructive"
                                @click="deleteMonitor(monitor.id)"
                                >Delete</Button
                            >
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Mobile Action Sheet -->
            <ActionSheetRoot v-model:open="showActionSheet">
                <ActionSheet
                    :buttons="actionSheetButtons"
                    :header="
                        actionSheetMonitor
                            ? actionSheetMonitor.name
                            : 'Monitors'
                    "
                    @action="showActionSheet = false"
                />
            </ActionSheetRoot>
        </div>
    </AppLayout>
</template>
