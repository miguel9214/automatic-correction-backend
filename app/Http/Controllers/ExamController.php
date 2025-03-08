<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exam;
use App\Models\ExamResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    // Subir y corregir exámenes
    public function uploadAndCorrect(Request $request)
    {
        try {
            // Validar la solicitud
            $request->validate([
                'exams' => 'required|array',
                'exams.*.student_name' => 'required|string',
                'exams.*.questions' => 'required|array',
                'exams.*.questions.*.question' => 'required|string',
                'exams.*.questions.*.answer' => 'required|string',
            ]);

            $results = [];

            foreach ($request->exams as $examData) {
                // Crear el examen
                $exam = Exam::create([
                    'student_name' => $examData['student_name'],
                    'questions' => json_encode($examData['questions']),
                ]);

                // Obtener retroalimentación de DeepSeek
                $feedback = $this->sendToDeepSeek($examData['questions']);

                // Crear el resultado del examen
                $examResult = ExamResult::create([
                    'exam_id' => $exam->id,
                    'feedback' => json_encode($feedback['detailed_feedback']),
                    'score' => $feedback['score'],
                ]);

                $results[] = [
                    'student_name' => $examData['student_name'],
                    'feedback' => $feedback['detailed_feedback'],
                    'score' => $feedback['score'],
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (ValidationException $e) {
            // Capturar errores de validación
            return response()->json([
                'success' => false,
                'error' => 'Validation Error',
                'message' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Capturar otros errores
            Log::error('Error en uploadAndCorrect: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Obtener todos los exámenes
    public function getAllExams()
    {
        try {
            // Obtener todos los exámenes con sus resultados relacionados
            $exams = Exam::with('result')->get();

            // Decodificar los campos JSON para la respuesta
            $formattedExams = $exams->map(function ($exam) {
                $examData = $exam->toArray();
                $examData['questions'] = json_decode($exam->questions);
                if ($exam->result) {
                    $examData['result']['feedback'] = json_decode($exam->result->feedback);
                }
                return $examData;
            });

            return response()->json([
                'success' => true,
                'data' => $formattedExams,
            ]);
        } catch (\Exception $e) {
            // Capturar errores
            Log::error('Error en getAllExams: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Revisar exámenes seleccionados
    public function reviewExams(Request $request)
    {
        try {
            $request->validate([
                'exam_ids' => 'required|array',
                'exam_ids.*' => 'exists:exams,id',
            ]);

            $examIds = $request->exam_ids;
            $results = [];

            foreach ($examIds as $examId) {
                $exam = Exam::findOrFail($examId);
                $questions = json_decode($exam->questions, true);

                $feedback = $this->sendToDeepSeek($questions);

                // Actualizar el resultado del examen en la base de datos
                ExamResult::updateOrCreate(
                    ['exam_id' => $exam->id],
                    [
                        'feedback' => json_encode($feedback['detailed_feedback']),
                        'score' => $feedback['score'],
                    ]
                );

                $results[] = [
                    'student_name' => $exam->student_name,
                    'feedback' => $feedback['detailed_feedback'],
                    'score' => $feedback['score'],
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (ValidationException $e) {
            // Capturar errores de validación
            return response()->json([
                'success' => false,
                'error' => 'Validation Error',
                'message' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Capturar otros errores
            Log::error('Error en reviewExams: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Enviar preguntas a la API de DeepSeek
    private function sendToDeepSeek($questions)
    {
        try {
            $apiKey = env('DEEPSEEK_API_KEY');
            if (!$apiKey) {
                throw new \Exception('API Key de DeepSeek no configurada.');
            }

            if (empty($questions) || !is_array($questions)) {
                throw new \Exception('No hay preguntas para evaluar.');
            }

            $url = 'https://api.deepseek.com/v1/chat/completions';

            // Prompt estructurado
            $systemPrompt = "Eres un profesor experto en evaluación de exámenes. Tu tarea es evaluar las respuestas de los estudiantes y proporcionar retroalimentación detallada. Para cada pregunta, debes:
            1. Determinar si la respuesta es correcta, parcialmente correcta o incorrecta.
            2. Explicar por qué la respuesta es correcta o incorrecta.
            3. Proporcionar la respuesta correcta si es necesario.
            4. Asignar una puntuación de 0 a 10 para cada pregunta.

            Responde en formato JSON con la siguiente estructura:
            {
              \"evaluations\": [
                {
                  \"question\": \"[Pregunta original]\",
                  \"student_answer\": \"[Respuesta del estudiante]\",
                  \"is_correct\": true/false/\"partial\",
                  \"feedback\": \"[Tu retroalimentación detallada]\",
                  \"points\": [puntuación de 0-10]
                }
              ],
              \"total_score\": [promedio de puntos en escala 0-100]
            }";

            // Construcción del mensaje del usuario
            $userContent = "Evalúa las siguientes respuestas de examen:\n\n";
            foreach ($questions as $index => $question) {
                if (!isset($question['question'], $question['answer'])) {
                    continue; // Saltar preguntas mal estructuradas
                }
                $userContent .= "Pregunta " . ($index + 1) . ": " . $question['question'] . "\n";
                $userContent .= "Respuesta del estudiante: " . $question['answer'] . "\n\n";
            }

            // Llamada a la API de DeepSeek
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(120)->post($url, [
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userContent]
                ],
                'response_format' => ['type' => 'json_object']
            ]);

            // Verificar si la solicitud a la API falló
            if ($response->failed()) {
                throw new \Exception('Error al comunicarse con la API de DeepSeek: ' . $response->body());
            }

            // Obtener y validar la respuesta de la API
            $responseData = $response->json();
            if (!isset($responseData['choices'][0]['message']['content'])) {
                throw new \Exception('Respuesta de la API DeepSeek inválida: ' . json_encode($responseData));
            }

            $responseContent = $responseData['choices'][0]['message']['content'];
            $parsedResponse = json_decode($responseContent, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsedResponse)) {
                Log::warning('La respuesta de DeepSeek no es un JSON válido: ' . $responseContent);
                return [
                    'detailed_feedback' => [
                        'error' => 'No se pudo analizar la respuesta',
                        'raw_response' => $responseContent
                    ],
                    'score' => 0
                ];
            }

            return [
                'detailed_feedback' => $parsedResponse,
                'score' => $parsedResponse['total_score'] ??
                    (isset($parsedResponse['evaluations']) && is_array($parsedResponse['evaluations'])
                        ? $this->calculateScoreFromEvaluations($parsedResponse['evaluations'])
                        : 0)
            ];
        } catch (\Exception $e) {
            // Capturar y registrar errores sin interrumpir la ejecución
            Log::error('Error en sendToDeepSeek: ' . $e->getMessage());
            return [
                'detailed_feedback' => [
                    'error' => $e->getMessage()
                ],
                'score' => 0
            ];
        }
    }


    // Calcular la calificación basada en las evaluaciones individuales
    private function calculateScoreFromEvaluations($evaluations)
    {
        if (empty($evaluations)) {
            return 0;
        }

        $totalPoints = 0;
        foreach ($evaluations as $evaluation) {
            $totalPoints += $evaluation['points'] ?? 0;
        }

        return ($totalPoints / (count($evaluations) * 10)) * 100;
    }
}
