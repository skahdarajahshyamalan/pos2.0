<?php

namespace App;

use App\Traits\HasUids;

use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasUids;
    protected $primaryKey = 'uid';

    use HasFactory;
    use Notifiable;
    use SoftDeletes;
    use HasRoles;
    use HasApiTokens;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    // change api guard to web
    protected $guard_name = 'web';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */

    /**
     * Get the business that owns the user.
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class);
    }

    public function scopeUser($query)
    {
        return $query->where('users.user_type', 'user');
    }

    /**
     * The contact the user has access to.
     * Applied only when selected_contacts is true for a user in
     * users table
     */
    public function contactAccess()
    {
        return $this->belongsToMany(\App\Contact::class, 'user_contact_access');
    }

    /**
     * Get all of the users's notes & documents.
     */
    public function documentsAndnote()
    {
        return $this->morphMany(\App\DocumentAndNote::class, 'notable');
    }

    /**
     * Creates a new user based on the input provided.
     *
     * @return object
     */
    public static function create_user($details)
    {
        $user = User::create([
            'surname' => $details['surname'],
            'first_name' => $details['first_name'],
            'last_name' => $details['last_name'],
            'username' => $details['username'],
            'email' => $details['email'],
            'password' => Hash::make($details['password']),
            'language' => ! empty($details['language']) ? $details['language'] : 'en',
        ]);

        return $user;
    }

    /**
     * Gives locations permitted for the logged in user
     *
     * @param: int $business_uid
     *
     * @return string or array
     */
    public function permitted_locations($business_uid = null)
    {
        $user = $this;

        if ($user->can('access_all_locations')) {
            return 'all';
        } else {
            $business_uid = ! is_null($business_uid) ? $business_uid : null;
            if (empty($business_uid) && auth()->check()) {
                $business_uid = auth()->user()->business_uid;
            }
            if (empty($business_uid) && session()->has('business')) {
                $business_uid = session('business.uid');
            }

            $permitted_locations = [];
            $all_locations = BusinessLocation::where('business_uid', $business_uid)->get();
            $permissions = $user->permissions->pluck('name')->all();
            foreach ($all_locations as $location) {
                if (in_array('location.'.$location->id, $permissions)) {
                    $permitted_locations[] = $location->id;
                }
            }

            return $permitted_locations;
        }
    }

    /**
     * Returns if a user can access the input location
     *
     * @param: int $location_uid
     *
     * @return bool
     */
    public static function can_access_this_location($location_uid, $business_uid = null)
    {
        $permitted_locations = auth()->user()->permitted_locations($business_uid);

        if ($permitted_locations == 'all' || in_array($location_uid, $permitted_locations)) {
            return true;
        }

        return false;
    }

    public function scopeOnlyPermittedLocations($query)
    {
        $user = auth()->user();
        $permitted_locations = $user->permitted_locations();
        $is_admin = $user->hasAnyPermission('Admin#'.$user->business_uid);
        if ($permitted_locations != 'all' && ! $user->can('superadmin') && ! $is_admin) {
            $permissions = ['access_all_locations'];
            foreach ($permitted_locations as $location_uid) {
                $permissions[] = 'location.'.$location_uid;
            }

            return $query->whereHas('permissions', function ($q) use ($permissions) {
                $q->whereIn('permissions.name', $permissions);
            });
        } else {
            return $query;
        }
    }

    /**
     * Return list of users dropdown for a business
     *
     * @param $business_uid int
     * @param $prepend_none = true (boolean)
     * @param $include_commission_agents = false (boolean)
     * @return array users
     */
    public static function forDropdown($business_uid, $prepend_none = true, $include_commission_agents = false, $prepend_all = false, $check_location_permission = false)
    {
        $query = User::where('business_uid', $business_uid)
                    ->user();

        if (! $include_commission_agents) {
            $query->where('is_cmmsn_agnt', 0);
        }

        if ($check_location_permission) {
            $query->onlyPermittedLocations();
        }

        $all_users = $query->select('id', DB::raw("CONCAT(COALESCE(surname, ''),' ',COALESCE(first_name, ''),' ',COALESCE(last_name,'')) as full_name"))->get();
        $users = $all_users->pluck('full_name', 'uid');

        //Prepend none
        if ($prepend_none) {
            $users = $users->prepend(__('lang_v1.none'), '');
        }

        //Prepend all
        if ($prepend_all) {
            $users = $users->prepend(__('lang_v1.all'), '');
        }

        return $users;
    }

    /**
     * Return list of sales commission agents dropdown for a business
     *
     * @param $business_uid int
     * @param $prepend_none = true (boolean)
     * @return array users
     */
    public static function saleCommissionAgentsDropdown($business_uid, $prepend_none = true)
    {
        $all_cmmsn_agnts = User::where('business_uid', $business_uid)
                        ->where('is_cmmsn_agnt', 1)
                        ->select('id', DB::raw("CONCAT(COALESCE(surname, ''),' ',COALESCE(first_name, ''),' ',COALESCE(last_name,'')) as full_name"));

        $users = $all_cmmsn_agnts->pluck('full_name', 'uid');

        //Prepend none
        if ($prepend_none) {
            $users = $users->prepend(__('lang_v1.none'), '');
        }

        return $users;
    }

    /**
     * Return list of users dropdown for a business
     *
     * @param $business_uid int
     * @param $prepend_none = true (boolean)
     * @param $prepend_all = false (boolean)
     * @return array users
     */
    public static function allUsersDropdown($business_uid, $prepend_none = true, $prepend_all = false)
    {
        $all_users = User::where('business_uid', $business_uid)
                        ->select('id', DB::raw("CONCAT(COALESCE(surname, ''),' ',COALESCE(first_name, ''),' ',COALESCE(last_name,'')) as full_name"));

        $users = $all_users->pluck('full_name', 'uid');

        //Prepend none
        if ($prepend_none) {
            $users = $users->prepend(__('lang_v1.none'), '');
        }

        //Prepend all
        if ($prepend_all) {
            $users = $users->prepend(__('lang_v1.all'), '');
        }

        return $users;
    }

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getUserFullNameAttribute()
    {
        return "{$this->surname} {$this->first_name} {$this->last_name}";
    }

    /**
     * Return true/false based on selected_contact access
     *
     * @return bool
     */
    public static function isSelectedContacts($user_uid)
    {
        $user = User::findOrFail($user_uid);

        return (bool) $user->selected_contacts;
    }

    public function getRoleNameAttribute()
    {
        $role_name_array = $this->getRoleNames();
        $role_name = ! empty($role_name_array[0]) ? explode('#', $role_name_array[0])[0] : '';

        return $role_name;
    }

    public function media()
    {
        return $this->morphOne(\App\Media::class, 'model');
    }

    /**
     * Find the user instance for the given username.
     *
     * @param  string  $username
     * @return \App\User
     */
    public function findForPassport($username)
    {
        return $this->where('username', $username)->first();
    }

    /**
     * Get the contact for the user.
     */
    public function contact()
    {
        return $this->belongsTo(\Modules\Crm\Entities\CrmContact::class, 'crm_contact_uid');
    }

    /**
     * Get the products image.
     *
     * @return string
     */
    public function getImageUrlAttribute()
    {
        if (isset($this->media->display_url)) {
            $img_src = $this->media->display_url;
        } else {
            $img_src = 'https://ui-avatars.com/api/?name='.$this->first_name;
        }

        return $img_src;
    }

    /**
     * Overriding notifications relationship to use custom morph key 'notifiable_uid'.
     */
    public function notifications()
    {
        return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable', 'notifiable_type', 'notifiable_uid');
    }
}
