<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Relationship;
use App\Models\Contact;
use App\Models\Address;
use App\Models\Work;
use Carbon\Carbon;

class ClientDataProcessorService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected Client $client) {}

    public function processDatadiverData(array $data): void
    {
        $infoGeneral = $data['info_general'] ?? [];
        $familyData = $infoGeneral['family'] ?? [];
        $genomeData = $data['info_family']['family'] ?? [];
        $contacts = $data['info_contacts']['phones'] ?? [];
        $addresses = $data['info_contacts']['address'] ?? [];
        $works = $data['info_labour']['jobInfoCompany'] ?? [];

        //$this->processClient($infoGeneral);
        $this->processFamilyData($familyData);
        $this->processFamilyData($genomeData);
        $this->processContacts($contacts);
        $this->processAddresses($addresses);
        $this->processLabours($works);
    }

    public static function createClientFromDatadiver(array $data): self
    {
        $infoGeneral = $data['info_general'] ?? [];
        $fullname = $infoGeneral['fullname'] ?? null;
        
        // Detectar si es un menor usando cédula de padres
        $usesParentId = preg_match('/menor\s+de\s+edad\s*\(.*\)/i', $fullname);

        $client = Client::updateOrCreate(
            [
                'identification' => $infoGeneral['dni'] ?? null,
            ],
            [
                'name' => $fullname,
                'uses_parent_identification' => $usesParentId,
                'parent_identification' => $usesParentId ? ($infoGeneral['dni'] ?? null) : null,
                'birth' => !empty($infoGeneral['dateOfBirth']) ? Carbon::createFromFormat('d/m/Y', $infoGeneral['dateOfBirth'])->format('Y-m-d') : null,
                'death' => !empty($infoGeneral['dateOfDeath']) ? Carbon::createFromFormat('d/m/Y', $infoGeneral['dateOfDeath'])->format('Y-m-d') : null,
                'gender' => $infoGeneral['gender'] ?? null,
                'state_civil' => $infoGeneral['civilStatus'] ?? null,
                'place_birth' => $infoGeneral['placeOfBirth'] ?? null,
                'nationality' => $infoGeneral['citizenship'] ?? null,
                'profession' => $infoGeneral['profession'] ?? null,
                'salary' => $infoGeneral['salary'] ?? null,
            ]
        );

        return new self($client);
    }

    protected function processClient(array $client): void
    {
        $create_client = Client::updateOrCreate(
            [
                'identification' => $client['dni'],
                "name" => $client['name']
            ],
            [
                'name' => $client['fullname'],
                'birth' => !empty($client['dateOfBirth']) ? date('Y-m-d', strtotime($client['dateOfBirth'])) : null,
                'death' => !empty($client['dateOfDeath']) ? date('Y-m-d', strtotime($client['dateOfDeath'])) : null,
                'gender' => $client['gender'],
                'state_civil' => $client['civilStatus'],
                'place_birth' => $client['placeOfBirth'],
                'nationality' => $client['citizenship'],
                'profession' => $client['profession'],
                'salary' => $client['salary']
            ]
        );
    }

    protected function processFamilyData(array $family): void
    {
        foreach ($family as $person) {
            if (empty($person['dni'])) continue;

            // Para hijos menores que vienen con cédula del padre, buscamos solo por nombre y fecha de nacimiento
            $isSameDniAsParent = $person['dni'] === $this->client->identification;
            $relationshipType = strtoupper($person['relationship'] ?? 'UNKNOWN');

            // Si la cédula es la misma que el padre y es una relación de hijo/hija, es un menor de edad sin cédula
            $isMinor = $isSameDniAsParent && in_array($relationshipType, ['HIJO', 'HIJA']);

            if ($isMinor && !empty($person['dateOfBirth'])) {
                // Limpiar el nombre
                $fullname = trim($person['fullname'] ?? '');

                // Buscar por nombre y fecha de nacimiento para menores de edad
                $existingPerson = Client::where('name', $fullname)
                    ->where('birth', $this->parseDate($person['dateOfBirth']))
                    ->whereNull('identification') // Solo buscar entre registros sin cédula
                    ->first();

                if (!$existingPerson) {
                    // Crear hijo sin cédula (incluso si el nombre es genérico como "Menor De Edad (Cedula Padres)")
                    $existingPerson = Client::create([
                        'identification' => null, // Menores de edad sin cédula
                        'uses_parent_identification' => true,
                        'parent_identification' => $this->client->identification,
                        'name' => $fullname,
                        'birth' => $this->parseDate($person['dateOfBirth']),
                        'gender' => $person['gender'] ?? null,
                        'state_civil' => $person['civilStatus'] ?? null,
                        'nationality' => $person['citizenship'] ?? null
                    ]);
                }
            } elseif (!$isSameDniAsParent) {
                // Proceso normal para personas con cédula propia (no es la misma que el padre)
                $existingPerson = Client::firstOrCreate(
                    ['identification' => $person['dni']],
                    [
                        'name' => $person['fullname'],
                        'birth' => $this->parseDate((!empty($person['dateOfBirth']) ? $person['dateOfBirth'] : '')),
                        'gender' => $person['gender'] ?? null,
                        'state_civil' => $person['civilStatus'] ?? null,
                        'nationality' => $person['citizenship'] ?? null
                    ]
                );
            } else {
                // Si la cédula es la misma pero no es una relación hijo/hija, saltar
                // (evita crear relaciones del cliente consigo mismo)
                continue;
            }

            // Evitar crear relaciones del cliente consigo mismo
            if ($existingPerson->id === $this->client->id) {
                continue;
            }

            // Verificar si ya existe CUALQUIER relación entre estos dos clientes
            $existingRelationship = Relationship::where('client_id', $this->client->id)
                ->where('relationship_client_id', $existingPerson->id)
                ->first();

            if ($existingRelationship) {
                // Si ya existe una relación, verificar si la nueva es más prioritaria
                $currentPriority = $this->getRelationshipPriority($existingRelationship->type);
                $newPriority = $this->getRelationshipPriority($relationshipType);

                // Solo actualizar si la nueva relación tiene mayor prioridad
                if ($newPriority > $currentPriority) {
                    $existingRelationship->update(['type' => $relationshipType]);
                }
            } else {
                // No existe relación, crear una nueva
                Relationship::create([
                    'type' => $relationshipType,
                    'relationship_client_id' => $existingPerson->id,
                    'client_id' => $this->client->id
                ]);
            }
        }
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

        return $priorities[$relationshipType] ?? 10; // Default priority para relaciones desconocidas
    }

    protected function processContacts(array $phones): void
    {
        foreach ($phones as $phone) {
            $number = str_replace(' ', '', $phone['phone']);
            Contact::firstOrCreate([
                'phone_number' => $number,
                'client_id' => $this->client->id
            ]);
        }
    }

    protected function processAddresses(array $addresses): void
    {
        foreach ($addresses as $addr) {
            Address::create([
                'address' => $addr['address'],
                'type' => $addr['type'],
                'province' => $addr['province'],
                'city' => $addr['city'],
                'is_valid' => 'NO',
                'client_id' => $this->client->id
            ]);
        }
    }

    protected function processLabours(array $labours): void
    {
        foreach ($labours as $labour) {
            Work::create([
                'type' => 'JOB',
                'address' => $labour['address'],
                'province' => '',
                'ruc' => $labour['ruc'],
                'activities_start_date' => $labour['admissionDate'],
                'suspension_request_date' => $labour['fireDate'],
                'legal_name' => $labour['legalName'],
                'activities_restart_date' => '',
                'phone' => $labour['phone'],
                'taxpayer_status' => '',
                'email' => $labour['email'],
                'economic_activity' => $labour['position'],
                'business_name' => $labour['legalName'],
                'client_id' => $this->client->id
            ]);
        }
    }

    protected function parseDate(string $date): ?string
    {
        return !empty($date) ? Carbon::createFromFormat('d/m/Y', $date)->format('Y-m-d') : null;
    }
}
