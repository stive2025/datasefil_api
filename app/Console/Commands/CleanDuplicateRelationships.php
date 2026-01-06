<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Relationship;
use Illuminate\Support\Facades\DB;

class CleanDuplicateRelationships extends Command
{
    protected $signature = 'relationships:clean';
    protected $description = 'Limpia relaciones duplicadas o contradictorias, manteniendo solo la más prioritaria';

    public function handle()
    {
        $this->info('Buscando relaciones duplicadas...');

        // Obtener todas las combinaciones de client_id y relationship_client_id que tienen múltiples relaciones
        $duplicates = DB::table('relationships')
            ->select('client_id', 'relationship_client_id', DB::raw('COUNT(*) as count'))
            ->groupBy('client_id', 'relationship_client_id')
            ->having('count', '>', 1)
            ->get();

        $this->info("Se encontraron {$duplicates->count()} conjuntos de relaciones duplicadas.");

        $cleaned = 0;

        foreach ($duplicates as $duplicate) {
            // Obtener todas las relaciones para este par de clientes
            $relationships = Relationship::where('client_id', $duplicate->client_id)
                ->where('relationship_client_id', $duplicate->relationship_client_id)
                ->get();

            // Ordenar por prioridad y mantener solo la más alta
            $sorted = $relationships->sortByDesc(function ($rel) {
                return $this->getRelationshipPriority($rel->type);
            });

            // Mantener la primera (mayor prioridad) y eliminar las demás
            $toKeep = $sorted->first();
            $toDelete = $sorted->skip(1);

            foreach ($toDelete as $rel) {
                $this->line("Eliminando: Cliente {$rel->client_id} -> {$rel->relationship_client_id} ({$rel->type}) [Manteniendo: {$toKeep->type}]");
                $rel->delete();
                $cleaned++;
            }
        }

        $this->info("✓ Se limpiaron {$cleaned} relaciones duplicadas.");
        $this->info('Proceso completado.');

        return Command::SUCCESS;
    }

    /**
     * Determina la prioridad de una relación familiar
     * Mayor número = mayor prioridad (relaciones más directas)
     */
    protected function getRelationshipPriority(string $relationshipType): int
    {
        $priorities = [
            // Relaciones directas - máxima prioridad
            'PADRE' => 100,
            'MADRE' => 100,
            'HIJO' => 100,
            'HIJA' => 100,
            'ESPOSO' => 100,
            'ESPOSA' => 100,
            'CONYUGE' => 100,
            
            // Hermanos - alta prioridad
            'HERMANO' => 80,
            'HERMANA' => 80,
            
            // Abuelos y nietos
            'ABUELO' => 70,
            'ABUELA' => 70,
            'NIETO' => 70,
            'NIETA' => 70,
            
            // Tíos y sobrinos
            'TIO' => 50,
            'TIA' => 50,
            'TIO PATERNO' => 50,
            'TIA PATERNA' => 50,
            'TIO MATERNO' => 50,
            'TIA MATERNA' => 50,
            'SOBRINO' => 50,
            'SOBRINA' => 50,
            
            // Primos
            'PRIMO' => 30,
            'PRIMA' => 30,
            
            // Otros
            'SUEGRO' => 20,
            'SUEGRA' => 20,
            'YERNO' => 20,
            'NUERA' => 20,
            'CUÑADO' => 20,
            'CUÑADA' => 20,
        ];

        return $priorities[$relationshipType] ?? 10;
    }
}
