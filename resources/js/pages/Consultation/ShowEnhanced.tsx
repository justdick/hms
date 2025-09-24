import { Head, useForm } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { User, Building, Clock, Activity, FileText, TestTube, Pill, Stethoscope } from 'lucide-react'
import { useState } from 'react'

// Import our enhanced components
import VitalsOverview from '@/components/Vitals/VitalsOverview'
import SmartSOAPNotes from '@/components/Consultation/SmartSOAPNotes'
import MedicalHistoryTimeline from '@/components/Patient/MedicalHistoryTimeline'
import QuickActionToolbar from '@/components/Consultation/QuickActionToolbar'
import LabOrderingSystem from '@/components/Lab/LabOrderingSystem'

interface Patient {
  id: number
  first_name: string
  last_name: string
  date_of_birth: string
  phone_number: string
  email?: string
  gender: string
}

interface Department {
  id: number
  name: string
}

interface VitalSigns {
  id: number
  temperature: number
  blood_pressure_systolic: number
  blood_pressure_diastolic: number
  heart_rate: number
  respiratory_rate: number
  recorded_at: string
}

interface Doctor {
  id: number
  name: string
}

interface Diagnosis {
  id: number
  icd_code: string
  diagnosis_description: string
  is_primary: boolean
}

interface Prescription {
  id: number
  medication_name: string
  dosage: string
  frequency: string
  duration: string
  instructions?: string
  status: string
}

interface LabService {
  id: number
  name: string
  code: string
  category: string
  price: number
  sample_type: string
  turnaround_time: string
  description?: string
  preparation_instructions?: string
  normal_range?: string
  clinical_significance?: string
}

interface LabOrder {
  id: number
  lab_service: LabService
  status: string
  priority: string
  special_instructions?: string
  ordered_at: string
}

interface Consultation {
  id: number
  started_at: string
  completed_at?: string
  status: string
  chief_complaint?: string
  subjective_notes?: string
  objective_notes?: string
  assessment_notes?: string
  plan_notes?: string
  follow_up_date?: string
  patient_checkin: {
    id: number
    patient: Patient
    department: Department
    checked_in_at: string
    vital_signs: VitalSigns[]
  }
  doctor: Doctor
  diagnoses: Diagnosis[]
  prescriptions: Prescription[]
  lab_orders: LabOrder[]
}

// Mock data for medical history (would come from backend)
interface PreviousConsultation {
  id: number
  date: string
  department: string
  doctor: string
  chief_complaint: string
  diagnosis: string
  status: string
}

interface Medication {
  id: number
  name: string
  dosage: string
  prescribed_date: string
  status: 'active' | 'discontinued' | 'completed'
  prescribing_doctor: string
}

interface Allergy {
  id: number
  allergen: string
  reaction: string
  severity: 'mild' | 'moderate' | 'severe'
  date_noted: string
}

interface FamilyHistory {
  id: number
  relationship: string
  condition: string
  age_of_onset?: number
  notes?: string
}

interface Props {
  consultation: Consultation
  labServices: LabService[]
  previousConsultations?: PreviousConsultation[]
  medications?: Medication[]
  allergies?: Allergy[]
  familyHistory?: FamilyHistory[]
}

