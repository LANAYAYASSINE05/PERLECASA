<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programme extends Model
{
    protected $fillable = ['nom', 'ville'];

    public function appartements(): HasMany
    {
        return $this->hasMany(Appartement::class);
    }
}
