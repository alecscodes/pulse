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
    ChevronLeft,
    ChevronRight,
    Edit,
    MoreVertical,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Check {
    id: number;
    status: 'up' | 'down';
    response_time?: number;
    status_code?: number;
    error_message?: string;
    content_valid?: boolean;
    checked_at: string;
}

interface Downtime {
    id: number;
    started_at: string;
    ended_at?: string;
    duration_seconds?: number;
}

interface Monitor {
    id: number;
    name: string;
    type: string;
    url: string;
    method: string;
    headers: Record<string, string>;
    parameters: Record<string, string>;
    enable_content_validation: boolean;
    expected_title?: string;
    expected_content?: string;
    is_active: boolean;
    check_interval: number;
    checks?: Check[];
    downtimes?: Downtime[];
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
        title: props.monitor.name,
        href: `/monitors/${props.monitor.id}`,
    },
];

const showActionSheet = ref(false);
const downtimePage = ref(1);
const downtimeItemsPerPage = 10;

const paginatedDowntimes = computed(() => {
    if (!props.monitor.downtimes || props.monitor.downtimes.length === 0) {
        return [];
    }
    const start = (downtimePage.value - 1) * downtimeItemsPerPage;
    const end = start + downtimeItemsPerPage;
    return props.monitor.downtimes.slice(start, end);
});

const downtimeTotalPages = computed(() => {
    if (!props.monitor.downtimes || props.monitor.downtimes.length === 0) {
        return 0;
    }
    return Math.ceil(props.monitor.downtimes.length / downtimeItemsPerPage);
});

function formatDuration(seconds?: number): string {
    if (!seconds || seconds < 0) {
        return 'N/A';
    }
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    const parts = [];
    if (hours > 0) parts.push(`${hours}h`);
    if (minutes > 0) parts.push(`${minutes}m`);
    if (secs > 0 || parts.length === 0) parts.push(`${secs}s`);
    return parts.join(' ');
}

function getDowntimeDuration(downtime: Downtime): number | undefined {
    if (downtime.duration_seconds != null && downtime.duration_seconds >= 0) {
        return downtime.duration_seconds;
    }
    
    if (!downtime.ended_at) {
        return undefined;
    }

    return Math.floor(
        (new Date(downtime.ended_at).getTime() -
            new Date(downtime.started_at).getTime()) /
            1000,
    );
}

function deleteMonitor(): void {
    if (confirm('Are you sure you want to delete this monitor?')) {
        router.delete(`/monitors/${props.monitor.id}`);
    }
}

const actionSheetButtons = computed(() => [
    {
        text: 'Edit Monitor',
        icon: Edit,
        handler: () => {
            showActionSheet.value = false;
            router.visit(`/monitors/${props.monitor.id}/edit`);
        },
    },
    {
        text: 'Delete Monitor',
        icon: Trash2,
        role: 'destructive' as const,
        handler: () => {
            showActionSheet.value = false;
            deleteMonitor();
        },
    },
    {
        text: 'Cancel',
        role: 'cancel' as const,
        handler: () => {
            showActionSheet.value = false;
        },
    },
]);
</script>

