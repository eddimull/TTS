<?php

namespace App\Services;

use App\Services\Search\BookingSearchService;
use App\Services\Search\Contracts\SearchableInterface;
use Illuminate\Support\Facades\File;
use Laravel\Scout\Searchable;
use InvalidArgumentException;
use ReflectionClass;

class SearchService
{
    private array $searchableModels = [];

    public function __construct()
    {
        $this->searchableModels = $this->getSearchableModelInstances();
    }


    public function search(string $query): array
    {
        $results = [];
        
        foreach ($this->searchableModels as $model) {
            try {
                $modelResults = $model->search($query)->get();
                
                $modelName = strtolower(class_basename($model));
                $results[$modelName] = $modelResults;
            } catch (\Exception $e) {
                // Log error or handle as needed
                continue;
            }
        }

        return $results;
    }


    /**
     * Get all models that use the Searchable trait
     */
    public function getSearchableModels(): array
    {
        $searchableModels = [];
        $modelPath = app_path('Models');
        
        if (!File::exists($modelPath)) {
            return $searchableModels;
        }

        $modelFiles = File::allFiles($modelPath);
        
        foreach ($modelFiles as $file) {
            $className = $this->getClassNameFromFile($file);
            
            if ($className && $this->usesSearchableTrait($className)) {
                $searchableModels[] = $className;
            }
        }
        
        return $searchableModels;
    }

    /**
     * Check if a model uses the Searchable trait
     */
    public function usesSearchableTrait(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($className);
            $traits = $reflection->getTraitNames();
            
            return in_array(Searchable::class, $traits);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get class name from file path
     */
    private function getClassNameFromFile($file): ?string
    {
        $relativePath = str_replace(app_path() . '/', '', $file->getRealPath());
        $className = str_replace(['/', '.php'], ['\\', ''], $relativePath);
        $fullClassName = 'App\\' . $className;
        
        return class_exists($fullClassName) ? $fullClassName : null;
    }

    /**
     * Get searchable model instances
     */
    private function getSearchableModelInstances(): array
    {
        $instances = [];
        $modelClasses = $this->getSearchableModels();
        
        foreach ($modelClasses as $modelClass) {
            try {
                $instances[] = new $modelClass();
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return $instances;
    }

    /**
     * Get searchable model instances with their search configurations
     */
    public function getSearchableModelConfigurations(): array
    {
        $configurations = [];
        $searchableModels = $this->getSearchableModels();
        
        foreach ($searchableModels as $modelClass) {
            $model = new $modelClass();
            
            $configurations[] = [
                'class' => $modelClass,
                'index' => method_exists($model, 'searchableAs') ? $model->searchableAs() : null,
                'should_be_searchable' => method_exists($model, 'shouldBeSearchable') ? $model->shouldBeSearchable() : true,
            ];
        }
        
        return $configurations;
    }
}
