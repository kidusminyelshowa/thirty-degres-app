import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// Internal reference page for reviewing the design system on preview links.
// Not user-facing, so its copy is not translated.

const brandColours = [
    { name: 'Ink', token: 'ink', hex: '#022018', className: 'bg-ink' },
    { name: 'Mist', token: 'mist', hex: '#F6F9F7', className: 'bg-mist' },
    {
        name: 'Green',
        token: 'brand-green',
        hex: '#01803B',
        className: 'bg-brand-green',
    },
    { name: 'Lime', token: 'lime', hex: '#D1F811', className: 'bg-lime' },
];

const roleColours = [
    { name: 'Background', className: 'bg-background' },
    { name: 'Card', className: 'bg-card' },
    { name: 'Primary', className: 'bg-primary' },
    { name: 'Secondary', className: 'bg-secondary' },
    { name: 'Accent', className: 'bg-accent' },
    { name: 'Muted text', className: 'bg-muted-foreground' },
    { name: 'Border', className: 'bg-border' },
    { name: 'Destructive', className: 'bg-destructive' },
];

const typeScale = [
    { name: 'Display', className: 'text-4xl font-semibold' },
    { name: 'Heading', className: 'text-2xl font-semibold' },
    { name: 'Subheading', className: 'text-lg font-medium' },
    { name: 'Body', className: 'text-base' },
    { name: 'Small', className: 'text-sm text-muted-foreground' },
];

const english = "Your brand's assets, guidelines and voice in one place.";
const amharic = 'የብራንድዎ ንብረቶች፣ መመሪያዎች እና ድምጽ በአንድ ቦታ።';

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4">
            <div>
                <h2 className="text-xl font-semibold">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            {children}
        </section>
    );
}

export default function DesignSystem() {
    return (
        <>
            <Head title="Design system" />
            <main className="mx-auto max-w-5xl space-y-12 px-4 py-10 sm:px-6">
                <header className="space-y-2">
                    <Badge variant="secondary">Internal reference</Badge>
                    <h1 className="text-4xl font-semibold">
                        Thirty Degres design system
                    </h1>
                    <p
                        lang="am"
                        className="text-2xl font-semibold text-brand-green dark:text-lime"
                    >
                        ሠላሳ ዲግሪ
                    </p>
                </header>

                <Section
                    title="Brand colours"
                    description="Taken from the Thirty Degres studio identity."
                >
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        {brandColours.map((colour) => (
                            <div key={colour.token} className="space-y-2">
                                <div
                                    className={`h-20 rounded-lg border ${colour.className}`}
                                />
                                <div className="text-sm">
                                    <div className="font-medium">
                                        {colour.name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {colour.hex} · {colour.token}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </Section>

                <Section
                    title="Colour roles"
                    description="Screens use these roles, never raw colours, so light and dark mode both work. Switch appearance in settings to compare."
                >
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        {roleColours.map((colour) => (
                            <div key={colour.name} className="space-y-2">
                                <div
                                    className={`h-12 rounded-lg border ${colour.className}`}
                                />
                                <div className="text-sm font-medium">
                                    {colour.name}
                                </div>
                            </div>
                        ))}
                    </div>
                </Section>

                <Section
                    title="Type scale in both scripts"
                    description={`Ge'ez text gets Noto Sans Ethiopic and extra line height automatically when it is marked lang="am".`}
                >
                    <div className="space-y-6">
                        {typeScale.map((step) => (
                            <div
                                key={step.name}
                                className="grid gap-2 border-b pb-6 sm:grid-cols-[8rem_1fr_1fr]"
                            >
                                <div className="text-sm text-muted-foreground">
                                    {step.name}
                                </div>
                                <p className={step.className}>{english}</p>
                                <p lang="am" className={step.className}>
                                    {amharic}
                                </p>
                            </div>
                        ))}
                    </div>
                </Section>

                <Section
                    title="Components"
                    description="Buttons, inputs and cards with English and Amharic labels side by side, to check that Ge'ez fits."
                >
                    <div className="grid gap-6 sm:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Brand kit</CardTitle>
                                <CardDescription>
                                    Logos, colours and fonts
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="brand-name">
                                        Brand name
                                    </Label>
                                    <Input
                                        id="brand-name"
                                        placeholder="Ethio Coffee"
                                    />
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button>Save</Button>
                                    <Button variant="outline">
                                        Upload file
                                    </Button>
                                    <Badge>Approved</Badge>
                                </div>
                            </CardContent>
                        </Card>
                        <Card lang="am">
                            <CardHeader>
                                <CardTitle>የብራንድ ኪት</CardTitle>
                                <CardDescription>
                                    አርማዎች፣ ቀለሞች እና ፊደላት
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="brand-name-am">
                                        የብራንድ ስም
                                    </Label>
                                    <Input
                                        id="brand-name-am"
                                        placeholder="ኢትዮ ቡና"
                                    />
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button>አስቀምጥ</Button>
                                    <Button variant="outline">ፋይል ስቀል</Button>
                                    <Badge>ጸድቋል</Badge>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </Section>
            </main>
        </>
    );
}
