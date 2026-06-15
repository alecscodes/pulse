<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { GITHUB_REPO_URL } from '@/lib/constants';
import { dashboard } from '@/routes';
import { index as logsIndex } from '@/routes/logs';
import { index as monitorsIndex } from '@/routes/monitors';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    BookOpen,
    FileText,
    Github,
    LayoutGrid,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Monitors',
        href: monitorsIndex(),
        icon: Activity,
    },
    {
        title: 'Logs',
        href: logsIndex(),
        icon: FileText,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: GITHUB_REPO_URL,
        icon: Github,
    },
    {
        title: 'Documentation',
        href: 'https://github.com/alecscodes/pulse/blob/main/README.md',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
