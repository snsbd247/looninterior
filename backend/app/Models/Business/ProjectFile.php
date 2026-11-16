<?php

namespace App\Models\Business;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** kind "image": public uploads path (shown as thumbnails); kind "doc": private storage (download via API). */
class ProjectFile extends Model
{
    protected $guarded = ['id'];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}