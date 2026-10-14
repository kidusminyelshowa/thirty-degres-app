import { File, FileText, Image, Type } from 'lucide-react';
import type { AssetKind } from '@/types';
import { cn } from '@/lib/utils';

/**
 * The light preview of a file, or an icon when it has none (fonts,
 * documents, design files). Previews load only as they scroll into view.
 */
export function AssetThumbnail({
    previewUrl,
    kind,
    extension,
    alt,
    className,
    eager = false,
}: {
    previewUrl: string | null;
    kind: AssetKind;
    extension: string;
    alt: string;
    className?: string;
    eager?: boolean;
}) {
    const Icon = {
        logo: Image,
        photo: Image,
        font: Type,
        document: FileText,
        other: File,
    }[kind];

    return (
        <div
            className={cn(
                'flex items-center justify-center overflow-hidden bg-muted',
                className,
            )}
        >
            {previewUrl ? (
                <img
                    src={previewUrl}
                    alt={alt}
                    loading={eager ? 'eager' : 'lazy'}
                    decoding="async"
                    className="h-full w-full object-contain"
                />
            ) : (
                <div className="flex flex-col items-center gap-2 text-muted-foreground">
                    <Icon className="size-8" aria-hidden />
                    <span className="text-xs font-medium uppercase">
                        {extension}
                    </span>
                </div>
            )}
        </div>
    );
}
