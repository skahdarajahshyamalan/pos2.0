<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class RefactorModuleUid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:convert-uid {module? : Optional module name to convert, e.g. Essentials}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refactor module database migrations, models, controllers and views from integer id to VARCHAR(30) uid';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $module_name = $this->argument('module');

        $base_modules_dir = base_path('Modules');
        if (! is_dir($base_modules_dir)) {
            $base_modules_dir = base_path('../Modules');
        }

        if (! is_dir($base_modules_dir)) {
            $this->error("Modules directory not found at {$base_modules_dir}");
            return 1;
        }

        if (! empty($module_name)) {
            $target_dir = $base_modules_dir . '/' . $module_name;
            if (! is_dir($target_dir)) {
                $this->error("Target module directory not found: {$target_dir}");
                return 1;
            }
            $this->convertModuleDir($target_dir);
        } else {
            $dirs = glob($base_modules_dir . '/*', GLOB_ONLYDIR);
            if (empty($dirs)) {
                $this->info("No module folders found inside {$base_modules_dir}");
                return 0;
            }

            foreach ($dirs as $dir) {
                $this->convertModuleDir($dir);
            }
        }

        $this->info('Module UID refactoring completed successfully!');
        return 0;
    }

    /**
     * Recursively convert PHP files in module directory
     */
    private function convertModuleDir($module_dir)
    {
        $module_name = basename($module_dir);
        $this->info("Processing module: {$module_name}...");

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($module_dir));
        $count = 0;

        foreach ($iterator as $file) {
            if ($file->isDir() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $filepath = $file->getPathname();
            $content = file_get_contents($filepath);
            $originalContent = $content;

            // 1. Primary keys in Migrations
            $content = preg_replace('/\$table->increments\([\'"]id[\'"]\)/i', '$table->string(\'uid\', 30)->primary()', $content);
            $content = preg_replace('/\$table->bigIncrements\([\'"]id[\'"]\)/i', '$table->string(\'uid\', 30)->primary()', $content);
            $content = preg_replace('/\$table->id\(\)/i', '$table->string(\'uid\', 30)->primary()', $content);

            // 2. Foreign Key Column Definitions in Migrations
            $content = preg_replace('/\$table->(?:integer|unsignedInteger|bigInteger|unsignedBigInteger)\([\'"]([a-zA-Z0-9_]+)_id[\'"]\)/i', '$table->string(\'$1_uid\', 30)->nullable()', $content);

            // 3. Remove ->unsigned() from string(...) column definitions
            $content = preg_replace('/(\$table->string\([^;\r\n]+)->unsigned\(\)/i', '$1', $content);

            // 4. Foreign Key references('id') -> references('uid')
            $content = preg_replace('/references\([\'"]id[\'"]\)/i', 'references(\'uid\')', $content);

            // 5. Automatic 'uid' injection for DB table inserts in migrations
            $content = preg_replace('/(\[\s*[\'"]key[\'"]\s*=>)/i', '[\'uid\' => uniqid(\'\', true), \'key\' =>', $content);

            // 6. Foreign Key Column Name Substitutions
            $column_map = [
                'business_id' => 'business_uid',
                'location_id' => 'location_uid',
                'user_id' => 'user_uid',
                'contact_id' => 'contact_uid',
                'transaction_id' => 'transaction_uid',
                'product_id' => 'product_uid',
                'variation_id' => 'variation_uid',
                'category_id' => 'category_uid',
                'brand_id' => 'brand_uid',
                'unit_id' => 'unit_uid',
                'tax_id' => 'tax_uid',
                'created_by' => 'created_by_uid',
                'package_id' => 'package_uid',
                'created_id' => 'created_uid',
            ];

            foreach ($column_map as $oldKey => $newKey) {
                $content = str_replace("'$oldKey'", "'$newKey'", $content);
                $content = str_replace('"' . $oldKey . '"', '"' . $newKey . '"', $content);
            }

            // 6. Query filters and reference replacements
            $content = str_replace('user.business_id', 'user.business_uid', $content);
            $content = str_replace("where('id',", "where('uid',", $content);
            $content = str_replace("whereIn('id',", "whereIn('uid',", $content);
            $content = str_replace("orderBy('id'", "orderBy('uid'", $content);

            if ($content !== $originalContent) {
                file_put_contents($filepath, $content);
                $count++;
            }
        }

        $this->info("Updated {$count} file(s) in {$module_name}.");
    }
}
