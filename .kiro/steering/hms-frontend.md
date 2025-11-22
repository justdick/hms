---
inclusion: manual
---

# HMS Frontend Development Guide

## Tech Stack

- **React 19** - UI library
- **Inertia.js v2** - SPA framework (no API needed)
- **TypeScript** - Type safety
- **Tailwind CSS v4** - Styling
- **Shadcn/ui** - Component library
- **Laravel Wayfinder** - Type-safe routing
- **Lucide React** - Icons
- **Sonner** - Toast notifications

## Project Structure

```
resources/js/
├── app.tsx                            # Inertia app setup
├── bootstrap.ts                       # Axios/Echo setup
├── pages/                             # Inertia pages (routes)
│   ├── Auth/
│   │   ├── Login.tsx
│   │   └── Register.tsx
│   ├── Dashboard.tsx
│   ├── Checkin/
│   │   └── Index.tsx
│   ├── Consultation/
│   │   ├── Index.tsx                  # Doctor dashboard
│   │   └── Show.tsx                   # Consultation interface
│   ├── Ward/
│   │   ├── Index.tsx                  # Ward list
│   │   ├── Create.tsx
│   │   ├── Edit.tsx
│   │   └── Show.tsx                   # Ward details
│   ├── Pharmacy/
│   │   ├── Drugs/
│   │   └── Dispensing/
│   ├── Lab/
│   │   ├── Services/
│   │   └── Orders/
│   └── Insurance/
│       ├── Providers/
│       ├── Plans/
│       ├── Coverage/
│       └── Claims/
├── components/                        # Reusable components
│   ├── Patient/
│   │   ├── SearchForm.tsx
│   │   ├── RegistrationForm.tsx
│   │   └── PatientCard.tsx
│   ├── Checkin/
│   │   ├── TodaysList.tsx
│   │   ├── CheckinModal.tsx
│   │   └── VitalsModal.tsx
│   ├── Consultation/
│   │   ├── SOAPNotes.tsx
│   │   ├── DiagnosisForm.tsx
│   │   └── LabOrderForm.tsx
│   ├── ui/                            # Shadcn/ui components
│   │   ├── button.tsx
│   │   ├── card.tsx
│   │   ├── dialog.tsx
│   │   ├── form.tsx
│   │   ├── input.tsx
│   │   ├── select.tsx
│   │   ├── sheet.tsx
│   │   ├── table.tsx
│   │   └── ...
│   └── layouts/
│       ├── AppLayout.tsx              # Main app layout
│       └── GuestLayout.tsx            # Auth pages layout
├── lib/
│   └── utils.ts                       # Utility functions
└── types/
    ├── index.d.ts                     # Global types
    └── models.ts                      # Model types
```

## Inertia.js Patterns

### Page Components

Inertia pages receive props from Laravel controllers:

```tsx
// resources/js/pages/Consultation/Show.tsx
import { Head } from '@inertiajs/react'
import AppLayout from '@/components/layouts/AppLayout'

interface Props {
    consultation: Consultation
    patient: Patient
    vitalSigns: VitalSign[]
    diagnoses: Diagnosis[]
}

export default function Show({ consultation, patient, vitalSigns, diagnoses }: Props) {
    return (
        <AppLayout>
            <Head title={`Consultation - ${patient.first_name} ${patient.last_name}`} />
            
            <div className="container mx-auto py-6">
                <h1 className="text-2xl font-bold mb-6">
                    Consultation for {patient.first_name} {patient.last_name}
                </h1>
                
                {/* Component content */}
            </div>
        </AppLayout>
    )
}
```

### Navigation

Use Inertia's `Link` component or `router` for navigation:

```tsx
import { Link, router } from '@inertiajs/react'

// Link component (declarative)
<Link href="/consultation/1" className="text-blue-600 hover:underline">
    View Consultation
</Link>

// Router (programmatic)
const handleClick = () => {
    router.visit('/consultation/1')
}

// With data
const handleSubmit = () => {
    router.post('/consultation/start', {
        patient_checkin_id: checkin.id
    })
}
```

### Form Handling

#### Multi-Field Forms (Use `<Form>` Component)

