import { Head, useForm } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { User, Building, Clock, Activity, FileText, TestTube, Pill } from 'lucide-react'
import { useState } from 'react'

interface Patient {
  id: number
  first_name: string
  last_name: string
  date_of_birth: string
  phone_number: string
  email?: string
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

interface Props {
  consultation: Consultation
  labServices: LabService[]
}

export default function ConsultationShow({ consultation, labServices }: Props) {
  const [activeTab, setActiveTab] = useState('soap')

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

  const latestVitals = consultation.patient_checkin.vital_signs?.[0]

  return (
    <AppLayout breadcrumbs={[
      { title: 'Consultation', href: '/consultation' },
      { title: `${consultation.patient_checkin.patient.first_name} ${consultation.patient_checkin.patient.last_name}` }
    ]}>
      <Head title={`Consultation - ${consultation.patient_checkin.patient.first_name} ${consultation.patient_checkin.patient.last_name}`} />

      <div className="space-y-6">
        {/* Header */}
        <div className="mb-6">
          <div className="flex justify-between items-start">
            <div>
              <h1 className="text-3xl font-bold text-gray-900">
                {consultation.patient_checkin.patient.first_name} {consultation.patient_checkin.patient.last_name}
              </h1>
              <p className="text-gray-600 mt-2">
                Age: {calculateAge(consultation.patient_checkin.patient.date_of_birth)} •
                Phone: {consultation.patient_checkin.patient.phone_number}
              </p>
            </div>
            <div className="text-right">
              {getStatusBadge(consultation.status)}
              <p className="text-sm text-gray-600 mt-2">
                Started: {formatDateTime(consultation.started_at)}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-6 mt-4 text-sm text-gray-600">
            <div className="flex items-center gap-2">
              <Building className="h-4 w-4" />
              {consultation.patient_checkin.department.name}
            </div>
            <div className="flex items-center gap-2">
              <User className="h-4 w-4" />
              Dr. {consultation.doctor.name}
            </div>
            <div className="flex items-center gap-2">
              <Clock className="h-4 w-4" />
              Checked in: {formatDateTime(consultation.patient_checkin.checked_in_at)}
            </div>
          </div>
        </div>

        {/* Quick Actions */}
        <div className="mb-6 flex gap-4 justify-between items-center">
          <div className="flex gap-4">
            {consultation.status === 'in_progress' && (
              <Button onClick={completeConsultation} variant="outline">
                Complete Consultation
              </Button>
            )}
          </div>

          <Button
            onClick={() => window.location.href = `/consultation/${consultation.id}/enhanced`}
            variant="default"
            className="bg-blue-600 hover:bg-blue-700 text-white"
          >
            <Activity className="h-4 w-4 mr-2" />
            Try Enhanced UI
          </Button>
        </div>

        <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
          <TabsList className="grid w-full grid-cols-4">
            <TabsTrigger value="soap" className="flex items-center gap-2">
              <FileText className="h-4 w-4" />
              SOAP Notes
            </TabsTrigger>
            <TabsTrigger value="vitals" className="flex items-center gap-2">
              <Activity className="h-4 w-4" />
              Vitals
            </TabsTrigger>
            <TabsTrigger value="diagnosis" className="flex items-center gap-2">
              <FileText className="h-4 w-4" />
              Diagnosis
            </TabsTrigger>
            <TabsTrigger value="orders" className="flex items-center gap-2">
              <TestTube className="h-4 w-4" />
              Lab Orders
            </TabsTrigger>
          </TabsList>

          {/* SOAP Notes Tab */}
          <TabsContent value="soap">
            <Card>
              <CardHeader>
                <CardTitle>SOAP Notes</CardTitle>
              </CardHeader>
              <CardContent>
                <form onSubmit={handleSubmit} className="space-y-6">
                  <div>
                    <Label htmlFor="chief_complaint">Chief Complaint</Label>
                    <Textarea
                      id="chief_complaint"
                      placeholder="Primary reason for the patient's visit..."
                      value={data.chief_complaint}
                      onChange={(e) => setData('chief_complaint', e.target.value)}
                      className="mt-1"
                    />
                  </div>

                  <Separator />

                  <div>
                    <Label htmlFor="subjective_notes">Subjective (S)</Label>
                    <Textarea
                      id="subjective_notes"
                      placeholder="Patient's description of symptoms, history..."
                      value={data.subjective_notes}
                      onChange={(e) => setData('subjective_notes', e.target.value)}
                      className="mt-1"
                      rows={4}
                    />
                  </div>

                  <div>
                    <Label htmlFor="objective_notes">Objective (O)</Label>
                    <Textarea
                      id="objective_notes"
                      placeholder="Physical examination findings, vital signs..."
                      value={data.objective_notes}
                      onChange={(e) => setData('objective_notes', e.target.value)}
                      className="mt-1"
                      rows={4}
                    />
                  </div>

                  <div>
                    <Label htmlFor="assessment_notes">Assessment (A)</Label>
                    <Textarea
                      id="assessment_notes"
                      placeholder="Clinical judgment, differential diagnosis..."
                      value={data.assessment_notes}
                      onChange={(e) => setData('assessment_notes', e.target.value)}
                      className="mt-1"
                      rows={3}
                    />
                  </div>

                  <div>
                    <Label htmlFor="plan_notes">Plan (P)</Label>
                    <Textarea
                      id="plan_notes"
                      placeholder="Treatment plan, medications, follow-up..."
                      value={data.plan_notes}
                      onChange={(e) => setData('plan_notes', e.target.value)}
                      className="mt-1"
                      rows={4}
                    />
                  </div>

                  <div>
                    <Label htmlFor="follow_up_date">Follow-up Date (Optional)</Label>
                    <Input
                      id="follow_up_date"
                      type="date"
                      value={data.follow_up_date}
                      onChange={(e) => setData('follow_up_date', e.target.value)}
                      className="mt-1"
                    />
                  </div>

                  {consultation.status === 'in_progress' && (
                    <Button type="submit" disabled={processing}>
                      {processing ? 'Saving...' : 'Save Notes'}
                    </Button>
                  )}
                </form>
              </CardContent>
            </Card>
          </TabsContent>

          {/* Vitals Tab */}
          <TabsContent value="vitals">
            <Card>
              <CardHeader>
                <CardTitle>Vital Signs</CardTitle>
              </CardHeader>
              <CardContent>
                {latestVitals ? (
                  <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div className="text-center p-4 bg-blue-50 rounded-lg">
                      <p className="text-sm text-gray-600">Temperature</p>
                      <p className="text-2xl font-bold text-blue-600">{latestVitals.temperature}°F</p>
                    </div>
                    <div className="text-center p-4 bg-red-50 rounded-lg">
                      <p className="text-sm text-gray-600">Blood Pressure</p>
                      <p className="text-2xl font-bold text-red-600">
                        {latestVitals.blood_pressure_systolic}/{latestVitals.blood_pressure_diastolic}
                      </p>
                    </div>
                    <div className="text-center p-4 bg-green-50 rounded-lg">
                      <p className="text-sm text-gray-600">Heart Rate</p>
                      <p className="text-2xl font-bold text-green-600">{latestVitals.heart_rate} bpm</p>
                    </div>
                    <div className="text-center p-4 bg-purple-50 rounded-lg">
                      <p className="text-sm text-gray-600">Respiratory Rate</p>
                      <p className="text-2xl font-bold text-purple-600">{latestVitals.respiratory_rate}/min</p>
                    </div>
                    <div className="text-center p-4 bg-gray-50 rounded-lg">
                      <p className="text-sm text-gray-600">Recorded</p>
                      <p className="text-sm font-medium text-gray-700">
                        {formatDateTime(latestVitals.recorded_at)}
                      </p>
                    </div>
                  </div>
                ) : (
                  <div className="text-center py-8 text-gray-500">
                    <Activity className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                    <p>No vital signs recorded</p>
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* Diagnosis Tab */}
          <TabsContent value="diagnosis">
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
                            <h3 className="font-semibold text-gray-900">{diagnosis.diagnosis_description}</h3>
                            <p className="text-sm text-gray-600">ICD Code: {diagnosis.icd_code}</p>
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
          </TabsContent>

          {/* Lab Orders Tab */}
          <TabsContent value="orders">
            <Card>
              <CardHeader>
                <CardTitle>Laboratory Orders</CardTitle>
              </CardHeader>
              <CardContent>
                {consultation.lab_orders.length > 0 ? (
                  <div className="space-y-4">
                    {consultation.lab_orders.map((order) => (
                      <div key={order.id} className="border rounded-lg p-4">
                        <div className="flex justify-between items-start mb-2">
                          <div>
                            <h3 className="font-semibold text-gray-900">{order.lab_service.name}</h3>
                            <p className="text-sm text-gray-600">
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
                        <p className="text-sm text-gray-600">
                          Ordered: {formatDateTime(order.ordered_at)}
                        </p>
                        {order.special_instructions && (
                          <p className="text-sm text-gray-700 mt-2 bg-yellow-50 p-2 rounded">
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
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </AppLayout>
  )
}