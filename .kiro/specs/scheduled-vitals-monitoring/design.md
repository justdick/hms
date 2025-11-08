# Design Document: Scheduled Vitals Monitoring

## Overview

This design document outlines the implementation of a scheduled vitals monitoring system for admitted patients. The system will enable healthcare staff to configure vitals recording intervals, receive toast notifications with sound alerts when vitals are due or overdue, and view vitals status across ward and patient pages. The solution leverages Laravel's queue system for scheduling, Inertia.js for real-time UI updates, and browser APIs for audio notifications.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Frontend (React + Inertia)              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │ Ward Pages   │  │ Patient Pages│  │ Toast System │     │
│  │ - Show       │  │ - Show       │  │ - Alerts     │     │
│  │ - Dashboard  │  │ - Vitals     │  │ - Sounds     │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
│         │                  │                  │             │
│         └──────────────────┴──────────────────┘             │
│                            │                                │
└────────────────────────────┼────────────────────────────────┘
                             │
                    ┌────────▼────────┐
                    │  Inertia Bridge │
                    └────────┬────────┘
                             │
┌────────────────────────────┼────────────────────────────────┐
│                     Backend (Laravel)                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │ Controllers  │  │   Services   │  │    Models    │     │
│  │ - Vitals     │  │ - Schedule   │  │ - Schedule   │     │
│  │ - Schedule   │  │ - Alert      │  │ - Admission  │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
│         │                  │                  │             │
│         └──────────────────┴──────────────────┘             │
│                            │                                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   Commands   │  │    Events    │  │   Database   │     │
│  │ - Check Due  │  │ - Vitals Due │  │ - Schedules  │     │
│  │   Vitals     │  │ - Overdue    │  │ - Vitals     │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

### Technology Stack

- **Backend**: Laravel 12, PHP 8.3
- **Frontend**: React 19, Inertia.js v2, TypeScript
- **Styling**: Tailwind CSS v4
- **Database**: MySQL
- **Queue**: Laravel Queue (database driver)
- **Real-time**: Polling mechanism (every 30 seconds)
- **Audio**: Web Audio API / HTML5 Audio

## Components and Interfaces

### 1. Database Schema

#### New Table: `vitals_schedules`

```sql
CREATE TABLE vitals_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_admission_id BIGINT UNSIGNED NOT NULL,
    interval_minutes INT UNSIGNED NOT NULL,
    next_due_at TIMESTAMP NULL,
    last_recorded_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (patient_admission_id) REFERENCES patient_admissions(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_next_due_at (next_due_at, is_active),
    INDEX idx_admission_active (patient_admission_id, is_active)
);
```

#### New Table: `vitals_alerts`

```sql
CREATE TABLE vitals_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vitals_schedule_id BIGINT UNSIGNED NOT NULL,
    patient_admission_id BIGINT UNSIGNED NOT NULL,
    due_at TIMESTAMP NOT NULL,
    status ENUM('pending', 'due', 'overdue', 'completed', 'dismissed') DEFAULT 'pending',
    acknowledged_at TIMESTAMP NULL,
    acknowledged_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (vitals_schedule_id) REFERENCES vitals_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_admission_id) REFERENCES patient_admissions(id) ON DELETE CASCADE,
    FOREIGN KEY (acknowledged_by) REFERENCES users(id),
    INDEX idx_status_due (status, due_at),
    INDEX idx_admission_status (patient_admission_id, status)
);
```

### 2. Models

#### VitalsSchedule Model

```php
namespace App\Models;

class VitalsSchedule extends Model
{
    protected $fillable = [
        'patient_admission_id',
        'interval_minutes',
        'next_due_at',
        'last_recorded_at',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'interval_minutes' => 'integer',
            'next_due_at' => 'datetime',
            'last_recorded_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function patientAdmission(): BelongsTo;
    public function createdBy(): BelongsTo;
    public function alerts(): HasMany;
    public function activeAlert(): HasOne;

    // Methods
    public function calculateNextDueTime(Carbon $fromTime): Carbon;
    public function updateNextDueTime(): void;
    public function getCurrentStatus(): string; // 'upcoming', 'due', 'overdue'
    public function getTimeUntilDue(): ?int; // minutes
    public function getTimeOverdue(): ?int; // minutes
    public function markAsCompleted(VitalSign $vitalSign): void;
}
```

#### VitalsAlert Model

```php
namespace App\Models;

class VitalsAlert extends Model
{
    protected $fillable = [
        'vitals_schedule_id',
        'patient_admission_id',
        'due_at',
        'status',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    // Relationships
    public function vitalsSchedule(): BelongsTo;
    public function patientAdmission(): BelongsTo;
    public function acknowledgedBy(): BelongsTo;

    // Methods
    public function markAsDue(): void;
    public function markAsOverdue(): void;
    public function markAsCompleted(): void;
    public function acknowledge(User $user): void;
}
```

