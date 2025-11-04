import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import PlanSetupWizard from './components/PlanSetupWizard';

interface InsuranceProvider {
    id: number;
    name: string;
    code: string;
}

interface Props {
    providers: { data: InsuranceProvider[] } | InsuranceProvider[];
}

export default function CreateWithWizard({ providers }: Props) {
    // Handle both array and resource collection formats
    const providersList = Array.isArray(providers) ? providers : providers.data;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Insurance Plans', href: '/admin/insurance/plans' },
                { title: 'Create Plan', href: '' },
            ]}
        >
            <Head title="Create Insurance Plan" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6">
                    <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
                        Create Insurance Plan
                    </h1>
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Set up a new insurance plan with coverage rules in just a few steps
                    </p>
                </div>

                <PlanSetupWizard providers={providersList} />
            </div>
        </AppLayout>
    );
}
