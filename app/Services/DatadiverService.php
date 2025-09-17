<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DatadiverService
{
    private string $server_ip;
    /**
     * Create a new class instance.
     */
    public function __construct(string $server_ip)
    {
        $this->setServerIp($server_ip);
    }

    public function setServerIp(string $server_ip){
        $this->server_ip=$server_ip;
    }
    
    public function ConsultData(){
        try {
            $response = Http::get($this->server_ip);
            if ($response->successful()) {

                return $response->json();

            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al obtener los datos',
                    'code' => $response->status()
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Excepción: ' . $e->getMessage()
            ], 500);
        }
    }

}
