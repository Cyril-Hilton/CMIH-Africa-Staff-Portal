<?php
namespace App\Services\Creative;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class CreativeUploadScreening
{
 public function check(UploadedFile $file):void
 {
  $path=$file->getRealPath();
  // Detect the standard scanner test signature even in isolated development mode.
  $stream=fopen($path,'rb');$tail='';
  while(!feof($stream)){$bytes=$tail.fread($stream,65536);if(str_contains($bytes,'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')){fclose($stream);throw ValidationException::withMessages(['file'=>'The upload failed the security check.']);}$tail=substr($bytes,-64);}fclose($stream);
  $binary=config('creative.upload_scanner');
  if(!$binary){abort_if(config('creative.upload_scan_required'),503,'File security screening is unavailable. Please retry later.');return;}
  try{$process=new Process([$binary,'--no-summary','--',$path]);$process->setTimeout(45);$process->run();}
  catch(\Throwable $e){Log::warning('Creative upload screening unavailable',['exception'=>get_class($e)]);abort(503,'File security screening is unavailable. Please retry later.');}
  if($process->getExitCode()===1)throw ValidationException::withMessages(['file'=>'The upload failed the security check.']);
  abort_unless($process->getExitCode()===0,503,'File security screening could not finish. Please retry later.');
 }
}
