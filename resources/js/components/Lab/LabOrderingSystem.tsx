import { useState, useMemo } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Separator } from '@/components/ui/separator'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '@/components/ui/tabs'
import {
  TestTube,
  Search,
  Plus,
  Clock,
  AlertTriangle,
  DollarSign,
  Info,
  Package,
  Star,
  Filter,
  ShoppingCart,
  X
} from 'lucide-react'
import { cn } from '@/lib/utils'

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

interface LabBundle {
  id: string
  name: string
  description: string
  tests: LabService[]
  discount_percentage: number
  recommended_for: string[]
}

interface LabOrder {
  lab_service_id: number
  priority: 'routine' | 'urgent' | 'stat'
  special_instructions?: string
}

interface LabOrderingSystemProps {
  labServices: LabService[]
  onOrderSubmit: (orders: LabOrder[], totalCost: number) => void
  onClose?: () => void
  patientInfo?: {
    age: number
    gender: string
    conditions: string[]
  }
}

const commonLabBundles: LabBundle[] = [
  {
    id: 'basic-metabolic',
    name: 'Basic Metabolic Panel (BMP)',
    description: 'Essential blood chemistry tests for routine screening',
    tests: [], // Would be populated with actual LabService objects
    discount_percentage: 15,
    recommended_for: ['Annual Physical', 'Diabetes Monitoring', 'Hypertension']
  },
  {
    id: 'comprehensive-metabolic',
    name: 'Comprehensive Metabolic Panel (CMP)',
    description: 'Extended blood chemistry panel including liver function',
    tests: [],
    discount_percentage: 20,
    recommended_for: ['Pre-surgical', 'Medication Monitoring', 'General Health']
  },
  {
    id: 'lipid-panel',
    name: 'Lipid Panel',
    description: 'Cholesterol and triglyceride testing',
    tests: [],
    discount_percentage: 10,
    recommended_for: ['Cardiovascular Risk', 'Diabetes', 'Age >40']
  },
  {
    id: 'cardiac-markers',
    name: 'Cardiac Markers',
    description: 'Tests for heart attack and cardiac injury',
    tests: [],
    discount_percentage: 25,
    recommended_for: ['Chest Pain', 'Suspected MI', 'Cardiac Monitoring']
  }
]

