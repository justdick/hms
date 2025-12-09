<?php

/**
 * Property-Based Tests for Badge Component
 *
 * These tests verify the correctness properties of the Badge component
 * as defined in the design document.
 *
 * **Feature: ui-theming-system, Property 4: Badge Status Color Mapping**
 * **Validates: Requirements 8.1**
 */

/**
 * Property 4: Badge Status Color Mapping
 *
 * *For any* status badge variant, the rendered badge should use the
 * corresponding semantic color CSS variable.
 */
describe('Property 4: Badge Status Color Mapping', function () {
    it('maps success variant to success semantic color CSS variable', function () {
        $componentPath = resource_path('js/components/ui/badge.tsx');
        expect(file_exists($componentPath))->toBeTrue();

        $componentContent = file_get_contents($componentPath);

        // Verify success variant uses bg-success and text-success-foreground
        expect($componentContent)->toContain('success:');
        expect($componentContent)->toContain('bg-success');
        expect($componentContent)->toContain('text-success-foreground');
    });

    it('maps warning variant to warning semantic color CSS variable', function () {
        $componentPath = resource_path('js/components/ui/badge.tsx');
        $componentContent = file_get_contents($componentPath);

        // Verify warning variant uses bg-warning and text-warning-foreground
        expect($componentContent)->toContain('warning:');
        expect($componentContent)->toContain('bg-warning');
        expect($componentContent)->toContain('text-warning-foreground');
    });

    it('maps error variant to error semantic color CSS variable', function () {
        $componentPath = resource_path('js/components/ui/badge.tsx');
        $componentContent = file_get_contents($componentPath);

        // Verify error variant uses bg-error and text-error-foreground
        expect($componentContent)->toContain('error:');
        expect($componentContent)->toContain('bg-error');
        expect($componentContent)->toContain('text-error-foreground');
    });

    it('maps info variant to info semantic color CSS variable', function () {
        $componentPath = resource_path('js/components/ui/badge.tsx');
        $componentContent = file_get_contents($componentPath);

        // Verify info variant uses bg-info and text-info-foreground
        expect($componentContent)->toContain('info:');
        expect($componentContent)->toContain('bg-info');
        expect($componentContent)->toContain('text-info-foreground');
    });

    it('maps secondary variant to secondary semantic color CSS variable', function () {
        $componentPath = resource_path('js/components/ui/badge.tsx');
        $componentContent = file_get_contents($componentPath);

        // Verify secondary variant uses bg-secondary and text-secondary-foreground
        expect($componentContent)->toContain('secondary:');
        expect($componentContent)->toContain('bg-secondary');
        expect($componentContent)->toContain('text-secondary-foreground');
    });

    it('all semantic variants have consistent border-transparent styling', function () {
        $componentPath = resource_path('js/components/ui/badge.tsx');
        $componentContent = file_get_contents($componentPath);

        // Extract all variant definitions
        // Each semantic variant should have border-transparent for consistent appearance
        $variants = ['success', 'warning', 'error', 'info', 'secondary'];

        foreach ($variants as $variant) {
            // Verify each variant definition includes border-transparent
            $pattern = '/'.$variant.':\s*["\']border-transparent/';
            expect(preg_match($pattern, $componentContent))->toBe(1, "Variant '$variant' should have border-transparent");
        }
    });

    it('all semantic variants have hover state styling', function () {
        $componentPath = resource_path('js/components/ui/badge.tsx');
        $componentContent = file_get_contents($componentPath);

        // Verify hover states exist for semantic variants
        expect($componentContent)->toContain('[a&]:hover:bg-success/90');
        expect($componentContent)->toContain('[a&]:hover:bg-warning/90');
        expect($componentContent)->toContain('[a&]:hover:bg-error/90');
        expect($componentContent)->toContain('[a&]:hover:bg-info/90');
    });
});

/**
 * Additional tests for Badge component structure
 */
describe('Badge Component Structure', function () {
    it('exports badgeVariants for external use', function () {
        $componentPath = resource_path('js/components/ui/badge.tsx');
        $componentContent = file_get_contents($componentPath);

        expect($componentContent)->toContain('export { Badge, badgeVariants }');
    });

    it('uses class-variance-authority for variant management', function () {
        $componentPath = resource_path('js/components/ui/badge.tsx');
        $componentContent = file_get_contents($componentPath);

        expect($componentContent)->toContain('import { cva, type VariantProps } from "class-variance-authority"');
        expect($componentContent)->toContain('const badgeVariants = cva(');
    });

    it('has default variant set', function () {
        $componentPath = resource_path('js/components/ui/badge.tsx');
        $componentContent = file_get_contents($componentPath);

        expect($componentContent)->toContain('defaultVariants: {');
        expect($componentContent)->toContain('variant: "default"');
    });

    it('has consistent base styling for all badges', function () {
        $componentPath = resource_path('js/components/ui/badge.tsx');
        $componentContent = file_get_contents($componentPath);

        // Verify base styling includes consistent sizing and layout
        expect($componentContent)->toContain('inline-flex');
        expect($componentContent)->toContain('items-center');
        expect($componentContent)->toContain('justify-center');
        expect($componentContent)->toContain('rounded-md');
        expect($componentContent)->toContain('text-xs');
        expect($componentContent)->toContain('font-medium');
    });

    it('supports asChild prop for composition', function () {
        $componentPath = resource_path('js/components/ui/badge.tsx');
        $componentContent = file_get_contents($componentPath);

        expect($componentContent)->toContain('asChild = false');
        expect($componentContent)->toContain('Slot');
    });
});
