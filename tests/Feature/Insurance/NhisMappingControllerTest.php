<?php

use App\Models\Drug;
use App\Models\LabService;
use App\Models\MinorProcedureType;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'nhis-mappings.view']);
    Permission::firstOrCreate(['name' => 'nhis-mappings.manage']);
});

describe('index', function () {
    // Note: Index page tests are skipped until frontend is implemented (Phase 5, Task 27)
    // The controller returns Inertia responses which require the frontend page to exist

    it('denies access to unauthorized user', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/admin/nhis-mappings');

        $response->assertForbidden();
    });
});

describe('store', function () {
    it('creates a new mapping for a drug', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.manage');

        $drug = Drug::factory()->create();
        $tariff = NhisTariff::factory()->medicine()->create();

        $response = $this->actingAs($user)
            ->post('/admin/nhis-mappings', [
                'item_type' => 'drug',
                'item_id' => $drug->id,
                'nhis_tariff_id' => $tariff->id,
            ]);

        $response->assertRedirect(route('admin.nhis-mappings.index'));

        expect(NhisItemMapping::count())->toBe(1);
        expect(NhisItemMapping::first())
            ->item_type->toBe('drug')
            ->item_id->toBe($drug->id)
            ->item_code->toBe($drug->drug_code)
            ->nhis_tariff_id->toBe($tariff->id);
    });

    it('creates a new mapping for a lab service', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.manage');

        $labService = LabService::factory()->create();
        $tariff = NhisTariff::factory()->lab()->create();

        $response = $this->actingAs($user)
            ->post('/admin/nhis-mappings', [
                'item_type' => 'lab_service',
                'item_id' => $labService->id,
                'nhis_tariff_id' => $tariff->id,
            ]);

        $response->assertRedirect(route('admin.nhis-mappings.index'));

        expect(NhisItemMapping::count())->toBe(1);
        expect(NhisItemMapping::first())
            ->item_type->toBe('lab_service')
            ->item_id->toBe($labService->id)
            ->item_code->toBe($labService->code);
    });

    it('creates a new mapping for a procedure', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.manage');

        $procedure = MinorProcedureType::factory()->create();
        $tariff = NhisTariff::factory()->procedure()->create();

        $response = $this->actingAs($user)
            ->post('/admin/nhis-mappings', [
                'item_type' => 'procedure',
                'item_id' => $procedure->id,
                'nhis_tariff_id' => $tariff->id,
            ]);

        $response->assertRedirect(route('admin.nhis-mappings.index'));

        expect(NhisItemMapping::count())->toBe(1);
        expect(NhisItemMapping::first())
            ->item_type->toBe('procedure')
            ->item_id->toBe($procedure->id)
            ->item_code->toBe($procedure->code);
    });

    it('prevents duplicate mapping for same item', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.manage');

        $drug = Drug::factory()->create();
        $tariff1 = NhisTariff::factory()->medicine()->create();
        $tariff2 = NhisTariff::factory()->medicine()->create();

        // Create first mapping
        NhisItemMapping::create([
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'nhis_tariff_id' => $tariff1->id,
        ]);

        // Try to create duplicate
        $response = $this->actingAs($user)
            ->post('/admin/nhis-mappings', [
                'item_type' => 'drug',
                'item_id' => $drug->id,
                'nhis_tariff_id' => $tariff2->id,
            ]);

        $response->assertSessionHasErrors('item_id');
        expect(NhisItemMapping::count())->toBe(1);
    });

    it('validates required fields', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.manage');

        $response = $this->actingAs($user)
            ->post('/admin/nhis-mappings', []);

        $response->assertSessionHasErrors(['item_type', 'item_id', 'nhis_tariff_id']);
    });

    it('validates item type is valid', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.manage');

        $response = $this->actingAs($user)
            ->post('/admin/nhis-mappings', [
                'item_type' => 'invalid_type',
                'item_id' => 1,
                'nhis_tariff_id' => 1,
            ]);

        $response->assertSessionHasErrors('item_type');
    });

    it('validates item exists', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.manage');

        $tariff = NhisTariff::factory()->medicine()->create();

        $response = $this->actingAs($user)
            ->post('/admin/nhis-mappings', [
                'item_type' => 'drug',
                'item_id' => 99999,
                'nhis_tariff_id' => $tariff->id,
            ]);

        $response->assertSessionHasErrors('item_id');
    });

    it('validates tariff exists', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.manage');

        $drug = Drug::factory()->create();

        $response = $this->actingAs($user)
            ->post('/admin/nhis-mappings', [
                'item_type' => 'drug',
                'item_id' => $drug->id,
                'nhis_tariff_id' => 99999,
            ]);

        $response->assertSessionHasErrors('nhis_tariff_id');
    });

    it('denies creation to unauthorized user', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.view');

        $drug = Drug::factory()->create();
        $tariff = NhisTariff::factory()->medicine()->create();

        $response = $this->actingAs($user)
            ->post('/admin/nhis-mappings', [
                'item_type' => 'drug',
                'item_id' => $drug->id,
                'nhis_tariff_id' => $tariff->id,
            ]);

        $response->assertForbidden();
    });
});

