# Design Document: UI Theming System

## Overview

This design establishes a professional, healthcare-appropriate UI theming system for the HMS application. The system uses CSS variables for centralized theme management, provides improved component variants (compact stat cards, better tables), and includes an admin UI for theme customization. The architecture ensures that theme changes propagate automatically throughout the application.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Application                               │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐    ┌─────────────────┐                     │
│  │  ThemeProvider  │───▶│  CSS Variables  │                     │
│  │  (React Context)│    │  (app.css)      │                     │
│  └────────┬────────┘    └────────┬────────┘                     │
│           │                      │                               │
│           ▼                      ▼                               │
│  ┌─────────────────┐    ┌─────────────────┐                     │
│  │  useTheme Hook  │    │  Components     │                     │
│  │                 │    │  (use variables)│                     │
│  └────────┬────────┘    └─────────────────┘                     │
│           │                                                      │
│           ▼                                                      │
│  ┌─────────────────────────────────────────┐                    │
│  │         Theme Settings Page              │                    │
│  │  - Color pickers                         │                    │
│  │  - Logo upload                           │                    │
│  │  - Live preview                          │                    │
│  └────────────────────┬────────────────────┘                    │
│                       │                                          │
│                       ▼                                          │
│  ┌─────────────────────────────────────────┐                    │
│  │         Backend API                      │                    │
│  │  - ThemeSettingController                │                    │
│  │  - ThemeSetting Model                    │                    │
│  └─────────────────────────────────────────┘                    │
└─────────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### 1. Theme Provider (React Context)

```typescript
interface ThemeConfig {
  colors: {
    primary: string;        // HSL value e.g., "210 100% 50%"
    primaryForeground: string;
    secondary: string;
    secondaryForeground: string;
    accent: string;
    accentForeground: string;
    success: string;
    warning: string;
    error: string;
    info: string;
  };
  branding: {
    logoUrl: string | null;
    hospitalName: string;
  };
}

interface ThemeContextValue {
  theme: ThemeConfig;
  updateTheme: (updates: Partial<ThemeConfig>) => void;
  resetTheme: () => void;
  isLoading: boolean;
}
```

### 2. Stat Card Component

```typescript
interface StatCardProps {
  label: string;
  value: string | number;
  icon?: React.ReactNode;
  trend?: {
    value: number;      // Percentage change
    direction: 'up' | 'down' | 'neutral';
  };
  variant?: 'default' | 'success' | 'warning' | 'error' | 'info';
  className?: string;
}
```

### 3. Theme Settings API

```typescript
// GET /api/settings/theme
interface ThemeSettingsResponse {
  data: ThemeConfig;
}

// PUT /api/settings/theme
interface UpdateThemeRequest {
  colors?: Partial<ThemeConfig['colors']>;
  branding?: Partial<ThemeConfig['branding']>;
}

// POST /api/settings/theme/reset
// Returns default theme config

// POST /api/settings/theme/logo
// Multipart form with logo file
```

### 4. Status Badge Variants

```typescript
type BadgeVariant = 
  | 'default'
  | 'success'    // Green - completed, active, approved
  | 'warning'    // Amber - pending, awaiting
  | 'error'      // Red - cancelled, rejected, critical
  | 'info'       // Blue - in progress, processing
  | 'secondary'; // Gray - draft, inactive
```

## Data Models

### ThemeSetting Model

```php
// database/migrations/xxxx_create_theme_settings_table.php
Schema::create('theme_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->json('value');
    $table->timestamps();
});

// Keys stored:
// - 'colors' => JSON object with color values
// - 'branding' => JSON object with logo URL and hospital name
```

### Default Theme Configuration

```typescript
const defaultTheme: ThemeConfig = {
  colors: {
    // Healthcare-appropriate blue palette
    primary: "210 90% 45%",           // Professional blue
    primaryForeground: "0 0% 100%",
    secondary: "210 20% 96%",
    secondaryForeground: "210 40% 20%",
    accent: "180 60% 45%",            // Teal accent
    accentForeground: "0 0% 100%",
    // Semantic colors
    success: "142 70% 45%",           // Green
    warning: "38 92% 50%",            // Amber
    error: "0 84% 60%",               // Red
    info: "210 100% 50%",             // Blue
  },
  branding: {
    logoUrl: null,
    hospitalName: "Hospital Management System",
  },
};
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Stat Card Trend Indicator Consistency
*For any* stat card with a trend prop, the rendered output should contain a trend indicator showing the direction (up/down arrow) and the percentage value.
**Validates: Requirements 3.3**

### Property 2: Theme Update Persistence
*For any* valid theme configuration update, after saving, fetching the theme should return the updated values.
**Validates: Requirements 6.2, 9.4**

### Property 3: Theme Validation
*For any* theme configuration with invalid color values (non-HSL format, out of range), the system should reject the update and return a validation error.
**Validates: Requirements 6.4**

### Property 4: Badge Status Color Mapping
*For any* status badge variant, the rendered badge should use the corresponding semantic color CSS variable.
**Validates: Requirements 8.1**

### Property 5: Logo Upload Validation
*For any* uploaded logo file, the system should validate file type (PNG, JPG, SVG) and reject invalid formats with an appropriate error message.
**Validates: Requirements 9.3**

### Property 6: Theme Reset Restores Defaults
*For any* customized theme, after reset, all theme values should match the default healthcare theme configuration.
**Validates: Requirements 9.5**

### Property 7: Custom Config Override
*For any* saved custom theme configuration, when the application loads, custom values should override the corresponding default values while preserving unset defaults.
**Validates: Requirements 10.2**

### Property 8: Invalid Config Fallback
*For any* corrupted or invalid theme configuration in the database, the system should gracefully fall back to default values without crashing.
**Validates: Requirements 10.4**

## Error Handling

### Theme Loading Errors
- If theme fetch fails, use default theme and log error
- Display toast notification for admin if in settings page

### Theme Save Errors
- Validate all color values before saving
- Return specific validation errors for each invalid field
- Rollback on database errors

### Logo Upload Errors
- Validate file type (PNG, JPG, SVG only)
- Validate file size (max 2MB)
- Validate dimensions (min 32x32, max 512x512)
- Return specific error messages for each validation failure

## Testing Strategy

### Unit Tests
- Theme validation functions
- Color format validation
- Default theme merging logic

### Property-Based Tests
Using Pest with faker for property-based testing:

1. **Theme Update Persistence**: Generate random valid theme configs, save, fetch, verify equality
2. **Theme Validation**: Generate invalid color formats, verify rejection
3. **Badge Color Mapping**: Generate all badge variants, verify correct CSS class applied
4. **Logo Validation**: Generate various file types, verify correct acceptance/rejection
5. **Theme Reset**: Apply random customizations, reset, verify defaults restored
6. **Config Override**: Generate partial configs, verify correct merging with defaults
7. **Invalid Config Fallback**: Generate corrupted JSON, verify graceful fallback

### Integration Tests
- Theme settings page renders correctly
- Color picker updates preview in real-time
- Logo upload and display workflow
- Theme persistence across page reloads

### Browser Tests (Pest v4)
- Visual verification of theme application
- Dark mode toggle functionality
- Responsive stat card grid layout
