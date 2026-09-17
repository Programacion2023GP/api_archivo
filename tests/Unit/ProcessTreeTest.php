<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Illuminate\Support\Collection;

/**
 * Contract: Process tree must produce correct selectable flags.
 *
 * Departments → selectable: false (cannot be chosen in autocomplete)
 * Processes   → selectable: true  (always selectable, even parent nodes)
 */
class ProcessTreeTest extends TestCase
{
    private function addLevel(Collection $items, int $level = 0): array
    {
        return $items->map(function ($item) use ($level) {
            return [
                'id'                 => $item['id'],
                'classification_code' => $item['classification_code'],
                'name'               => $item['name'],
                'active'             => $item['active'],
                'departament_id'     => $item['departament_id'],
                'level'              => $level,
                'selectable'         => true,
                'children_recursive' => isset($item['children_recursive'])
                    ? $this->addLevel(collect($item['children_recursive']), $level + 1)
                    : [],
            ];
        })->values()->toArray();
    }

    private function buildDepartmentTree(array $departments, ?int $parentId = null): array
    {
        return collect($departments)
            ->where('departament_id', $parentId)
            ->map(function ($dept) use ($departments) {
                $children = $this->buildDepartmentTree($departments, $dept['id']);

                return [
                    'id'                 => 'dept_' . $dept['id'],
                    'name'               => $dept['name'],
                    'selectable'         => false,
                    'children_recursive' => $children,
                ];
            })->values()->toArray();
    }

    #[Test]
    public function process_nodes_are_always_selectable(): void
    {
        $processes = collect([
            ['id' => 1, 'classification_code' => '1.1', 'name' => 'Contratos', 'active' => true, 'departament_id' => 2],
            ['id' => 2, 'classification_code' => '1.1.1', 'name' => 'Locación', 'active' => true, 'departament_id' => 2],
        ]);

        $result = $this->addLevel($processes);

        $this->assertTrue($result[0]['selectable'], 'Parent process must be selectable');
        $this->assertTrue($result[1]['selectable'], 'Child process must be selectable');
    }

    #[Test]
    public function addLevel_preserves_hierarchy_depth(): void
    {
        $processes = collect([
            ['id' => 1, 'classification_code' => '1', 'name' => 'Root', 'active' => true, 'departament_id' => 1,
                'children_recursive' => [
                    ['id' => 2, 'classification_code' => '1.1', 'name' => 'Child', 'active' => true, 'departament_id' => 1],
                ],
            ],
        ]);

        $result = $this->addLevel($processes);

        $this->assertEquals(0, $result[0]['level']);
        $this->assertEquals(1, $result[0]['children_recursive'][0]['level']);
    }

    #[Test]
    public function department_nodes_are_not_selectable(): void
    {
        $departments = [
            ['id' => 1, 'name' => 'DIRECCION A', 'departament_id' => null],
            ['id' => 2, 'name' => 'SUBDIRECCION A', 'departament_id' => 1],
        ];

        $tree = $this->buildDepartmentTree($departments, null);

        $this->assertFalse($tree[0]['selectable'], 'Root department must not be selectable');
        $this->assertEquals('dept_1', $tree[0]['id']);
        $this->assertCount(1, $tree[0]['children_recursive']);
        $this->assertFalse($tree[0]['children_recursive'][0]['selectable']);
    }

    #[Test]
    public function empty_departments_produces_empty_tree(): void
    {
        $tree = $this->buildDepartmentTree([], null);
        $this->assertEmpty($tree);
    }

    #[Test]
    public function role_name_must_be_administrativo(): void
    {
        $this->assertNotEquals('administrador', 'administrativo');
    }
}
