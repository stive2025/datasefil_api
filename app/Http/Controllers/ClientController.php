<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\DatadiverService;
use Illuminate\Support\Facades\DB;
use App\Services\ClientDataProcessorService;
use Illuminate\Support\Facades\Log;

class ClientController extends Controller
{
    public function filtrarDatosVerificados($attributesObj, $verifiedAttributesObj) {
        $attributes = (array)$attributesObj;
        $verified_attributes = (array)$verifiedAttributesObj;

        $resultado = [];

        foreach ($attributes as $clave => $valor) {
            if (isset($verified_attributes[$clave]) && $verified_attributes[$clave] === true) {
                $resultado[$clave] = $valor;
            } else {
                $resultado[$clave] = null;
            }
        }

        return (object)$resultado;
    }

    public function aplanarAtributos($data) {
        $data = (array) $data;
        $ci = $data['ci'];
        $attributes = (array) $data['attributes'];
        $resultado = array_merge(['ci' => $ci], $attributes);

        return $resultado;
    }

    public function indexLeads()
    {
        $clients=DB::table('leads')
            ->when(request()->filled('ci'),function($query){
                $query->where('document',request('ci'));
            })
            ->orderBy('id','DESC')
            ->get();

        $data_clients=[];

        foreach($clients as $client){
            $attributes=json_decode($client->attributes);
            $verified_attributes=json_decode($client->verified_attributes);

            $attributes_clean=$this->filtrarDatosVerificados($attributes,$verified_attributes);

            array_push($data_clients,$this->aplanarAtributos([
                "ci"=>$client->document,
                "attributes"=>$attributes_clean
            ]));
        };

        return $data_clients;
    }

    public function index()
    {
        $contacts=Client::with(['contacts','parents.relatedClient','address','emails'])
            ->when(request()->filled('name'),function($query){
                $query->where('name','REGEXP',request('name'));
            })
            ->when(request()->filled('identification'),function($query){
                $query->where('identification','REGEXP',request('identification'));
            })
            ->when(request()->filled('email'),function($query){
                $query->where('email','REGEXP',request('email'));
            })
            ->when(request()->filled('phone'), function ($query) {
                $query->whereHas('contacts', function ($q) {
                    $q->where('phone_number','REGEXP', request('phone'));
                });
            })
            ->paginate(10);

        if(count($contacts)==0 & request()->filled('identification')){
            $datadiver=new DatadiverService(env('ROOT_DATADIVERSERVICE').request('identification'));
            $contacts=$datadiver->ConsultData();

            Log::info(json_encode($contacts));

            $processor = ClientDataProcessorService::createClientFromDatadiver($contacts);
            $processor->processDatadiverData($contacts);

            $contacts=Client::with(['contacts','parents','address'])
                ->when(request()->filled('identification'),function($query){
                    $query->where('identification','REGEXP',request('identification'));
                })
                ->paginate(10);
        }

        return response()->json($contacts,200);
    }

    public function show(Client $id)
    {
        $id->load(['contacts', 'address', 'parents']);
        $id->age;

        if ($id->uses_parent_identification && $id->parent_identification) {
            $parent = Client::where('identification', $id->parent_identification)->first();

            if (!$parent) {
                $datadiver = new DatadiverService(env('ROOT_DATADIVERSERVICE') . $id->parent_identification);
                $contactsData = $datadiver->ConsultData();

                $processor = ClientDataProcessorService::createClientFromDatadiver($contactsData);
                $processor->processDatadiverData($contactsData);

                $parent = Client::where('identification', $id->parent_identification)->first();
            } else {
                if ($parent->parents->isEmpty()) {
                    $datadiver = new DatadiverService(env('ROOT_DATADIVERSERVICE') . $parent->identification);
                    $contactsData = $datadiver->ConsultData();

                    $processor = new ClientDataProcessorService($parent);
                    $processor->processDatadiverData($contactsData);
                }
            }

            if ($parent) {
                $parent->load(['contacts', 'address', 'parents','works','emails']);

                return response()->json($parent, 200);
            }
        }

        $needsQuery = $id->parents->isEmpty();

        if ($needsQuery) {
            if (!empty($id->identification)) {
                $datadiver = new DatadiverService(env('ROOT_DATADIVERSERVICE') . $id->identification);
                $contactsData = $datadiver->ConsultData();

                $processor = new ClientDataProcessorService($id);
                $processor->processDatadiverData($contactsData);
            }
        }

        $id->load(['contacts', 'address', 'parents','works','emails']);

        return response()->json($id, 200);
    }
}
