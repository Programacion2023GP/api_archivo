<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class RollbackOneMigration extends Command
{
    protected $signature = 'migrate:refresh-with-data 
                            {table : Nombre de la tabla a refrescar}
                            {--keep-data : Mantener los datos existentes (default)}
                            {--force : Forzar ejecución sin confirmación}
                            {--skip-backup : Saltar el backup automático}';

    protected $description = 'Refresca una tabla manteniendo sus datos (rollback + migrate + restore)';

    public function handle()
    {
        $table = $this->argument('table');
        $force = $this->option('force');
        $keepData = $this->option('keep-data');
        $skipBackup = $this->option('skip-backup');

        $this->showBanner();

        // Validar que la tabla existe
        if (!Schema::hasTable($table)) {
            $this->error("❌ La tabla '$table' no existe en la base de datos.");
            return 1;
        }

        // Contar registros actuales
        $recordCount = DB::table($table)->count();
        $this->info("📊 Tabla encontrada: <fg=cyan>$table</>");
        $this->info("   📈 Registros actuales: <fg=yellow>$recordCount</>");

        // Encontrar migraciones relacionadas
        $migrations = $this->findMigrationsForTable($table);

        if ($migrations->isEmpty()) {
            $this->error("❌ No se encontraron migraciones para la tabla '$table'.");
            return 1;
        }

        // Mostrar resumen de migraciones
        $this->showMigrationsSummary($migrations);

        // Confirmar con el usuario
        if (!$force && !$this->confirm("¿Estás seguro de que quieres refrescar la tabla '$table'?", true)) {
            $this->info('🛑 Operación cancelada.');
            return 0;
        }

        try {
            DB::beginTransaction();

            // Paso 1: Hacer backup de datos
            $backupPath = null;
            if (!$skipBackup) {
                $backupPath = $this->backupTableData($table);
            }

            // Paso 2: Obtener estructura actual para referencia
            $tableStructure = $this->getTableStructure($table);

            // Paso 3: Extraer datos con cuidado
            $backupData = $this->extractTableData($table);

            // Paso 4: Rollback de migraciones
            $this->info("\n🔄 <fg=yellow>Ejecutando rollback...</>");
            $this->rollbackMigrations($migrations);

            // Paso 5: Volver a migrar
            $this->info("\n🔄 <fg=yellow>Migrando nuevamente...</>");
            $this->migrateMigrations($migrations);

            // Paso 6: Restaurar datos (adaptando a nueva estructura)
            $this->info("\n💾 <fg=yellow>Restaurando datos...</>");
            $restoredCount = $this->restoreTableData($table, $backupData, $tableStructure);

            // Paso 7: Revisar integridad
            $this->verifyRestoration($table, $recordCount, $restoredCount);

            DB::commit();

            $this->showSuccessMessage($table, $restoredCount, $backupPath);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->error("\n❌ ERROR CRÍTICO: " . $e->getMessage());
            $this->error("   📍 Archivo: " . $e->getFile() . ":" . $e->getLine());

            // Intentar restaurar desde backup si existe
            if (isset($backupPath) && File::exists($backupPath)) {
                $this->warn("\n⚠️  Se ha creado un backup en: $backupPath");
                $this->info("   Puedes restaurar manualmente si es necesario.");
            }

            return 1;
        }

        return 0;
    }

    /**
     * Muestra un banner informativo
     */
    private function showBanner()
    {
        $this->line("\n<fg=cyan>========================================</>");
        $this->line("<fg=cyan>  REFRESCO DE TABLA CON CONSERVACIÓN DE DATOS</>");
        $this->line("<fg=cyan>========================================</>\n");
    }

    /**
     * Encuentra migraciones relacionadas con una tabla
     */
    private function findMigrationsForTable(string $table): Collection
    {
        $migrations = DB::table('migrations')->get();
        $relatedMigrations = collect();

        foreach ($migrations as $migration) {
            $path = database_path("migrations/{$migration->migration}.php");

            if (!file_exists($path)) {
                continue;
            }

            $content = file_get_contents($path);

            // Patrones para detectar operaciones en la tabla
            $patterns = [
                "/Schema::create\s*\(\s*['\"]{$table}['\"]/i",
                "/Schema::table\s*\(\s*['\"]{$table}['\"]/i",
                "/->from\s*\(\s*['\"]{$table}['\"]/i",
                "/->into\s*\(\s*['\"]{$table}['\"]/i",
                "/['\"]{$table}['\"]\s*=>/i"
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $relatedMigrations->push($migration);
                    break;
                }
            }
        }

        return $relatedMigrations->sortBy('batch');
    }

    /**
     * Muestra resumen de migraciones
     */
    private function showMigrationsSummary(Collection $migrations)
    {
        $this->info("📋 <fg=cyan>Migraciones a procesar:</>");

        $this->table(
            ['#', 'Migración', 'Batch', 'Fecha'],
            $migrations->map(function ($migration, $index) {
                return [
                    '#' => $index + 1,
                    'Migración' => $this->truncateMigrationName($migration->migration),
                    'Batch' => $migration->batch,
                    'Fecha' => $migration->created_at ?? 'N/A'
                ];
            })
        );
    }

    /**
     * Hace backup de los datos
     */
    private function backupTableData(string $table): string
    {
        $backupDir = storage_path('backups/migrations');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = date('Y-m-d_His');
        $filename = "{$table}_{$timestamp}.json";
        $filepath = $backupDir . '/' . $filename;

        $data = DB::table($table)->get();
        $backupData = [
            'table' => $table,
            'timestamp' => $timestamp,
            'record_count' => $data->count(),
            'structure' => $this->getTableStructure($table),
            'data' => $data->toArray()
        ];

        file_put_contents($filepath, json_encode($backupData, JSON_PRETTY_PRINT));

        $this->info("💾 <fg=green>Backup creado:</> " . basename($filepath));

        return $filepath;
    }

    /**
     * Obtiene la estructura de la tabla
     */
    private function getTableStructure(string $table): array
    {
        $columns = DB::select("DESCRIBE `{$table}`");

        $structure = [];
        foreach ($columns as $column) {
            $structure[$column->Field] = [
                'type' => $column->Type,
                'null' => $column->Null,
                'key' => $column->Key,
                'default' => $column->Default,
                'extra' => $column->Extra
            ];
        }

        return $structure;
    }

    /**
     * Extrae datos de la tabla
     */
    private function extractTableData(string $table): Collection
    {
        return DB::table($table)->get();
    }

    /**
     * Ejecuta rollback de migraciones
     */
    private function rollbackMigrations(Collection $migrations): void
    {
        $progressBar = $this->output->createProgressBar($migrations->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        foreach ($migrations->sortByDesc('batch') as $migration) {
            $progressBar->setMessage("Rollback: {$this->truncateMigrationName($migration->migration)}");

            $path = database_path("migrations/{$migration->migration}.php");
            require_once $path;

            $className = $this->getClassNameFromFile($path);
            $instance = new $className();

            // Ejecutar down()
            $instance->down();

            // Eliminar de migrations
            DB::table('migrations')->where('id', $migration->id)->delete();

            $progressBar->advance();
        }

        $progressBar->setMessage("✅ Rollback completado");
        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Vuelve a ejecutar las migraciones
     */
    private function migrateMigrations(Collection $migrations): void
    {
        $progressBar = $this->output->createProgressBar($migrations->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        $maxBatch = DB::table('migrations')->max('batch') ?? 0;
        $newBatch = $maxBatch + 1;

        foreach ($migrations as $migration) {
            $progressBar->setMessage("Migrando: {$this->truncateMigrationName($migration->migration)}");

            $path = database_path("migrations/{$migration->migration}.php");
            require_once $path;

            $className = $this->getClassNameFromFile($path);
            $instance = new $className();

            // Ejecutar up()
            $instance->up();

            // Insertar en migrations con nuevo batch
            DB::table('migrations')->insert([
                'migration' => $migration->migration,
                'batch' => $newBatch
            ]);

            $progressBar->advance();
        }

        $progressBar->setMessage("✅ Migración completada");
        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Restaura los datos en la tabla
     */
    private function restoreTableData(string $table, Collection $data, array $oldStructure): int
    {
        if ($data->isEmpty()) {
            return 0;
        }

        $newStructure = $this->getTableStructure($table);
        $restoredCount = 0;

        $progressBar = $this->output->createProgressBar($data->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->setMessage("Restaurando registros...");

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($data as $record) {
            $recordArray = (array) $record;

            // Filtrar solo las columnas que existen en la nueva estructura
            $filteredRecord = [];
            foreach ($recordArray as $column => $value) {
                if (array_key_exists($column, $newStructure)) {
                    $filteredRecord[$column] = $value;
                }
            }

            // Insertar registro
            if (!empty($filteredRecord)) {
                try {
                    DB::table($table)->insert($filteredRecord);
                    $restoredCount++;
                } catch (\Exception $e) {
                    $this->warn("\n⚠️  Error insertando registro: " . $e->getMessage());
                }
            }

            $progressBar->advance();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $progressBar->setMessage("✅ Restauración completada: $restoredCount registros");
        $progressBar->finish();
        $this->newLine();

        return $restoredCount;
    }

    /**
     * Verifica la restauración
     */
    private function verifyRestoration(string $table, int $originalCount, int $restoredCount): void
    {
        $currentCount = DB::table($table)->count();

        $this->info("📊 <fg=cyan>Verificación de integridad:</>");
        $this->line("   • Registros originales: $originalCount");
        $this->line("   • Registros restaurados: $restoredCount");
        $this->line("   • Registros actuales: $currentCount");

        if ($originalCount === $restoredCount && $restoredCount === $currentCount) {
            $this->info("   ✅ <fg=green>¡Todos los registros se restauraron correctamente!</>");
        } elseif ($restoredCount < $originalCount) {
            $this->warn("   ⚠️  Se perdieron " . ($originalCount - $restoredCount) . " registros");
            $this->info("   ℹ️  Esto puede ser normal si hubo cambios en la estructura que afectaron la restauración");
        }
    }

    /**
     * Muestra mensaje de éxito
     */
    private function showSuccessMessage(string $table, int $restoredCount, ?string $backupPath): void
    {
        $this->line("\n<fg=green>========================================</>");
        $this->line("<fg=green>  ✅ OPERACIÓN COMPLETADA EXITOSAMENTE</>");
        $this->line("<fg=green>========================================</>");
        $this->info("\n📋 <fg=cyan>Resumen:</>");
        $this->line("   • Tabla: <fg=yellow>$table</>");
        $this->line("   • Registros restaurados: <fg=yellow>$restoredCount</>");

        if ($backupPath) {
            $this->line("   • Backup: <fg=cyan>" . basename($backupPath) . "</>");
        }

        $this->line("\n🎉 <fg=green>¡La tabla ha sido refrescada manteniendo sus datos!</>");
        $this->line("   Los cambios en la migración han sido aplicados.");
    }

    /**
     * Trunca nombres largos de migración
     */
    private function truncateMigrationName(string $name, int $length = 50): string
    {
        if (strlen($name) <= $length) {
            return $name;
        }

        return substr($name, 0, $length - 3) . '...';
    }

    /**
     * Extrae el nombre de la clase del archivo
     */
    private function getClassNameFromFile($file): string
    {
        $tokens = token_get_all(file_get_contents($file));
        $classToken = false;
        $namespace = '';

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if ($token[0] === T_NAMESPACE) {
                    $namespace = '';
                    while (($token = next($tokens)) && $token[0] !== T_SEMICOLON) {
                        if ($token[0] === T_STRING) {
                            $namespace .= '\\' . $token[1];
                        }
                    }
                }

                if ($token[0] === T_CLASS) {
                    $classToken = true;
                } elseif ($classToken && $token[0] === T_STRING) {
                    return $namespace . '\\' . $token[1];
                }
            }
        }

        throw new \Exception("Class name not found in $file");
    }
}
