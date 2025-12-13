import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useState,
    type ReactNode,
    type JSX,
} from 'react';

export interface ThemeColors {
    primary: string;
    primaryForeground: string;
    secondary: string;
    secondaryForeground: string;
    accent: string;
    accentForeground: string;
    success: string;
    warning: string;
    error: string;
    info: string;
    // Sidebar colors
    sidebar: string;
    sidebarForeground: string;
    sidebarPrimary: string;
    sidebarPrimaryForeground: string;
    sidebarAccent: string;
    sidebarAccentForeground: string;
}

export interface ThemeBranding {
    logoUrl: string | null;
    hospitalName: string;
}

export interface ThemeConfig {
    colors: ThemeColors;
    branding: ThemeBranding;
}

export interface ThemeContextValue {
    theme: ThemeConfig;
    updateTheme: (updates: Partial<ThemeConfig>) => void;
    resetTheme: () => void;
    isLoading: boolean;
}

export const defaultTheme: ThemeConfig = {
    colors: {
        primary: '210 90% 45%',
        primaryForeground: '0 0% 100%',
        secondary: '210 20% 96%',
        secondaryForeground: '210 40% 20%',
        accent: '180 60% 45%',
        accentForeground: '0 0% 100%',
        success: '142 70% 45%',
        warning: '38 92% 50%',
        error: '0 84% 60%',
        info: '210 100% 50%',
        sidebar: '210 20% 98%',
        sidebarForeground: '210 40% 20%',
        sidebarPrimary: '210 90% 45%',
        sidebarPrimaryForeground: '0 0% 100%',
        sidebarAccent: '210 30% 94%',
        sidebarAccentForeground: '210 40% 25%',
    },
    branding: {
        logoUrl: null,
        hospitalName: 'Hospital Management System',
    },
};


const ThemeContext = createContext<ThemeContextValue | undefined>(undefined);

/**
 * Convert camelCase to kebab-case.
 */
function camelToKebab(str: string): string {
    return str.replace(/([A-Z])/g, '-$1').toLowerCase();
}

/**
 * Convert HSL string (e.g., "210 90% 45%") to CSS hsl() format.
 */
function hslToCSS(hslValue: string): string {
    // If already in a CSS function format, return as-is
    if (hslValue.startsWith('hsl(') || hslValue.startsWith('oklch(')) {
        return hslValue;
    }
    // Convert "H S% L%" format to "hsl(H S% L%)"
    return `hsl(${hslValue})`;
}

/**
 * Apply theme CSS variables to the document root.
 * Maps theme colors to the CSS variables used by Tailwind.
 */
function applyThemeVariables(theme: ThemeConfig): void {
    const root = document.documentElement;

    // Map theme color keys to CSS variable names
    const colorMapping: Record<string, string> = {
        primary: '--primary',
        primaryForeground: '--primary-foreground',
        secondary: '--secondary',
        secondaryForeground: '--secondary-foreground',
        accent: '--accent',
        accentForeground: '--accent-foreground',
        success: '--success',
        warning: '--warning',
        error: '--error',
        info: '--info',
        sidebar: '--sidebar',
        sidebarForeground: '--sidebar-foreground',
        sidebarPrimary: '--sidebar-primary',
        sidebarPrimaryForeground: '--sidebar-primary-foreground',
        sidebarAccent: '--sidebar-accent',
        sidebarAccentForeground: '--sidebar-accent-foreground',
    };

    // Apply color variables
    Object.entries(theme.colors).forEach(([key, value]) => {
        const cssVarName = colorMapping[key];
        if (cssVarName && value) {
            // Set the CSS variable with the HSL value in CSS format
            root.style.setProperty(cssVarName, hslToCSS(value));
        }
    });

    // Also set theme-prefixed variables for any custom usage
    Object.entries(theme.colors).forEach(([key, value]) => {
        const cssVarName = `--theme-${camelToKebab(key)}`;
        root.style.setProperty(cssVarName, value);
    });
}

interface ThemeProviderProps {
    children: ReactNode;
    initialTheme?: ThemeConfig;
}

export function ThemeProvider({
    children,
    initialTheme,
}: ThemeProviderProps): JSX.Element {
    const [theme, setTheme] = useState<ThemeConfig>(
        initialTheme ?? defaultTheme,
    );
    const [isLoading] = useState(false);

    // Apply CSS variables when theme changes
    useEffect(() => {
        applyThemeVariables(theme);
    }, [theme]);

    const updateTheme = useCallback((updates: Partial<ThemeConfig>) => {
        setTheme((current) => {
            const newTheme = {
                colors: {
                    ...current.colors,
                    ...(updates.colors ?? {}),
                },
                branding: {
                    ...current.branding,
                    ...(updates.branding ?? {}),
                },
            };
            return newTheme;
        });
    }, []);

    const resetTheme = useCallback(() => {
        setTheme(defaultTheme);
    }, []);

    return (
        <ThemeContext.Provider
            value={{ theme, updateTheme, resetTheme, isLoading }}
        >
            {children}
        </ThemeContext.Provider>
    );
}

/**
 * Hook to access theme context.
 */
export function useTheme(): ThemeContextValue {
    const context = useContext(ThemeContext);

    if (context === undefined) {
        throw new Error('useTheme must be used within a ThemeProvider');
    }

    return context;
}

/**
 * Validate HSL color format.
 */
export function isValidHslColor(value: string): boolean {
    const pattern = /^\d{1,3}\s+\d{1,3}%\s+\d{1,3}%$/;

    if (!pattern.test(value)) {
        return false;
    }

    const matches = value.match(/^(\d{1,3})\s+(\d{1,3})%\s+(\d{1,3})%$/);

    if (!matches || matches.length !== 4) {
        return false;
    }

    const hue = parseInt(matches[1], 10);
    const saturation = parseInt(matches[2], 10);
    const lightness = parseInt(matches[3], 10);

    return (
        hue >= 0 &&
        hue <= 360 &&
        saturation >= 0 &&
        saturation <= 100 &&
        lightness >= 0 &&
        lightness <= 100
    );
}

/**
 * Validate a complete theme configuration.
 */
export function validateThemeConfig(
    config: Partial<ThemeConfig>,
): { valid: boolean; errors: string[] } {
    const errors: string[] = [];

    if (config.colors) {
        for (const [key, value] of Object.entries(config.colors)) {
            if (typeof value === 'string' && !isValidHslColor(value)) {
                errors.push(
                    `Invalid HSL color for '${key}': ${value}. Expected format: 'H S% L%'`,
                );
            }
        }
    }

    return {
        valid: errors.length === 0,
        errors,
    };
}
