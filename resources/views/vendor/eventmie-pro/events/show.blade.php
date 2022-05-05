@extends('eventmie::events.show')


@section('javascript')
<script type="text/javascript" src="{{ asset('js/events_show_v1.6.js') }}"></script>
<script type="text/javascript">
    var google_map_key = {!! json_encode( $google_map_key) !!};
    
</script>
@stop