### 3. Services

#### VitalsScheduleService

```php
namespace App\Services;

class VitalsScheduleService
{
    public function createSchedule(
        PatientAdmission $admission,
        int $intervalMinutes,
        User $createdBy
    ): VitalsSchedule;

    public function updateSchedule(
        VitalsSchedule $schedule,
        int $intervalMinutes
    ): VitalsSchedule;

    public function disableSchedule(VitalsSchedule $schedule): void;

    public function calculateNextDueTime(
        VitalsSchedule $schedule,
        Carbon $fromTime
    ): Carbon;

    public function recordVitalsCompleted(
        VitalsSchedule $schedule,
        VitalSign $vitalSign
    ): void;

    public function getScheduleStatus(VitalsSchedule $schedule): array;
}
```

#### VitalsAlertService

```php
namespace App\Services;

class VitalsAlertService
{
    public function checkDueAlerts(): Collection;
    
    public function checkOverdueAlerts(): Collection;
    
    public function createAlert(VitalsSchedule $schedule): VitalsAlert;
    
    public function updateAlertStatus(VitalsAlert $alert, string $status): void;
    
    public function getActiveAlertsForWard(Ward $ward): Collection;
    
    public function getActiveAlertsForUser(User $user): Collection;
    
    public function acknowledgeAlert(VitalsAlert $alert, User $user): void;
    
    public function dismissAlert(VitalsAlert $alert, User $user): void;
}
```

### 4. Controllers

#### VitalsScheduleController

```php
namespace App\Http\Controllers\Ward;

class VitalsScheduleController extends Controller
{
    // POST /wards/{ward}/patients/{admission}/vitals-schedule
    public function store(Request $request, Ward $ward, PatientAdmission $admission);
    
    // PUT /wards/{ward}/patients/{admission}/vitals-schedule/{schedule}
    public function update(Request $request, Ward $ward, PatientAdmission $admission, VitalsSchedule $schedule);
    
    // DELETE /wards/{ward}/patients/{admission}/vitals-schedule/{schedule}
    public function destroy(Ward $ward, PatientAdmission $admission, VitalsSchedule $schedule);
    
    // GET /wards/{ward}/vitals-alerts
    public function alerts(Ward $ward);
}
```

#### VitalsAlertController

```php
namespace App\Http\Controllers\Ward;

class VitalsAlertController extends Controller
{
    // GET /api/vitals-alerts/active
    public function active(Request $request);
    
    // POST /api/vitals-alerts/{alert}/acknowledge
    public function acknowledge(VitalsAlert $alert);
    
    // POST /api/vitals-alerts/{alert}/dismiss
    public function dismiss(VitalsAlert $alert);
}
```

### 5. Commands

#### CheckDueVitalsCommand

```php
namespace App\Console\Commands;

class CheckDueVitalsCommand extends Command
{
    protected $signature = 'vitals:check-due';
    protected $description = 'Check for due and overdue vitals schedules';

    public function handle(VitalsAlertService $alertService): int
    {
        // Check for vitals that are now due (within grace period)
        // Check for vitals that are overdue (past grace period)
        // Update alert statuses accordingly
        // Create new alerts as needed
    }
}
```

This command will be scheduled to run every minute in `routes/console.php`:

```php
Schedule::command('vitals:check-due')->everyMinute();
```

### 6. Frontend Components

#### React Components Structure

```
resources/js/
├── components/
│   ├── Ward/
│   │   ├── VitalsScheduleModal.tsx
│   │   ├── VitalsStatusBadge.tsx
│   │   ├── VitalsAlertToast.tsx
│   │   └── VitalsAlertDashboard.tsx
│   └── ui/
│       └── toast.tsx (existing)
├── hooks/
│   ├── useVitalsAlerts.ts
│   ├── useVitalsSchedule.ts
│   └── useSoundAlert.ts
└── lib/
    └── audio.ts
```

#### VitalsScheduleModal Component

Modal for creating/editing vitals schedules with:
- Interval selection (dropdown with preset options + custom input)
- Start time configuration
- Preview of next due times
- Save/Cancel actions

#### VitalsStatusBadge Component

Visual indicator showing:
- Status (Upcoming, Due, Overdue)
- Time until due / time overdue
- Color coding (green, yellow, red)
- Click to view details

#### VitalsAlertToast Component

