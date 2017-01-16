<?php

namespace Interpro\Feedback\Model;

use Illuminate\Database\Eloquent\Model;

class Fbform extends Model
{
    protected $primaryKey = 'name';
    public $incrementing = false;
    public $timestamps = false;
    protected static $unguarded = true;
}
