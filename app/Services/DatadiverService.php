<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            $response = Http::withOptions([
                'connect_timeout' => 10,
                'timeout' => 60,
            ])->get($this->server_ip);
            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('DatadiverService error: ' . $response->status());
                return null;
            }
        } catch (\Exception $e) {
            Log::error('DatadiverService exception: ' . $e->getMessage());
            return null;
        }
    }

}