Toast notification with:
- Patient information (name, bed number)
- Alert type (due vs overdue)
- Time information
- Action buttons (Record Vitals, Dismiss)
- Sound trigger on display

#### VitalsAlertDashboard Component

Dashboard view showing:
- List of all active schedules
- Filterable by ward
- Sortable by urgency
- Quick actions for each patient

### 7. Hooks

#### useVitalsAlerts Hook

```typescript
interface VitalsAlert {
    id: number;
    patient_admission_id: number;
    patient_name: string;
    bed_number: string;
    due_at: string;
    status: 'pending' | 'due' | 'overdue';
    time_overdue_minutes?: number;
}

function useVitalsAlerts(wardId?: number) {
    const [alerts, setAlerts] = useState<VitalsAlert[]>([]);
    const [loading, setLoading] = useState(true);
    
    // Poll for alerts every 30 seconds
    useEffect(() => {
        const fetchAlerts = async () => {
            // Fetch active alerts from API
        };
        
        fetchAlerts();
        const interval = setInterval(fetchAlerts, 30000);
        
        return () => clearInterval(interval);
    }, [wardId]);
    
    const acknowledgeAlert = async (alertId: number) => {
        // POST to acknowledge endpoint
    };
    
    const dismissAlert = async (alertId: number) => {
        // POST to dismiss endpoint
    };
    
    return { alerts, loading, acknowledgeAlert, dismissAlert };
}
```

#### useSoundAlert Hook

```typescript
interface SoundAlertOptions {
    enabled: boolean;
    volume: number;
    soundType: 'gentle' | 'urgent';
}

function useSoundAlert() {
    const [settings, setSettings] = useState<SoundAlertOptions>({
        enabled: true,
        volume: 0.7,
        soundType: 'gentle',
    });
    
    const playAlert = (type: 'gentle' | 'urgent') => {
        if (!settings.enabled) return;
        
        const audio = new Audio(`/sounds/vitals-alert-${type}.mp3`);
        audio.volume = settings.volume;
        audio.play();
    };
    
    const updateSettings = (newSettings: Partial<SoundAlertOptions>) => {
        setSettings(prev => ({ ...prev, ...newSettings }));
        // Persist to localStorage
    };
    
    return { settings, playAlert, updateSettings };
}
```

## Data Models

### VitalsSchedule Data Flow

1. **Creation**: Nurse creates schedule for admitted patient
   - Input: `patient_admission_id`, `interval_minutes`
   - Calculate: `next_due_at` = now + interval
   - Store: VitalsSchedule record

2. **Monitoring**: Scheduled command checks for due vitals
   - Query: Schedules where `next_due_at` <= now + 15 minutes
   - Create/Update: VitalsAlert records
   - Status transitions:
     - `pending` → `due` (when current time >= due_at)
     - `due` → `overdue` (when current time >= due_at + 15 minutes)

3. **Recording**: Nurse records vitals
   - Input: VitalSign data
   - Update: `last_recorded_at` = recorded_at
   - Calculate: `next_due_at` = recorded_at + interval
   - Update: Alert status to `completed`

4. **Discharge**: Patient is discharged
   - Update: `is_active` = false
   - Update: All pending alerts to `dismissed`

### Alert Status State Machine

```
┌─────────┐
│ pending │ (created when schedule is set)
└────┬────┘
     │
     ▼
┌─────────┐
│   due   │ (current time >= due_at)
└────┬────┘
     │
     ├──────────────┐
     │              │
     ▼              ▼
┌──────────┐   ┌───────────┐
│ overdue  │   │ completed │ (vitals recorded)
└────┬─────┘   └───────────┘
     │
     ▼
┌───────────┐
│ completed │ (vitals recorded late)
└───────────┘
```

## Error Handling

### Backend Error Scenarios

1. **Invalid Interval**
   - Validation: Interval must be between 15 minutes and 24 hours
   - Response: 422 Unprocessable Entity with error message

2. **Schedule Already Exists**
   - Check: One active schedule per admission
   - Action: Update existing schedule instead of creating new

3. **Discharged Patient**
   - Validation: Cannot create schedule for discharged patient
   - Response: 422 with appropriate message

4. **Missing Permissions**
   - Authorization: Only nurses and doctors can manage schedules
   - Response: 403 Forbidden

### Frontend Error Scenarios

1. **Audio Playback Failure**
   - Fallback: Show visual-only notification
   - Log: Console warning for debugging

2. **API Polling Failure**
   - Retry: Exponential backoff (30s, 60s, 120s)
   - Notification: Show connection error after 3 failures

3. **Browser Notification Permission Denied**
   - Fallback: In-app notifications only
   - Prompt: One-time request for permission

## Testing Strategy