```tsx
import { Form } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export default function PatientRegistrationForm() {
    return (
        <Form action="/patients" method="post">
            {({ errors, processing, wasSuccessful }) => (
                <div className="space-y-4">
                    <div>
                        <Label htmlFor="first_name">First Name</Label>
                        <Input 
                            id="first_name"
                            name="first_name" 
                            required 
                        />
                        {errors.first_name && (
                            <p className="text-sm text-red-600 mt-1">
                                {errors.first_name}
                            </p>
                        )}
                    </div>

                    <div>
                        <Label htmlFor="last_name">Last Name</Label>
                        <Input 
                            id="last_name"
                            name="last_name" 
                            required 
                        />
                        {errors.last_name && (
                            <p className="text-sm text-red-600 mt-1">
                                {errors.last_name}
                            </p>
                        )}
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing ? 'Registering...' : 'Register Patient'}
                    </Button>

                    {wasSuccessful && (
                        <p className="text-sm text-green-600">
                            Patient registered successfully!
                        </p>
                    )}
                </div>
            )}
        </Form>
    )
}
```

#### Simple Actions (Use `router.post()`)

```tsx
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'

export default function StartConsultationButton({ checkinId }: { checkinId: number }) {
    const handleStart = () => {
        router.post('/consultation/start', {
            patient_checkin_id: checkinId
        })
    }

    return (
        <Button onClick={handleStart}>
            Start Consultation
        </Button>
    )
}
```

### Flash Messages

Display success/error messages from Laravel:

```tsx
import { usePage } from '@inertiajs/react'
import { useEffect } from 'react'
import { toast } from 'sonner'

export default function AppLayout({ children }: { children: React.ReactNode }) {
    const { flash } = usePage().props

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success)
        }
        if (flash.error) {
            toast.error(flash.error)
        }
    }, [flash])

    return (
        <div>
            {children}
        </div>
    )
}
```

## Component Patterns

### Reusable Form Components

Extract common form patterns:

```tsx
// components/Patient/SearchForm.tsx
import { useState } from 'react'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'

interface Props {
    onSearch: (query: string) => void
    isLoading?: boolean
}

export default function PatientSearchForm({ onSearch, isLoading }: Props) {
    const [query, setQuery] = useState('')

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()
        onSearch(query)
    }

    return (
        <form onSubmit={handleSubmit} className="flex gap-2">
            <Input
                type="text"
                placeholder="Search by name, phone, or patient number..."
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                className="flex-1"
            />
            <Button type="submit" disabled={isLoading}>
                {isLoading ? 'Searching...' : 'Search'}
            </Button>
        </form>
    )
}
```

### Modal/Dialog Patterns

Use Shadcn's Dialog or Sheet components:

```tsx
// components/Checkin/VitalsModal.tsx
import { useState } from 'react'
import { Form } from '@inertiajs/react'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface Props {
    checkinId: number
}

export default function VitalsModal({ checkinId }: Props) {
    const [open, setOpen] = useState(false)

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>Record Vitals</Button>
            </DialogTrigger>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Record Vital Signs</DialogTitle>
                </DialogHeader>
                
                <Form 
                    action={`/vitals/${checkinId}`} 
                    method="post"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="blood_pressure_systolic">
                                    Blood Pressure (Systolic)
                                </Label>
                                <Input
                                    id="blood_pressure_systolic"
                                    name="blood_pressure_systolic"
                                    type="number"
                                    placeholder="120"
                                />
                                {errors.blood_pressure_systolic && (
                                    <p className="text-sm text-red-600 mt-1">
                                        {errors.blood_pressure_systolic}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="blood_pressure_diastolic">
                                    Blood Pressure (Diastolic)
                                </Label>
                                <Input
                                    id="blood_pressure_diastolic"
                                    name="blood_pressure_diastolic"
                                    type="number"
                                    placeholder="80"
                                />
                            </div>

                            {/* More vital fields... */}

                            <div className="col-span-2">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Vitals'}
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    )
}
```

### Slide-Over Panel Pattern

For quick actions without navigation:

```tsx
// components/Insurance/ClaimVettingPanel.tsx
import { Form } from '@inertiajs/react'
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'

interface Props {
    claim: InsuranceClaim
    open: boolean
    onOpenChange: (open: boolean) => void
}

export default function ClaimVettingPanel({ claim, open, onOpenChange }: Props) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full sm:max-w-2xl overflow-y-auto">
                <SheetHeader>
                    <SheetTitle>Review Claim {claim.claim_check_code}</SheetTitle>
                </SheetHeader>

                <div className="space-y-6 py-4">
                    {/* Claim details */}
                    <div>
                        <h3 className="font-semibold mb-2">Patient Information</h3>
                        <p>{claim.patient_surname} {claim.patient_other_names}</p>
                        <p className="text-sm text-gray-600">
                            Membership: {claim.membership_id}
                        </p>
                    </div>

                    {/* Claim items table */}
                    <div>
                        <h3 className="font-semibold mb-2">Claim Items</h3>
                        {/* Table component */}
                    </div>

                    {/* Actions */}
                    <Form 
                        action={`/insurance/claims/${claim.id}/vet`} 
                        method="post"
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ errors, processing }) => (
                            <div className="space-y-4">
                                <div>
                                    <Label htmlFor="notes">Notes</Label>
                                    <Textarea
                                        id="notes"
                                        name="notes"
                                        placeholder="Add any notes..."
                                    />
                                </div>

                                <div className="flex gap-2">
                                    <Button 
                                        type="submit" 
                                        name="action" 
                                        value="approve"
                                        disabled={processing}
                                    >
                                        Approve Claim
                                    </Button>
                                    <Button 
                                        type="submit" 
                                        name="action" 
                                        value="reject"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        Reject Claim
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Form>
                </div>
            </SheetContent>
        </Sheet>
    )
}
```

