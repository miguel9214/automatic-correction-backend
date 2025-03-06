<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamResult extends Model
{
    use HasFactory;

        // Campos que se pueden asignar masivamente
        protected $fillable = [
            'exam_id',
            'feedback',
            'score',
        ];

        // Relación con el modelo Exam (un resultado pertenece a un examen)
        public function exam()
        {
            return $this->belongsTo(Exam::class);
        }
}
