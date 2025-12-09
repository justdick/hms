import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { ArrowDown, ArrowUp, Minus } from "lucide-react"

import { cn } from "@/lib/utils"

const statCardVariants = cva(
  "bg-card text-card-foreground flex items-center gap-3 rounded-lg border p-3 shadow-sm",
  {
    variants: {
      variant: {
        default: "border-border",
        success: "border-success/30 bg-success/5",
        warning: "border-warning/30 bg-warning/5",
        error: "border-error/30 bg-error/5",
        info: "border-info/30 bg-info/5",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

const statCardIconVariants = cva(
  "flex h-9 w-9 shrink-0 items-center justify-center rounded-md",
  {
    variants: {
      variant: {
        default: "bg-primary/10 text-primary",
        success: "bg-success/10 text-success",
        warning: "bg-warning/10 text-warning",
        error: "bg-error/10 text-error",
        info: "bg-info/10 text-info",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

export interface StatCardTrend {
  value: number
  direction: "up" | "down" | "neutral"
}

export interface StatCardProps
  extends React.ComponentProps<"div">,
    VariantProps<typeof statCardVariants> {
  label: string
  value: string | number
  icon?: React.ReactNode
  trend?: StatCardTrend
}

function TrendIndicator({ trend }: { trend: StatCardTrend }) {
  const { value, direction } = trend
  
  const trendClasses = cn(
    "inline-flex items-center gap-0.5 text-xs font-medium",
    {
      "text-success": direction === "up",
      "text-error": direction === "down",
      "text-muted-foreground": direction === "neutral",
    }
  )

  const TrendIcon = direction === "up" 
    ? ArrowUp 
    : direction === "down" 
      ? ArrowDown 
      : Minus

  return (
    <span className={trendClasses} data-testid="trend-indicator" data-direction={direction}>
      <TrendIcon className="h-3 w-3" aria-hidden="true" />
      <span data-testid="trend-value">{Math.abs(value)}%</span>
    </span>
  )
}

function StatCard({
  className,
  variant,
  label,
  value,
  icon,
  trend,
  ...props
}: StatCardProps) {
  return (
    <div
      data-slot="stat-card"
      className={cn(statCardVariants({ variant }), className)}
      {...props}
    >
      {icon && (
        <div className={cn(statCardIconVariants({ variant }))}>
          {icon}
        </div>
      )}
      <div className="flex min-w-0 flex-1 flex-col gap-0.5">
        <span className="text-xs font-medium text-muted-foreground truncate">
          {label}
        </span>
        <div className="flex items-baseline gap-2">
          <span className="text-xl font-semibold leading-none tracking-tight">
            {value}
          </span>
          {trend && <TrendIndicator trend={trend} />}
        </div>
      </div>
    </div>
  )
}

export { StatCard, statCardVariants, TrendIndicator }
