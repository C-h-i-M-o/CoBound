@extends($layout)

@section('content')

    <x-auth::onboardingProgress :percentComplete="100" current="" :completed="['account', 'theme', 'personalization', 'time']" />

<h2>{{ __('onboarding.complete_title') }}</h2>

<div class="regcontent">

    <form id="resetPassword" action="" method="post">

        <input type="hidden" name="step" value="5"/>
        <input type="hidden" name="complete" value="1"/>

        {{  $tpl->displayInlineNotification() }}

        <div class="row">
            <div class="col-md-6">
                <div class="ticketBox tw-p-[20px]">
                    <span class="fancyLink">{{ __('onboarding.did_you_know') }}</span><br />
                    <span style="font-size:16px;">{!! __('onboarding.intentions_fact') !!}</span>
                </div>
            </div>
            <div class="col-md-6">
                <x-global::undrawSvg image="undraw_adventure_map_hnin.svg" maxWidth="60%" maxHeight="300px"></x-global::undrawSvg>
            </div>
        </div>

        <p><br />{!! __('onboarding.complete_description') !!}</p> <br />

        <br />
        <input type="submit" name="createAccount" value="{{ __('onboarding.complete_signup') }}" />


    </form>

</div>

@endsection
