<?php
namespace App\Traits;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

trait CurrentParent {

	protected static function bootCurrentParent()
    {
    	if (auth()->guard('api')->check()) {
	        // if user is superadmin - usertype admin
	        $getAdminId = User::select('id')->where('userType', 0)->withoutGlobalScope('parent_id')->first();
	        if (auth()->guard('api')->user()->userType == 0 || (auth()->guard('api')->user()->parent_id == $getAdminId->id && auth()->guard('api')->user()->userType == 3)) {
	        	//Nothing Heppen
	        }
	        else
	        {
	        	static::creating(function ($model) {
		            $model->parent_id = auth()->guard('api')->user()->parent_id;
		        });
	        }
	    }

	    if (auth()->guard('api')->check()) 
	    {
	    	$getAdminId = User::select('id')->where('userType', 0)->withoutGlobalScope('parent_id')->first();
	        if (auth()->guard('api')->user()->userType == 0 || (auth()->guard('api')->user()->parent_id == $getAdminId->id && auth()->guard('api')->user()->userType == 3)) {
	        	//Nothing Heppen
	        }
	        else
	        {
	            static::addGlobalScope('parent_id', function (Builder $builder) {
	            	if(auth()->guard('api')->user()->parent_id == 1)
	            	{
	            		$builder->where(function($q) {
		                	$q->where('parent_id', auth()->guard('api')->user()->parent_id)
		                	->orWhere('parent_id', auth()->guard('api')->user()->id);
		                });
	            	}
	            	else
	            	{
	            		$builder->where('parent_id', auth()->guard('api')->user()->parent_id);
	            	}
	            });
	        }
	    }
    }

}