export default function ConsultationShowEnhanced({
  consultation,
  labServices,
  previousConsultations = [],
  medications = [],
  allergies = [],
  familyHistory = []
}: Props) {
  const [activeTab, setActiveTab] = useState('soap')
  const [showLabOrdering, setShowLabOrdering] = useState(false)
  const [showPrescriptionModal, setShowPrescriptionModal] = useState(false)

  const { data, setData, patch, processing } = useForm({
    chief_complaint: consultation.chief_complaint || '',
    subjective_notes: consultation.subjective_notes || '',
    objective_notes: consultation.objective_notes || '',
    assessment_notes: consultation.assessment_notes || '',
    plan_notes: consultation.plan_notes || '',
    follow_up_date: consultation.follow_up_date || '',
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    patch(`/consultation/${consultation.id}`)
  }

  const completeConsultation = () => {
    if (confirm('Are you sure you want to complete this consultation?')) {
      const form = document.createElement('form')
      form.method = 'POST'
      form.action = `/consultation/${consultation.id}/complete`

      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      if (csrfToken) {
        const csrfInput = document.createElement('input')
        csrfInput.type = 'hidden'
        csrfInput.name = '_token'
        csrfInput.value = csrfToken
        form.appendChild(csrfInput)
      }

      document.body.appendChild(form)
      form.submit()
    }
  }

  const handleLabOrderSubmit = (orders: any[], totalCost: number) => {
    console.log('Lab orders submitted:', orders, 'Total cost:', totalCost)
    // Would submit to backend
    setShowLabOrdering(false)
  }

  const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  const calculateAge = (dateOfBirth: string) => {
    const today = new Date()
    const birth = new Date(dateOfBirth)
    let age = today.getFullYear() - birth.getFullYear()
    const monthDiff = today.getMonth() - birth.getMonth()

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
      age--
    }

    return age
  }

  const getStatusBadge = (status: string) => {
    const variants = {
      'in_progress': 'default',
      'completed': 'outline',
      'paused': 'secondary',
    } as const

    return (
      <Badge variant={variants[status as keyof typeof variants] || 'secondary'}>
        {status.replace('_', ' ').toUpperCase()}
      </Badge>
    )
  }

  const patientAge = calculateAge(consultation.patient_checkin.patient.date_of_birth)
  const patientInfo = {
    age: patientAge,
    gender: consultation.patient_checkin.patient.gender,
    conditions: consultation.diagnoses.map(d => d.diagnosis_description)
  }

  return (
    <AppLayout breadcrumbs={[
      { title: 'Consultation', href: '/consultation' },
      { title: `${consultation.patient_checkin.patient.first_name} ${consultation.patient_checkin.patient.last_name}` }
    ]}>
      <Head title={`Consultation - ${consultation.patient_checkin.patient.first_name} ${consultation.patient_checkin.patient.last_name}`} />

      <div className="space-y-6">
        {/* Enhanced Header */}
        <Card>
          <CardContent className="pt-6">
            <div className="flex justify-between items-start">
              <div>
                <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                  {consultation.patient_checkin.patient.first_name} {consultation.patient_checkin.patient.last_name}
                </h1>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4 text-sm text-gray-600 dark:text-gray-400">
                  <div>
                    <p><strong>Age:</strong> {patientAge}</p>
                    <p><strong>Phone:</strong> {consultation.patient_checkin.patient.phone_number}</p>
                    {consultation.patient_checkin.patient.email && (
                      <p><strong>Email:</strong> {consultation.patient_checkin.patient.email}</p>
                    )}
                  </div>
                  <div>
                    <p className="flex items-center gap-2">
                      <Building className="h-4 w-4" />
                      {consultation.patient_checkin.department.name}
                    </p>
                    <p className="flex items-center gap-2">
                      <User className="h-4 w-4" />
                      Dr. {consultation.doctor.name}
                    </p>
                    <p className="flex items-center gap-2">
                      <Clock className="h-4 w-4" />
                      Checked in: {formatDateTime(consultation.patient_checkin.checked_in_at)}
                    </p>
                  </div>
                  <div>
                    <p className="flex items-center gap-2">
                      <Stethoscope className="h-4 w-4" />
                      Started: {formatDateTime(consultation.started_at)}
                    </p>
                    {getStatusBadge(consultation.status)}
                  </div>
                </div>
              </div>
            </div>

            {/* Alert for known allergies */}
            {allergies.length > 0 && (
              <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div className="flex items-center gap-2 text-red-700 font-medium mb-2">
                  <Activity className="h-4 w-4" />
                  Known Allergies
                </div>
                <div className="flex flex-wrap gap-2">
                  {allergies.map((allergy) => (
                    <Badge key={allergy.id} variant="destructive">
                      {allergy.allergen} ({allergy.reaction})
                    </Badge>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Quick Action Toolbar */}
        <QuickActionToolbar
          onPrescriptionClick={() => setShowPrescriptionModal(true)}
          onLabOrderClick={() => setShowLabOrdering(true)}
          onCompleteConsultationClick={completeConsultation}
          consultationStatus={consultation.status}
          pendingPrescriptions={consultation.prescriptions.length}
          pendingLabOrders={consultation.lab_orders.length}
        />

        {/* Enhanced Tabbed Interface */}
        <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
          <TabsList className="grid w-full grid-cols-5">
            <TabsTrigger value="soap" className="flex items-center gap-2">
              <FileText className="h-4 w-4" />
              SOAP Notes
            </TabsTrigger>
            <TabsTrigger value="vitals" className="flex items-center gap-2">
              <Activity className="h-4 w-4" />
              Vitals
            </TabsTrigger>
            <TabsTrigger value="history" className="flex items-center gap-2">
              <Clock className="h-4 w-4" />
              History
            </TabsTrigger>
            <TabsTrigger value="diagnosis" className="flex items-center gap-2">
              <FileText className="h-4 w-4" />
              Diagnosis & Orders
            </TabsTrigger>
            <TabsTrigger value="prescriptions" className="flex items-center gap-2">
              <Pill className="h-4 w-4" />
              Prescriptions
            </TabsTrigger>
          </TabsList>

          {/* Smart SOAP Notes Tab */}
          <TabsContent value="soap">
            <SmartSOAPNotes
              initialData={data}
              onDataChange={setData}
              onSubmit={handleSubmit}
              processing={processing}
              status={consultation.status}
              previousNotes={previousConsultations.map(c => ({
                date: c.date,
                chief_complaint: c.chief_complaint,
                assessment_notes: c.diagnosis
              }))}
            />
          </TabsContent>

          {/* Enhanced Vitals Tab */}
          <TabsContent value="vitals">
            <VitalsOverview
              vitals={consultation.patient_checkin.vital_signs}
              showAddButton={consultation.status === 'in_progress'}
              onAddVitals={() => console.log('Add vitals clicked')}
            />
          </TabsContent>

          {/* Medical History Timeline Tab */}
          <TabsContent value="history">
            <MedicalHistoryTimeline
              patientId={consultation.patient_checkin.patient.id}
              consultations={previousConsultations}
              medications={medications}
              allergies={allergies}
              familyHistory={familyHistory}
            />
          </TabsContent>

          {/* Diagnosis and Orders Tab */}
          <TabsContent value="diagnosis">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* Diagnoses */}
              <Card>
                <CardHeader>
                  <CardTitle>Diagnoses</CardTitle>
                </CardHeader>
                <CardContent>
                  {consultation.diagnoses.length > 0 ? (
                    <div className="space-y-4">
                      {consultation.diagnoses.map((diagnosis) => (
                        <div key={diagnosis.id} className="border rounded-lg p-4">
                          <div className="flex justify-between items-start mb-2">
                            <div>
                              <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                {diagnosis.diagnosis_description}
                              </h3>
                              <p className="text-sm text-gray-600 dark:text-gray-400">
                                ICD Code: {diagnosis.icd_code}
                              </p>
                            </div>
                            {diagnosis.is_primary && (
                              <Badge variant="default">Primary</Badge>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="text-center py-8 text-gray-500">
                      <FileText className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                      <p>No diagnoses recorded</p>
                    </div>
                  )}
                </CardContent>
              </Card>

              {/* Lab Orders */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center justify-between">
                    Laboratory Orders
                    <Button
                      onClick={() => setShowLabOrdering(true)}
                      size="sm"
                      disabled={consultation.status !== 'in_progress'}
                    >
                      <TestTube className="h-4 w-4 mr-2" />
                      Order Labs
                    </Button>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  {consultation.lab_orders.length > 0 ? (
                    <div className="space-y-4">
                      {consultation.lab_orders.map((order) => (
                        <div key={order.id} className="border rounded-lg p-4">
                          <div className="flex justify-between items-start mb-2">
                            <div>
                              <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                {order.lab_service.name}
                              </h3>
                              <p className="text-sm text-gray-600 dark:text-gray-400">
                                {order.lab_service.code} • {order.lab_service.category} • ${order.lab_service.price}
                              </p>
                            </div>
                            <div className="text-right">
                              <Badge variant={order.status === 'completed' ? 'outline' : 'default'}>
                                {order.status.toUpperCase()}
                              </Badge>
                              {order.priority !== 'routine' && (
                                <Badge variant="destructive" className="ml-2">
                                  {order.priority.toUpperCase()}
                                </Badge>
                              )}
                            </div>
                          </div>
                          <p className="text-sm text-gray-600 dark:text-gray-400">
                            Ordered: {formatDateTime(order.ordered_at)}
                          </p>
                          {order.special_instructions && (
                            <p className="text-sm text-gray-700 dark:text-gray-300 mt-2 bg-yellow-50 dark:bg-yellow-900/30 p-2 rounded">
                              <strong>Instructions:</strong> {order.special_instructions}
                            </p>
                          )}
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="text-center py-8 text-gray-500">
                      <TestTube className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                      <p>No lab orders placed</p>
                      {consultation.status === 'in_progress' && (
                        <Button
                          onClick={() => setShowLabOrdering(true)}
                          className="mt-4"
                        >
                          <TestTube className="h-4 w-4 mr-2" />
                          Order First Lab
                        </Button>
                      )}
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>
          </TabsContent>

          {/* Prescriptions Tab */}
          <TabsContent value="prescriptions">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center justify-between">
                  Prescriptions
                  <Button
                    onClick={() => setShowPrescriptionModal(true)}
                    size="sm"
                    disabled={consultation.status !== 'in_progress'}
                  >
                    <Pill className="h-4 w-4 mr-2" />
                    New Prescription
                  </Button>
                </CardTitle>
              </CardHeader>
              <CardContent>
                {consultation.prescriptions.length > 0 ? (
                  <div className="space-y-4">
                    {consultation.prescriptions.map((prescription) => (
                      <div key={prescription.id} className="border rounded-lg p-4">
                        <div className="flex justify-between items-start mb-2">
                          <div>
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                              {prescription.medication_name}
                            </h3>
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                              {prescription.dosage} • {prescription.frequency} • {prescription.duration}
                            </p>
                          </div>
                          <Badge variant={prescription.status === 'active' ? 'default' : 'outline'}>
                            {prescription.status.toUpperCase()}
                          </Badge>
                        </div>
                        {prescription.instructions && (
                          <p className="text-sm text-gray-700 dark:text-gray-300 mt-2 bg-blue-50 dark:bg-blue-900/30 p-2 rounded">
                            <strong>Instructions:</strong> {prescription.instructions}
                          </p>
                        )}
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-8 text-gray-500">
                    <Pill className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                    <p>No prescriptions written</p>
                    {consultation.status === 'in_progress' && (
                      <Button
                        onClick={() => setShowPrescriptionModal(true)}
                        className="mt-4"
                      >
                        <Pill className="h-4 w-4 mr-2" />
                        Write First Prescription
                      </Button>
                    )}
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>

        {/* Lab Ordering Modal */}
        {showLabOrdering && (
          <LabOrderingSystem
            labServices={labServices}
            onOrderSubmit={handleLabOrderSubmit}
            onClose={() => setShowLabOrdering(false)}
            patientInfo={patientInfo}
          />
        )}
      </div>
    </AppLayout>
  )
}