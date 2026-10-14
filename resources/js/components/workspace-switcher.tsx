import { Link, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslation } from '@/lib/i18n';
import { roleLabel } from '@/lib/roles';
import { create, show } from '@/routes/workspaces';

/**
 * Shows the current company workspace and lets people who belong to several
 * (an agency designer working for three clients) move between them.
 */
export function WorkspaceSwitcher() {
    const { t } = useTranslation();
    const { workspaces, currentWorkspace } = usePage().props;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent"
                            tooltip={{
                                children:
                                    currentWorkspace?.name ??
                                    t('Choose a workspace'),
                            }}
                        >
                            <Building2 />
                            <span className="grid flex-1 text-left leading-tight">
                                <span className="truncate font-medium">
                                    {currentWorkspace?.name ??
                                        t('Choose a workspace')}
                                </span>
                                {currentWorkspace && (
                                    <span className="truncate text-xs text-muted-foreground">
                                        {roleLabel(t, currentWorkspace.role)}
                                    </span>
                                )}
                            </span>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56"
                        align="start"
                    >
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            {t('Workspaces')}
                        </DropdownMenuLabel>
                        {workspaces.map((workspace) => (
                            <DropdownMenuItem key={workspace.slug} asChild>
                                <Link href={show(workspace.slug)}>
                                    <span className="truncate">
                                        {workspace.name}
                                    </span>
                                    {workspace.slug ===
                                        currentWorkspace?.slug && (
                                        <Check className="ml-auto" />
                                    )}
                                </Link>
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link href={create()}>
                                <Plus />
                                {t('New workspace')}
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
