<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Relationship;
use App\Models\Contact;
use App\Models\Address;

class ClientDataProcessorService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected Client $client){}

    public function processDatadiverData(array $data): void
    {
        $infoGeneral = $data['info_general'] ?? [];
        $familyData = $infoGeneral['family'] ?? [];
        $genomeData = $data['info_family']['family'] ?? [];
        $contacts = $data['info_contacts']['phones'] ?? [];
        $addresses = $data['info_contacts']['address'] ?? [];

        //$this->processClient($infoGeneral);
        $this->processFamilyData($familyData);
        $this->processFamilyData($genomeData);
        $this->processContacts($contacts);
        $this->processAddresses($addresses);
    }

    public static function createClientFromDatadiver(array $data): self
    {
        $infoGeneral = $data['info_general'] ?? [];

        $client = Client::updateOrCreate(
            [
                'identification' => $infoGeneral['dni'] ?? null,
                'name' => $infoGeneral['name'] ?? null,
            ],
            [
                'name' => $infoGeneral['fullname'] ?? null,
                'birth' => !empty($infoGeneral['dateOfBirth']) ? date('Y-m-d', strtotime($infoGeneral['dateOfBirth'])) : null,
                'death' => !empty($infoGeneral['dateOfDeath']) ? date('Y-m-d', strtotime($infoGeneral['dateOfDeath'])) : null,
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

    protected function processClient(array $client) : void{
        $create_client=Client::updateOrCreate(
        [
                        'identification'=>$client['dni'],
                        "name"=>$client['name']
                    ],
            [
                        'name'=>$client['fullname'],
                        'birth' => !empty($client['dateOfBirth']) ? date('Y-m-d', strtotime($client['dateOfBirth'])) : null,
                        'death' => !empty($client['dateOfDeath']) ? date('Y-m-d', strtotime($client['dateOfDeath'])) : null,
                        'gender'=>$client['gender'],
                        'state_civil'=>$client['civilStatus'],
                        'place_birth'=>$client['placeOfBirth'],
                        'nationality'=>$client['citizenship'],
                        'profession'=>$client['profession'],
                        'salary'=>$client['salary']
                    ]
                );
    }

    protected function processFamilyData(array $family): void
    {
        foreach ($family as $person) {
            if (empty($person['dni'])) continue;

            $existingPerson = Client::firstOrCreate(
                ['identification' => $person['dni'],'name' => $person['fullname']],
                [
                    'name' => $person['fullname'],
                    'birth' => $this->parseDate($person['dateOfBirth'] ?? ''),
                    'gender' => $person['gender'] ?? null,
                    'state_civil' => $person['civilStatus'] ?? null,
                    'nationality' => $person['citizenship'] ?? null
                ]
            );

            Relationship::firstOrCreate([
                'type' => strtoupper($person['relationship'] ?? 'UNKNOWN'),
                'relationship_client_id' => $existingPerson->id,
                'client_id' => $this->client->id
            ]);
        }
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

    protected function parseDate(string $date): ?string
    {
        return !empty($date) ? date('Y-m-d', strtotime($date)) : null;
    }

}
