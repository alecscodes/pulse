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
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    Calendar,
    CheckCircle2,
    Globe,
    Plus,
    TrendingDown,
    TrendingUp,
} from 'lucide-vue-next';

interface Monitor {
    id: number;
    name: string;
    url: string;
    status: 'up' | 'down' | 'unknown';
    is_down: boolean;
    response_time?: number;
}

interface DownWebsite {
    id: number;
    name: string;
    url: string;
    started_at?: string;
}

interface Domain {
    id: number;
    name: string;
    url: string;
    days_until_expiration?: number;
}

interface Stats {
    total_monitors: number;
    up_monitors: number;
    down_monitors: number;
    expiring_domains: number;
}

interface Props {
    stats: Stats;
    monitors: Monitor[];
    downWebsites: DownWebsite[];
    domains: Domain[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const getMonitorStatus = (monitor: Monitor) => {
    if (monitor.is_down)
        return { label: 'Down', variant: 'destructive' as const };
    if (monitor.status === 'up')
        return { label: 'Up', variant: 'default' as const };
    return { label: 'Unknown', variant: 'secondary' as const };
};

const getDomainStatus = (days?: number) => {
    if (days === null || days === undefined) return null;
    if (days <= 0) return { label: 'Expired', variant: 'destructive' as const };
    if (days <= 30)
        return { label: 'Expiring Soon', variant: 'destructive' as const };
    return { label: 'Active', variant: 'default' as const };
};

const getDomainDays = (days?: number) => {
    if (days === null || days === undefined) return null;
    if (days <= 0) return 'Expired';
    if (days === 1) return '1 day';
    return `${days} days`;
};

const isExpiringSoon = (days?: number) => {
    return days !== null && days !== undefined && days <= 30;
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Dashboard</h1>
                    <p class="text-muted-foreground">
                        Overview of your monitoring system
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
                v-if="props.stats.total_monitors === 0"
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

            <div v-else class="space-y-6">
                <!-- Stats Cards -->
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader
                            class="flex flex-row items-center justify-between space-y-0 pb-2"
                        >
                            <CardTitle class="text-sm font-medium">
                                Total Monitors
                            </CardTitle>
                            <Activity class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">
                                {{ props.stats.total_monitors }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Active monitoring
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            class="flex flex-row items-center justify-between space-y-0 pb-2"
                        >
                            <CardTitle class="text-sm font-medium"
                                >Up Monitors</CardTitle
                            >
                            <CheckCircle2
                                class="h-4 w-4 text-green-600 dark:text-green-400"
                            />
                        </CardHeader>
                        <CardContent>
                            <div
                                class="text-2xl font-bold text-green-600 dark:text-green-400"
                            >
                                {{ props.stats.up_monitors }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                <TrendingUp class="mr-1 inline h-3 w-3" />
                                Operational
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            class="flex flex-row items-center justify-between space-y-0 pb-2"
                        >
                            <CardTitle class="text-sm font-medium"
                                >Down Monitors</CardTitle
                            >
                            <AlertTriangle class="h-4 w-4 text-destructive" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-destructive">
                                {{ props.stats.down_monitors }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                <TrendingDown class="mr-1 inline h-3 w-3" />
                                Requires attention
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            class="flex flex-row items-center justify-between space-y-0 pb-2"
                        >
                            <CardTitle class="text-sm font-medium"
                                >Expiring Domains</CardTitle
                            >
                            <Calendar
                                class="h-4 w-4 text-orange-600 dark:text-orange-400"
                            />
                        </CardHeader>
                        <CardContent>
                            <div
                                class="text-2xl font-bold text-orange-600 dark:text-orange-400"
                            >
                                {{ props.stats.expiring_domains }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Expiring within 30 days
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Main Content Grid -->
                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Recent Monitors -->
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle>Recent Monitors</CardTitle>
                                    <CardDescription>
                                        Latest monitored websites
                                    </CardDescription>
                                </div>
                                <Link href="/monitors">
                                    <Button variant="ghost" size="sm">
                                        View All
                                    </Button>
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="monitors.length === 0"
                                class="py-8 text-center text-sm text-muted-foreground"
                            >
                                No monitors found
                            </div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="monitor in props.monitors"
                                    :key="monitor.id"
                                    class="group flex items-center justify-between rounded-lg border p-3 transition-colors hover:bg-accent"
                                >
                                    <Link
                                        :href="`/monitors/${monitor.id}`"
                                        class="flex flex-1 items-center gap-3"
                                    >
                                        <div
                                            :class="[
                                                'flex h-10 w-10 items-center justify-center rounded-lg',
                                                monitor.is_down
                                                    ? 'bg-destructive/10 text-destructive'
                                                    : monitor.status === 'up'
                                                      ? 'bg-green-500/10 text-green-600 dark:text-green-400'
                                                      : 'bg-muted text-muted-foreground',
                                            ]"
                                        >
                                            <Globe class="h-5 w-5" />
                                        </div>
                                        <div class="flex-1">
                                            <div class="font-medium">
                                                {{ monitor.name }}
                                            </div>
                                            <div
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{ monitor.url }}
                                            </div>
                                        </div>
                                    </Link>
                                    <div class="flex items-center gap-2">
                                        <div
                                            v-if="monitor.response_time"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ monitor.response_time }}ms
                                        </div>
                                        <Badge
                                            :variant="
                                                getMonitorStatus(monitor)
                                                    .variant
                                            "
                                            class="text-xs"
                                        >
                                            {{
                                                getMonitorStatus(monitor).label
                                            }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Down Websites -->
                    <Card v-if="props.downWebsites.length > 0">
                        <CardHeader>
                            <div class="flex items-center gap-2">
                                <AlertTriangle
                                    class="h-5 w-5 text-destructive"
                                />
                                <CardTitle>Down Websites</CardTitle>
                            </div>
                            <CardDescription>
                                Websites currently experiencing downtime
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2">
                                <div
                                    v-for="website in props.downWebsites"
                                    :key="website.id"
                                    class="flex items-center justify-between rounded-lg border border-destructive/20 bg-destructive/5 p-3 dark:border-destructive/30 dark:bg-destructive/10"
                                >
                                    <Link
                                        :href="`/monitors/${website.id}`"
                                        class="flex-1"
                                    >
                                        <div class="font-medium">
                                            {{ website.name }}
                                        </div>
                                        <div
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ website.url }}
                                        </div>
                                        <div
                                            v-if="website.started_at"
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            Down since:
                                            {{
                                                new Date(
                                                    website.started_at,
                                                ).toLocaleString()
                                            }}
                                        </div>
                                    </Link>
                                    <Badge variant="destructive" class="ml-2">
                                        Down
                                        <AlertCircle class="ml-1 h-3 w-3" />
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Domain Expiration -->
                    <Card v-if="props.domains.length > 0">
                        <CardHeader>
                            <div class="flex items-center gap-2">
                                <Calendar class="h-5 w-5" />
                                <CardTitle>Domain Expiration</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2">
                                <div
                                    v-for="domain in props.domains"
                                    :key="domain.id"
                                    :class="[
                                        'flex items-center justify-between rounded-lg border p-3 transition-colors hover:bg-accent/50',
                                        isExpiringSoon(
                                            domain.days_until_expiration,
                                        )
                                            ? 'border-destructive/30 bg-destructive/5 dark:border-destructive/30 dark:bg-destructive/10'
                                            : 'border-sidebar-border/70 dark:border-sidebar-border',
                                    ]"
                                >
                                    <Link
                                        :href="`/monitors/${domain.id}`"
                                        class="flex-1"
                                    >
                                        <div class="font-medium">
                                            {{ domain.name }}
                                        </div>
                                    </Link>
                                    <div class="flex items-center gap-2">
                                        <div
                                            v-if="
                                                getDomainDays(
                                                    domain.days_until_expiration,
                                                )
                                            "
                                            :class="[
                                                'text-sm font-medium',
                                                (domain.days_until_expiration ??
                                                    0) <= 0
                                                    ? 'text-destructive'
                                                    : isExpiringSoon(
                                                            domain.days_until_expiration,
                                                        )
                                                      ? 'text-orange-600 dark:text-orange-400'
                                                      : 'text-muted-foreground',
                                            ]"
                                        >
                                            {{
                                                getDomainDays(
                                                    domain.days_until_expiration,
                                                )
                                            }}
                                        </div>
                                        <span
                                            v-else
                                            class="text-sm text-muted-foreground"
                                            >—</span
                                        >
                                        <Badge
                                            v-if="
                                                getDomainStatus(
                                                    domain.days_until_expiration,
                                                )
                                            "
                                            :variant="
                                                getDomainStatus(
                                                    domain.days_until_expiration,
                                                )!.variant
                                            "
                                            class="text-xs"
                                        >
                                            {{
                                                getDomainStatus(
                                                    domain.days_until_expiration,
                                                )!.label
                                            }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
