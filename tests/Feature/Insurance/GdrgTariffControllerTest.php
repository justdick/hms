<?php

use App\Models\GdrgTariff;
use App\Models\InsuranceClaim;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'gdrg-tariffs.view']);
    Permission::firstOrCreate(['name' => 'gdrg-tariffs.manage']);
});

describe('index', function () {
    it('denies access to unauthorized user', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/admin/gdrg-tariffs');

        $response->assertForbidden();
    });

    // Note: Tests for index page rendering are skipped until frontend page is created (Phase 5)
    // The controller and authorization are tested via the authorization test above
});

describe('store', function () {
    it('creates a new G-DRG tariff', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.manage');

        $response = $this->actingAs($user)
            ->post('/admin/gdrg-tariffs', [
                'code' => 'GDRG-001',
                'name' => 'Cardiac Surgery',
                'mdc_category' => 'Surgical',
                'tariff_price' => 500.00,
                'age_category' => 'adult',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('admin.gdrg-tariffs.index'));

        expect(GdrgTariff::count())->toBe(1)
            ->and(GdrgTariff::first()->code)->toBe('GDRG-001')
            ->and(GdrgTariff::first()->name)->toBe('Cardiac Surgery');
    });

    it('validates unique code', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.manage');

        GdrgTariff::factory()->create(['code' => 'GDRG-001']);

        $response = $this->actingAs($user)
            ->post('/admin/gdrg-tariffs', [
                'code' => 'GDRG-001',
                'name' => 'Another Tariff',
                'mdc_category' => 'Medical',
                'tariff_price' => 100.00,
            ]);

        $response->assertSessionHasErrors('code');
    });

    it('validates required fields', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.manage');

        $response = $this->actingAs($user)
            ->post('/admin/gdrg-tariffs', []);

        $response->assertSessionHasErrors(['code', 'name', 'mdc_category', 'tariff_price']);
    });

    it('validates age_category is valid', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.manage');

        $response = $this->actingAs($user)
            ->post('/admin/gdrg-tariffs', [
                'code' => 'GDRG-001',
                'name' => 'Test',
                'mdc_category' => 'Medical',
                'tariff_price' => 100.00,
                'age_category' => 'invalid_category',
            ]);

        $response->assertSessionHasErrors('age_category');
    });

    it('denies creation to unauthorized user', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.view');

        $response = $this->actingAs($user)
            ->post('/admin/gdrg-tariffs', [
                'code' => 'GDRG-001',
                'name' => 'Test',
                'mdc_category' => 'Medical',
                'tariff_price' => 100.00,
            ]);

        $response->assertForbidden();
    });

    it('sets default age_category to all', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.manage');

        $response = $this->actingAs($user)
            ->post('/admin/gdrg-tariffs', [
                'code' => 'GDRG-001',
                'name' => 'Test Tariff',
                'mdc_category' => 'Medical',
                'tariff_price' => 100.00,
            ]);

        $response->assertRedirect();
        expect(GdrgTariff::first()->age_category)->toBe('all');
    });
});

describe('update', function () {
    it('updates an existing G-DRG tariff', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.manage');

        $tariff = GdrgTariff::factory()->create([
            'code' => 'GDRG-001',
            'name' => 'Old Name',
            'tariff_price' => 100.00,
        ]);

        $response = $this->actingAs($user)
            ->put("/admin/gdrg-tariffs/{$tariff->id}", [
                'code' => 'GDRG-001',
                'name' => 'New Name',
                'mdc_category' => 'Surgical',
                'tariff_price' => 200.00,
                'age_category' => 'adult',
            ]);

        $response->assertRedirect(route('admin.gdrg-tariffs.index'));

        $tariff->refresh();
        expect($tariff->name)->toBe('New Name')
            ->and((float) $tariff->tariff_price)->toBe(200.00);
    });

    it('allows updating code to same value', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.manage');

        $tariff = GdrgTariff::factory()->create(['code' => 'GDRG-001']);

        $response = $this->actingAs($user)
            ->put("/admin/gdrg-tariffs/{$tariff->id}", [
                'code' => 'GDRG-001',
                'name' => 'Updated Name',
                'mdc_category' => 'Medical',
                'tariff_price' => 150.00,
            ]);

        $response->assertRedirect(route('admin.gdrg-tariffs.index'));
    });

    it('prevents duplicate code on update', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.manage');

        GdrgTariff::factory()->create(['code' => 'GDRG-001']);
        $tariff = GdrgTariff::factory()->create(['code' => 'GDRG-002']);

        $response = $this->actingAs($user)
            ->put("/admin/gdrg-tariffs/{$tariff->id}", [
                'code' => 'GDRG-001',
                'name' => 'Test',
                'mdc_category' => 'Medical',
                'tariff_price' => 100.00,
            ]);

        $response->assertSessionHasErrors('code');
    });
});