## Styling with Tailwind CSS v4

### Utility-First Approach

Use Tailwind utilities for styling:

```tsx
<div className="container mx-auto py-6">
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <Card className="hover:shadow-lg transition-shadow">
            <CardHeader>
                <CardTitle className="text-lg font-semibold">
                    General OPD
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-sm text-gray-600">
                    20 patients waiting
                </p>
            </CardContent>
        </Card>
    </div>
</div>
```

### Responsive Design

Use responsive prefixes:

```tsx
<div className="
    flex flex-col          // Mobile: stack vertically
    md:flex-row            // Tablet+: horizontal layout
    gap-4                  // Consistent spacing
    p-4 md:p-6 lg:p-8      // Responsive padding
">
    <div className="w-full md:w-1/2 lg:w-1/3">
        {/* Content */}
    </div>
</div>
```

### Dark Mode Support

Use `dark:` prefix for dark mode styles:

```tsx
<div className="
    bg-white dark:bg-gray-800
    text-gray-900 dark:text-gray-100
    border border-gray-200 dark:border-gray-700
">
    {/* Content */}
</div>
```

## TypeScript Types

### Define Model Types

```typescript
// types/models.ts
export interface Patient {
    id: number
    patient_number: string
    first_name: string
    last_name: string
    gender: 'male' | 'female' | 'other'
    date_of_birth: string
    phone_number: string
    address: string
    status: 'active' | 'inactive' | 'deceased'
    created_at: string
    updated_at: string
}

export interface PatientCheckin {
    id: number
    patient_id: number
    department_id: number
    checked_in_at: string
    status: 'checked_in' | 'vitals_taken' | 'awaiting_consultation' | 'in_consultation' | 'completed' | 'admitted'
    patient?: Patient
    department?: Department
    vitalSigns?: VitalSign[]
}

export interface Consultation {
    id: number
    patient_checkin_id: number
    doctor_id: number
    started_at: string
    completed_at: string | null
    status: 'in_progress' | 'completed'
    presenting_complaint: string
    history_presenting_complaint: string
    on_direct_questioning: string
    examination_findings: string
    assessment_notes: string
    plan_notes: string
    patientCheckin?: PatientCheckin
    doctor?: User
    diagnoses?: Diagnosis[]
    prescriptions?: Prescription[]
    labOrders?: LabOrder[]
}
```

### Page Props Type

```typescript
// types/index.d.ts
import { PageProps as InertiaPageProps } from '@inertiajs/core'
import { User } from './models'

export interface PageProps extends InertiaPageProps {
    auth: {
        user: User
    }
    flash: {
        success?: string
        error?: string
    }
}
```

## State Management

### Local State (useState)

For component-specific state:

```tsx
import { useState } from 'react'

export default function PatientSearch() {
    const [query, setQuery] = useState('')
    const [results, setResults] = useState<Patient[]>([])
    const [isLoading, setIsLoading] = useState(false)

    const handleSearch = async () => {
        setIsLoading(true)
        // Search logic
        setIsLoading(false)
    }

    return (
        // Component JSX
    )
}
```

### Shared State (Inertia Props)

For data from Laravel:

```tsx
import { usePage } from '@inertiajs/react'
import { PageProps } from '@/types'

export default function Dashboard() {
    const { auth } = usePage<PageProps>().props
    
    return (
        <div>
            <p>Welcome, {auth.user.name}!</p>
        </div>
    )
}
```

## Performance Optimization

### Lazy Loading

Use React.lazy for code splitting:

```tsx
import { lazy, Suspense } from 'react'

const HeavyComponent = lazy(() => import('@/components/HeavyComponent'))

export default function Page() {
    return (
        <Suspense fallback={<div>Loading...</div>}>
            <HeavyComponent />
        </Suspense>
    )
}
```

### Memoization

Use useMemo for expensive calculations:

