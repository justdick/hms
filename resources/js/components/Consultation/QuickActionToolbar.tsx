import { useState } from 'react'
import * as React from 'react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Pill,
  TestTube,
  FileText,
  Calendar,
  Phone,
  Camera,
  Printer,
  Send,
  ChevronDown,
  Clock,
  User,
  Building,
  CreditCard,
  Stethoscope,
  Activity,
  Plus,
  Eye,
  CheckCircle
} from 'lucide-react'
import { cn } from '@/lib/utils'

interface QuickAction {
  id: string
  label: string
  icon: React.ComponentType<any>
  color: string
  shortcut?: string
  disabled?: boolean
  badge?: string | number
}

interface QuickActionToolbarProps {
  onPrescriptionClick?: () => void
  onLabOrderClick?: () => void
  onReferralClick?: () => void
  onFollowUpClick?: () => void
  onContactClick?: () => void
  onVitalsClick?: () => void
  onBillingClick?: () => void
  onPrintClick?: () => void
  onImageCaptureClick?: () => void
  onCompleteConsultationClick?: () => void

  // Data for badges and status
  pendingPrescriptions?: number
  pendingLabOrders?: number
  consultationStatus?: string
  isFloating?: boolean
  className?: string
}

export default function QuickActionToolbar({
  onPrescriptionClick,
  onLabOrderClick,
  onReferralClick,
  onFollowUpClick,
  onContactClick,
  onVitalsClick,
  onBillingClick,
  onPrintClick,
  onImageCaptureClick,
  onCompleteConsultationClick,
  pendingPrescriptions = 0,
  pendingLabOrders = 0,
  consultationStatus = 'in_progress',
  isFloating = false,
  className
}: QuickActionToolbarProps) {
  const [isExpanded, setIsExpanded] = useState(false)

  const primaryActions: QuickAction[] = [
    {
      id: 'prescribe',
      label: 'Prescribe',
      icon: Pill,
      color: 'bg-green-500 hover:bg-green-600 text-white',
      shortcut: 'Ctrl+P',
      badge: pendingPrescriptions > 0 ? pendingPrescriptions : undefined
    },
    {
      id: 'lab-order',
      label: 'Order Lab',
      icon: TestTube,
      color: 'bg-blue-500 hover:bg-blue-600 text-white',
      shortcut: 'Ctrl+L',
      badge: pendingLabOrders > 0 ? pendingLabOrders : undefined
    },
    {
      id: 'vitals',
      label: 'Record Vitals',
      icon: Activity,
      color: 'bg-red-500 hover:bg-red-600 text-white',
      shortcut: 'Ctrl+V'
    },
    {
      id: 'referral',
      label: 'Referral',
      icon: Send,
      color: 'bg-purple-500 hover:bg-purple-600 text-white',
      shortcut: 'Ctrl+R'
    }
  ]

  const secondaryActions: QuickAction[] = [
    {
      id: 'follow-up',
      label: 'Schedule Follow-up',
      icon: Calendar,
      color: 'bg-orange-500 hover:bg-orange-600 text-white'
    },
    {
      id: 'contact',
      label: 'Contact Patient',
      icon: Phone,
      color: 'bg-teal-500 hover:bg-teal-600 text-white'
    },
    {
      id: 'image-capture',
      label: 'Capture Image',
      icon: Camera,
      color: 'bg-indigo-500 hover:bg-indigo-600 text-white'
    },
    {
      id: 'print',
      label: 'Print Summary',
      icon: Printer,
      color: 'bg-gray-600 hover:bg-gray-700 text-white'
    },
    {
      id: 'billing',
      label: 'Billing',
      icon: CreditCard,
      color: 'bg-yellow-500 hover:bg-yellow-600 text-white'
    }
  ]

  const handleActionClick = (actionId: string) => {
    switch (actionId) {
      case 'prescribe':
        onPrescriptionClick?.()
        break
      case 'lab-order':
        onLabOrderClick?.()
        break
      case 'referral':
        onReferralClick?.()
        break
      case 'follow-up':
        onFollowUpClick?.()
        break
      case 'contact':
        onContactClick?.()
        break
      case 'vitals':
        onVitalsClick?.()
        break
      case 'billing':
        onBillingClick?.()
        break
      case 'print':
        onPrintClick?.()
        break
      case 'image-capture':
        onImageCaptureClick?.()
        break
      default:
        console.log(`Action ${actionId} clicked`)
    }
  }

  const ActionButton = ({ action, size = 'default' }: { action: QuickAction; size?: 'sm' | 'default' | 'lg' }) => (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            onClick={() => handleActionClick(action.id)}
            className={cn(action.color, 'relative')}
            size={size}
            disabled={action.disabled}
          >
            <action.icon className={cn(
              size === 'sm' ? 'h-4 w-4' : 'h-5 w-5',
              size !== 'sm' && 'mr-2'
            )} />
            {size !== 'sm' && action.label}
            {action.badge && (
              <Badge
                className="absolute -top-2 -right-2 h-5 w-5 p-0 flex items-center justify-center bg-red-500 text-white text-xs"
                variant="default"
              >
                {action.badge}
              </Badge>
            )}
          </Button>
        </TooltipTrigger>
        <TooltipContent>
          <p>{action.label}</p>
          {action.shortcut && (
            <p className="text-xs text-gray-400">{action.shortcut}</p>
          )}
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  )

  if (isFloating) {
    return (
      <div className={cn(
        "fixed bottom-6 right-6 z-50 flex flex-col gap-2",
        isExpanded ? "items-end" : "items-center",
        className
      )}>
        {/* Secondary actions (shown when expanded) */}
        {isExpanded && (
          <div className="flex flex-col gap-2 mb-2">
            {secondaryActions.map((action) => (
              <ActionButton key={action.id} action={action} size="sm" />
            ))}
          </div>
        )}

        {/* Primary actions */}
        <div className={cn(
          "flex gap-2 p-3 bg-white dark:bg-gray-800 rounded-full shadow-lg border",
          isExpanded ? "flex-col" : "flex-row"
        )}>
          {primaryActions.map((action) => (
            <ActionButton key={action.id} action={action} size="sm" />
          ))}

          {/* Expand/Collapse button */}
          <Button
            onClick={() => setIsExpanded(!isExpanded)}
            variant="outline"
            size="sm"
            className="rounded-full"
          >
            <ChevronDown className={cn(
              "h-4 w-4 transition-transform",
              isExpanded && "rotate-180"
            )} />
          </Button>
        </div>

        {/* Complete consultation button (always visible) */}
        {consultationStatus === 'in_progress' && onCompleteConsultationClick && (
          <Button
            onClick={onCompleteConsultationClick}
            className="bg-green-600 hover:bg-green-700 text-white shadow-lg"
            size="lg"
          >
            <CheckCircle className="h-5 w-5 mr-2" />
            Complete Consultation
          </Button>
        )}
      </div>
    )
  }

  // Regular toolbar (not floating)
  return (
    <div className={cn(
      "bg-white dark:bg-gray-800 border rounded-lg p-4 shadow-sm",
      className
    )}>
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
          <Stethoscope className="h-5 w-5" />
          Quick Actions
        </h3>
        {consultationStatus === 'in_progress' && (
          <Badge variant="default" className="bg-green-100 text-green-800">
            <Clock className="h-3 w-3 mr-1" />
            In Progress
          </Badge>
        )}
      </div>

      {/* Primary Actions */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        {primaryActions.map((action) => (
          <ActionButton key={action.id} action={action} />
        ))}
      </div>

      {/* Secondary Actions */}
      <details className="group">
        <summary className="flex items-center justify-center cursor-pointer text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">
          <span>More Actions</span>
          <ChevronDown className="h-4 w-4 ml-1 transition-transform group-open:rotate-180" />
        </summary>
        <div className="mt-3 grid grid-cols-2 md:grid-cols-5 gap-2">
          {secondaryActions.map((action) => (
            <ActionButton key={action.id} action={action} size="sm" />
          ))}
        </div>
      </details>

      {/* Complete Consultation */}
      {consultationStatus === 'in_progress' && onCompleteConsultationClick && (
        <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
          <Button
            onClick={onCompleteConsultationClick}
            className="w-full bg-green-600 hover:bg-green-700 text-white"
            size="lg"
          >
            <FileText className="h-5 w-5 mr-2" />
            Complete Consultation
          </Button>
        </div>
      )}
    </div>
  )
}

// Keyboard shortcuts hook (optional enhancement)
export const useQuickActionShortcuts = (actions: {
  onPrescriptionClick?: () => void
  onLabOrderClick?: () => void
  onVitalsClick?: () => void
  onReferralClick?: () => void
}) => {
  React.useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.ctrlKey) {
        switch (e.key.toLowerCase()) {
          case 'p':
            e.preventDefault()
            actions.onPrescriptionClick?.()
            break
          case 'l':
            e.preventDefault()
            actions.onLabOrderClick?.()
            break
          case 'v':
            e.preventDefault()
            actions.onVitalsClick?.()
            break
          case 'r':
            e.preventDefault()
            actions.onReferralClick?.()
            break
        }
      }
    }

    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [actions])
}