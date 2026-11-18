<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * AyudaDefault model representing the default help entries managed by the Scrum Team.
 */
class AyudaDefault extends Model
{
    use HasFactory;

    protected $table = 'ayuda_default';
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