describe('destroy', function () {
    it('deletes a G-DRG tariff without claims', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.manage');

        $tariff = GdrgTariff::factory()->create();

        $response = $this->actingAs($user)
            ->delete("/admin/gdrg-tariffs/{$tariff->id}");

        $response->assertRedirect(route('admin.gdrg-tariffs.index'));
        expect(GdrgTariff::count())->toBe(0);
    });

    it('prevents deletion of tariff with existing claims', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.manage');

        $tariff = GdrgTariff::factory()->create();
        InsuranceClaim::factory()->create(['gdrg_tariff_id' => $tariff->id]);

        $response = $this->actingAs($user)
            ->delete("/admin/gdrg-tariffs/{$tariff->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        expect(GdrgTariff::count())->toBe(1);
    });

    it('denies deletion to unauthorized user', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.view');

        $tariff = GdrgTariff::factory()->create();

        $response = $this->actingAs($user)
            ->delete("/admin/gdrg-tariffs/{$tariff->id}");

        $response->assertForbidden();
    });
});

describe('import', function () {
    it('imports tariffs from CSV file', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.manage');

        $csvContent = "code,name,mdc_category,tariff_price,age_category\n";
        $csvContent .= "GDRG-001,Cardiac Surgery,Surgical,500.00,adult\n";
        $csvContent .= "GDRG-002,Orthopedic Surgery,Surgical,400.00,all\n";

        $file = UploadedFile::fake()->createWithContent('tariffs.csv', $csvContent);

        $response = $this->actingAs($user)
            ->post('/admin/gdrg-tariffs/import', [
                'file' => $file,
            ]);

        $response->assertRedirect(route('admin.gdrg-tariffs.index'));
        expect(GdrgTariff::count())->toBe(2);
    });

    it('validates file is required', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.manage');

        $response = $this->actingAs($user)
            ->post('/admin/gdrg-tariffs/import', []);

        $response->assertSessionHasErrors('file');
    });

    it('updates existing tariffs on import', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.manage');

        GdrgTariff::factory()->create([
            'code' => 'GDRG-001',
            'name' => 'Old Name',
            'tariff_price' => 100.00,
        ]);

        $csvContent = "code,name,mdc_category,tariff_price,age_category\n";
        $csvContent .= "GDRG-001,New Name,Surgical,500.00,adult\n";

        $file = UploadedFile::fake()->createWithContent('tariffs.csv', $csvContent);

        $response = $this->actingAs($user)
            ->post('/admin/gdrg-tariffs/import', [
                'file' => $file,
            ]);

        $response->assertRedirect();
        expect(GdrgTariff::count())->toBe(1);

        $tariff = GdrgTariff::first();
        expect($tariff->name)->toBe('New Name')
            ->and((float) $tariff->tariff_price)->toBe(500.00);
    });
});

describe('search', function () {
    it('returns JSON response for search endpoint', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.view');

        GdrgTariff::factory()->create(['name' => 'Cardiac Surgery', 'code' => 'GDRG-001']);
        GdrgTariff::factory()->create(['name' => 'Orthopedic Surgery', 'code' => 'GDRG-002']);

        $response = $this->actingAs($user)
            ->getJson('/api/gdrg-tariffs/search?search=Cardiac');

        $response->assertOk()
            ->assertJsonCount(1, 'tariffs')
            ->assertJsonPath('tariffs.0.name', 'Cardiac Surgery');
    });

    it('filters search by MDC category', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.view');

        GdrgTariff::factory()->create(['mdc_category' => 'Surgical']);
        GdrgTariff::factory()->create(['mdc_category' => 'Medical']);

        $response = $this->actingAs($user)
            ->getJson('/api/gdrg-tariffs/search?mdc_category=Surgical');

        $response->assertOk()
            ->assertJsonCount(1, 'tariffs');
    });

    it('returns display_name in correct format', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.view');

        GdrgTariff::factory()->create([
            'name' => 'Cardiac Surgery',
            'code' => 'GDRG-001',
            'tariff_price' => 500.00,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/gdrg-tariffs/search');

        $response->assertOk();
        $tariff = $response->json('tariffs.0');
        expect($tariff['display_name'])->toBe('Cardiac Surgery (GDRG-001 - GHS 500.00)');
    });

    it('only returns active tariffs', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('gdrg-tariffs.view');

        GdrgTariff::factory()->create(['is_active' => true, 'name' => 'Active']);
        GdrgTariff::factory()->create(['is_active' => false, 'name' => 'Inactive']);

        $response = $this->actingAs($user)
            ->getJson('/api/gdrg-tariffs/search');

        $response->assertOk()
            ->assertJsonCount(1, 'tariffs')
            ->assertJsonPath('tariffs.0.name', 'Active');
    });
});
