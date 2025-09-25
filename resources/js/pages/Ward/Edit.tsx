import { Head, useForm } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Checkbox } from '@/components/ui/checkbox'
import { Hospital, ArrowLeft } from 'lucide-react'
import { Link } from '@inertiajs/react'

interface Ward {
  id: number
  name: string
  code: string
  description?: string
  is_active: boolean
  total_beds: number
  available_beds: number
}

interface Props {
  ward: Ward
}

export default function WardEdit({ ward }: Props) {
  const { data, setData, put, processing, errors } = useForm({
    name: ward.name,
    code: ward.code,
    description: ward.description || '',
    is_active: ward.is_active,
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    put(`/wards/${ward.id}`)
  }


  return (
    <AppLayout breadcrumbs={[
      { title: 'Wards', href: '/wards' },
      { title: ward.name, href: `/wards/${ward.id}` },
      { title: 'Edit', href: '' }
    ]}>
      <Head title={`Edit ${ward.name}`} />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center gap-4">
          <Link href={`/wards/${ward.id}`}>
            <Button variant="ghost" size="sm">
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back to Ward
            </Button>
          </Link>
          <div>
            <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-2">
              <Hospital className="h-8 w-8" />
              Edit Ward: {ward.name}
            </h1>
            <p className="text-gray-600 mt-1">
              Update ward information and configuration
            </p>
          </div>
        </div>

        <Card className="max-w-2xl">
          <CardHeader>
            <CardTitle>Ward Information</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-6">
              {/* Basic Information */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="name">Ward Name</Label>
                  <Input
                    id="name"
                    type="text"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="e.g., General Ward A"
                    required
                    className="mt-1"
                  />
                  {errors.name && (
                    <p className="text-sm text-red-600 mt-1">{errors.name}</p>
                  )}
                </div>

                <div>
                  <Label htmlFor="code">Ward Code</Label>
                  <Input
                    id="code"
                    type="text"
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value.toUpperCase())}
                    placeholder="e.g., GWA"
                    maxLength={10}
                    required
                    className="mt-1"
                  />
                  {errors.code && (
                    <p className="text-sm text-red-600 mt-1">{errors.code}</p>
                  )}
                </div>
              </div>

              <div>
                <Label htmlFor="description">Description (Optional)</Label>
                <Textarea
                  id="description"
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  placeholder="Brief description of the ward..."
                  rows={3}
                  className="mt-1"
                />
                {errors.description && (
                  <p className="text-sm text-red-600 mt-1">{errors.description}</p>
                )}
              </div>


              {/* Bed Information (Read-only) */}
              <div className="border rounded-lg p-4 bg-gray-50">
                <h3 className="font-semibold text-gray-900 mb-2">Bed Information</h3>
                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <p className="text-gray-600">Total Beds</p>
                    <p className="font-semibold text-lg">{ward.total_beds}</p>
                  </div>
                  <div>
                    <p className="text-gray-600">Available Beds</p>
                    <p className="font-semibold text-lg text-green-600">{ward.available_beds}</p>
                  </div>
                </div>
                <p className="text-xs text-gray-500 mt-2">
                  To manage individual beds, visit the ward details page.
                </p>
              </div>

              {/* Active Status */}
              <div className="flex items-center space-x-2">
                <Checkbox
                  id="is_active"
                  checked={data.is_active}
                  onCheckedChange={(checked) => setData('is_active', !!checked)}
                />
                <Label htmlFor="is_active" className="text-sm font-medium">
                  Active Ward
                </Label>
              </div>
              {errors.is_active && (
                <p className="text-sm text-red-600">{errors.is_active}</p>
              )}

              <div className="flex gap-4 pt-6 border-t">
                <Link href={`/wards/${ward.id}`}>
                  <Button type="button" variant="outline" className="flex-1 md:flex-none">
                    Cancel
                  </Button>
                </Link>
                <Button
                  type="submit"
                  disabled={processing}
                  className="flex-1 md:flex-none"
                >
                  {processing ? 'Updating...' : 'Update Ward'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  )
}