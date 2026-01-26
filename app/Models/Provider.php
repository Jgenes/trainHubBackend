<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id','legal_name','brand_name','provider_type','registration_ref','tin','website',
        'country','region','district','physical_address','google_maps_link',
        'contact_name','contact_role','contact_phone','contact_email',
        'status','provider_slug','created_by'
    ];

    public function admin()
    {
        return $this->belongsTo(User::class,'created_by');
    }
    public function provider()
{
    return $this->hasOne(Provider::class, 'user_id');
}

}
