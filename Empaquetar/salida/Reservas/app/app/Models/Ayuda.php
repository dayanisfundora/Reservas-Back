<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ayuda model representing user-editable help entries.
 */
class Ayuda extends Model
{
    use HasFactory;

    protected $table = 'ayuda';
    protected $primaryKey = 'id_ayuda';
    protected $fillable = [
        'topico',
        'subtopico',
        'contenido',
        'ruta',
        'path',
        'id_user',
    ];
}
