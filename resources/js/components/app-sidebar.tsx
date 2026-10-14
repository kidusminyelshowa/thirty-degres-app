import { Link, usePage } from '@inertiajs/react';
import { Home, Library, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { WorkspaceSwitcher } from '@/components/workspace-switcher';
import { useTranslation } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { library, show } from '@/routes/workspaces';
import { index as members } from '@/routes/workspaces/members';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { t } = useTranslation();
    const { currentWorkspace } = usePage().props;

    const mainNavItems: NavItem[] = currentWorkspace
        ? [
              {
                  title: t('Home'),
                  href: show(currentWorkspace.slug),
                  icon: Home,
              },
              {
                  title: t('Library'),
                  href: library(currentWorkspace.slug),
                  icon: Library,
              },
              ...(currentWorkspace.canManageMembers
                  ? [
                        {
                            title: t('People'),
                            href: members(currentWorkspace.slug),
                            icon: Users,
                        },
                    ]
                  : []),
          ]
        : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <WorkspaceSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
