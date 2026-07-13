<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateToUniqidKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Disable foreign key constraints to allow changing primary keys
        Schema::disableForeignKeyConstraints();

        $dbName = DB::getDatabaseName();

        // 1b. Dynamically drop all foreign key constraints in the database
        $foreignKeys = DB::select("
            SELECT 
                TABLE_NAME, 
                CONSTRAINT_NAME 
            FROM 
                information_schema.KEY_COLUMN_USAGE 
            WHERE 
                TABLE_SCHEMA = '{$dbName}' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        foreach ($foreignKeys as $fk) {
            try {
                DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Exception $e) {
                // ignore
            }
        }
        $tables = DB::select('SHOW TABLES');
        $key = "Tables_in_" . $dbName;

        // We will collect information about the tables we modify
        $tablesToProcess = [];

        foreach ($tables as $t) {
            $tableName = $t->$key;

            // Skip Laravel framework system tables
            if (in_array($tableName, ['migrations', 'password_resets', 'failed_jobs', 'personal_access_tokens'])) {
                continue;
            }

            // Skip Passport tables (they manage their own schemas)
            if (str_starts_with($tableName, 'oauth_')) {
                continue;
            }

            $tablesToProcess[] = $tableName;
        }

        // --- PHASE 1: Add New unique ID and foreign ID columns ---
        foreach ($tablesToProcess as $table) {
            $hasId = Schema::hasColumn($table, 'id');
            $columns = Schema::getColumnListing($table);

            Schema::table($table, function (Blueprint $tableObj) use ($table, $hasId, $columns) {
                // If the table has an auto-incrementing id, add 'uid'
                if ($hasId && !Schema::hasColumn($table, 'uid')) {
                    $tableObj->string('uid', 30)->nullable()->after('id');
                }

                // Add corresponding _uid columns for foreign keys
                foreach ($columns as $column) {
                    if ($column === 'id' || $column === 'uid') {
                        continue;
                    }

                    if (str_ends_with($column, '_id')) {
                        $uidColumn = substr($column, 0, -3) . '_uid';
                        if (!Schema::hasColumn($table, $uidColumn)) {
                            $tableObj->string($uidColumn, 30)->nullable();
                        }
                    }

                    if (in_array($column, ['created_by', 'updated_by', 'deleted_by'])) {
                        $uidColumn = $column . '_uid';
                        if (!Schema::hasColumn($table, $uidColumn)) {
                            $tableObj->string($uidColumn, 30)->nullable();
                        }
                    }
                }
            });
        }

        // --- PHASE 2: Generate unique IDs for all primary key records ---
        foreach ($tablesToProcess as $table) {
            if (Schema::hasColumn($table, 'id')) {
                $rows = DB::table($table)->select('id')->get();
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update([
                        'uid' => uniqid('', true)
                    ]);
                }
            }
        }

        // --- PHASE 3: Map foreign keys based on old integer connections ---
        foreach ($tablesToProcess as $table) {
            $columns = Schema::getColumnListing($table);
            foreach ($columns as $column) {
                if ($column === 'id' || $column === 'uid') {
                    continue;
                }

                $isForeignKey = str_ends_with($column, '_id') || in_array($column, ['created_by', 'updated_by', 'deleted_by']);
                if (!$isForeignKey) {
                    continue;
                }

                $uidColumn = str_ends_with($column, '_id') ? (substr($column, 0, -3) . '_uid') : ($column . '_uid');
                $foreignTable = $this->getForeignTable($column, $table);

                if ($foreignTable && Schema::hasTable($foreignTable) && Schema::hasColumn($foreignTable, 'uid')) {
                    if ($foreignTable === $table) {
                        // Self-referential foreign key, use aliases to avoid "Not unique table/alias" error
                        DB::statement("
                            UPDATE `{$table}` AS child
                            JOIN `{$table}` AS parent ON child.`{$column}` = parent.id
                            SET child.`{$uidColumn}` = parent.uid
                            WHERE child.`{$column}` IS NOT NULL
                        ");
                    } else {
                        // Update child table with parent's new uid
                        DB::statement("
                            UPDATE `{$table}`
                            JOIN `{$foreignTable}` ON `{$table}`.`{$column}` = `{$foreignTable}`.id
                            SET `{$table}`.`{$uidColumn}` = `{$foreignTable}`.uid
                            WHERE `{$table}`.`{$column}` IS NOT NULL
                        ");
                    }
                }
            }
        }

        // --- PHASE 4: Swap Primary Keys and Drop Old Columns ---
        foreach ($tablesToProcess as $table) {
            $hasId = Schema::hasColumn($table, 'id');
            $columns = Schema::getColumnListing($table);

            // Handle Composite Key tables (without 'id')
            // Handle Composite Key tables (without 'id')
            if (!$hasId) {
                $compositeKeys = [];
                foreach ($columns as $column) {
                    if (str_ends_with($column, '_id')) {
                        $compositeKeys[] = $column;
                    }
                }

                if (!empty($compositeKeys)) {
                    // Drop composite primary key constraint
                    try {
                        DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY");
                    } catch (\Exception $e) {
                        // ignore if no primary key exists
                    }

                    // Drop old columns and rename new ones
                    Schema::table($table, function (Blueprint $tableObj) use ($table, $compositeKeys) {
                        foreach ($compositeKeys as $keyCol) {
                            if (Schema::hasColumn($table, $keyCol)) {
                                $tableObj->dropColumn($keyCol);
                            }
                            $newKeyCol = str_ends_with($keyCol, '_id') ? (substr($keyCol, 0, -3) . '_uid') : ($keyCol . '_uid');
                            DB::statement("ALTER TABLE `{$table}` MODIFY `{$newKeyCol}` VARCHAR(30) NOT NULL");
                        }
                    });

                    // Re-create composite primary key on new columns
                    Schema::table($table, function (Blueprint $tableObj) use ($table, $compositeKeys) {
                        $newKeys = [];
                        foreach ($compositeKeys as $keyCol) {
                            $newKeys[] = str_ends_with($keyCol, '_id') ? (substr($keyCol, 0, -3) . '_uid') : ($keyCol . '_uid');
                        }
                        if (Schema::hasColumn($table, 'model_type')) {
                            $newKeys[] = 'model_type';
                        }
                        $tableObj->primary($newKeys);
                    });
                }
                continue;
            }

            // Normal tables with single 'id' primary key
            // 1. Remove Auto Increment from old 'id'
            DB::statement("ALTER TABLE `{$table}` MODIFY id INT NOT NULL");

            // 2. Drop primary key constraint
            try {
                DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY");
            } catch (\Exception $e) {
                // ignore
            }

            // 3. Drop old 'id' and make 'uid' the primary key
            Schema::table($table, function (Blueprint $tableObj) use ($table) {
                $tableObj->dropColumn('id');
            });

            DB::statement("ALTER TABLE `{$table}` MODIFY uid VARCHAR(30) NOT NULL");

            Schema::table($table, function (Blueprint $tableObj) {
                $tableObj->primary('uid');
            });

            // 4. Drop old foreign key '_id' / '_by' columns and rename new ones
            Schema::table($table, function (Blueprint $tableObj) use ($table, $columns) {
                foreach ($columns as $column) {
                    if ($column === 'id' || $column === 'uid') {
                        continue;
                    }

                    if (str_ends_with($column, '_id') || in_array($column, ['created_by', 'updated_by', 'deleted_by'])) {
                        $tableObj->dropColumn($column);
                        
                        // Rename the new _uid column to replace the old name
                        $uidColumn = str_ends_with($column, '_id') ? (substr($column, 0, -3) . '_uid') : ($column . '_uid');
                        // Set the new column to VARCHAR(30)
                        DB::statement("ALTER TABLE `{$table}` MODIFY `{$uidColumn}` VARCHAR(30) NULL");
                    }
                }
            });
        }

        // Re-enable foreign key constraints
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reversing this structural migration dynamically is highly destructive and complex.
        // It is omitted as we rely on starting with a fresh dump if needed.
        throw new \Exception("Cannot reverse structural UUID migration automatically.");
    }

    /**
     * Map prefix to correct database table names.
     */
    private function getForeignTable($column, $currentTable)
    {
        if (in_array($column, ['created_by', 'updated_by', 'deleted_by'])) {
            return 'users';
        }
        if ($column === 'parent_id') {
            return $currentTable;
        }
        if ($column === 'location_id') {
            return 'business_locations';
        }

        $prefix = substr($column, 0, -3); // remove _id

        if (Schema::hasTable(Str::plural($prefix))) {
            return Str::plural($prefix);
        }
        if (Schema::hasTable($prefix)) {
            return $prefix;
        }

        // Custom plurals & table overrides
        $mappings = [
            'tax_rate' => 'tax_rates',
            'expense_category' => 'expense_categories',
            'customer_group' => 'customer_groups',
            'selling_price_group' => 'selling_price_groups',
            'types_of_service' => 'types_of_services',
            'account_type' => 'account_types',
            'product_variation' => 'product_variations',
            'dashboard_configuration' => 'dashboard_configurations',
            'cash_register' => 'cash_registers',
            'invoice_layout' => 'invoice_layouts',
            'invoice_scheme' => 'invoice_schemes',
        ];

        if (isset($mappings[$prefix])) {
            return $mappings[$prefix];
        }

        return null;
    }
}
