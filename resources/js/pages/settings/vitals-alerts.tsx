import { Head } from '@inertiajs/react';
import { Volume2, VolumeX } from 'lucide-react';
import { useEffect, useState } from 'react';

import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { useSoundAlert, type SoundType } from '@/hooks/use-sound-alert';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editVitalsAlerts } from '@/routes/vitals-alerts';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Vitals alerts settings',
        href: editVitalsAlerts().url,
    },
];

export default function VitalsAlerts() {
    const { settings, playAlert, updateSettings } = useSoundAlert();
    const [localVolume, setLocalVolume] = useState(settings.volume * 100);

    // Sync local volume with settings
    useEffect(() => {
        setLocalVolume(settings.volume * 100);
    }, [settings.volume]);

    const handleVolumeChange = (value: number[]) => {
        setLocalVolume(value[0]);
    };

    const handleVolumeCommit = (value: number[]) => {
        updateSettings({ volume: value[0] / 100 });
    };

    const handleEnabledToggle = (checked: boolean) => {
        updateSettings({ enabled: checked });
    };

    const handleSoundTypeChange = (value: string) => {
        updateSettings({ soundType: value as SoundType });
    };

    const handleTestSound = async () => {
        await playAlert(settings.soundType);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vitals alerts settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Vitals alerts settings"
                        description="Configure sound alerts for scheduled vitals monitoring"
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle>Sound alerts</CardTitle>
                            <CardDescription>
                                Customize how you receive audio notifications
                                when vitals are due or overdue
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Enable/Disable Toggle */}
                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label
                                        htmlFor="sound-enabled"
                                        className="text-base"
                                    >
                                        Enable sound alerts
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        Play audio notifications when vitals are
                                        due
                                    </p>
                                </div>
                                <Switch
                                    id="sound-enabled"
                                    checked={settings.enabled}
                                    onCheckedChange={handleEnabledToggle}
                                />
                            </div>

                            {/* Volume Slider */}
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <Label
                                        htmlFor="volume"
                                        className="text-base"
                                    >
                                        Volume
                                    </Label>
                                    <span className="text-sm text-muted-foreground">
                                        {Math.round(localVolume)}%
                                    </span>
                                </div>
                                <div className="flex items-center gap-4">
                                    <VolumeX className="h-4 w-4 text-muted-foreground" />
                                    <Slider
                                        id="volume"
                                        min={0}
                                        max={100}
                                        step={5}
                                        value={[localVolume]}
                                        onValueChange={handleVolumeChange}
                                        onValueCommit={handleVolumeCommit}
                                        disabled={!settings.enabled}
                                        className="flex-1"
                                    />
                                    <Volume2 className="h-4 w-4 text-muted-foreground" />
                                </div>
                            </div>

                            {/* Sound Type Selector */}
                            <div className="space-y-3">
                                <Label className="text-base">Sound type</Label>
                                <RadioGroup
                                    value={settings.soundType}
                                    onValueChange={handleSoundTypeChange}
                                    disabled={!settings.enabled}
                                >
                                    <div className="flex items-center space-x-2">
                                        <RadioGroupItem
                                            value="gentle"
                                            id="gentle"
                                        />
                                        <Label
                                            htmlFor="gentle"
                                            className="font-normal"
                                        >
                                            Gentle
                                            <span className="ml-2 text-sm text-muted-foreground">
                                                Soft beep for due vitals
                                            </span>
                                        </Label>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <RadioGroupItem
                                            value="urgent"
                                            id="urgent"
                                        />
                                        <Label
                                            htmlFor="urgent"
                                            className="font-normal"
                                        >
                                            Urgent
                                            <span className="ml-2 text-sm text-muted-foreground">
                                                Prominent tone for overdue
                                                vitals
                                            </span>
                                        </Label>
                                    </div>
                                </RadioGroup>
                            </div>

                            {/* Test Button */}
                            <div className="pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleTestSound}
                                    disabled={!settings.enabled}
                                >
                                    Test sound
                                </Button>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Click to preview the selected alert sound
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>About vitals alerts</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm text-muted-foreground">
                            <p>
                                Sound alerts help ensure timely vitals recording
                                for admitted patients. When vitals are due,
                                you'll receive a gentle notification. If vitals
                                become overdue (15 minutes past due time),
                                you'll receive a more urgent alert.
                            </p>
                            <p>
                                Your preferences are saved locally in your
                                browser and will persist across sessions.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
