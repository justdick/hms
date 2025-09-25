import { Head, router } from '@inertiajs/react'
import { Link } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Clock, User, Building, Activity } from 'lucide-react'
import { useState } from 'react'

interface Patient {
  id: number
  first_name: string
  last_name: string
  date_of_birth: string
  phone_number: string
}

interface Department {
  id: number
  name: string
}

interface VitalSigns {
  temperature: number
  blood_pressure_systolic: number
  blood_pressure_diastolic: number
  heart_rate: number
  respiratory_rate: number
}

interface PatientCheckin {
  id: number
  patient: Patient
  department: Department
  checked_in_at: string
  status: string
  vital_signs?: VitalSigns[]
}

interface ActiveConsultation {
  id: number
  started_at: string
  status: string
  patient_checkin: {
    patient: Pick<Patient, 'id' | 'first_name' | 'last_name'>
    department: Department
  }
}

interface Props {
  awaitingConsultation: PatientCheckin[]
  activeConsultations: ActiveConsultation[]
}

export default function ConsultationIndex({ awaitingConsultation, activeConsultations }: Props) {
  const [selectedPatient, setSelectedPatient] = useState<number | null>(null)
  const [processing, setProcessing] = useState(false)

  const formatTime = (dateString: string) => {
    return new Date(dateString).toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  const getStatusBadge = (status: string) => {
    const variants = {
      'awaiting_consultation': 'secondary',
      'in_progress': 'default',
      'completed': 'outline',
    } as const

    return (
      <Badge variant={variants[status as keyof typeof variants] || 'secondary'}>
        {status.replace('_', ' ').toUpperCase()}
      </Badge>
    )
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

  const startConsultation = (patientCheckinId: number) => {
    setProcessing(true)
    router.post('/consultation', {
      patient_checkin_id: patientCheckinId,
    }, {
      onFinish: () => setProcessing(false)
    })
  }

  return (
    <AppLayout breadcrumbs={[
      { title: 'Consultation', href: '/consultation' }
    ]}>
      <Head title="Consultation Dashboard" />

      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Consultation Dashboard</h1>
          <p className="text-gray-600 mt-2">Manage patient consultations and medical records</p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Patients Awaiting Consultation */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Clock className="h-5 w-5 text-blue-600" />
                Awaiting Consultation ({awaitingConsultation.length})
              </CardTitle>
            </CardHeader>
            <CardContent>
              {awaitingConsultation.length === 0 ? (
                <div className="text-center py-8 text-gray-500">
                  <Activity className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                  <p>No patients awaiting consultation</p>
                </div>
              ) : (
                <div className="space-y-4">
                  {awaitingConsultation.map((checkin) => (
                    <div
                      key={checkin.id}
                      className="border rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer"
                      onClick={() => setSelectedPatient(selectedPatient === checkin.id ? null : checkin.id)}
                    >
                      <div className="flex justify-between items-start mb-2">
                        <div>
                          <h3 className="font-semibold text-gray-900">
                            {checkin.patient.first_name} {checkin.patient.last_name}
                          </h3>
                          <p className="text-sm text-gray-600">
                            Age: {calculateAge(checkin.patient.date_of_birth)} • Phone: {checkin.patient.phone_number}
                          </p>
                        </div>
                        {getStatusBadge(checkin.status)}
                      </div>

                      <div className="flex items-center gap-4 text-sm text-gray-600 mb-3">
                        <div className="flex items-center gap-1">
                          <Building className="h-4 w-4" />
                          {checkin.department.name}
                        </div>
                        <div className="flex items-center gap-1">
                          <Clock className="h-4 w-4" />
                          Checked in: {formatTime(checkin.checked_in_at)}
                        </div>
                      </div>

                      {checkin.vital_signs && checkin.vital_signs.length > 0 && (
                        <div className="bg-green-50 p-2 rounded mb-3">
                          <p className="text-xs text-green-700 font-medium">Vitals taken ✓</p>
                        </div>
                      )}

                      {selectedPatient === checkin.id && (
                        <div className="mt-4 pt-4 border-t">
                          <Button
                            onClick={(e) => {
                              e.stopPropagation()
                              startConsultation(checkin.id)
                            }}
                            disabled={processing}
                            className="w-full"
                          >
                            {processing ? 'Starting...' : 'Start Consultation'}
                          </Button>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Active Consultations */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <User className="h-5 w-5 text-green-600" />
                Active Consultations ({activeConsultations.length})
              </CardTitle>
            </CardHeader>
            <CardContent>
              {activeConsultations.length === 0 ? (
                <div className="text-center py-8 text-gray-500">
                  <User className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                  <p>No active consultations</p>
                </div>
              ) : (
                <div className="space-y-4">
                  {activeConsultations.map((consultation) => (
                    <div key={consultation.id} className="border rounded-lg p-4 hover:shadow-md transition-shadow">
                      <div className="flex justify-between items-start mb-2">
                        <div>
                          <h3 className="font-semibold text-gray-900">
                            {consultation.patient_checkin.patient.first_name} {consultation.patient_checkin.patient.last_name}
                          </h3>
                          <p className="text-sm text-gray-600">
                            {consultation.patient_checkin.department.name}
                          </p>
                        </div>
                        {getStatusBadge(consultation.status)}
                      </div>

                      <div className="flex items-center gap-1 text-sm text-gray-600 mb-3">
                        <Clock className="h-4 w-4" />
                        Started: {formatTime(consultation.started_at)}
                      </div>

                      <Link
                        href={`/consultation/${consultation.id}`}
                        className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2 w-full"
                      >
                        Continue Consultation
                      </Link>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AppLayout>
  )
}