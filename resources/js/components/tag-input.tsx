import { X } from 'lucide-react';
import { useId, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';

const MAX_TAGS = 20;
const MAX_LENGTH = 40;

/**
 * Type a tag and press Enter or a comma to add it. Sends the tags as
 * `name[]` when the surrounding form submits.
 */
export function TagInput({
    value,
    onChange,
    suggestions = [],
    name = 'tags',
    id,
}: {
    value: string[];
    onChange: (tags: string[]) => void;
    suggestions?: string[];
    name?: string;
    id?: string;
}) {
    const { t } = useTranslation();
    const [draft, setDraft] = useState('');
    const listId = useId();

    const add = (raw: string) => {
        const tag = raw.replace(/\s+/g, ' ').trim().slice(0, MAX_LENGTH);

        if (
            tag === '' ||
            value.length >= MAX_TAGS ||
            value.some((held) => held.toLowerCase() === tag.toLowerCase())
        ) {
            setDraft('');

            return;
        }

        onChange([...value, tag]);
        setDraft('');
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter' || event.key === ',' || event.key === '፣') {
            event.preventDefault();
            add(draft);
        } else if (event.key === 'Backspace' && draft === '' && value.length) {
            onChange(value.slice(0, -1));
        }
    };

    return (
        <div className="flex flex-col gap-2">
            {value.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {value.map((tag) => (
                        <Badge
                            key={tag}
                            variant="secondary"
                            className="gap-1 py-1 pr-1 font-normal"
                        >
                            {tag}
                            <button
                                type="button"
                                onClick={() =>
                                    onChange(value.filter((t) => t !== tag))
                                }
                                className="rounded-sm p-0.5 hover:bg-background"
                                aria-label={t('Remove tag :tag', { tag })}
                            >
                                <X />
                            </button>
                            <input
                                type="hidden"
                                name={`${name}[]`}
                                value={tag}
                            />
                        </Badge>
                    ))}
                </div>
            )}
            <Input
                id={id}
                value={draft}
                list={suggestions.length ? listId : undefined}
                maxLength={MAX_LENGTH}
                onChange={(event) => setDraft(event.target.value)}
                onKeyDown={onKeyDown}
                onBlur={() => add(draft)}
                placeholder={t('Type a tag and press Enter')}
            />
            {suggestions.length > 0 && (
                <datalist id={listId}>
                    {suggestions
                        .filter((s) => !value.includes(s))
                        .map((s) => (
                            <option key={s} value={s} />
                        ))}
                </datalist>
            )}
        </div>
    );
}