<template>
    <Head :title="monitor.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ monitor.name }}</h1>
                    <p class="text-muted-foreground">
                        <a
                            :href="monitor.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hover:underline"
                        >
                            {{ monitor.url }}
                        </a>
                    </p>
                </div>

                <!-- Desktop: Show buttons, Mobile: Show 3-dot menu -->
                <div class="hidden gap-2 md:flex">
                    <Link :href="`/monitors/${monitor.id}/edit`">
                        <Button variant="outline">
                            <Edit class="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                    <Button variant="destructive" @click="deleteMonitor">
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                </div>

                <!-- Mobile: 3-dot menu -->
                <div class="md:hidden">
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="showActionSheet = true"
                    >
                        <MoreVertical class="h-5 w-5" />
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Card class="self-start">
                    <CardHeader>
                        <CardTitle>Monitor Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Type:</span>
                            <span class="font-medium">{{ monitor.type }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Method:</span>
                            <span class="font-medium">{{
                                monitor.method
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Status:</span>
                            <Badge
                                :variant="
                                    monitor.is_active ? 'default' : 'secondary'
                                "
                            >
                                {{ monitor.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Check Interval:</span
                            >
                            <span class="font-medium"
                                >{{ monitor.check_interval }}s</span
                            >
                        </div>
                        <div
                            v-if="monitor.enable_content_validation"
                            class="mt-4 rounded-md bg-muted p-2"
                        >
                            <p class="text-xs font-medium">
                                Content Validation Enabled
                            </p>
                            <p
                                v-if="monitor.expected_title"
                                class="text-xs text-muted-foreground"
                            >
                                Expected Title: {{ monitor.expected_title }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div>
                    <Card>
                        <CardHeader class="flex items-center">
                            <CardTitle>Recent Checks</CardTitle>
                        </CardHeader>
                        <CardContent
                            v-if="
                                !monitor.checks || monitor.checks.length === 0
                            "
                        >
                            <div class="text-sm text-muted-foreground">
                                No checks yet
                            </div>
                        </CardContent>
                    </Card>

                    <div
                        v-if="monitor.checks && monitor.checks.length > 0"
                        class="mt-2 space-y-2 px-2"
                    >
                        <div
                            v-for="check in monitor.checks.slice(0, 5)"
                            :key="check.id"
                            class="flex items-center justify-between rounded-md border p-2 text-sm"
                        >
                            <div class="flex items-center gap-2">
                                <CheckCircle2
                                    v-if="check.status === 'up'"
                                    class="h-4 w-4 text-green-500"
                                />
                                <AlertCircle
                                    v-else
                                    class="h-4 w-4 text-red-500"
                                />
                                <span class="font-medium">{{
                                    check.status === 'up' ? 'Up' : 'Down'
                                }}</span>
                                <span
                                    v-if="check.response_time"
                                    class="text-muted-foreground"
                                >
                                    ({{ check.response_time }}ms)
                                </span>
                                <span
                                    v-if="check.status_code"
                                    class="text-muted-foreground"
                                >
                                    [{{ check.status_code }}]
                                </span>
                            </div>
                            <span class="text-xs text-muted-foreground">
                                {{
                                    new Date(check.checked_at).toLocaleString()
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle
                        >Downtime History ({{
                            monitor.downtimes?.length
                        }})</CardTitle
                    >
                    <CardDescription
                        >Past incidents when the monitor was
                        down</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div
                        v-if="
                            !monitor.downtimes || monitor.downtimes.length === 0
                        "
                        class="text-sm text-muted-foreground"
                    >
                        No downtime recorded
                    </div>
                    <div v-else>
                        <div class="space-y-2">
                            <div
                                v-for="downtime in paginatedDowntimes"
                                :key="downtime.id"
                                class="flex items-center justify-between rounded-md border p-3"
                            >
                                <div>
                                    <p class="font-medium">
                                        {{
                                            new Date(
                                                downtime.started_at,
                                            ).toLocaleString()
                                        }}
                                    </p>
                                    <p
                                        v-if="downtime.ended_at"
                                        class="text-sm text-muted-foreground"
                                    >
                                        Ended:
                                        {{
                                            new Date(
                                                downtime.ended_at,
                                            ).toLocaleString()
                                        }}
                                    </p>
                                    <p v-else class="text-sm text-green-600">
                                        Currently Down
                                    </p>
                                </div>
                                <Badge variant="destructive">
                                    {{
                                        formatDuration(
                                            getDowntimeDuration(downtime),
                                        )
                                    }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div
                            v-if="downtimeTotalPages > 1"
                            class="mt-4 flex items-center justify-center gap-2"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="downtimePage === 1"
                                @click="downtimePage--"
                            >
                                <ChevronLeft class="h-4 w-4" />
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="downtimePage === downtimeTotalPages"
                                @click="downtimePage++"
                            >
                                Next
                                <ChevronRight class="ml-2 h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Mobile Action Sheet -->
            <ActionSheetRoot v-model:open="showActionSheet">
                <ActionSheet
                    :buttons="actionSheetButtons"
                    :header="props.monitor.name"
                    @action="showActionSheet = false"
                />
            </ActionSheetRoot>
        </div>
    </AppLayout>
</template>
