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
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { User, Building, Clock, Activity, FileText, TestTube, Pill, History, Plus, Trash2, Search, X, UserPlus } from 'lucide-react'
import { useState, useRef, useEffect } from 'react'

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
  patientHistory?: {
    previousConsultations: Consultation[]
    previousPrescriptions: Prescription[]
    allergies: string[]
  }
}

export default function ConsultationShow({ consultation, labServices, patientHistory }: Props) {
  const [activeTab, setActiveTab] = useState('soap')

  const { data, setData, patch, processing } = useForm({
    chief_complaint: consultation.chief_complaint || '',
    subjective_notes: consultation.subjective_notes || '',
    objective_notes: consultation.objective_notes || '',
    assessment_notes: consultation.assessment_notes || '',
    plan_notes: consultation.plan_notes || '',
    follow_up_date: consultation.follow_up_date || '',
  })

  const { data: prescriptionData, setData: setPrescriptionData, post: postPrescription, processing: prescriptionProcessing, reset: resetPrescription } = useForm({
    medication_name: '',
    dosage: '',
    frequency: '',
    duration: '',
    instructions: '',
  })

  const { data: diagnosisData, setData: setDiagnosisData, post: postDiagnosis, processing: diagnosisProcessing, reset: resetDiagnosis } = useForm({
    icd_code: '',
    diagnosis_description: '',
    is_primary: false,
  })

  const [icdSearchResults, setIcdSearchResults] = useState<{code: string, description: string}[]>([])
  const [showIcdResults, setShowIcdResults] = useState(false)
  const [icdSearchQuery, setIcdSearchQuery] = useState('')
  const searchTimeout = useRef<NodeJS.Timeout | null>(null)

  const [availableWards, setAvailableWards] = useState<any[]>([])
  const [showAdmissionModal, setShowAdmissionModal] = useState(false)

  const { data: admissionData, setData: setAdmissionData, post: postAdmission, processing: admissionProcessing, reset: resetAdmission } = useForm({
    ward_id: '',
    admission_reason: '',
    admission_notes: '',
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    patch(`/consultation/${consultation.id}`)
  }

  const handlePrescriptionSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    postPrescription(`/consultation/${consultation.id}/prescriptions`, {
      onSuccess: () => {
        resetPrescription()
      }
    })
  }

  const handleDiagnosisSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    postDiagnosis(`/consultation/${consultation.id}/diagnoses`, {
      onSuccess: () => {
        resetDiagnosis()
        setIcdSearchQuery('')
        setShowIcdResults(false)
      }
    })
  }

  const searchIcdCodes = async (query: string) => {
    if (query.length < 3) {
      setIcdSearchResults([])
      setShowIcdResults(false)
      return
    }

    try {
      const response = await fetch(`/consultation/diagnoses/search?query=${encodeURIComponent(query)}`)
      const data = await response.json()
      setIcdSearchResults(data.icd_codes || [])
      setShowIcdResults(true)
    } catch (error) {
      console.error('Failed to search ICD codes:', error)
    }
  }

  const handleIcdSearch = (query: string) => {
    setIcdSearchQuery(query)

    if (searchTimeout.current) {
      clearTimeout(searchTimeout.current)
    }

    searchTimeout.current = setTimeout(() => {
      searchIcdCodes(query)
    }, 300)
  }

  const selectIcdCode = (code: {code: string, description: string}) => {
    setDiagnosisData('icd_code', code.code)
    setDiagnosisData('diagnosis_description', code.description)
    setIcdSearchQuery(`${code.code} - ${code.description}`)
    setShowIcdResults(false)
  }

  const handleAdmissionSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    postAdmission(`/consultation/${consultation.id}/admit`, {
      onSuccess: () => {
        resetAdmission()
        setShowAdmissionModal(false)
      }
    })
  }

  const loadAvailableWards = async () => {
    try {
      const response = await fetch('/consultation/wards/available')
      const data = await response.json()
      setAvailableWards(data.wards || [])
    } catch (error) {
      console.error('Failed to load available wards:', error)
    }
  }


  const handleWardChange = (wardId: string) => {
    setAdmissionData('ward_id', wardId)
  }

  const openAdmissionModal = () => {
    setShowAdmissionModal(true)
    loadAvailableWards()
  }

  useEffect(() => {
    return () => {
      if (searchTimeout.current) {
        clearTimeout(searchTimeout.current)
      }
    }
  }, [])

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
      { title: `${consultation.patient_checkin.patient.first_name} ${consultation.patient_checkin.patient.last_name}`, href: '' }
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
              <>
                <Button onClick={completeConsultation} variant="outline">
                  Complete Consultation
                </Button>
                <Dialog open={showAdmissionModal} onOpenChange={setShowAdmissionModal}>
                  <DialogTrigger asChild>
                    <Button
                      onClick={openAdmissionModal}
                      variant="outline"
                      className="text-green-600 border-green-600 hover:bg-green-50"
                    >
                      <UserPlus className="h-4 w-4 mr-2" />
                      Admit Patient
                    </Button>
                  </DialogTrigger>
                  <DialogContent className="max-w-md">
                    <DialogHeader>
                      <DialogTitle>Admit Patient</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleAdmissionSubmit} className="space-y-4">
                      <div>
                        <Label htmlFor="ward_id">Select Ward</Label>
                        <Select value={admissionData.ward_id} onValueChange={handleWardChange}>
                          <SelectTrigger>
                            <SelectValue placeholder="Choose a ward" />
                          </SelectTrigger>
                          <SelectContent>
                            {availableWards.map((ward) => (
                              <SelectItem key={ward.id} value={ward.id.toString()}>
                                {ward.name} ({ward.available_beds} beds available)
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>


                      <div>
                        <Label htmlFor="admission_reason">Admission Reason</Label>
                        <Textarea
                          id="admission_reason"
                          placeholder="Reason for admission..."
                          value={admissionData.admission_reason}
                          onChange={(e) => setAdmissionData('admission_reason', e.target.value)}
                          required
                          rows={3}
                        />
                      </div>

                      <div>
                        <Label htmlFor="admission_notes">Admission Notes (Optional)</Label>
                        <Textarea
                          id="admission_notes"
                          placeholder="Additional notes..."
                          value={admissionData.admission_notes}
                          onChange={(e) => setAdmissionData('admission_notes', e.target.value)}
                          rows={2}
                        />
                      </div>


                      <div className="flex gap-2 pt-4">
                        <Button
                          type="button"
                          variant="outline"
                          onClick={() => setShowAdmissionModal(false)}
                          className="flex-1"
                        >
                          Cancel
                        </Button>
                        <Button
                          type="submit"
                          disabled={admissionProcessing || !admissionData.ward_id || !admissionData.admission_reason}
                          className="flex-1 bg-green-600 hover:bg-green-700"
                        >
                          {admissionProcessing ? 'Admitting...' : 'Admit Patient'}
                        </Button>
                      </div>
                    </form>
                  </DialogContent>
                </Dialog>
              </>
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
          <TabsList className="grid w-full grid-cols-6">
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
            <TabsTrigger value="prescriptions" className="flex items-center gap-2">
              <Pill className="h-4 w-4" />
              Prescriptions
            </TabsTrigger>
            <TabsTrigger value="orders" className="flex items-center gap-2">
              <TestTube className="h-4 w-4" />
              Lab Orders
            </TabsTrigger>
            <TabsTrigger value="history" className="flex items-center gap-2">
              <History className="h-4 w-4" />
              History
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
                {/* Add New Diagnosis Form */}
                {consultation.status === 'in_progress' && (
                  <div className="mb-6 p-4 bg-gray-50 rounded-lg">
                    <h3 className="text-lg font-semibold mb-4">Add New Diagnosis</h3>
                    <form onSubmit={handleDiagnosisSubmit} className="space-y-4">
                      {/* ICD Code Search */}
                      <div className="relative">
                        <Label htmlFor="icd_search">Search ICD Code</Label>
                        <div className="relative">
                          <Input
                            id="icd_search"
                            placeholder="Search by ICD code or description..."
                            value={icdSearchQuery}
                            onChange={(e) => handleIcdSearch(e.target.value)}
                            className="mt-1 pr-10"
                          />
                          <Search className="absolute right-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                        </div>

                        {/* Search Results Dropdown */}
                        {showIcdResults && icdSearchResults.length > 0 && (
                          <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
                            {icdSearchResults.map((code, index) => (
                              <div
                                key={index}
                                className="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0"
                                onClick={() => selectIcdCode(code)}
                              >
                                <div className="font-medium text-sm text-gray-900">{code.code}</div>
                                <div className="text-sm text-gray-600">{code.description}</div>
                              </div>
                            ))}
                          </div>
                        )}
                      </div>

                      {/* Selected Diagnosis Details */}
                      {diagnosisData.icd_code && (
                        <div className="p-3 bg-blue-50 border border-blue-200 rounded-md">
                          <div className="flex justify-between items-start">
                            <div>
                              <div className="font-medium text-blue-900">Selected: {diagnosisData.icd_code}</div>
                              <div className="text-sm text-blue-700">{diagnosisData.diagnosis_description}</div>
                            </div>
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              onClick={() => {
                                setDiagnosisData('icd_code', '')
                                setDiagnosisData('diagnosis_description', '')
                                setIcdSearchQuery('')
                              }}
                            >
                              <X className="h-4 w-4" />
                            </Button>
                          </div>
                        </div>
                      )}

                      {/* Primary Diagnosis Checkbox */}
                      <div className="flex items-center space-x-2">
                        <Checkbox
                          id="is_primary"
                          checked={diagnosisData.is_primary}
                          onCheckedChange={(checked) => setDiagnosisData('is_primary', !!checked)}
                        />
                        <Label htmlFor="is_primary" className="text-sm font-medium">
                          Primary Diagnosis
                        </Label>
                      </div>

                      <Button
                        type="submit"
                        disabled={diagnosisProcessing || !diagnosisData.icd_code}
                      >
                        <Plus className="h-4 w-4 mr-2" />
                        {diagnosisProcessing ? 'Adding...' : 'Add Diagnosis'}
                      </Button>
                    </form>
                  </div>
                )}

                {/* Existing Diagnoses List */}
                {consultation.diagnoses.length > 0 ? (
                  <div className="space-y-4">
                    <h3 className="text-lg font-semibold">Current Diagnoses</h3>
                    {consultation.diagnoses.map((diagnosis) => (
                      <div key={diagnosis.id} className="border rounded-lg p-4">
                        <div className="flex justify-between items-start mb-2">
                          <div>
                            <h4 className="font-semibold text-gray-900">{diagnosis.diagnosis_description}</h4>
                            <p className="text-sm text-gray-600">ICD Code: {diagnosis.icd_code}</p>
                          </div>
                          <div className="flex items-center gap-2">
                            {diagnosis.is_primary && (
                              <Badge variant="default">Primary</Badge>
                            )}
                            {consultation.status === 'in_progress' && (
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                  if (confirm('Are you sure you want to remove this diagnosis?')) {
                                    // Handle delete diagnosis
                                  }
                                }}
                              >
                                <Trash2 className="h-4 w-4" />
                              </Button>
                            )}
                          </div>
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

          {/* Prescriptions Tab */}
          <TabsContent value="prescriptions">
            <Card>
              <CardHeader>
                <CardTitle>Prescriptions</CardTitle>
              </CardHeader>
              <CardContent>
                {/* Add New Prescription Form */}
                {consultation.status === 'in_progress' && (
                  <div className="mb-6 p-4 bg-gray-50 rounded-lg">
                    <h3 className="text-lg font-semibold mb-4">Add New Prescription</h3>
                    <form onSubmit={handlePrescriptionSubmit} className="space-y-4">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                          <Label htmlFor="medication_name">Medication Name</Label>
                          <Input
                            id="medication_name"
                            placeholder="e.g., Amoxicillin"
                            value={prescriptionData.medication_name}
                            onChange={(e) => setPrescriptionData('medication_name', e.target.value)}
                            required
                          />
                        </div>
                        <div>
                          <Label htmlFor="dosage">Dosage</Label>
                          <Input
                            id="dosage"
                            placeholder="e.g., 500mg"
                            value={prescriptionData.dosage}
                            onChange={(e) => setPrescriptionData('dosage', e.target.value)}
                            required
                          />
                        </div>
                        <div>
                          <Label htmlFor="frequency">Frequency</Label>
                          <Input
                            id="frequency"
                            placeholder="e.g., 3 times daily"
                            value={prescriptionData.frequency}
                            onChange={(e) => setPrescriptionData('frequency', e.target.value)}
                            required
                          />
                        </div>
                        <div>
                          <Label htmlFor="duration">Duration</Label>
                          <Input
                            id="duration"
                            placeholder="e.g., 7 days"
                            value={prescriptionData.duration}
                            onChange={(e) => setPrescriptionData('duration', e.target.value)}
                            required
                          />
                        </div>
                      </div>
                      <div>
                        <Label htmlFor="instructions">Instructions (Optional)</Label>
                        <Textarea
                          id="instructions"
                          placeholder="Special instructions for the patient..."
                          value={prescriptionData.instructions}
                          onChange={(e) => setPrescriptionData('instructions', e.target.value)}
                          rows={3}
                        />
                      </div>
                      <Button type="submit" disabled={prescriptionProcessing}>
                        <Plus className="h-4 w-4 mr-2" />
                        {prescriptionProcessing ? 'Adding...' : 'Add Prescription'}
                      </Button>
                    </form>
                  </div>
                )}

                {/* Existing Prescriptions List */}
                {consultation.prescriptions.length > 0 ? (
                  <div className="space-y-4">
                    <h3 className="text-lg font-semibold">Current Prescriptions</h3>
                    {consultation.prescriptions.map((prescription) => (
                      <div key={prescription.id} className="border rounded-lg p-4">
                        <div className="flex justify-between items-start mb-2">
                          <div>
                            <h4 className="font-semibold text-gray-900">{prescription.medication_name}</h4>
                            <p className="text-sm text-gray-600">
                              {prescription.dosage} • {prescription.frequency} • {prescription.duration}
                            </p>
                            {prescription.instructions && (
                              <p className="text-sm text-gray-700 mt-2 bg-blue-50 p-2 rounded">
                                <strong>Instructions:</strong> {prescription.instructions}
                              </p>
                            )}
                          </div>
                          <div className="flex items-center gap-2">
                            <Badge variant={
                              prescription.status === 'prescribed' ? 'default' :
                              prescription.status === 'dispensed' ? 'outline' : 'destructive'
                            }>
                              {prescription.status.toUpperCase()}
                            </Badge>
                            {consultation.status === 'in_progress' && prescription.status === 'prescribed' && (
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                  if (confirm('Are you sure you want to cancel this prescription?')) {
                                    // Handle cancel prescription
                                  }
                                }}
                              >
                                <Trash2 className="h-4 w-4" />
                              </Button>
                            )}
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-8 text-gray-500">
                    <Pill className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                    <p>No prescriptions recorded</p>
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

          {/* Patient History Tab */}
          <TabsContent value="history">
            <div className="space-y-6">
              {/* Allergies & Medical Alerts */}
              {patientHistory?.allergies && patientHistory.allergies.length > 0 && (
                <Card>
                  <CardHeader>
                    <CardTitle className="text-red-600 flex items-center gap-2">
                      <Activity className="h-5 w-5" />
                      Medical Alerts & Allergies
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="flex flex-wrap gap-2">
                      {patientHistory.allergies.map((allergy, index) => (
                        <Badge key={index} variant="destructive">
                          {allergy}
                        </Badge>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              )}

              {/* Previous Consultations */}
              <Card>
                <CardHeader>
                  <CardTitle>Previous Consultations</CardTitle>
                </CardHeader>
                <CardContent>
                  {patientHistory?.previousConsultations && patientHistory.previousConsultations.length > 0 ? (
                    <div className="space-y-4">
                      {patientHistory.previousConsultations.slice(0, 5).map((prevConsultation) => (
                        <div key={prevConsultation.id} className="border rounded-lg p-4">
                          <div className="flex justify-between items-start mb-2">
                            <div>
                              <h4 className="font-semibold text-gray-900">
                                {formatDateTime(prevConsultation.started_at)}
                              </h4>
                              <p className="text-sm text-gray-600">
                                Dr. {prevConsultation.doctor.name} • {prevConsultation.patient_checkin.department.name}
                              </p>
                            </div>
                            <Badge variant={prevConsultation.status === 'completed' ? 'outline' : 'default'}>
                              {prevConsultation.status.toUpperCase()}
                            </Badge>
                          </div>

                          {prevConsultation.chief_complaint && (
                            <div className="mt-3">
                              <p className="text-sm font-medium text-gray-700">Chief Complaint:</p>
                              <p className="text-sm text-gray-600">{prevConsultation.chief_complaint}</p>
                            </div>
                          )}

                          {prevConsultation.diagnoses.length > 0 && (
                            <div className="mt-3">
                              <p className="text-sm font-medium text-gray-700">Diagnoses:</p>
                              <div className="flex flex-wrap gap-1 mt-1">
                                {prevConsultation.diagnoses.map((diagnosis) => (
                                  <Badge key={diagnosis.id} variant="secondary" className="text-xs">
                                    {diagnosis.diagnosis_description}
                                  </Badge>
                                ))}
                              </div>
                            </div>
                          )}

                          {prevConsultation.prescriptions.length > 0 && (
                            <div className="mt-3">
                              <p className="text-sm font-medium text-gray-700">Medications Prescribed:</p>
                              <div className="flex flex-wrap gap-1 mt-1">
                                {prevConsultation.prescriptions.map((prescription) => (
                                  <Badge key={prescription.id} variant="outline" className="text-xs">
                                    {prescription.medication_name}
                                  </Badge>
                                ))}
                              </div>
                            </div>
                          )}
                        </div>
                      ))}
                      {patientHistory.previousConsultations.length > 5 && (
                        <p className="text-sm text-gray-500 text-center">
                          ... and {patientHistory.previousConsultations.length - 5} more consultations
                        </p>
                      )}
                    </div>
                  ) : (
                    <div className="text-center py-8 text-gray-500">
                      <History className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                      <p>No previous consultations found</p>
                    </div>
                  )}
                </CardContent>
              </Card>

              {/* Medication History */}
              <Card>
                <CardHeader>
                  <CardTitle>Medication History</CardTitle>
                </CardHeader>
                <CardContent>
                  {patientHistory?.previousPrescriptions && patientHistory.previousPrescriptions.length > 0 ? (
                    <div className="space-y-3">
                      {patientHistory.previousPrescriptions.slice(0, 10).map((prescription) => (
                        <div key={prescription.id} className="flex justify-between items-center py-2 border-b border-gray-100">
                          <div>
                            <p className="font-medium text-gray-900">{prescription.medication_name}</p>
                            <p className="text-sm text-gray-600">
                              {prescription.dosage} • {prescription.frequency} • {prescription.duration}
                            </p>
                          </div>
                          <Badge variant={
                            prescription.status === 'prescribed' ? 'default' :
                            prescription.status === 'dispensed' ? 'outline' : 'secondary'
                          } className="text-xs">
                            {prescription.status.toUpperCase()}
                          </Badge>
                        </div>
                      ))}
                      {patientHistory.previousPrescriptions.length > 10 && (
                        <p className="text-sm text-gray-500 text-center">
                          ... and {patientHistory.previousPrescriptions.length - 10} more prescriptions
                        </p>
                      )}
                    </div>
                  ) : (
                    <div className="text-center py-8 text-gray-500">
                      <Pill className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                      <p>No previous medications found</p>
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>
          </TabsContent>
        </Tabs>
      </div>
    </AppLayout>
  )
}