// Read-only polling: avoid overlapping requests and stop after session expiry.
export function createNotificationPoller({getUrl,onData,onState=()=>{},fetcher=(...args)=>fetch(...args),clock=()=>Date.now()}) {
 let running=false,stopped=false,nextAttempt=0,failures=0,state='live';
 const setState=value=>{if(state!==value){state=value;onState(value);}};
 return async function poll(){
  if(running||stopped||clock()<nextAttempt)return;
  running=true;
  try{
   const response=await fetcher(getUrl(),{credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest',Accept:'application/json'}});
   if([401,403].includes(response.status)){stopped=true;setState('signed_out');return;}
   if(!response.ok)throw new Error('Notification service is temporarily unavailable.');
   const data=await response.json();
   if(typeof data?.timestamp!=='string'||!Array.isArray(data.notifications))throw new Error('Notification response is incomplete.');
   await onData(data);failures=0;nextAttempt=0;setState('live');
  }catch{
   failures++;nextAttempt=clock()+Math.min(300000,15000*2**Math.min(failures-1,5));setState('reconnecting');
  }finally{running=false;}
 };
}
if(typeof window!=='undefined')window.createNotificationPoller=createNotificationPoller;
