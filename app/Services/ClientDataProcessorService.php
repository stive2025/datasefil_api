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
        $familyNames = $infoGeneral['familyName'] ?? [];
        $genomeData = $data['info_family']['family'] ?? [];
        $contacts = $data['info_contacts']['phones'] ?? [];
        $emails = $data['info_contacts']['emails'] ?? [];
        $addresses = $data['info_contacts']['address'] ?? [];
        $works = $data['info_labour']['jobInfoCompany'] ?? [];

        //$this->processClient($infoGeneral);
        $this->processFamilyNames($familyNames);
        $this->processFamilyData($familyData);
        $this->processFamilyData($genomeData);
        $this->processContacts($contacts);
        $this->processEmails($emails);
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

    protected function processFamilyNames(array $familyNames): void
    {
        // Procesar cónyuge
        if (!empty($familyNames['spouse'])) {
            $this->createOrFindPersonWithoutDni($familyNames['spouse'], 'CONYUGE');
        }

        // Procesar padre
        if (!empty($familyNames['dad'])) {
            $this->createOrFindPersonWithoutDni($familyNames['dad'], 'PADRE');
        }

        // Procesar madre
        if (!empty($familyNames['mom'])) {
            $this->createOrFindPersonWithoutDni($familyNames['mom'], 'MADRE');
        }
    }

    protected function createOrFindPersonWithoutDni(string $fullname, string $relationshipType): void
    {
        $fullname = trim($fullname);

        if (empty($fullname)) return;

        // Buscar si ya existe una persona con este nombre relacionada con este cliente
        $existingRelationship = Relationship::where('client_id', $this->client->id)
            ->where('type', $relationshipType)
            ->first();

        if ($existingRelationship) {
            // Ya existe una relación de este tipo, no crear duplicado
            return;
        }

        // Buscar por nombre exacto sin DNI
        $existingPerson = Client::where('name', $fullname)
            ->whereNull('identification')
            ->first();

        if (!$existingPerson) {
            // Crear nueva persona sin DNI
            $existingPerson = Client::create([
                'identification' => null,
                'name' => $fullname,
            ]);
        }

        // Evitar crear relación del cliente consigo mismo
        if ($existingPerson->id === $this->client->id) {
            return;
        }

        // Crear la relación
        Relationship::firstOrCreate([
            'type' => $relationshipType,
            'relationship_client_id' => $existingPerson->id,
            'client_id' => $this->client->id
        ]);
    }

    protected function processFamilyData(array $family): void
    {
        foreach ($family as $person) {
            if (empty($person['dni']) && empty($person['fullname'])) continue;

            $relationshipType = strtoupper($person['relationship'] ?? 'UNKNOWN');
            $fullname = trim($person['fullname'] ?? '');

            // Detectar si es un menor de edad:
            // 1. El nombre contiene "MENOR DE EDAD" O
            // 2. Es relación hijo/hija Y (la cédula es la del cliente O la cédula ya existe en DB con otro nombre)
            $isMinorByName = preg_match('/menor\s+de\s+edad/i', $fullname);
            $isChildRelationship = in_array($relationshipType, ['HIJO', 'HIJA']);

            // Para menores, necesitamos verificar si la cédula pertenece a otro familiar
            $isMinor = false;
            if ($isChildRelationship && !empty($person['dni'])) {
                // Verificar si esta cédula ya existe con un nombre diferente (no es un menor)
                $existingWithSameDni = Client::where('identification', $person['dni'])
                    ->where('name', '!=', $fullname)
                    ->first();

                $isSameDniAsParent = $person['dni'] === $this->client->identification;
                $isMinor = $isMinorByName || $isSameDniAsParent || $existingWithSameDni;
            } elseif ($isMinorByName) {
                $isMinor = true;
            }

            // Si no tiene DNI, buscar o crear por nombre
            if (empty($person['dni'])) {
                if (empty($fullname)) continue;

                // Buscar por nombre (puede haber duplicados, tomar el primero)
                $existingPerson = Client::where('name', $fullname)
                    ->whereNull('identification')
                    ->first();

                if (!$existingPerson) {
                    $existingPerson = Client::create([
                        'identification' => null,
                        'name' => $fullname,
                        'birth' => $this->parseDate($person['dateOfBirth'] ?? ''),
                        'gender' => $person['gender'] ?? null,
                        'state_civil' => $person['civilStatus'] ?? null,
                        'nationality' => $person['citizenship'] ?? null
                    ]);
                }
            } elseif ($isMinor) {
                // Es un menor de edad que usa cédula de padre/madre
                if (empty($fullname)) continue;

                // Buscar por nombre y fecha de nacimiento para menores de edad
                $birthDate = $this->parseDate($person['dateOfBirth'] ?? '');

                $existingPerson = Client::where('name', $fullname)
                    ->where('birth', $birthDate)
                    ->whereNull('identification') // Solo buscar entre registros sin cédula
                    ->first();

                if (!$existingPerson) {
                    // Determinar de quién es la cédula que está usando
                    $parentDni = $person['dni'];

                    // Crear hijo sin cédula propia
                    $existingPerson = Client::create([
                        'identification' => null, // Menores de edad sin cédula
                        'uses_parent_identification' => true,
                        'parent_identification' => $parentDni,
                        'name' => $fullname,
                        'birth' => $birthDate,
                        'gender' => $person['gender'] ?? null,
                        'state_civil' => $person['civilStatus'] ?? null,
                        'nationality' => $person['citizenship'] ?? null
                    ]);
                }
            } else {
                // Proceso normal para personas con cédula propia
                $existingPerson = Client::firstOrCreate(
                    ['identification' => $person['dni']],
                    [
                        'name' => $fullname,
                        'birth' => $this->parseDate($person['dateOfBirth'] ?? ''),
                        'gender' => $person['gender'] ?? null,
                        'state_civil' => $person['civilStatus'] ?? null,
                        'nationality' => $person['citizenship'] ?? null
                    ]
                );
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
            if (empty($addr['address'])) continue;

            Address::firstOrCreate(
                [
                    'address' => $addr['address'],
                    'client_id' => $this->client->id
                ],
                [
                    'type' => $addr['type'] ?? 'actualizado',
                    'province' => $addr['province'] ?? 'sin datos',
                    'city' => $addr['city'] ?? 'sin datos',
                    'is_valid' => 'NO'
                ]
            );
        }
    }

    protected function processLabours(array $labours): void
    {
        foreach ($labours as $labour) {
            if (empty($labour['ruc'])) continue;

            Work::firstOrCreate(
                [
                    'ruc' => $labour['ruc'],
                    'client_id' => $this->client->id
                ],
                [
                    'type' => 'JOB',
                    'address' => $labour['address'] ?? '',
                    'province' => '',
                    'activities_start_date' => $this->parseDate($labour['admissionDate'] ?? ''),
                    'suspension_request_date' => $this->parseDate($labour['fireDate'] ?? ''),
                    'legal_name' => $labour['legalName'] ?? '',
                    'activities_restart_date' => null,
                    'phone' => $labour['phone'] ?? '',
                    'taxpayer_status' => '',
                    'email' => $labour['email'] ?? '',
                    'economic_activity' => $labour['position'] ?? '',
                    'business_name' => $labour['legalName'] ?? ''
                ]
            );
        }
    }

    protected function processEmails(array $emails): void
    {
        foreach ($emails as $emailData) {
            if (empty($emailData['email'])) continue;

            $email = trim(strtolower($emailData['email']));

            // Evitar duplicados de emails
            Contact::firstOrCreate([
                'email' => $email,
                'client_id' => $this->client->id
            ]);
        }
    }

    protected function parseDate(string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $date)->format('Y-m-d');
        } catch (\Exception $e) {
            // Si falla el formato d/m/Y, intentar otros formatos comunes
            try {
                return Carbon::parse($date)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
    }
}
