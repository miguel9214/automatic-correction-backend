<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exam;
use App\Models\ExamResult;
use Illuminate\Support\Facades\Http;

class ExamController extends Controller
{
    // Subir y corregir exámenes
    public function uploadAndCorrect(Request $request)
    {
        $request->validate([
            'exams' => 'required|array',
            'exams.*.student_name' => 'required|string',
            'exams.*.questions' => 'required|array',
        ]);

        $results = [];

        foreach ($request->exams as $examData) {
            $exam = Exam::create([
                'student_name' => $examData['student_name'],
                'questions' => json_encode($examData['questions']),
            ]);

            $feedback = $this->sendToDeepSeek($examData['questions']);

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
    }

    // Obtener todos los exámenes
    public function getAllExams()
    {
        $exams = Exam::with('results')->get();
        return response()->json($exams);
    }

    // Revisar exámenes seleccionados
    public function reviewExams(Request $request)
    {
        $examIds = $request->exam_ids;
        $results = [];

        foreach ($examIds as $examId) {
            $exam = Exam::find($examId);
            $feedback = $this->sendToDeepSeek(json_decode($exam->questions, true));
            $results[] = [
                'student_name' => $exam->student_name,
                'feedback' => $feedback['feedback'],
                'score' => $feedback['score'],
            ];
        }

        return response()->json($results);
    }

    // Enviar preguntas a la API de DeepSeek
    private function sendToDeepSeek($questions)
    {
        $apiKey = env('DEEPSEEK_API_KEY');
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

        $feedback = $response->json()['choices'][0]['message']['content'];
        $score = $this->calculateScore($feedback);

        return [
            'feedback' => $feedback,
            'score' => $score,
        ];
    }

    // Calcular la calificación
    private function calculateScore($feedback)
    {
        $correctCount = substr_count(strtolower($feedback), 'correcto');
        $totalQuestions = substr_count(strtolower($feedback), 'pregunta');
        return ($correctCount / max($totalQuestions, 1)) * 100;
    }
}