```tsx
import { useMemo } from 'react'

export default function PatientList({ patients }: { patients: Patient[] }) {
    const sortedPatients = useMemo(() => {
        return [...patients].sort((a, b) => 
            a.last_name.localeCompare(b.last_name)
        )
    }, [patients])

    return (
        // Render sorted patients
    )
}
```

## Accessibility

### Semantic HTML

Use proper HTML elements:

```tsx
<nav aria-label="Main navigation">
    <ul>
        <li><Link href="/dashboard">Dashboard</Link></li>
        <li><Link href="/patients">Patients</Link></li>
    </ul>
</nav>

<main>
    <h1>Page Title</h1>
    {/* Content */}
</main>
```

### ARIA Labels

Add labels for screen readers:

```tsx
<Button 
    aria-label="Close dialog"
    onClick={onClose}
>
    <X className="h-4 w-4" />
</Button>

<Input
    type="search"
    aria-label="Search patients"
    placeholder="Search..."
/>
```

### Keyboard Navigation

Ensure keyboard accessibility:

```tsx
<div
    role="button"
    tabIndex={0}
    onClick={handleClick}
    onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            handleClick()
        }
    }}
>
    Click me
</div>
```

## Common Patterns

### Loading States

```tsx
import { Loader2 } from 'lucide-react'

{isLoading ? (
    <div className="flex items-center justify-center py-8">
        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
    </div>
) : (
    <div>{/* Content */}</div>
)}
```

### Empty States

```tsx
import { FileQuestion } from 'lucide-react'

{patients.length === 0 ? (
    <div className="text-center py-12">
        <FileQuestion className="h-12 w-12 text-gray-400 mx-auto mb-4" />
        <h3 className="text-lg font-semibold text-gray-900 mb-2">
            No patients found
        </h3>
        <p className="text-gray-600">
            Try adjusting your search criteria
        </p>
    </div>
) : (
    <div>{/* Patient list */}</div>
)}
```

### Error Boundaries

```tsx
import { Component, ReactNode } from 'react'

interface Props {
    children: ReactNode
}

interface State {
    hasError: boolean
}

export class ErrorBoundary extends Component<Props, State> {
    constructor(props: Props) {
        super(props)
        this.state = { hasError: false }
    }

    static getDerivedStateFromError() {
        return { hasError: true }
    }

    render() {
        if (this.state.hasError) {
            return (
                <div className="text-center py-12">
                    <h2 className="text-xl font-semibold text-red-600 mb-2">
                        Something went wrong
                    </h2>
                    <Button onClick={() => window.location.reload()}>
                        Reload Page
                    </Button>
                </div>
            )
        }

        return this.props.children
    }
}
```

## Testing

### Component Testing

```tsx
import { render, screen } from '@testing-library/react'
import { describe, it, expect } from 'vitest'
import PatientCard from '@/components/Patient/PatientCard'

describe('PatientCard', () => {
    it('renders patient information', () => {
        const patient = {
            id: 1,
            first_name: 'John',
            last_name: 'Doe',
            patient_number: 'PAT2025000001',
        }

        render(<PatientCard patient={patient} />)

        expect(screen.getByText('John Doe')).toBeInTheDocument()
        expect(screen.getByText('PAT2025000001')).toBeInTheDocument()
    })
})
```

## Best Practices

### 1. Component Organization
- One component per file
- Co-locate related components
- Extract reusable logic into hooks

### 2. Props Destructuring
```tsx
// ✅ Good
export default function PatientCard({ patient, onSelect }: Props) {
    return <div>{patient.name}</div>
}

// ❌ Bad
export default function PatientCard(props: Props) {
    return <div>{props.patient.name}</div>
}
```

### 3. Conditional Rendering
```tsx
// ✅ Good - Early return
if (!patient) {
    return <div>Loading...</div>
}

return <div>{patient.name}</div>

// ❌ Bad - Nested ternaries
return patient ? <div>{patient.name}</div> : <div>Loading...</div>
```

### 4. Event Handlers
```tsx
// ✅ Good - Named function
const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    // Logic
}

return <form onSubmit={handleSubmit}>...</form>

// ❌ Bad - Inline arrow function
return <form onSubmit={(e) => { /* logic */ }}>...</form>
```

### 5. Type Safety
```tsx
// ✅ Good - Explicit types
interface Props {
    patient: Patient
    onSelect: (id: number) => void
}

// ❌ Bad - Any types
interface Props {
    patient: any
    onSelect: any
}
```

---

**Remember**: Keep components small, focused, and reusable. Use TypeScript for type safety and Tailwind for consistent styling.
