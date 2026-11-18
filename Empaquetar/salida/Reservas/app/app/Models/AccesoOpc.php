<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccesoOpc extends Model
{
    protected $connection = 'ihsecurity';
    protected $table = 'Acceso_Opc';
    public $timestamps = false;
}
