<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contact;
use DateTime;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {   
        $contacts=Contact::when(request()->filled('phone_number'),function($query){
                $query->where('phone_number',request('phone_number'));
            })
            ->paginate(10);

        return response()->json($contacts,200);
    }

    public function update(Request $request,string $id){
        $contact=Contact::findOrFail($id);
        if($contact){
            $contact->update($request->all());
            return response()->json($contact,200);
        }else{
            return response()->json(["message"=>"Contact no encontrado"],200);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function import(Request $request)
    {
        $contacts=$request->data;

        foreach($contacts as $contact){
            $create_client=Client::create([
                'identification'=>$contact['ci'],
                'name'=>$contact['nombre'],
                'email'=>$contact['email'],
                'micro_activa'=>$contact['micro_activa'],
                'birth' => ($contact['fecha_nacimiento'] != '')
                    ? DateTime::createFromFormat('d/m/Y', $contact['fecha_nacimiento'])->format('Y-m-d H:i:s')
                    : null,
                'gender'=>$contact['genero'],
                'state_civil'=>$contact['estado_civil'],
                'economic_activity'=>$contact['actividad_economica'],
                'economic_area'=>$contact['sector_economico'],
                'nationality'=>$contact['nacionalidad'],
                'profession'=>$contact['profesion'],
                // 'place_birth'=>$contact['lugar_nacimiento'],
                'salary'=>$contact['salario'],
            ]);

            $phones=[
                $contact['phone1'],
                $contact['phone2'],
                $contact['phone3'],
                $contact['phone4'],
                $contact['phone5'],
                $contact['phone6'],
                $contact['phone7'],
                $contact['phone8'],
                $contact['phone9']
            ];

            foreach($phones as $phone){
                if($phone!=''){
                    $create_contact=Contact::create([
                        "phone_number"=>$phone,
                        "client_id"=>$create_client->id
                    ]);
                }
            }
        }

        $clients=Client::get();
        $con=Contact::get();

        return response()->json([
            "contactos"=>$con,
            "clientes"=>$clients
        ],200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Contact $id)
    {
        return response()->json($id,200);
    }
}