export default function LabOrderingSystem({
  labServices,
  onOrderSubmit,
  onClose,
  patientInfo
}: LabOrderingSystemProps) {
  const [searchTerm, setSearchTerm] = useState('')
  const [selectedCategory, setSelectedCategory] = useState('all')
  const [selectedTests, setSelectedTests] = useState<Map<number, LabOrder>>(new Map())
  const [activeTab, setActiveTab] = useState<'individual' | 'bundles' | 'cart'>('individual')
  const [showDetails, setShowDetails] = useState<number | null>(null)

  // Filter and search logic
  const filteredServices = useMemo(() => {
    return labServices.filter(service => {
      const matchesSearch = service.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                           service.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
                           service.description?.toLowerCase().includes(searchTerm.toLowerCase())

      const matchesCategory = selectedCategory === 'all' || service.category === selectedCategory

      return matchesSearch && matchesCategory
    })
  }, [labServices, searchTerm, selectedCategory])

  // Get unique categories
  const categories = useMemo(() => {
    const cats = ['all', ...new Set(labServices.map(service => service.category))]
    return cats
  }, [labServices])

  // Calculate total cost
  const totalCost = useMemo(() => {
    return Array.from(selectedTests.values()).reduce((sum, order) => {
      const service = labServices.find(s => s.id === order.lab_service_id)
      return sum + (service?.price || 0)
    }, 0)
  }, [selectedTests, labServices])

  const toggleTestSelection = (service: LabService) => {
    const newSelected = new Map(selectedTests)
    if (newSelected.has(service.id)) {
      newSelected.delete(service.id)
    } else {
      newSelected.set(service.id, {
        lab_service_id: service.id,
        priority: 'routine',
        special_instructions: ''
      })
    }
    setSelectedTests(newSelected)
  }

  const updateTestOrder = (serviceId: number, updates: Partial<LabOrder>) => {
    const newSelected = new Map(selectedTests)
    const existing = newSelected.get(serviceId)
    if (existing) {
      newSelected.set(serviceId, { ...existing, ...updates })
      setSelectedTests(newSelected)
    }
  }

  const selectBundle = (bundle: LabBundle) => {
    const newSelected = new Map(selectedTests)
    bundle.tests.forEach(test => {
      if (!newSelected.has(test.id)) {
        newSelected.set(test.id, {
          lab_service_id: test.id,
          priority: 'routine',
          special_instructions: `Part of ${bundle.name}`
        })
      }
    })
    setSelectedTests(newSelected)
    setActiveTab('cart')
  }

  const handleSubmit = () => {
    const orders = Array.from(selectedTests.values())
    onOrderSubmit(orders, totalCost)
  }

  const getPriorityColor = (priority: string) => {
    const colors = {
      routine: 'bg-blue-100 text-blue-800',
      urgent: 'bg-orange-100 text-orange-800',
      stat: 'bg-red-100 text-red-800'
    }
    return colors[priority as keyof typeof colors] || colors.routine
  }

  const getRecommendedTests = () => {
    if (!patientInfo) return []

    const { age, gender, conditions } = patientInfo
    const recommended = []

    if (age > 40) {
      recommended.push('Lipid Panel', 'Diabetes Screening')
    }
    if (gender === 'female' && age > 50) {
      recommended.push('Bone Density Markers')
    }
    if (conditions.includes('Diabetes')) {
      recommended.push('HbA1c', 'Microalbumin')
    }
    if (conditions.includes('Hypertension')) {
      recommended.push('Basic Metabolic Panel', 'Kidney Function')
    }

    return recommended
  }

  return (
    <Dialog open onOpenChange={() => onClose?.()}>
      <DialogContent className="max-w-6xl max-h-[90vh] overflow-hidden">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <TestTube className="h-5 w-5" />
            Laboratory Order System
          </DialogTitle>
          <DialogDescription>
            Order laboratory tests with intelligent recommendations and bundling options
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col h-[calc(90vh-120px)]">
          <Tabs value={activeTab} onValueChange={setActiveTab as any} className="flex-1">
            <TabsList className="grid w-full grid-cols-3">
              <TabsTrigger value="individual" className="flex items-center gap-2">
                <TestTube className="h-4 w-4" />
                Individual Tests
              </TabsTrigger>
              <TabsTrigger value="bundles" className="flex items-center gap-2">
                <Package className="h-4 w-4" />
                Test Bundles
              </TabsTrigger>
              <TabsTrigger value="cart" className="flex items-center gap-2">
                <ShoppingCart className="h-4 w-4" />
                Cart ({selectedTests.size})
              </TabsTrigger>
            </TabsList>

            <div className="flex-1 overflow-hidden">
              {/* Individual Tests Tab */}
              <TabsContent value="individual" className="h-full overflow-hidden">
                <div className="flex gap-4 mb-4">
                  <div className="flex-1 relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                    <Input
                      placeholder="Search tests by name, code, or description..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      className="pl-10"
                    />
                  </div>
                  <Select value={selectedCategory} onValueChange={setSelectedCategory}>
                    <SelectTrigger className="w-48">
                      <Filter className="h-4 w-4 mr-2" />
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {categories.map(category => (
                        <SelectItem key={category} value={category}>
                          {category === 'all' ? 'All Categories' : category}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                {/* Recommended Tests */}
                {patientInfo && getRecommendedTests().length > 0 && (
                  <Card className="mb-4 bg-blue-50 border-blue-200">
                    <CardHeader className="pb-3">
                      <CardTitle className="text-sm flex items-center gap-2">
                        <Star className="h-4 w-4 text-blue-600" />
                        Recommended for this patient
                      </CardTitle>
                    </CardHeader>
                    <CardContent>
                      <div className="flex flex-wrap gap-2">
                        {getRecommendedTests().map((test, index) => (
                          <Badge key={index} variant="secondary" className="bg-blue-100 text-blue-800">
                            {test}
                          </Badge>
                        ))}
                      </div>
                    </CardContent>
                  </Card>
                )}

                {/* Tests List */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 overflow-y-auto max-h-[400px] pr-2">
                  {filteredServices.map((service) => {
                    const isSelected = selectedTests.has(service.id)
                    return (
                      <Card
                        key={service.id}
                        className={cn(
                          "cursor-pointer transition-all hover:shadow-md",
                          isSelected && "ring-2 ring-blue-500 bg-blue-50"
                        )}
                        onClick={() => toggleTestSelection(service)}
                      >
                        <CardHeader className="pb-2">
                          <div className="flex items-start justify-between">
                            <div className="flex-1">
                              <CardTitle className="text-sm font-medium">
                                {service.name}
                              </CardTitle>
                              <p className="text-xs text-gray-600 mt-1">
                                {service.code} • {service.category}
                              </p>
                            </div>
                            <div className="flex items-center gap-2">
                              <Badge variant="outline" className="text-xs">
                                ${service.price}
                              </Badge>
                              <Checkbox checked={isSelected} />
                            </div>
                          </div>
                        </CardHeader>
                        <CardContent>
                          <div className="space-y-2 text-xs">
                            <div className="flex justify-between">
                              <span className="text-gray-600">Sample:</span>
                              <span>{service.sample_type}</span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-gray-600">Turnaround:</span>
                              <span>{service.turnaround_time}</span>
                            </div>
                            {service.description && (
                              <p className="text-gray-700 text-xs mt-2">
                                {service.description}
                              </p>
                            )}
                          </div>

                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                              e.stopPropagation()
                              setShowDetails(showDetails === service.id ? null : service.id)
                            }}
                            className="mt-2 p-0 h-auto text-xs text-blue-600"
                          >
                            <Info className="h-3 w-3 mr-1" />
                            {showDetails === service.id ? 'Hide' : 'Show'} Details
                          </Button>

                          {showDetails === service.id && (
                            <div className="mt-2 p-2 bg-gray-50 rounded text-xs space-y-1">
                              {service.normal_range && (
                                <p><strong>Normal Range:</strong> {service.normal_range}</p>
                              )}
                              {service.clinical_significance && (
                                <p><strong>Clinical Significance:</strong> {service.clinical_significance}</p>
                              )}
                              {service.preparation_instructions && (
                                <p><strong>Preparation:</strong> {service.preparation_instructions}</p>
                              )}
                            </div>
                          )}
                        </CardContent>
                      </Card>
                    )
                  })}
                </div>
              </TabsContent>

              {/* Test Bundles Tab */}
              <TabsContent value="bundles" className="h-full overflow-y-auto">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {commonLabBundles.map((bundle) => (
                    <Card key={bundle.id} className="hover:shadow-md transition-all">
                      <CardHeader>
                        <CardTitle className="flex items-center justify-between">
                          <span>{bundle.name}</span>
                          <Badge className="bg-green-100 text-green-800">
                            {bundle.discount_percentage}% OFF
                          </Badge>
                        </CardTitle>
                        <p className="text-sm text-gray-600">{bundle.description}</p>
                      </CardHeader>
                      <CardContent>
                        <div className="space-y-3">
                          <div>
                            <h4 className="text-sm font-medium mb-2">Recommended for:</h4>
                            <div className="flex flex-wrap gap-1">
                              {bundle.recommended_for.map((condition, index) => (
                                <Badge key={index} variant="outline" className="text-xs">
                                  {condition}
                                </Badge>
                              ))}
                            </div>
                          </div>

                          <div>
                            <h4 className="text-sm font-medium mb-2">Includes:</h4>
                            <p className="text-xs text-gray-600">
                              {bundle.tests.length || 'Multiple'} specialized tests
                            </p>
                          </div>

                          <Button
                            onClick={() => selectBundle(bundle)}
                            className="w-full"
                          >
                            <Plus className="h-4 w-4 mr-2" />
                            Add Bundle
                          </Button>
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              </TabsContent>

              {/* Cart Tab */}
              <TabsContent value="cart" className="h-full overflow-hidden">
                <div className="flex flex-col h-full">
                  <div className="flex-1 overflow-y-auto">
                    {selectedTests.size === 0 ? (
                      <div className="text-center py-12">
                        <ShoppingCart className="h-16 w-16 mx-auto mb-4 text-gray-300" />
                        <p className="text-gray-500">No tests selected</p>
                        <p className="text-sm text-gray-400 mt-2">
                          Add tests from Individual Tests or Bundles tabs
                        </p>
                      </div>
                    ) : (
                      <div className="space-y-4">
                        {Array.from(selectedTests.entries()).map(([serviceId, order]) => {
                          const service = labServices.find(s => s.id === serviceId)
                          if (!service) return null

                          return (
                            <Card key={serviceId}>
                              <CardContent className="pt-4">
                                <div className="flex justify-between items-start mb-3">
                                  <div>
                                    <h3 className="font-medium">{service.name}</h3>
                                    <p className="text-sm text-gray-600">
                                      {service.code} • ${service.price}
                                    </p>
                                  </div>
                                  <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                      const newSelected = new Map(selectedTests)
                                      newSelected.delete(serviceId)
                                      setSelectedTests(newSelected)
                                    }}
                                  >
                                    <X className="h-4 w-4" />
                                  </Button>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                  <div>
                                    <Label htmlFor={`priority-${serviceId}`}>Priority</Label>
                                    <Select
                                      value={order.priority}
                                      onValueChange={(value) => updateTestOrder(serviceId, { priority: value as any })}
                                    >
                                      <SelectTrigger>
                                        <SelectValue />
                                      </SelectTrigger>
                                      <SelectContent>
                                        <SelectItem value="routine">Routine</SelectItem>
                                        <SelectItem value="urgent">Urgent</SelectItem>
                                        <SelectItem value="stat">STAT</SelectItem>
                                      </SelectContent>
                                    </Select>
                                  </div>

                                  <div className="flex items-center">
                                    <Badge className={getPriorityColor(order.priority)}>
                                      {order.priority.toUpperCase()}
                                    </Badge>
                                    {order.priority === 'stat' && (
                                      <AlertTriangle className="h-4 w-4 ml-2 text-red-500" />
                                    )}
                                  </div>
                                </div>

                                <div className="mt-3">
                                  <Label htmlFor={`instructions-${serviceId}`}>Special Instructions</Label>
                                  <Textarea
                                    id={`instructions-${serviceId}`}
                                    placeholder="Any special instructions for collection or processing..."
                                    value={order.special_instructions || ''}
                                    onChange={(e) => updateTestOrder(serviceId, { special_instructions: e.target.value })}
                                    className="mt-1"
                                    rows={2}
                                  />
                                </div>
                              </CardContent>
                            </Card>
                          )
                        })}
                      </div>
                    )}
                  </div>

                  {/* Cart Summary */}
                  {selectedTests.size > 0 && (
                    <div className="border-t pt-4 mt-4">
                      <div className="flex justify-between items-center mb-4">
                        <div>
                          <p className="text-lg font-semibold">
                            Total: ${totalCost.toFixed(2)}
                          </p>
                          <p className="text-sm text-gray-600">
                            {selectedTests.size} test{selectedTests.size !== 1 ? 's' : ''} selected
                          </p>
                        </div>
                        <div className="flex gap-2">
                          <Button variant="outline" onClick={() => setSelectedTests(new Map())}>
                            Clear All
                          </Button>
                          <Button onClick={handleSubmit}>
                            <TestTube className="h-4 w-4 mr-2" />
                            Submit Orders
                          </Button>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              </TabsContent>
            </div>
          </Tabs>
        </div>
      </DialogContent>
    </Dialog>
  )
}