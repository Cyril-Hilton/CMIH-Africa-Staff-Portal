<?php
namespace App\Services\Creative;
use App\Models\{User,CreativeRequest};
use App\Services\PerformanceAccess;
class CreativeAccess {
 public static function staff(User $u):bool{return $u->isActive() && ($u->isCvoOrSuperAdmin()||array_key_exists(User::normalizeDepartmentKey($u->department),config('performance.departments'))) && !in_array($u->access_role,[...User::MERCHANDISER_FIELD_ROLES,User::BRAND_PROMOTER_ROLE],true);}
 public static function lead(User $u):bool{return self::staff($u) && PerformanceAccess::manages($u,'creatives');}
 public static function view(User $u,CreativeRequest $r):bool{return self::staff($u) && (self::lead($u)||$r->visibility==='shared'||in_array($u->id,[$r->requester_id,$r->assignee_id,$r->reviewer_id]));}
 public static function owner(User $u,CreativeRequest $r):bool{return self::lead($u)||($u->isActive() && $u->id===$r->assignee_id);}
 public static function reviewer(User $u,CreativeRequest $r):bool{return self::staff($u)&&$u->id!==$r->assignee_id && (self::lead($u)||$u->id===$r->reviewer_id);}
 public static function snapshot(?User $u):?array{return $u?['name'=>$u->name,'department'=>User::normalizeDepartmentKey($u->department),'role'=>$u->job_title ?: $u->job_level ?: $u->access_role]:null;}
}
