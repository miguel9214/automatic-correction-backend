<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    use HasFactory;

        // Campos que se pueden asignar masivamente
        protected $fillable = [
            'student_name',
            'questions', // Almacena las preguntas en formato JSON
        ];

        // Relación con el modelo ExamResult (un examen tiene un resultado)
        public function result()
        {
            return $this->hasOne(ExamResult::class);
        }

        // Convertir el campo 'questions' a un array al acceder a él
        protected $casts = [
            'questions' => 'array',
        ];
}
