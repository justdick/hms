# Consultation Dashboard Pagination

## Overview
Add pagination and search to all three sections of the Consultation Dashboard:
1. Awaiting Consultation
2. Active Consultations  
3. Completed Consultations

## Current Issues
- Completed tab limited to 50 records
- No search in Completed tab
- Large datasets can't be navigated

## Solution

### Backend Changes (ConsultationController)

1. Add separate search parameters for each tab:
   - `awaiting_search` - search in awaiting queue
   - `active_search` - search in active consultations
   - `completed_search` - search in completed consultations

2. Use Laravel pagination (10 per page default):
   - `awaitingConsultation` → paginated
   - `activeConsultations` → paginated
   - `completedConsultations` → paginated

3. Add page parameters:
   - `awaiting_page`
   - `active_page`
   - `completed_page`

### Frontend Changes (Consultation/Index.tsx)

1. Add search input to Patient Queue tab (for awaiting)
2. Add search input to Active section
3. Add search input to Completed tab
4. Add pagination controls to each section
5. Handle page navigation with Inertia

### Data Structure

```typescript
interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}
```

## Implementation Steps

1. Update ConsultationController to use pagination
2. Update frontend types for paginated data
3. Add Pagination component
4. Add search inputs to each section
5. Test all three sections