### Unit Tests

1. **VitalsSchedule Model**
   - Test `calculateNextDueTime()` with various intervals
   - Test `getCurrentStatus()` for different time scenarios
   - Test `getTimeUntilDue()` and `getTimeOverdue()` calculations

2. **VitalsScheduleService**
   - Test schedule creation with valid/invalid data
   - Test schedule updates and deactivation
   - Test vitals completion flow

3. **VitalsAlertService**
   - Test alert creation and status updates
   - Test due/overdue detection logic
   - Test ward and user alert filtering

### Feature Tests

1. **Schedule Management**
   - Test creating schedule via API
   - Test updating schedule interval
   - Test disabling schedule
   - Test automatic deactivation on discharge

2. **Alert Generation**
   - Test command creates alerts at due time
   - Test status transitions (pending → due → overdue)
   - Test grace period (15 minutes)
   - Test alert completion when vitals recorded

3. **API Endpoints**
   - Test fetching active alerts for ward
   - Test acknowledging alerts
   - Test dismissing alerts
   - Test authorization for all endpoints

### Browser Tests (Pest v4)

1. **Toast Notifications**
   - Test toast appears when alert is due
   - Test toast styling for due vs overdue
   - Test toast actions (Record Vitals, Dismiss)
   - Test toast auto-dismiss timing

2. **Sound Alerts**
   - Test audio plays on due alert
   - Test different sounds for due vs overdue
   - Test volume control
   - Test mute functionality

3. **Ward Page Integration**
   - Test vitals status badges display
   - Test filtering by status
   - Test navigation to vitals recording

4. **Patient Page Integration**
   - Test schedule display
   - Test schedule creation modal
   - Test schedule editing
   - Test quick record vitals button

### Integration Tests

1. **End-to-End Flow**
   - Create schedule → Wait for due time → Receive alert → Record vitals → Next alert scheduled
   - Test with multiple patients and overlapping schedules
   - Test discharge flow stops alerts

2. **Real-time Updates**
   - Test polling mechanism updates UI
   - Test multiple users see same alerts
   - Test alert dismissal syncs across sessions

## Implementation Notes

### Performance Considerations

1. **Database Indexing**
   - Index on `next_due_at` and `is_active` for efficient due vitals queries
   - Index on `patient_admission_id` for quick lookups

2. **Query Optimization**
   - Eager load relationships when fetching schedules
   - Use database-level filtering for active schedules
   - Cache ward alert counts for dashboard

3. **Frontend Optimization**
   - Debounce polling requests
   - Use React.memo for toast components
   - Lazy load audio files

### Security Considerations

1. **Authorization**
   - Verify user has access to ward before showing alerts
   - Validate admission belongs to ward in all endpoints
   - Audit log for schedule changes

2. **Data Validation**
   - Sanitize interval inputs
   - Validate timestamps are in future
   - Prevent duplicate schedules

### Scalability Considerations

1. **Queue System**
   - Use Laravel queues for alert processing
   - Batch alert creation for multiple patients
   - Consider Redis for high-volume deployments

2. **Notification Delivery**
   - Current: Polling every 30 seconds
   - Future: WebSocket/Pusher for real-time updates
   - Future: Mobile push notifications

### Browser Compatibility

- Audio API: Supported in all modern browsers
- localStorage: For user preferences
- Notification API: Optional, graceful degradation

## Migration Path

### Phase 1: Database and Models
1. Create migrations for new tables
2. Create models with relationships
3. Add factory and seeders for testing

### Phase 2: Backend Services
1. Implement VitalsScheduleService
2. Implement VitalsAlertService
3. Create scheduled command
4. Add routes and controllers

### Phase 3: Frontend Components
1. Create hooks for alerts and sound
2. Build VitalsScheduleModal
3. Build VitalsStatusBadge
4. Build VitalsAlertToast

### Phase 4: Integration
1. Update Ward Show page
2. Update Patient Show page
3. Add alert polling
4. Add sound alert system

### Phase 5: Testing and Refinement
1. Write unit tests
2. Write feature tests
3. Write browser tests
4. User acceptance testing
5. Performance optimization

## Future Enhancements

1. **Advanced Scheduling**
   - Different intervals for day/night shifts
   - Critical patient priority alerts
   - Escalation to supervisors for missed vitals

2. **Analytics**
   - Vitals compliance reports
   - Average response time to alerts
   - Missed vitals tracking

3. **Mobile App**
   - Native push notifications
   - Offline vitals recording
   - Wearable device integration

4. **AI/ML Features**
   - Predict optimal vitals intervals based on patient condition
   - Anomaly detection in vitals trends
   - Smart alert prioritization
