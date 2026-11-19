<?php
namespace App\Services;
use App\Models\{OperationsHandover,User};
/** Handovers feed their master task and project journey. Weekly reports are authored and reviewed separately. */
class OperationsHandoverPublication
{
 public function sync(OperationsHandover $handover,User $actor):void {}
}
