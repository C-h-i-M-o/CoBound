@extends($layout)
@section('content')

@dispatchEvent('beforePageHeaderOpen')
<div class="pageheader">
    <div class="pagetitle">
        <h1>{!! __('headlines.reset_password') !!}</h1>
    </div>
</div>
@dispatchEvent('afterPageHeaderClose')
<div class="regcontent">
    @dispatchEvent('afterRegcontentOpen')
    <div id="resetPassword">
        @dispatchEvent('afterFormOpen')
        {!! $tpl->displayInlineNotification() !!}
        <p>{!! __('text.contact_admin_for_password_reset') !!}</p>
        <div class="forgotPwContainer">
            <a href="{{ BASE_URL }}/" class="forgotPw">{!! __('links.back_to_login') !!}</a>
        </div>
        @dispatchEvent('beforeFormClose')
    </div>
    @dispatchEvent('beforeRegcontentClose')
</div>

@endsection
