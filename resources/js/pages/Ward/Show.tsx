import { Head, Link } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Hospital,
  ArrowLeft,
  Edit,
  Bed,
  Users,
  Building2,
  User,
  Calendar,
  Activity,
  Settings,
  UserCheck
} from 'lucide-react'


interface Patient {
  id: number
  first_name: string
  last_name: string
}

interface Doctor {
  id: number
  name: string
}

interface Bed {
  id: number
  ward_id: number
  bed_number: string
  status: 'available' | 'occupied' | 'maintenance' | 'cleaning'
  type: 'standard' | 'icu' | 'isolation' | 'private'
  is_active: boolean
}

interface PatientAdmission {
  id: number
  admission_number: string
  patient: Patient
  attending_doctor: Doctor
  status: string
  admitted_at: string
  bed_id?: number
}

interface Ward {
  id: number
  name: string
  code: string
  description?: string
  total_beds: number
  available_beds: number
  is_active: boolean
  beds: Bed[]
  admissions: PatientAdmission[]
  created_at: string
}

interface Props {
  ward: Ward
}

export default function WardShow({ ward }: Props) {

  const getBedStatusColor = (status: string) => {
    const colors = {
      'available': 'bg-green-100 text-green-800 border-green-200',
      'occupied': 'bg-red-100 text-red-800 border-red-200',
      'maintenance': 'bg-yellow-100 text-yellow-800 border-yellow-200',
      'cleaning': 'bg-blue-100 text-blue-800 border-blue-200',
    }
    return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800 border-gray-200'
  }

  const getBedTypeIcon = (type: string) => {
    switch (type) {
      case 'icu': return <Activity className="h-4 w-4" />
      case 'isolation': return <Settings className="h-4 w-4" />
      case 'private': return <UserCheck className="h-4 w-4" />
      default: return <Bed className="h-4 w-4" />
    }
  }

  const getOccupancyRate = () => {
    if (ward.total_beds === 0) return 0
    const occupied = ward.total_beds - ward.available_beds
    return Math.round((occupied / ward.total_beds) * 100)
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

  return (
    <AppLayout breadcrumbs={[
      { title: 'Wards', href: '/wards' },
      { title: ward.name, href: '' }
    ]}>
      <Head title={`${ward.name} - Ward Details`} />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex justify-between items-start">
          <div className="flex items-center gap-4">
            <Link href="/wards">
              <Button variant="ghost" size="sm">
                <ArrowLeft className="h-4 w-4 mr-2" />
                Back to Wards
              </Button>
            </Link>
            <div>
              <div className="flex items-center gap-3">
                <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-2">
                  <Hospital className="h-8 w-8" />
                  {ward.name}
                </h1>
                <Badge variant={ward.is_active ? "default" : "secondary"}>
                  {ward.is_active ? "Active" : "Inactive"}
                </Badge>
              </div>
              <p className="text-gray-600 mt-2">
                Code: {ward.code}
              </p>
              {ward.description && (
                <p className="text-gray-600 mt-1">{ward.description}</p>
              )}
            </div>
          </div>

          <Link href={`/wards/${ward.id}/edit`}>
            <Button>
              <Edit className="h-4 w-4 mr-2" />
              Edit Ward
            </Button>
          </Link>
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">Total Beds</p>
                  <p className="text-3xl font-bold text-gray-900">{ward.total_beds}</p>
                </div>
                <Bed className="h-8 w-8 text-blue-600" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">Available</p>
                  <p className="text-3xl font-bold text-green-600">{ward.available_beds}</p>
                </div>
                <Users className="h-8 w-8 text-green-600" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">Occupied</p>
                  <p className="text-3xl font-bold text-red-600">{ward.total_beds - ward.available_beds}</p>
                </div>
                <User className="h-8 w-8 text-red-600" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">Occupancy</p>
                  <p className="text-3xl font-bold text-purple-600">{getOccupancyRate()}%</p>
                </div>
                <Activity className="h-8 w-8 text-purple-600" />
              </div>
            </CardContent>
          </Card>
        </div>

        <Tabs defaultValue="beds" className="w-full">
          <TabsList>
            <TabsTrigger value="beds" className="flex items-center gap-2">
              <Bed className="h-4 w-4" />
              Beds
            </TabsTrigger>
            <TabsTrigger value="patients" className="flex items-center gap-2">
              <Users className="h-4 w-4" />
              Current Patients ({ward.admissions.length})
            </TabsTrigger>
          </TabsList>

          {/* Beds Tab */}
          <TabsContent value="beds">
            <Card>
              <CardHeader>
                <CardTitle>Bed Management</CardTitle>
              </CardHeader>
              <CardContent>
                {ward.beds.length > 0 ? (
                  <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4">
                    {ward.beds
                      .sort((a, b) => a.bed_number.localeCompare(b.bed_number))
                      .map((bed) => {
                        const currentPatient = ward.admissions.find(admission => admission.bed_id === bed.id)
                        return (
                          <div
                            key={bed.id}
                            className={`relative p-4 rounded-lg border-2 ${getBedStatusColor(bed.status)} ${!bed.is_active ? 'opacity-50' : ''}`}
                          >
                            <div className="text-center">
                              <div className="flex justify-center mb-2">
                                {getBedTypeIcon(bed.type)}
                              </div>
                              <div className="font-semibold text-sm">
                                Bed {bed.bed_number}
                              </div>
                              <div className="text-xs text-gray-600 capitalize">
                                {bed.type}
                              </div>
                              <div className="mt-2">
                                <Badge
                                  variant="outline"
                                  className={`text-xs ${getBedStatusColor(bed.status).split(' ')[1]} ${getBedStatusColor(bed.status).split(' ')[2]}`}
                                >
                                  {bed.status}
                                </Badge>
                              </div>
                              {currentPatient && (
                                <div className="mt-2 p-2 bg-white bg-opacity-50 rounded text-xs">
                                  <div className="font-medium">
                                    {currentPatient.patient.first_name} {currentPatient.patient.last_name}
                                  </div>
                                  <div className="text-gray-600">
                                    {currentPatient.admission_number}
                                  </div>
                                </div>
                              )}
                            </div>
                          </div>
                        )
                      })}
                  </div>
                ) : (
                  <div className="text-center py-8 text-gray-500">
                    <Bed className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                    <p>No beds configured for this ward</p>
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* Current Patients Tab */}
          <TabsContent value="patients">
            <Card>
              <CardHeader>
                <CardTitle>Current Patients</CardTitle>
              </CardHeader>
              <CardContent>
                {ward.admissions.length > 0 ? (
                  <div className="space-y-4">
                    {ward.admissions.map((admission) => {
                      const assignedBed = ward.beds.find(bed => bed.id === admission.bed_id)
                      return (
                        <div key={admission.id} className="border rounded-lg p-4">
                          <div className="flex justify-between items-start">
                            <div>
                              <h3 className="font-semibold text-gray-900">
                                {admission.patient.first_name} {admission.patient.last_name}
                              </h3>
                              <p className="text-sm text-gray-600">
                                Admission: {admission.admission_number}
                              </p>
                              <div className="flex items-center text-sm text-gray-600 mt-2">
                                <User className="h-4 w-4 mr-2" />
                                Dr. {admission.attending_doctor.name}
                              </div>
                            </div>
                            <div className="text-right">
                              <Badge variant="default">
                                {admission.status.replace('_', ' ').toUpperCase()}
                              </Badge>
                              {assignedBed && (
                                <p className="text-sm text-gray-600 mt-2">
                                  Bed: {assignedBed.bed_number}
                                </p>
                              )}
                            </div>
                          </div>
                          <div className="flex items-center text-sm text-gray-600 mt-3">
                            <Calendar className="h-4 w-4 mr-2" />
                            Admitted: {formatDateTime(admission.admitted_at)}
                          </div>
                        </div>
                      )
                    })}
                  </div>
                ) : (
                  <div className="text-center py-8 text-gray-500">
                    <Users className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                    <p>No patients currently admitted to this ward</p>
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