describe('destroy', function () {
    it('deletes a mapping', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.manage');

        $mapping = NhisItemMapping::factory()->forDrug()->create();

        $response = $this->actingAs($user)
            ->delete("/admin/nhis-mappings/{$mapping->id}");

        $response->assertRedirect(route('admin.nhis-mappings.index'));
        expect(NhisItemMapping::count())->toBe(0);
    });

    it('denies deletion to unauthorized user', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.view');

        $mapping = NhisItemMapping::factory()->forDrug()->create();

        $response = $this->actingAs($user)
            ->delete("/admin/nhis-mappings/{$mapping->id}");

        $response->assertForbidden();
        expect(NhisItemMapping::count())->toBe(1);
    });
});

describe('import', function () {
    it('imports mappings from CSV file', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.manage');

        $drug = Drug::factory()->create(['drug_code' => 'DRG-IMP-001']);
        $tariff = NhisTariff::factory()->medicine()->create(['nhis_code' => 'NHIS-MED-001']);

        $csvContent = "item_type,item_code,nhis_code\n";
        $csvContent .= "drug,DRG-IMP-001,NHIS-MED-001\n";

        $file = UploadedFile::fake()->createWithContent('mappings.csv', $csvContent);

        $response = $this->actingAs($user)
            ->post('/admin/nhis-mappings/import', [
                'file' => $file,
            ]);

        $response->assertRedirect(route('admin.nhis-mappings.index'));
        expect(NhisItemMapping::count())->toBe(1);
        expect(NhisItemMapping::first())
            ->item_type->toBe('drug')
            ->item_id->toBe($drug->id)
            ->nhis_tariff_id->toBe($tariff->id);
    });

    it('validates file is required', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.manage');

        $response = $this->actingAs($user)
            ->post('/admin/nhis-mappings/import', []);

        $response->assertSessionHasErrors('file');
    });

    it('handles invalid item codes gracefully', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.manage');

        $tariff = NhisTariff::factory()->medicine()->create(['nhis_code' => 'NHIS-MED-001']);

        $csvContent = "item_type,item_code,nhis_code\n";
        $csvContent .= "drug,NONEXISTENT-CODE,NHIS-MED-001\n";

        $file = UploadedFile::fake()->createWithContent('mappings.csv', $csvContent);

        $response = $this->actingAs($user)
            ->post('/admin/nhis-mappings/import', [
                'file' => $file,
            ]);

        $response->assertRedirect(route('admin.nhis-mappings.index'));
        expect(NhisItemMapping::count())->toBe(0);
    });
});

describe('unmapped', function () {
    it('lists unmapped drugs', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.view');

        // Create drugs - some mapped, some not
        $mappedDrug = Drug::factory()->create();
        $unmappedDrug = Drug::factory()->create();

        $tariff = NhisTariff::factory()->medicine()->create();
        NhisItemMapping::create([
            'item_type' => 'drug',
            'item_id' => $mappedDrug->id,
            'item_code' => $mappedDrug->drug_code,
            'nhis_tariff_id' => $tariff->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/admin/nhis-mappings/unmapped?item_type=drug');

        $response->assertOk();

        $items = $response->json('items');
        expect(count($items))->toBe(1);
        expect($items[0]['id'])->toBe($unmappedDrug->id);
    });

    it('lists unmapped lab services', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.view');

        $unmappedService = LabService::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/admin/nhis-mappings/unmapped?item_type=lab_service');

        $response->assertOk();

        $items = $response->json('items');
        expect(count($items))->toBe(1);
        expect($items[0]['id'])->toBe($unmappedService->id);
    });

    it('filters unmapped items by search', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-mappings.view');

        Drug::factory()->create(['name' => 'Paracetamol 500mg']);
        Drug::factory()->create(['name' => 'Amoxicillin 250mg']);

        $response = $this->actingAs($user)
            ->getJson('/admin/nhis-mappings/unmapped?item_type=drug&search=Paracetamol');

        $response->assertOk();

        $items = $response->json('items');
        expect(count($items))->toBe(1);
        expect($items[0]['name'])->toBe('Paracetamol 500mg');
    });

    it('denies access to unauthorized user', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/admin/nhis-mappings/unmapped?item_type=drug');

        $response->assertForbidden();
    });
});
