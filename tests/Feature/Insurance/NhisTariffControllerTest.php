<?php

use App\Models\NhisTariff;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'nhis-tariffs.view']);
    Permission::firstOrCreate(['name' => 'nhis-tariffs.manage']);
});

describe('index', function () {
    it('denies access to unauthorized user', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/admin/nhis-tariffs');

        $response->assertForbidden();
    });
});

describe('store', function () {
    it('creates a new NHIS tariff', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-tariffs.manage');

        $response = $this->actingAs($user)
            ->post('/admin/nhis-tariffs', [
                'nhis_code' => 'MED-001',
                'name' => 'Paracetamol 500mg',
                'category' => 'medicine',
                'price' => 5.50,
                'unit' => 'tablet',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('admin.nhis-tariffs.index'));

        expect(NhisTariff::count())->toBe(1)
            ->and(NhisTariff::first()->nhis_code)->toBe('MED-001')
            ->and(NhisTariff::first()->name)->toBe('Paracetamol 500mg');
    });

    it('validates unique nhis_code', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-tariffs.manage');

        NhisTariff::factory()->create(['nhis_code' => 'MED-001']);

        $response = $this->actingAs($user)
            ->post('/admin/nhis-tariffs', [
                'nhis_code' => 'MED-001',
                'name' => 'Another Medicine',
                'category' => 'medicine',
                'price' => 10.00,
            ]);

        $response->assertSessionHasErrors('nhis_code');
    });

    it('validates required fields', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-tariffs.manage');

        $response = $this->actingAs($user)
            ->post('/admin/nhis-tariffs', []);

        $response->assertSessionHasErrors(['nhis_code', 'name', 'category', 'price']);
    });

    it('validates category is valid', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-tariffs.manage');

        $response = $this->actingAs($user)
            ->post('/admin/nhis-tariffs', [
                'nhis_code' => 'MED-001',
                'name' => 'Test',
                'category' => 'invalid_category',
                'price' => 10.00,
            ]);

        $response->assertSessionHasErrors('category');
    });

    it('denies creation to unauthorized user', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-tariffs.view');

        $response = $this->actingAs($user)
            ->post('/admin/nhis-tariffs', [
                'nhis_code' => 'MED-001',
                'name' => 'Test',
                'category' => 'medicine',
                'price' => 10.00,
            ]);

        $response->assertForbidden();
    });
});

describe('update', function () {
    it('updates an existing NHIS tariff', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-tariffs.manage');

        $tariff = NhisTariff::factory()->create([
            'nhis_code' => 'MED-001',
            'name' => 'Old Name',
            'price' => 5.00,
        ]);

        $response = $this->actingAs($user)
            ->put("/admin/nhis-tariffs/{$tariff->id}", [
                'nhis_code' => 'MED-001',
                'name' => 'New Name',
                'category' => 'medicine',
                'price' => 10.00,
            ]);

        $response->assertRedirect(route('admin.nhis-tariffs.index'));

        $tariff->refresh();
        expect($tariff->name)->toBe('New Name')
            ->and((float) $tariff->price)->toBe(10.00);
    });

    it('allows updating nhis_code to same value', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-tariffs.manage');

        $tariff = NhisTariff::factory()->create(['nhis_code' => 'MED-001']);

        $response = $this->actingAs($user)
            ->put("/admin/nhis-tariffs/{$tariff->id}", [
                'nhis_code' => 'MED-001',
                'name' => 'Updated Name',
                'category' => 'medicine',
                'price' => 15.00,
            ]);

        $response->assertRedirect(route('admin.nhis-tariffs.index'));
    });

    it('prevents duplicate nhis_code on update', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-tariffs.manage');

        NhisTariff::factory()->create(['nhis_code' => 'MED-001']);
        $tariff = NhisTariff::factory()->create(['nhis_code' => 'MED-002']);

        $response = $this->actingAs($user)
            ->put("/admin/nhis-tariffs/{$tariff->id}", [
                'nhis_code' => 'MED-001',
                'name' => 'Test',
                'category' => 'medicine',
                'price' => 10.00,
            ]);

        $response->assertSessionHasErrors('nhis_code');
    });
});

describe('destroy', function () {
    it('deletes an NHIS tariff without mappings', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-tariffs.manage');

        $tariff = NhisTariff::factory()->create();

        $response = $this->actingAs($user)
            ->delete("/admin/nhis-tariffs/{$tariff->id}");

        $response->assertRedirect(route('admin.nhis-tariffs.index'));
        expect(NhisTariff::count())->toBe(0);
    });

    it('denies deletion to unauthorized user', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-tariffs.view');

        $tariff = NhisTariff::factory()->create();

        $response = $this->actingAs($user)
            ->delete("/admin/nhis-tariffs/{$tariff->id}");

        $response->assertForbidden();
    });
});

describe('import', function () {
    it('imports tariffs from CSV file', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-tariffs.manage');

        $csvContent = "nhis_code,name,category,price,unit\n";
        $csvContent .= "MED-001,Paracetamol 500mg,medicine,5.50,tablet\n";
        $csvContent .= "LAB-001,Blood Test,lab,25.00,test\n";

        $file = UploadedFile::fake()->createWithContent('tariffs.csv', $csvContent);

        $response = $this->actingAs($user)
            ->post('/admin/nhis-tariffs/import', [
                'file' => $file,
            ]);

        $response->assertRedirect(route('admin.nhis-tariffs.index'));
        expect(NhisTariff::count())->toBe(2);
    });

    it('validates file is required', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-tariffs.manage');

        $response = $this->actingAs($user)
            ->post('/admin/nhis-tariffs/import', []);

        $response->assertSessionHasErrors('file');
    });
});

describe('search', function () {
    it('returns JSON response for search endpoint', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-tariffs.view');

        NhisTariff::factory()->create(['name' => 'Paracetamol', 'nhis_code' => 'MED-001']);
        NhisTariff::factory()->create(['name' => 'Amoxicillin', 'nhis_code' => 'MED-002']);

        $response = $this->actingAs($user)
            ->getJson('/api/nhis-tariffs/search?search=Paracetamol');

        $response->assertOk()
            ->assertJsonCount(1, 'tariffs')
            ->assertJsonPath('tariffs.0.name', 'Paracetamol');
    });

    it('filters search by category', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('nhis-tariffs.view');

        NhisTariff::factory()->create(['category' => 'medicine']);
        NhisTariff::factory()->create(['category' => 'lab']);

        $response = $this->actingAs($user)
            ->getJson('/api/nhis-tariffs/search?category=medicine');

        $response->assertOk()
            ->assertJsonCount(1, 'tariffs');
    });
});
