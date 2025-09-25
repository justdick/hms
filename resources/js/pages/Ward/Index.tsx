import { Head, Link, router } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  Building2,
  Bed,
  Users,
  Plus,
  Edit,
  Trash2,
  Eye,
  ToggleLeft,
  ToggleRight,
  Hospital
} from 'lucide-react'


interface Bed {
  id: number
  ward_id: number
  status: 'available' | 'occupied' | 'maintenance' | 'cleaning'
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
  created_at: string
}

interface Props {
  wards: Ward[]
}

export default function WardIndex({ wards }: Props) {
  const handleDelete = (ward: Ward) => {
    if (confirm(`Are you sure you want to delete "${ward.name}"? This action cannot be undone.`)) {
      router.delete(`/wards/${ward.id}`)
    }
  }

  const handleToggleStatus = (ward: Ward) => {
    const action = ward.is_active ? 'deactivate' : 'activate'
    if (confirm(`Are you sure you want to ${action} "${ward.name}"?`)) {
      router.post(`/wards/${ward.id}/toggle-status`)
    }
  }


  const getOccupancyRate = (ward: Ward) => {
    if (ward.total_beds === 0) return 0
    const occupied = ward.total_beds - ward.available_beds
    return Math.round((occupied / ward.total_beds) * 100)
  }

  const getOccupancyColor = (rate: number) => {
    if (rate >= 90) return 'text-red-600'
    if (rate >= 70) return 'text-orange-600'
    if (rate >= 50) return 'text-yellow-600'
    return 'text-green-600'
  }

  return (
    <AppLayout breadcrumbs={[
      { title: 'Wards', href: '/wards' }
    ]}>
      <Head title="Ward Management" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-2">
              <Hospital className="h-8 w-8" />
              Ward Management
            </h1>
            <p className="text-gray-600 mt-2">
              Manage hospital wards and bed allocation
            </p>
          </div>
          <Link href="/wards/create">
            <Button>
              <Plus className="h-4 w-4 mr-2" />
              Create Ward
            </Button>
          </Link>
        </div>

        {/* Stats Overview */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">Total Wards</p>
                  <p className="text-3xl font-bold text-gray-900">{wards.length}</p>
                </div>
                <Building2 className="h-8 w-8 text-blue-600" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">Active Wards</p>
                  <p className="text-3xl font-bold text-green-600">
                    {wards.filter(w => w.is_active).length}
                  </p>
                </div>
                <ToggleRight className="h-8 w-8 text-green-600" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">Total Beds</p>
                  <p className="text-3xl font-bold text-gray-900">
                    {wards.reduce((sum, w) => sum + w.total_beds, 0)}
                  </p>
                </div>
                <Bed className="h-8 w-8 text-purple-600" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">Available Beds</p>
                  <p className="text-3xl font-bold text-green-600">
                    {wards.reduce((sum, w) => sum + w.available_beds, 0)}
                  </p>
                </div>
                <Users className="h-8 w-8 text-green-600" />
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Wards List */}
        {wards.length > 0 ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {wards.map((ward) => (
              <Card key={ward.id} className={`${!ward.is_active ? 'opacity-75 border-gray-300' : ''}`}>
                <CardHeader className="pb-3">
                  <div className="flex justify-between items-start">
                    <div>
                      <CardTitle className="text-lg">{ward.name}</CardTitle>
                      <p className="text-sm text-gray-600">Code: {ward.code}</p>
                    </div>
                    <div>
                      <Badge variant={ward.is_active ? "default" : "secondary"}>
                        {ward.is_active ? "Active" : "Inactive"}
                      </Badge>
                    </div>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  {ward.description && (
                    <p className="text-sm text-gray-600">{ward.description}</p>
                  )}

                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <p className="text-gray-600">Total Beds</p>
                      <p className="font-semibold text-lg">{ward.total_beds}</p>
                    </div>
                    <div>
                      <p className="text-gray-600">Available</p>
                      <p className="font-semibold text-lg text-green-600">{ward.available_beds}</p>
                    </div>
                  </div>

                  <div className="pt-2">
                    <div className="flex justify-between items-center mb-2">
                      <span className="text-sm text-gray-600">Occupancy</span>
                      <span className={`text-sm font-semibold ${getOccupancyColor(getOccupancyRate(ward))}`}>
                        {getOccupancyRate(ward)}%
                      </span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-blue-600 h-2 rounded-full"
                        style={{ width: `${getOccupancyRate(ward)}%` }}
                      ></div>
                    </div>
                  </div>

                  <div className="flex justify-between items-center pt-4 border-t">
                    <div className="flex gap-2">
                      <Link href={`/wards/${ward.id}`}>
                        <Button variant="ghost" size="sm">
                          <Eye className="h-4 w-4" />
                        </Button>
                      </Link>
                      <Link href={`/wards/${ward.id}/edit`}>
                        <Button variant="ghost" size="sm">
                          <Edit className="h-4 w-4" />
                        </Button>
                      </Link>
                    </div>

                    <div className="flex gap-2">
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleToggleStatus(ward)}
                      >
                        {ward.is_active ? (
                          <ToggleLeft className="h-4 w-4 text-orange-600" />
                        ) : (
                          <ToggleRight className="h-4 w-4 text-green-600" />
                        )}
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleDelete(ward)}
                        className="text-red-600 hover:text-red-700"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        ) : (
          <Card>
            <CardContent className="p-12 text-center">
              <Hospital className="h-16 w-16 mx-auto mb-4 text-gray-300" />
              <h3 className="text-lg font-semibold text-gray-900 mb-2">No wards found</h3>
              <p className="text-gray-600 mb-4">Get started by creating your first ward.</p>
              <Link href="/wards/create">
                <Button>
                  <Plus className="h-4 w-4 mr-2" />
                  Create Ward
                </Button>
              </Link>
            </CardContent>
          </Card>
        )}
      </div>
    </AppLayout>
  )
}