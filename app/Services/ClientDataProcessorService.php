<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Relationship;
use App\Models\Contact;
use App\Models\Address;
use App\Models\Work;
use App\Models\Email;
use Carbon\Carbon;

class ClientDataProcessorService
{
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

        $this->processFamilyData($familyData);
        $this->processFamilyData($genomeData);
        $this->processFamilyNames($familyNames);
        $this->processContacts($contacts);
        $this->processEmails($emails);
        $this->processAddresses($addresses);
        $this->processLabours($works);
    }

    public static function createClientFromDatadiver(array $data): self
    {
        $infoGeneral = $data['info_general'] ?? [];
        $fullname = $infoGeneral['fullname'] ?? null;

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
        if (!empty($familyNames['spouse'])) {
            $this->createOrFindPersonWithoutDni($familyNames['spouse'], 'CONYUGE');
        }

        if (!empty($familyNames['dad'])) {
            $this->createOrFindPersonWithoutDni($familyNames['dad'], 'PADRE');
        }

        if (!empty($familyNames['mom'])) {
            $this->createOrFindPersonWithoutDni($familyNames['mom'], 'MADRE');
        }
    }

    protected function createOrFindPersonWithoutDni(string $fullname, string $relationshipType): void
    {
        $fullname = trim($fullname);

        if (empty($fullname)) return;

        $existingRelationship = Relationship::where('client_id', $this->client->id)
            ->where('type', $relationshipType)
            ->first();

        if ($existingRelationship) {
            return;
        }

        $existingPerson = Client::where('name', $fullname)->first();

        if (!$existingPerson) {
            $existingPerson = Client::create([
                'identification' => null,
                'name' => $fullname,
            ]);
        }

        if ($existingPerson->id === $this->client->id) {
            return;
        }

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

            $isMinorByName = preg_match('/menor\s+de\s+edad/i', $fullname);
            $isChildRelationship = in_array($relationshipType, ['HIJO', 'HIJA']);

            $isMinor = false;
            if ($isChildRelationship && !empty($person['dni'])) {
                $existingWithSameDni = Client::where('identification', $person['dni'])
                    ->where('name', '!=', $fullname)
                    ->first();

                $isSameDniAsParent = $person['dni'] === $this->client->identification;
                $isMinor = $isMinorByName || $isSameDniAsParent || $existingWithSameDni;
            } elseif ($isMinorByName) {
                $isMinor = true;
            }

            if (empty($person['dni'])) {
                if (empty($fullname)) continue;

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
                if (empty($fullname)) continue;

                $birthDate = $this->parseDate($person['dateOfBirth'] ?? '');

                $existingPerson = Client::where('name', $fullname)
                    ->where('birth', $birthDate)
                    ->whereNull('identification')
                    ->first();

                if (!$existingPerson) {
                    $parentDni = $person['dni'];

                    $existingPerson = Client::create([
                        'identification' => null,
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

            if ($existingPerson->id === $this->client->id) {
                continue;
            }

            $existingRelationship = Relationship::where('client_id', $this->client->id)
                ->where('relationship_client_id', $existingPerson->id)
                ->first();

            if ($existingRelationship) {
                $currentPriority = $this->getRelationshipPriority($existingRelationship->type);
                $newPriority = $this->getRelationshipPriority($relationshipType);

                if ($newPriority > $currentPriority) {
                    $existingRelationship->update(['type' => $relationshipType]);
                }
            } else {
                Relationship::create([
                    'type' => $relationshipType,
                    'relationship_client_id' => $existingPerson->id,
                    'client_id' => $this->client->id
                ]);
            }
        }
    }

    protected function getRelationshipPriority(string $relationshipType): int
    {
        $priorities = [
            'PADRE' => 100,
            'MADRE' => 100,
            'HIJO' => 100,
            'HIJA' => 100,
            'ESPOSO' => 100,
            'ESPOSA' => 100,
            'CONYUGE' => 100,
            'HERMANO' => 80,
            'HERMANA' => 80,
            'ABUELO' => 70,
            'ABUELA' => 70,
            'NIETO' => 70,
            'NIETA' => 70,
            'TIO' => 50,
            'TIA' => 50,
            'TIO PATERNO' => 50,
            'TIA PATERNA' => 50,
            'TIO MATERNO' => 50,
            'TIA MATERNA' => 50,
            'SOBRINO' => 50,
            'SOBRINA' => 50,
            'PRIMO' => 30,
            'PRIMA' => 30,
            'SUEGRO' => 20,
            'SUEGRA' => 20,
            'YERNO' => 20,
            'NUERA' => 20,
            'CUÑADO' => 20,
            'CUÑADA' => 20,
        ];

        return $priorities[$relationshipType] ?? 10;
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

            $emailAddress = trim(strtolower($emailData['email']));

            Email::firstOrCreate([
                'direction' => $emailAddress,
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
            try {
                return Carbon::parse($date)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
    }
}
