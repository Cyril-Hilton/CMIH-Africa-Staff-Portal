<?php
namespace App\Services;
class CompanyCalendar {
 public static function nonWorkingDates(): array {
  $fixed=config('company_calendar.non_working_dates',[]);$request=request();$key='hr_public_holidays';
  if(!$request->attributes->has($key)){
   $dates=\Illuminate\Support\Facades\Schema::hasTable('hr_register_records')
    ?\App\Models\HrRegisterRecord::where('kind','holiday')->get()->pluck('data.date')->filter()->all():[];
   $request->attributes->set($key,$dates);
  }
  return array_values(array_unique([...$fixed,...$request->attributes->get($key)]));
 }
}
