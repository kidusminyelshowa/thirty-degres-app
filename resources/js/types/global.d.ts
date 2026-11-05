import type { Auth } from '@/types/auth';
import type { CurrentWorkspace, WorkspaceSummary } from '@/types/workspace';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            workspaces: WorkspaceSummary[];
            currentWorkspace: CurrentWorkspace | null;
            locale: string;
            interfaceLanguages: { code: string; native: string }[];
            translations: Record<string, string>;
            [key: string]: unknown;
        };
    }
}
