import { forwardRef } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    date_of_birth: string;
    gender: string;
}

interface HospitalInfo {
    name: string;
    logo_url?: string;
}

interface ResultValue {
    value: string | number | boolean;
    unit?: string;
    range?: string;
    flag?: 'normal' | 'high' | 'low';
}

interface LabResult {
    id: number;
    test_name: string;
    test_code: string;
    category: string;
    result_values?: Record<string, ResultValue | string | number | boolean>;
    result_notes?: string;
}

interface PrintableLabResultProps {
    hospital: HospitalInfo;
    patient: Patient;
    results: LabResult[];
    printDate?: string;
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function calculateAge(dob: string): number {
    const today = new Date();
    const birthDate = new Date(dob);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
}

function getFlagBadge(flag?: string): React.ReactNode {
    if (!flag || flag === 'normal') return null;

    const styles = {
        high: 'bg-red-100 text-red-800 border-red-300',
        low: 'bg-blue-100 text-blue-800 border-blue-300',
    };

    const labels = { high: 'HIGH', low: 'LOW' };

    return (
        <span className={cn('ml-2 px-1.5 py-0.5 text-xs font-bold border rounded', styles[flag as keyof typeof styles])}>
            {labels[flag as keyof typeof labels]}
        </span>
    );
}

export const PrintableLabResult = forwardRef<HTMLDivElement, PrintableLabResultProps>(
    ({ hospital, patient, results, printDate }, ref) => {
        const currentDate = printDate || new Date().toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });

        // Use createPortal to render directly into body, outside the React app container
        return createPortal(
            <div
                ref={ref}
                className="print-container bg-white text-black"
                style={{
                    width: '210mm',
                    minHeight: '297mm',
                    fontFamily: 'Arial, sans-serif',
                    fontSize: '12px',
                    lineHeight: '1.5',
                    padding: '15mm 20mm',
                    boxSizing: 'border-box',
                }}
            >
                {/* Hospital Letterhead */}
                <header className="mb-6 border-b-2 border-gray-800 pb-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            {hospital.logo_url && (
                                <img
                                    src={hospital.logo_url}
                                    alt="Hospital Logo"
                                    className="h-16 w-auto object-contain"
                                />
                            )}
                            <h1 className="text-2xl font-bold text-gray-900">
                                {hospital.name}
                            </h1>
                        </div>
                        <div className="text-right">
                            <p className="text-lg font-semibold">Laboratory Report</p>
                            <p className="text-sm text-gray-600">Printed: {currentDate}</p>
                        </div>
                    </div>
                </header>

                {/* Patient Information - Simplified */}
                <section className="mb-6 rounded border border-gray-300 p-4">
                    <h2 className="mb-3 text-sm font-bold uppercase text-gray-700">Patient Information</h2>
                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <span className="text-xs text-gray-500">Name:</span>
                            <p className="font-semibold">{patient.first_name} {patient.last_name}</p>
                        </div>
                        <div>
                            <span className="text-xs text-gray-500">Age:</span>
                            <p className="font-semibold">{calculateAge(patient.date_of_birth)} years</p>
                        </div>
                        <div>
                            <span className="text-xs text-gray-500">Gender:</span>
                            <p className="font-semibold capitalize">{patient.gender}</p>
                        </div>
                    </div>
                </section>

                {/* Lab Results */}
                <section>
                    <h2 className="mb-3 text-sm font-bold uppercase text-gray-700">Test Results</h2>

                    {results.map((result) => (
                        <div key={result.id} className="mb-4 rounded border border-gray-300">
                            {/* Test Header */}
                            <div className="border-b border-gray-200 bg-gray-50 px-4 py-2">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <span className="font-bold">{result.test_name}</span>
                                        <span className="ml-2 text-xs text-gray-500">({result.test_code})</span>
                                    </div>
                                    <span className="rounded bg-gray-200 px-2 py-0.5 text-xs text-gray-700">
                                        {result.category}
                                    </span>
                                </div>
                            </div>

                            {/* Results Table */}
                            <div className="p-4">
                                {result.result_values && Object.keys(result.result_values).length > 0 ? (
                                    <table className="w-full border-collapse text-sm">
                                        <thead>
                                            <tr className="border-b border-gray-200 text-left">
                                                <th className="py-2 pr-4 font-semibold text-gray-700">Parameter</th>
                                                <th className="py-2 pr-4 font-semibold text-gray-700">Result</th>
                                                <th className="py-2 pr-4 font-semibold text-gray-700">Unit</th>
                                                <th className="py-2 font-semibold text-gray-700">Reference Range</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {Object.entries(result.result_values).map(([paramName, paramValue]) => {
                                                // Handle both structured and unstructured results
                                                const isStructured = typeof paramValue === 'object' && paramValue !== null && 'value' in paramValue;
                                                const value = isStructured ? (paramValue as ResultValue).value : paramValue;
                                                const unit = isStructured ? (paramValue as ResultValue).unit : '';
                                                const range = isStructured ? (paramValue as ResultValue).range : '';
                                                const flag = isStructured ? (paramValue as ResultValue).flag : undefined;

                                                return (
                                                    <tr key={paramName} className="border-b border-gray-100">
                                                        <td className="py-2 pr-4 capitalize">
                                                            {paramName.replace(/_/g, ' ')}
                                                        </td>
                                                        <td className={cn(
                                                            'py-2 pr-4 font-medium',
                                                            flag === 'high' && 'text-red-700',
                                                            flag === 'low' && 'text-blue-700'
                                                        )}>
                                                            {String(value)}
                                                            {getFlagBadge(flag)}
                                                        </td>
                                                        <td className="py-2 pr-4 text-gray-600">{unit}</td>
                                                        <td className="py-2 text-gray-600">{range}</td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                ) : (
                                    <p className="text-gray-500 italic">No results entered</p>
                                )}

                                {/* Result Notes */}
                                {result.result_notes && (
                                    <div className="mt-3 border-t border-gray-200 pt-3">
                                        <p className="text-xs font-semibold text-gray-600">Notes:</p>
                                        <p className="mt-1 text-sm text-gray-700">{result.result_notes}</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </section>

                {/* Footer */}
                <footer className="mt-8 border-t border-gray-300 pt-4">
                    <div className="flex items-center justify-between text-xs text-gray-500">
                        <p>This report is computer generated and does not require a signature.</p>
                        <p>Page 1 of 1</p>
                    </div>
                </footer>

                {/* Note: Print styles are defined globally in app.css */}
            </div>,
            document.body
        );
    }
);

PrintableLabResult.displayName = 'PrintableLabResult';

export default PrintableLabResult;
