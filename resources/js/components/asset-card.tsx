import { Link, usePage } from '@inertiajs/react';
import { AssetThumbnail } from '@/components/asset-thumbnail';
import { Badge } from '@/components/ui/badge';
import { statusLabel } from '@/lib/assets';
import { useTranslation } from '@/lib/i18n';
import { show } from '@/routes/workspaces/assets';
import type { AssetSummary } from '@/types';

export function AssetCard({
    asset,
    showBrand,
}: {
    asset: AssetSummary;
    showBrand: boolean;
}) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;

    return (
        <Link
            href={show([workspace.slug, asset.id])}
            className="group flex flex-col overflow-hidden rounded-xl border bg-card transition-colors hover:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            <div className="relative">
                <AssetThumbnail
                    previewUrl={asset.previewUrl}
                    kind={asset.kind}
                    extension={asset.extension}
                    alt=""
                    className="aspect-[4/3] w-full"
                />
                {asset.status !== 'approved' && (
                    <Badge
                        variant={
                            asset.status === 'draft' ? 'secondary' : 'outline'
                        }
                        className="absolute top-2 left-2 bg-background/90"
                    >
                        {statusLabel(t, asset.status)}
                    </Badge>
                )}
            </div>
            <div className="flex flex-col gap-1 p-3">
                <p className="truncate text-sm font-medium" title={asset.name}>
                    {asset.name}
                </p>
                <p className="truncate text-xs text-muted-foreground">
                    {showBrand && `${asset.brand.name} · `}
                    <span className="uppercase">{asset.extension}</span>
                </p>
                {asset.tags.length > 0 && (
                    <div className="flex flex-wrap gap-1 pt-1">
                        {asset.tags.slice(0, 3).map((tag) => (
                            <Badge
                                key={tag}
                                variant="outline"
                                className="max-w-full truncate font-normal"
                            >
                                {tag}
                            </Badge>
                        ))}
                        {asset.tags.length > 3 && (
                            <span className="text-xs text-muted-foreground">
                                +{asset.tags.length - 3}
                            </span>
                        )}
                    </div>
                )}
            </div>
        </Link>
    );
}
