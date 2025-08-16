<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Laravel\Scout\Searchable;
use ReflectionClass;

class importAllSearchableModels extends Command
{
    protected $signature = 'scout:import-all {--fresh : Delete existing index data before importing}';
    
    protected $description = 'Import all searchable models into the search index';

    public function handle()
    {
        $searchableModels = $this->getSearchableModels();
        
        if (empty($searchableModels)) {
            $this->info('No searchable models found.');
            return;
        }

        $this->info('Found ' . count($searchableModels) . ' searchable models:');
        
        foreach ($searchableModels as $model) {
            $this->line("- {$model}");
        }

        $this->newLine();

        foreach ($searchableModels as $model) {
            $this->info("Importing {$model}...");
            
            if ($this->option('fresh')) {
                $this->call('scout:flush', ['model' => $model]);
            }
            
            $this->call('scout:import', ['model' => $model]);
        }

        $this->info('All searchable models have been imported successfully!');
    }

    private function getSearchableModels(): array
    {
        $models = [];
        $modelPath = app_path('Models');
        
        if (!File::exists($modelPath)) {
            return $models;
        }

        $files = File::allFiles($modelPath);
        
        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);
            
            if ($className && $this->isSearchableModel($className)) {
                $models[] = $className;
            }
        }

        return $models;
    }

    private function getClassNameFromFile($file): ?string
    {
        $relativePath = str_replace(app_path() . '/', '', $file->getPathname());
        $className = str_replace(['/', '.php'], ['\\', ''], $relativePath);
        $fullClassName = 'App\\' . $className;

        if (class_exists($fullClassName)) {
            return $fullClassName;
        }

        return null;
    }

    private function isSearchableModel(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($className);
            
            // Check if class uses Searchable trait
            $traits = class_uses_recursive($className);
            
            return in_array(Searchable::class, $traits);
        } catch (\Exception $e) {
            return false;
        }
    }
}