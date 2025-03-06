<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DeepSeekController extends Controller
{
    public function consultarDeepSeek(Request $request)
    {
        // Validar la solicitud
        $request->validate([
            'prompt' => 'required|string',
        ]);

        // Obtener la clave de API de DeepSeek desde el archivo .env
        $apiKey = env('DEEPSEEK_API_KEY');

        // URL de la API de DeepSeek
        $url = 'https://api.deepseek.com/v1/chat/completions'; // Asegúrate de usar la URL correcta

        // Datos que se enviarán a la API de DeepSeek
        $data = [
            'model' => 'deepseek-chat', // Asegúrate de usar el modelo correcto
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $request->input('prompt'),
                ],
            ],
        ];

        // Hacer la solicitud HTTP a la API de DeepSeek
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, $data);

        // Devolver la respuesta de la API de DeepSeek
        return response()->json($response->json(), $response->status());
    }
}
