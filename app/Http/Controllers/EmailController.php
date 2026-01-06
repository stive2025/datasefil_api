<?php

namespace App\Http\Controllers;

use App\Models\Email;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'direction' => 'required|string',
            'client_id' => 'required|exists:clients,id',
        ]);
        
        $create_email = Email::create($validated);
        return response()->json($create_email, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $email = Email::findOrFail($id);
        $validated = $request->validate([
            'direction' => 'required|string',
            'client_id' => 'required|exists:clients,id',
        ]);
        $email->update($validated);
        return response()->json($email);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $email = Email::findOrFail($id);
        $email->update(['active' => false]);
        return response()->json(null, 204);
    }
}
