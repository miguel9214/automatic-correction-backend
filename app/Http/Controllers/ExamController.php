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
                    'feedback' => $feedback['feedback'],
                    'score' => $feedback['score'],
                ]);

                $results[] = [
                    'student_name' => $examData['student_name'],
                    'feedback' => $feedback['feedback'],
                    'score' => $feedback['score'],
                ];
            }

            return response()->json($results);
        } catch (ValidationException $e) {
            // Capturar errores de validación
            return response()->json([
                'error' => 'Error de validación',
                'message' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Capturar otros errores
            Log::error('Error en uploadAndCorrect: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error interno del servidor',
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
            return response()->json($exams);
        } catch (\Exception $e) {
            // Capturar errores
            Log::error('Error en getAllExams: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Revisar exámenes seleccionados
    public function reviewExams(Request $request)
    {
        try {
            $examIds = $request->exam_ids;
            $results = [];

            foreach ($examIds as $examId) {
                $exam = Exam::find($examId);
                if (!$exam) {
                    throw new \Exception("Examen con ID $examId no encontrado.");
                }

                $feedback = $this->sendToDeepSeek(json_decode($exam->questions, true));
                $results[] = [
                    'student_name' => $exam->student_name,
                    'feedback' => $feedback['feedback'],
                    'score' => $feedback['score'],
                ];
            }

            return response()->json($results);
        } catch (\Exception $e) {
            // Capturar errores
            Log::error('Error en reviewExams: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error interno del servidor',
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

            $url = 'https://api.deepseek.com/v1/chat/completions';

            $messages = [];
            foreach ($questions as $question) {
                $messages[] = [
                    'role' => 'user',
                    'content' => "Pregunta: {$question['question']}\nRespuesta: {$question['answer']}",
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'model' => 'deepseek-chat',
                'messages' => $messages,
            ]);

            if ($response->failed()) {
                throw new \Exception('Error al comunicarse con la API de DeepSeek: ' . $response->body());
            }

            $feedback = $response->json()['choices'][0]['message']['content'];
            $score = $this->calculateScore($feedback);

            return [
                'feedback' => $feedback,
                'score' => $score,
            ];
        } catch (\Exception $e) {
            // Capturar errores
            Log::error('Error en sendToDeepSeek: ' . $e->getMessage());
            throw $e; // Relanzar la excepción para manejarla en el método que llama
        }
    }

    // Calcular la calificación
    private function calculateScore($feedback)
    {
        try {
            $correctCount = substr_count(strtolower($feedback), 'correcto');
            $totalQuestions = substr_count(strtolower($feedback), 'pregunta');
            return ($correctCount / max($totalQuestions, 1)) * 100;
        } catch (\Exception $e) {
            // Capturar errores
            Log::error('Error en calculateScore: ' . $e->getMessage());
            throw $e; // Relanzar la excepción para manejarla en el método que llama
        }
    }
}
