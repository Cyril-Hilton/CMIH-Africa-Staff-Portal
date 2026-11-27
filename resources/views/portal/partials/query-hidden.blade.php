@foreach($queryFields as $hiddenKey=>$hiddenValue)
 @php($hiddenName=isset($queryPrefix)?$queryPrefix.'['.$hiddenKey.']':$hiddenKey)
 @if(is_array($hiddenValue))@include('portal.partials.query-hidden',['queryFields'=>$hiddenValue,'queryPrefix'=>$hiddenName])
 @elseif(is_scalar($hiddenValue))<input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}">
 @endif
@endforeach
