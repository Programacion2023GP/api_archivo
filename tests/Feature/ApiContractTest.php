<?php

namespace Tests\Feature;

use App\Models\Departament;
use App\Models\Permission;
use App\Models\Proccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Compact behavioral contract for the Archivo API.
 *
 * Each test = one behavioral assertion — the smallest unit
 * that would break if the contract were violated.
 */
class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    private function seedMinimalData(): void
    {
        Permission::insert([
            ['name' => 'tramite_crear', 'active' => true],
            ['name' => 'tramite_ver', 'active' => true],
            ['name' => 'catalogo_departamentos_ver', 'active' => true],
        ]);

        Departament::insert([
            ['id' => 1, 'name' => 'OFICIALIA MAYOR', 'abbreviation' => 'OR', 'classification_code' => '7S', 'departament_id' => null, 'authorized' => 0, 'active' => 1],
            ['id' => 2, 'name' => 'INFORMATICA', 'abbreviation' => 'IA', 'classification_code' => 'SE1', 'departament_id' => 1, 'authorized' => 0, 'active' => 1],
        ]);

        User::create([
            'id' => 1,
            'firstName' => 'Admin',
            'paternalSurname' => 'Test',
            'maternalSurname' => 'Test',
            'fullName' => 'Admin Test Test',
            'payroll' => 'admin',
            'role' => 'administrativo',
            'departament_id' => null,
            'password' => Hash::make('password'),
            'active' => true,
        ]);

        User::create([
            'id' => 2,
            'firstName' => 'Director',
            'paternalSurname' => 'Test',
            'maternalSurname' => 'Test',
            'fullName' => 'Director Test Test',
            'payroll' => '612053',
            'role' => 'Director',
            'departament_id' => 2,
            'password' => Hash::make('password'),
            'active' => true,
        ]);

        Proccess::create([
            'id' => 1,
            'classification_code' => '1.1',
            'name' => 'DOCUMENTACION',
            'departament_id' => 2,
            'at' => 1,
            'ac' => 1,
            'active' => true,
        ]);
    }

    private function loginAs(string $payroll, string $password = 'password'): string
    {
        $response = $this->postJson('/api/users/login', [
            'payroll' => $payroll,
            'password' => $password,
        ]);

        $this->assertEquals('success', $response->json('status'));
        return $response->json('data.token');
    }

    // ── Auth Contract ──────────────────────────────────────────────

    #[Test]
    public function login_returns_token_and_user_data(): void
    {
        $this->seedMinimalData();
        $response = $this->postJson('/api/users/login', [
            'payroll' => 'admin',
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertEquals('success', $response->json('status'));
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertEquals('administrativo', $response->json('data.user.role'));
    }

    #[Test]
    public function login_rejects_wrong_password(): void
    {
        $this->seedMinimalData();
        $response = $this->postJson('/api/users/login', [
            'payroll' => 'admin',
            'password' => 'wrong',
        ]);

        $this->assertNotEquals('success', $response->json('status'));
    }

    #[Test]
    public function unauthenticated_request_returns_401(): void
    {
        $this->seedMinimalData();
        $response = $this->getJson('/api/departaments/index');
        $response->assertUnauthorized();
    }

    // ── Department CRUD Contract ───────────────────────────────────

    #[Test]
    public function create_and_list_departments(): void
    {
        $this->seedMinimalData();
        $token = $this->loginAs('admin');

        $create = $this->postJson('/api/departaments/createorUpdate', [
            'id' => 0,
            'name' => 'NUEVO DEPTO',
            'classification_code' => '9S',
            'abbreviation' => 'ND',
            'departament_id' => null,
        ], ['Authorization' => "Bearer $token"]);

        $create->assertOk();
        $this->assertEquals('success', $create->json('status'));

        $index = $this->getJson('/api/departaments/index', ['Authorization' => "Bearer $token"]);
        $index->assertOk();

        $names = collect($index->json('data'))->pluck('name')->toArray();
        $this->assertContains('NUEVO DEPTO', $names);
    }

    #[Test]
    public function delete_department_removes_it(): void
    {
        $this->seedMinimalData();
        $token = $this->loginAs('admin');

        $delete = $this->deleteJson('/api/departaments/delete', ['id' => 1], ['Authorization' => "Bearer $token"]);
        $delete->assertOk();

        $index = $this->getJson('/api/departaments/index', ['Authorization' => "Bearer $token"]);
        $ids = collect($index->json('data'))->pluck('id')->toArray();
        $this->assertNotContains(1, $ids);
    }

    // ── Process Tree Contract ──────────────────────────────────────

    #[Test]
    public function process_index_returns_selectable_true(): void
    {
        $this->seedMinimalData();
        $token = $this->loginAs('admin');

        $response = $this->getJson('/api/proccess/index/2', ['Authorization' => "Bearer $token"]);
        $response->assertOk();

        $items = $response->json('data');
        $this->assertNotEmpty($items);
        $this->assertTrue($items[0]['selectable'], 'Process must be selectable');
    }

    #[Test]
    public function process_by_user_returns_tree_for_admin(): void
    {
        $this->seedMinimalData();
        $token = $this->loginAs('admin');

        $response = $this->getJson('/api/proccess/processbyuser', ['Authorization' => "Bearer $token"]);
        $response->assertOk();

        $tree = $response->json('data');
        $this->assertNotEmpty($tree, 'Admin should see all departments');
        $this->assertFalse($tree[0]['selectable'], 'Root must be department (not selectable)');
        $this->assertStringStartsWith('dept_', $tree[0]['id']);
    }

    #[Test]
    public function process_by_user_returns_subtree_for_director(): void
    {
        $this->seedMinimalData();
        $token = $this->loginAs('612053');

        $response = $this->getJson('/api/proccess/processbyuser', ['Authorization' => "Bearer $token"]);
        $response->assertOk();

        $tree = $response->json('data');
        $this->assertNotEmpty($tree, 'Director should see their department subtree');
    }

    // ── Process CRUD Contract ──────────────────────────────────────

    #[Test]
    public function create_and_delete_process(): void
    {
        $this->seedMinimalData();
        $token = $this->loginAs('admin');

        $create = $this->postJson('/api/proccess/createorUpdate', [
            'id' => 0,
            'classification_code' => '2.1',
            'name' => 'NUEVO TRAMITE',
            'departament_id' => 2,
            'at' => 1,
            'ac' => 1,
            'active' => true,
        ], ['Authorization' => "Bearer $token"]);

        $create->assertOk();
        $newId = $create->json('data.id');

        $index = $this->getJson('/api/proccess/index/2', ['Authorization' => "Bearer $token"]);
        $names = collect($index->json('data'))->pluck('name')->toArray();
        $this->assertContains('NUEVO TRAMITE', $names);

        $delete = $this->deleteJson('/api/proccess/delete', ['id' => $newId], ['Authorization' => "Bearer $token"]);
        $delete->assertOk();
    }
}
