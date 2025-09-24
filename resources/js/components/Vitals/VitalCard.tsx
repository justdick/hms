import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { AlertTriangle, TrendingUp, TrendingDown, Minus } from 'lucide-react'
import { cn } from '@/lib/utils'

interface VitalCardProps {
  title: string
  current: string
  unit?: string
  normalRange: string
  status: 'normal' | 'elevated' | 'high' | 'low' | 'critical'
  trend?: number[]
  lastReading?: string
  alert?: string
  icon?: React.ReactNode
}

export default function VitalCard({
  title,
  current,
  unit,
  normalRange,
  status,
  trend = [],
  lastReading,
  alert,
  icon
}: VitalCardProps) {
  const getStatusConfig = (status: string) => {
    const configs = {
      normal: {
        bgColor: 'bg-green-50 dark:bg-green-950',
        textColor: 'text-green-700 dark:text-green-300',
        badgeVariant: 'secondary' as const,
        borderColor: 'border-green-200 dark:border-green-800'
      },
      elevated: {
        bgColor: 'bg-yellow-50 dark:bg-yellow-950',
        textColor: 'text-yellow-700 dark:text-yellow-300',
        badgeVariant: 'secondary' as const,
        borderColor: 'border-yellow-200 dark:border-yellow-800'
      },
      high: {
        bgColor: 'bg-orange-50 dark:bg-orange-950',
        textColor: 'text-orange-700 dark:text-orange-300',
        badgeVariant: 'secondary' as const,
        borderColor: 'border-orange-200 dark:border-orange-800'
      },
      low: {
        bgColor: 'bg-blue-50 dark:bg-blue-950',
        textColor: 'text-blue-700 dark:text-blue-300',
        badgeVariant: 'secondary' as const,
        borderColor: 'border-blue-200 dark:border-blue-800'
      },
      critical: {
        bgColor: 'bg-red-50 dark:bg-red-950',
        textColor: 'text-red-700 dark:text-red-300',
        badgeVariant: 'destructive' as const,
        borderColor: 'border-red-200 dark:border-red-800'
      }
    }
    return configs[status as keyof typeof configs] || configs.normal
  }

  const getTrendIcon = () => {
    if (trend.length < 2) return <Minus className="h-4 w-4" />

    const latest = trend[trend.length - 1]
    const previous = trend[trend.length - 2]

    if (latest > previous) return <TrendingUp className="h-4 w-4 text-red-500" />
    if (latest < previous) return <TrendingDown className="h-4 w-4 text-green-500" />
    return <Minus className="h-4 w-4 text-gray-500" />
  }

  const statusConfig = getStatusConfig(status)

  return (
    <Card className={cn("relative transition-all hover:shadow-md", statusConfig.borderColor)}>
      <CardHeader className="pb-2">
        <CardTitle className="text-sm font-medium text-gray-600 dark:text-gray-300 flex items-center gap-2">
          {icon}
          {title}
          {alert && status === 'critical' && (
            <AlertTriangle className="h-4 w-4 text-red-500 animate-pulse" />
          )}
        </CardTitle>
      </CardHeader>
      <CardContent className={cn("pt-0", statusConfig.bgColor)}>
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <div>
              <div className={cn("text-2xl font-bold", statusConfig.textColor)}>
                {current}
                {unit && <span className="text-sm ml-1">{unit}</span>}
              </div>
              <div className="text-xs text-gray-500 dark:text-gray-400">
                Normal: {normalRange}
              </div>
            </div>
            <div className="flex flex-col items-end gap-1">
              {getTrendIcon()}
              <Badge variant={statusConfig.badgeVariant} className="text-xs">
                {status.toUpperCase()}
              </Badge>
            </div>
          </div>

          {trend.length > 0 && (
            <div className="mt-3">
              <div className="flex items-end justify-between h-8 gap-1">
                {trend.slice(-5).map((value, index) => {
                  const maxValue = Math.max(...trend.slice(-5))
                  const minValue = Math.min(...trend.slice(-5))
                  const range = maxValue - minValue || 1
                  const height = ((value - minValue) / range) * 24 + 4

                  return (
                    <div
                      key={index}
                      className={cn(
                        "flex-1 rounded-sm transition-all",
                        index === trend.slice(-5).length - 1
                          ? statusConfig.textColor.replace('text-', 'bg-')
                          : 'bg-gray-300 dark:bg-gray-600'
                      )}
                      style={{ height: `${height}px` }}
                      title={`Reading ${index + 1}: ${value}`}
                    />
                  )
                })}
              </div>
              <div className="text-xs text-gray-500 dark:text-gray-400 mt-1 text-center">
                Last 5 readings
              </div>
            </div>
          )}

          {lastReading && (
            <div className="text-xs text-gray-500 dark:text-gray-400 pt-1 border-t border-gray-200 dark:border-gray-700">
              Last: {lastReading}
            </div>
          )}

          {alert && (
            <div className="text-xs font-medium text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/30 p-2 rounded flex items-center gap-1">
              <AlertTriangle className="h-3 w-3" />
              {alert}
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  )
}